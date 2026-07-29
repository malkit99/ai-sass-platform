import ffmpegPath from 'ffmpeg-static';
import ffmpeg from 'fluent-ffmpeg';
import { createHash } from 'crypto';
import { createWriteStream } from 'fs';
import fs from 'fs/promises';
import path from 'path';
import axios from 'axios';

ffmpeg.setFfmpegPath(ffmpegPath);

const CACHE_DIR = path.resolve(process.cwd(), 'cache', 'audio');

function cacheKeyFor(url) {
  return createHash('sha256').update(url).digest('hex');
}

async function download(url, destPath) {
  const response = await axios.get(url, { responseType: 'stream', timeout: 60000 });

  await new Promise((resolve, reject) => {
    const writer = createWriteStream(destPath);
    response.data.pipe(writer);
    writer.on('finish', resolve);
    writer.on('error', reject);
  });
}

function convertToOpus(sourcePath, destPath) {
  return new Promise((resolve, reject) => {
    ffmpeg(sourcePath)
      .audioCodec('libopus')
      .audioChannels(1)
      .outputOptions('-avoid_negative_ts', 'make_zero')
      .format('ogg')
      .on('error', reject)
      .on('end', resolve)
      .save(destPath);
  });
}

/**
 * WhatsApp is unreliable about non-voice-note audio containers on unofficial
 * connections — raw mp3/wav/m4a often get silently dropped by the recipient's
 * client. OGG/Opus is the format WhatsApp's own recording/playback pipeline
 * actually uses, so non-ogg audio gets transcoded to it here before sending.
 * Results are cached by source URL (hashed) — a bulk campaign reuses the same
 * template audio for every recipient, so only the first send pays the
 * download+convert cost; the rest reuse the cached file.
 *
 * @returns {Promise<string>} local filesystem path to a .ogg/opus file
 */
export async function transcodeToOpus(mediaUrl) {
  await fs.mkdir(CACHE_DIR, { recursive: true });

  const cachedPath = path.join(CACHE_DIR, `${cacheKeyFor(mediaUrl)}.ogg`);

  try {
    await fs.access(cachedPath);

    return cachedPath;
  } catch {
    // Not cached yet — fall through to download + convert.
  }

  const tempSourcePath = path.join(CACHE_DIR, `${cacheKeyFor(mediaUrl)}.source`);

  try {
    await download(mediaUrl, tempSourcePath);
    await convertToOpus(tempSourcePath, cachedPath);
  } finally {
    await fs.rm(tempSourcePath, { force: true });
  }

  return cachedPath;
}
