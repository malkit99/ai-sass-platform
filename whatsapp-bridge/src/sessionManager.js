import makeWASocket, {
  useMultiFileAuthState,
  fetchLatestBaileysVersion,
  DisconnectReason,
  Browsers,
} from '@whiskeysockets/baileys';
import { Boom } from '@hapi/boom';
import pino from 'pino';
import QRCode from 'qrcode';
import path from 'path';
import fs from 'fs';
import { sendWebhook } from './webhookClient.js';

const SESSIONS_DIR = path.resolve(process.cwd(), 'sessions');
const logger = pino({ level: 'warn' });

/** @type {Map<string, { sock: import('@whiskeysockets/baileys').WASocket, status: string, qr: string|null }>} */
const instances = new Map();

function sessionPath(channelId) {
  return path.join(SESSIONS_DIR, String(channelId));
}

function toJid(phone) {
  return `${phone}@s.whatsapp.net`;
}

function phoneFromJid(jid) {
  return jid?.split('@')[0];
}

// The authenticated socket's own jid looks like "919628061241:14@s.whatsapp.net"
// (device-suffixed) — strip both the domain and the ":deviceId" part.
function ownPhoneFromJid(jid) {
  return jid?.split('@')[0]?.split(':')[0];
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

  const entry = { sock, status: 'connecting', qr: null, contacts: new Map() };
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
        profile_phone: ownPhoneFromJid(sock.user?.id),
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
      if (msg.key.fromMe || !msg.message) continue;

      await sendWebhook('message.inbound', {
        channel_id: channelId,
        phone: phoneFromJid(msg.key.remoteJid),
        name: msg.pushName || null,
        type: 'text',
        body: extractText(msg.message),
        external_id: msg.key.id,
      });
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

function buildMediaContent(mediaUrl, body, mediaType) {
  const kind = mediaType || mediaKindFromUrl(mediaUrl);
  const ext = mediaUrl?.split(/[?#]/)[0]?.split('.').pop()?.toLowerCase();
  const mimetype = MIME_TYPES[ext];

  if (kind === 'video') return { video: { url: mediaUrl }, caption: body ?? undefined, mimetype: mimetype ?? 'video/mp4' };
  if (kind === 'audio') return { audio: { url: mediaUrl }, mimetype: mimetype ?? 'audio/mpeg', ptt: false };
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

export async function sendMessage(channelId, phone, type, body, mediaUrl, mediaType) {
  const entry = instances.get(channelId);
  if (!entry || entry.status !== 'connected') {
    throw new Error('Instance not connected');
  }

  const jid = toJid(phone);
  const content = type === 'media' ? buildMediaContent(mediaUrl, body, mediaType) : { text: body };

  const result = await entry.sock.sendMessage(jid, content);
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
