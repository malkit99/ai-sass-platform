// Switched from @whiskeysockets/baileys to this maintained fork specifically
// for its interactive-message support (buttons/list) — six different manual
// proto constructions on vanilla Baileys were all confirmed (live, Android)
// to silently fail; this fork's own shorthand (buttons/sections/templateButtons
// in generateWAMessageContent) is a different, more complete implementation,
// not just a different way of building the same bytes.
import makeWASocket, {
  useMultiFileAuthState,
  fetchLatestBaileysVersion,
  DisconnectReason,
  Browsers,
} from '@itsliaaa/baileys';
import { Boom } from '@hapi/boom';
import pino from 'pino';
import QRCode from 'qrcode';
import path from 'path';
import fs from 'fs';
import { sendWebhook } from './webhookClient.js';
import { transcodeToOpus } from './audioTranscoder.js';

const SESSIONS_DIR = path.resolve(process.cwd(), 'sessions');
const logger = pino({ level: 'warn' });

/** @type {Map<string, { sock: import('@itsliaaa/baileys').WASocket, status: string, qr: string|null }>} */
const instances = new Map();

function sessionPath(channelId) {
  return path.join(SESSIONS_DIR, String(channelId));
}

function toJid(phone) {
  return `${phone}@s.whatsapp.net`;
}

// The authenticated socket's own jid ("919628061241:14@s.whatsapp.net") and a
// resolved LID->phone-number JID ("919876543210:0@...") are both
// device-suffixed — strip both the domain and any ":deviceId", a real phone
// number never legitimately contains one.
function phoneFromJid(jid) {
  return jid?.split('@')[0]?.split(':')[0];
}

// WhatsApp's newer privacy protocol sometimes delivers an inbound message's
// remoteJid as a @lid (Linked ID) — an opaque identifier, not the sender's
// real phone number — instead of the usual @s.whatsapp.net JID. Naively
// stripping the domain off a @lid JID stores that opaque ID as if it were a
// phone number, so replies (autoresponder, chatbot) get sent to a JID that
// doesn't correspond to any reachable account: silently accepted by
// WhatsApp's servers, never delivered. Resolve it back to the real
// phone-number JID via the fork's Signal-layer LID mapping store first.
async function resolveToPhoneJid(sock, jid) {
  if (!jid?.endsWith('@lid')) {
    return jid;
  }

  try {
    return (await sock.signalRepository.lidMapping.getPNForLID(jid)) || jid;
  } catch {
    return jid;
  }
}

/**
 * Extracts a plain-text body from whichever WhatsApp message type Baileys
 * handed us. Good enough for autoresponder/chatbot keyword matching and the
 * inbox — v1 doesn't attempt to download/re-host inbound media.
 */
function extractText(message) {
  return (
    message?.conversation ||
    message?.extendedTextMessage?.text ||
    message?.imageMessage?.caption ||
    message?.videoMessage?.caption ||
    null
  );
}

