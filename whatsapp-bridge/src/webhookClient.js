import axios from 'axios';
import crypto from 'crypto';

const { LARAVEL_WEBHOOK_URL, WEBHOOK_SECRET } = process.env;

/**
 * Pushes an event back to Laravel, signed with HMAC-SHA256 over the raw JSON
 * body so WebhookController can verify it actually came from this bridge.
 */
export async function sendWebhook(event, payload) {
  const body = JSON.stringify({ event, ...payload });
  const signature = crypto.createHmac('sha256', WEBHOOK_SECRET).update(body).digest('hex');

  try {
    await axios.post(LARAVEL_WEBHOOK_URL, body, {
      headers: {
        'Content-Type': 'application/json',
        'X-Bridge-Signature': signature,
      },
      timeout: 10_000,
    });
  } catch (err) {
    console.error(`[webhook] failed to deliver "${event}":`, err.message);
  }
}
