import 'dotenv/config';
import express from 'express';
import * as sessions from './sessionManager.js';

const app = express();
app.use(express.json());

// Internal-only service — every request must carry the shared API key Laravel
// was configured with (services.whatsapp_bridge.api_key). Never expose this
// port publicly.
app.use((req, res, next) => {
  if (req.get('X-Api-Key') !== process.env.BRIDGE_API_KEY) {
    return res.status(401).json({ error: 'Unauthorized' });
  }
  next();
});

app.post('/instances/:channelId', async (req, res) => {
  const channelId = req.params.channelId;
  await sessions.createInstance(channelId);
  res.json({ status: 'connecting' });
});

app.get('/instances/:channelId/qr', (req, res) => {
  const entry = sessions.getInstance(req.params.channelId);
  if (!entry) return res.status(404).json({ error: 'Instance not found' });
  res.json({ qr: entry.qr });
});

app.post('/instances/:channelId/pairing-code', async (req, res) => {
  try {
    const code = await sessions.requestPairingCode(req.params.channelId, req.body.phone_number);
    res.json({ code });
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

app.get('/instances/:channelId/status', (req, res) => {
  const entry = sessions.getInstance(req.params.channelId);
  if (!entry) return res.status(404).json({ status: 'disconnected' });
  res.json({ status: entry.status });
});

app.post('/instances/:channelId/send', async (req, res) => {
  const { phone, type, body, media_url, media_type } = req.body;

  try {
    const result = await sessions.sendMessage(req.params.channelId, phone, type, body, media_url, media_type);
    res.json(result);
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

app.get('/instances/:channelId/contacts', (req, res) => {
  try {
    res.json({ contacts: sessions.getDeviceContacts(req.params.channelId) });
  } catch (err) {
    res.status(404).json({ error: err.message });
  }
});

app.post('/instances/:channelId/check-numbers', async (req, res) => {
  try {
    const results = await sessions.checkNumbersOnWhatsapp(req.params.channelId, req.body.phones ?? []);
    res.json({ results });
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

app.delete('/instances/:channelId', async (req, res) => {
  await sessions.logout(req.params.channelId);
  res.json({ status: 'disconnected' });
});

const port = process.env.PORT || 3001;
app.listen(port, () => {
  console.log(`whatsapp-bridge listening on :${port}`);
});