export async function createInstance(channelId) {
  if (instances.has(channelId)) {
    return instances.get(channelId);
  }

  fs.mkdirSync(sessionPath(channelId), { recursive: true });
  const { state, saveCreds } = await useMultiFileAuthState(sessionPath(channelId));

  // Without an explicit, current protocol version Baileys' baked-in default can
  // be stale enough that WhatsApp's servers accept the socket but never advance
  // past "connecting" — no error, no QR, just a silent hang. Always fetch it.
  const { version } = await fetchLatestBaileysVersion();

  const sock = makeWASocket({
    auth: state,
    logger,
    printQRInTerminal: false,
    browser: Browsers.ubuntu('Chrome'),
    version,
  });

  const entry = { sock, status: 'connecting', qr: null, contacts: new Map(), autoRejectedCallIds: new Set(), callJids: new Map() };
  instances.set(channelId, entry);

  sock.ev.on('creds.update', saveCreds);

  // Baileys streams the phone's own saved contacts in via these events during
  // the post-connect history sync — there's no on-demand "list contacts" API,
  // so this is accumulated passively and only reflects what's arrived since
  // this particular socket connected (resets on every bridge restart/reconnect).
  const upsertContacts = (contacts) => {
    for (const c of contacts ?? []) {
      if (!c.id || c.id.endsWith('@g.us') || c.id === 'status@broadcast') continue;
      entry.contacts.set(c.id, { id: c.id, name: c.name || c.notify || c.verifiedName || null });
    }
  };
  sock.ev.on('contacts.upsert', upsertContacts);
  sock.ev.on('contacts.update', upsertContacts);

  sock.ev.on('connection.update', async (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      entry.qr = await QRCode.toDataURL(qr);
    }

    if (connection === 'open') {
      entry.status = 'connected';
      entry.qr = null;

      // WhatsApp's multi-device protocol never hands over the account's own
      // display name over the socket (confirmed by inspecting a real
      // session's saved creds.json — only `id`/`lid`, no `name` field exists
      // anywhere) — only the phone number is reliably known automatically.
      // A user-supplied label (channels.name) is the only source of a name;
      // see ChannelController::update and Channel::getDisplayNameAttribute.
      await sendWebhook('connection.update', {
        channel_id: channelId,
        status: 'connected',
        profile_phone: phoneFromJid(sock.user?.id),
      });
    } else if (connection === 'close') {
      const statusCode = lastDisconnect?.error instanceof Boom
        ? lastDisconnect.error.output.statusCode
        : null;
      const loggedOut = statusCode === DisconnectReason.loggedOut;

      entry.status = loggedOut ? 'disconnected' : 'connecting';
      await sendWebhook('connection.update', { channel_id: channelId, status: entry.status });

      if (loggedOut) {
        instances.delete(channelId);
        fs.rmSync(sessionPath(channelId), { recursive: true, force: true });
      } else {
        // Transient disconnect (network blip, restart) — Baileys' own socket
        // is done, so re-create ours to keep reconnecting with the same creds.
        instances.delete(channelId);
        await createInstance(channelId);
      }
    }
  });

  sock.ev.on('messages.upsert', async ({ messages, type }) => {
    if (type !== 'notify') return;

    for (const msg of messages) {
      if (!msg.message) continue;

      // Group traffic (@g.us) is never a 1:1 conversation — phoneFromJid()
      // on a group JID produces a garbage "phone number", which used to
      // silently create bogus conversations and could even fire an
      // autoresponder/chatbot reply at a non-address. Record that the group
      // exists (Export Participants discovery, screenshot 90's "send a
      // message to the group" step) instead of routing it through the
      // normal inbound-message path. Checked *before* the fromMe filter
      // below — the discovery step is naturally a message the connected
      // account itself sends into the group, which is exactly what fromMe
      // would otherwise skip.
      if (msg.key.remoteJid?.endsWith('@g.us')) {
        await sendWebhook('group.seen', { channel_id: channelId, group_jid: msg.key.remoteJid });
        continue;
      }

      if (msg.key.fromMe) continue;

      const remoteJid = await resolveToPhoneJid(sock, msg.key.remoteJid);

      await sendWebhook('message.inbound', {
        channel_id: channelId,
        phone: phoneFromJid(remoteJid),
        name: msg.pushName || null,
        type: 'text',
        body: extractText(msg.message),
        external_id: msg.key.id,
      });
    }
  });

  // Call Responder (screenshots 93/94) — Baileys only ever sees call
  // *signaling* (ring/answer/reject/hangup), never actual audio/video, so
  // this can only auto-reject + send a text reply, never join a call. The
  // reply-routing decision (which of the 4 templates to send) lives in
  // Laravel; this just forwards each raw status change plus whether *we*
  // were the one who rejected it (autoRejectedCallIds, set by rejectCall()
  // below) so Laravel can tell "auto-rejected by us" apart from "declined on
  // the phone itself" without guessing.
  //
  // call.from can arrive as a @lid (Linked ID — an opaque identifier, not a
  // real phone number) just like inbound messages can — see
  // resolveToPhoneJid() above. The *webhook* always sends the resolved real
  // phone number (needed for Laravel to actually message the caller back —
  // a raw @lid value stored as "the phone number" would be silently
  // accepted by WhatsApp's send API and never delivered, confirmed live).
  // The *reject* stanza is the opposite: it needs call.from's original raw
  // JID exactly as WhatsApp sent it, not the resolved phone number — kept
  // in entry.callJids so rejectCall() below doesn't have to round-trip a
  // JID through Laravel and risk it coming back malformed.
  sock.ev.on('call', async ([call]) => {
    if (call.isGroup) return;

    if (call.from) {
      entry.callJids.set(call.id, call.from);
    }

    const terminal = call.status === 'reject' || call.status === 'timeout' || call.status === 'terminate';
    const resolvedJid = await resolveToPhoneJid(sock, call.from);

    await sendWebhook('call.event', {
      channel_id: channelId,
      call_id: call.id,
      phone: phoneFromJid(resolvedJid),
      is_video: !!call.isVideo,
      status: call.status,
      auto_rejected: entry.autoRejectedCallIds.has(call.id),
    });

    if (terminal) {
      entry.autoRejectedCallIds.delete(call.id);
      entry.callJids.delete(call.id);
    }
  });

  return entry;
}

export function getInstance(channelId) {
  return instances.get(channelId) ?? null;
}

/**
 * The linked phone's saved contacts synced in since this instance connected
 * (see the contacts.upsert/contacts.update listeners in createInstance) —
 * powers "Import from WhatsApp" as a best-effort convenience, not a
 * guaranteed full address-book export.
 */
export function getDeviceContacts(channelId) {
  const entry = instances.get(channelId);
  if (!entry) throw new Error('Instance not found');

  return Array.from(entry.contacts.values()).map((c) => ({
    phone: phoneFromJid(c.id),
    name: c.name,
  }));
}

/**
 * Checks which of the given phone numbers are actually registered on
 * WhatsApp, via Baileys' onWhatsApp — a lightweight lookup (no message sent),
 * used to power the contacts "Validate" action instead of only ever finding
 * out a number is bad when a real send to it fails.
 */
export async function checkNumbersOnWhatsapp(channelId, phones) {
  const entry = instances.get(channelId);
  if (!entry || entry.status !== 'connected') {
    throw new Error('Instance not connected');
  }

  const results = await entry.sock.onWhatsApp(...phones);
  const existsByPhone = new Map(results.map((r) => [phoneFromJid(r.jid), r.exists]));

  return phones.map((phone) => ({ phone, exists: existsByPhone.get(phone) ?? false }));
}

/**
 * On-demand group metadata + participant list (Export Participants,
 * screenshot 90's "Download" action) — Baileys' groupMetadata, not something
 * cached passively like device contacts, since it's only needed at export
 * time and group membership can change between exports.
 */
export async function fetchGroupParticipants(channelId, groupJid) {
  const entry = instances.get(channelId);
  if (!entry || entry.status !== 'connected') {
    throw new Error('Instance not connected');
  }

  const metadata = await entry.sock.groupMetadata(groupJid);

  return {
    name: metadata.subject || null,
    participants: (metadata.participants ?? []).map((p) => ({
      phone: phoneFromJid(p.id),
      admin: p.admin ?? null,
    })),
  };
}

/**
 * Call Responder's "Auto-Reject Incoming Calls" action — sends a real reject
 * stanza (sock.rejectCall, confirmed present in this fork's messages-recv.js)
 * and marks the call so the 'call' listener above tells Laravel this was an
 * auto-reject, not the phone itself declining.
 *
 * Prefers entry.callJids' remembered *raw* JID from the original offer event
 * over the $callFrom argument (which Laravel only has as the already-resolved
 * phone number, no @domain suffix) — sock.rejectCall's stanza needs the full
 * original JID (possibly @lid, not a plain phone number) to correctly
 * address the reject. Falls back to the argument only if the bridge doesn't
 * have it (e.g. restarted mid-call) — best-effort, matches how this whole
 * action is already treated as best-effort by its caller.
 */
export async function rejectCall(channelId, callId, callFrom) {
  const entry = instances.get(channelId);
  if (!entry || entry.status !== 'connected') {
    throw new Error('Instance not connected');
  }

  entry.autoRejectedCallIds.add(callId);
  await entry.sock.rejectCall(callId, entry.callJids.get(callId) ?? callFrom);
}

export async function requestPairingCode(channelId, phoneNumber) {
  const entry = instances.get(channelId);
  if (!entry) throw new Error('Instance not found');

  return entry.sock.requestPairingCode(phoneNumber);
}

// `type` from Laravel is only ever "text" or "media" — it doesn't say *what
// kind* of media, so a video sent through the generic "media" path used to
// always go out as an `image` message (WhatsApp then shows it as a broken/
// mislabeled image instead of a playable video). Baileys needs the right key
// (image/video/document/audio) to render each kind correctly, so infer it
// from the URL's file extension here — the one chokepoint every media send
// (single message, autoresponder, chatbot, campaigns) already passes through.
const EXTENSION_KINDS = {
  video: ['mp4', 'mov', 'm4v', '3gp', 'avi', 'mkv', 'webm'],
  audio: ['mp3', 'ogg', 'oga', 'wav', 'm4a', 'aac', 'opus'],
  document: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'txt', 'csv'],
};

const MIME_TYPES = {
  mp4: 'video/mp4', mov: 'video/quicktime', m4v: 'video/x-m4v', '3gp': 'video/3gpp', avi: 'video/x-msvideo', mkv: 'video/x-matroska', webm: 'video/webm',
  mp3: 'audio/mpeg', ogg: 'audio/ogg', oga: 'audio/ogg', wav: 'audio/wav', m4a: 'audio/mp4', aac: 'audio/aac', opus: 'audio/ogg',
  pdf: 'application/pdf', doc: 'application/msword', docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  xls: 'application/vnd.ms-excel', xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  ppt: 'application/vnd.ms-powerpoint', pptx: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  zip: 'application/zip', txt: 'text/plain', csv: 'text/csv',
};

function mediaKindFromUrl(mediaUrl) {
  const ext = mediaUrl?.split(/[?#]/)[0]?.split('.').pop()?.toLowerCase();
  if (ext && EXTENSION_KINDS.video.includes(ext)) return 'video';
  if (ext && EXTENSION_KINDS.audio.includes(ext)) return 'audio';
  if (ext && EXTENSION_KINDS.document.includes(ext)) return 'document';

  return 'image';
}

async function buildMediaContent(mediaUrl, body, mediaType) {
  const kind = mediaType || mediaKindFromUrl(mediaUrl);
  const ext = mediaUrl?.split(/[?#]/)[0]?.split('.').pop()?.toLowerCase();
  const mimetype = MIME_TYPES[ext];

  if (kind === 'video') return { video: { url: mediaUrl }, caption: body ?? undefined, mimetype: mimetype ?? 'video/mp4' };
  if (kind === 'audio') {
    // Raw mp3/wav/m4a audio is unreliable over this unofficial connection —
    // WhatsApp's own recording/playback pipeline is built around OGG/Opus, so
    // anything not already in that format gets transcoded before sending (see
    // audioTranscoder.js; cached by source URL so a bulk campaign only pays
    // the conversion cost once, not per recipient).
    const isAlreadyOpus = mimetype?.includes('ogg');
    const audioPath = isAlreadyOpus ? mediaUrl : await transcodeToOpus(mediaUrl);

    return { audio: { url: audioPath }, mimetype: 'audio/ogg; codecs=opus', ptt: false };
  }
  if (kind === 'document') {
    return {
      document: { url: mediaUrl },
      caption: body ?? undefined,
      mimetype: mimetype ?? 'application/octet-stream',
      fileName: mediaUrl?.split(/[?#]/)[0]?.split('/').pop() ?? 'file',
    };
  }

  return { image: { url: mediaUrl }, caption: body ?? undefined };
}

export async function sendMessage(channelId, phone, type, body, mediaUrl, mediaType, interactive) {
  const entry = instances.get(channelId);
  if (!entry || entry.status !== 'connected') {
    throw new Error('Instance not connected');
  }

  const jid = toJid(phone);

  if (type === 'poll') {
    const result = await entry.sock.sendMessage(jid, {
      poll: { name: body ?? '', values: interactive?.options ?? [], selectableCount: 1 },
    });
    return { message_id: result?.key?.id ?? null };
  }

  // @itsliaaa/baileys' `nativeFlow` shorthand — builds an
  // interactiveMessage.nativeFlowMessage AND (crucially) the fork's own
  // relayMessage injects a `biz` stanza node carrying a native-flow marker
  // alongside it (getBizBinaryNode in its WABinary/generic-utils.js). That
  // stanza-level node is the ingredient vanilla Baileys never sent — our
  // earlier bare-interactiveMessage attempt delivered body text but no
  // buttons, exactly matching its absence. NOT the fork's `templateButtons`
  // shorthand: that builds templateMessage, which the fork's own source notes
  // renders only on Web/Desktop/iOS, never in normal Android chats.
  if (type === 'buttons') {
    const result = await entry.sock.sendMessage(jid, {
      text: body ?? '',
      footer: interactive?.footer || undefined,
      nativeFlow: (interactive?.buttons ?? []).map((label, i) => ({ text: label, id: `btn_${i}` })),
    });
    return { message_id: result?.key?.id ?? null };
  }

  if (type === 'list') {
    // A single `sections` button becomes a `single_select` native flow — the
    // modern list-message form (legacy listMessage is Web/Desktop-only now).
    const result = await entry.sock.sendMessage(jid, {
      text: body ?? '',
      footer: interactive?.footer || undefined,
      nativeFlow: [{
        text: interactive?.button_text || 'View Options',
        sections: (interactive?.sections ?? []).map((section) => ({
          title: section.title,
          rows: (section.rows ?? []).map((row) => ({
            title: row.title,
            description: row.description ?? '',
            id: row.id || row.title,
          })),
        })),
      }],
    });
    return { message_id: result?.key?.id ?? null };
  }

  if (type === 'media') {
    // WhatsApp audio messages have no caption field at all (same as the real
    // app — you can't attach text to a voice/audio message there either), so
    // a non-empty body would otherwise silently vanish. Sent as its own text
    // message first instead of being dropped.
    if ((mediaType || mediaKindFromUrl(mediaUrl)) === 'audio' && body) {
      await entry.sock.sendMessage(jid, { text: body });
    }

    const content = await buildMediaContent(mediaUrl, body, mediaType);
    const result = await entry.sock.sendMessage(jid, content);
    return { message_id: result?.key?.id ?? null };
  }

  const result = await entry.sock.sendMessage(jid, { text: body });
  return { message_id: result?.key?.id ?? null };
}

export async function logout(channelId) {
  const entry = instances.get(channelId);
  if (!entry) return;

  try {
    await entry.sock.logout();
  } catch {
    // Already disconnected — fall through to local cleanup regardless.
  }

  instances.delete(channelId);
  fs.rmSync(sessionPath(channelId), { recursive: true, force: true });
}
