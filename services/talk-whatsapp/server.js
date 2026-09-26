import path from 'node:path';
import { fileURLToPath } from 'node:url';
import express from 'express';
import dotenv from 'dotenv';
import QRCode from 'qrcode';
import pino from 'pino';
import makeWASocket, { DisconnectReason, fetchLatestBaileysVersion, useMultiFileAuthState } from '@whiskeysockets/baileys';

const serviceDir = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.join(serviceDir, '.env'), quiet: true });

const app = express();
const port = Number(process.env.TALK_BAILEYS_PORT || 3011);
const host = String(process.env.TALK_BAILEYS_HOST || '127.0.0.1');
const configuredAuthDir = String(process.env.TALK_BAILEYS_AUTH_DIR || 'baileys_auth');
const authDir = path.isAbsolute(configuredAuthDir) ? configuredAuthDir : path.resolve(serviceDir, configuredAuthDir);
const bridgeToken = String(process.env.TALK_BAILEYS_BRIDGE_TOKEN || '').trim();
const channelExternalId = String(process.env.TALK_CHANNEL_EXTERNAL_ID || 'whatsapp-default').trim();
const movesInbound = String(process.env.TALK_MOVES_INBOUND_URL || 'http://127.0.0.1/talk/bridge/inbound');
const logger = pino({ level: process.env.TALK_BAILEYS_LOG_LEVEL || 'info' });

if (!bridgeToken) throw new Error('TALK_BAILEYS_BRIDGE_TOKEN é obrigatório');

let socket = null;
let reconnectTimer = null;
let generation = 0;
let state = { status: 'starting', connected: false, qr: null, detail: 'Inicializando WhatsApp', profile: null, updated_at: new Date().toISOString() };

function updateState(next) {
  state = { ...state, ...next, updated_at: new Date().toISOString() };
}

app.disable('x-powered-by');
app.use(express.json({ limit: '12mb' }));
app.use((req, res, next) => {
  if (req.headers.authorization !== `Bearer ${bridgeToken}`) return res.status(401).json({ error: 'unauthorized' });
  return next();
});
app.get('/status', (_req, res) => res.json(state));
app.post('/send/text', async (req, res) => {
  try {
    if (!socket || !state.connected) throw new Error('WhatsApp desconectado');
    const text = String(req.body?.text || '').trim();
    if (!text) return res.status(422).json({ error: 'Mensagem vazia' });
    const sent = await socket.sendMessage(toJid(req.body?.to), { text });
    return res.json({ message_id: sent?.key?.id || '', status: 'sent' });
  } catch (error) {
    return res.status(503).json({ error: error.message });
  }
});
app.post('/logout', async (_req, res) => {
  try {
    clearTimeout(reconnectTimer);
    reconnectTimer = null;
    generation += 1;
    await socket?.logout();
    socket = null;
    updateState({ status: 'disconnected', connected: false, qr: null, detail: 'Sessão encerrada', profile: null });
    return res.json({ ok: true });
  } catch (error) {
    return res.status(500).json({ error: error.message });
  }
});

function toJid(value) {
  const address = String(value || '').trim();
  if (/^\d+@(s\.whatsapp\.net|lid)$/.test(address)) return address;
  const digits = String(value || '').replace(/\D/g, '');
  if (!digits) throw new Error('Destinatário inválido');
  return `${digits}@s.whatsapp.net`;
}

function messageText(message = {}) {
  const content = message.ephemeralMessage?.message || message.viewOnceMessage?.message || message.viewOnceMessageV2?.message || message;
  return content.conversation || content.extendedTextMessage?.text || content.imageMessage?.caption || content.videoMessage?.caption || (content.audioMessage ? '🎵 Áudio' : '') || (content.documentMessage ? `📄 ${content.documentMessage.fileName || 'Documento'}` : '') || (content.stickerMessage ? '🖼️ Figurinha' : '');
}

async function postInbound(payload) {
  let lastError;
  for (let attempt = 1; attempt <= 3; attempt += 1) {
    try {
      const response = await fetch(movesInbound, {
        method: 'POST',
        headers: { 'content-type': 'application/json', authorization: `Bearer ${bridgeToken}` },
        body: JSON.stringify(payload),
        signal: AbortSignal.timeout(10_000),
      });
      const body = await response.text();
      if (!response.ok) throw new Error(`Moves respondeu HTTP ${response.status}: ${body.slice(0, 300)}`);
      logger.info({ external_id: payload.external_id, from: payload.from }, 'Mensagem entregue ao Moves Talk');
      return;
    } catch (error) {
      lastError = error;
      if (attempt < 3) await new Promise((resolve) => setTimeout(resolve, attempt * 500));
    }
  }
  logger.error({ err: lastError, external_id: payload.external_id }, 'Falha ao entregar mensagem ao Moves');
}

function scheduleReconnect(currentGeneration) {
  if (reconnectTimer || currentGeneration !== generation) return;
  reconnectTimer = setTimeout(() => {
    reconnectTimer = null;
    startWhatsApp().catch(handleStartError);
  }, 2_000);
}

function handleStartError(error) {
  updateState({ status: 'error', connected: false, qr: null, detail: error.message, profile: null });
  logger.error({ err: error }, 'Falha ao iniciar Baileys');
  scheduleReconnect(generation);
}

async function startWhatsApp() {
  const currentGeneration = ++generation;
  updateState({ status: 'starting', connected: false, qr: null, detail: 'Inicializando WhatsApp' });
  const { state: auth, saveCreds } = await useMultiFileAuthState(authDir);
  const { version } = await fetchLatestBaileysVersion();
  const nextSocket = makeWASocket({ auth, version, logger, printQRInTerminal: false, syncFullHistory: false, markOnlineOnConnect: false, generateHighQualityLinkPreview: false });
  socket = nextSocket;
  nextSocket.ev.on('creds.update', saveCreds);
  nextSocket.ev.on('connection.update', async (update) => {
    if (currentGeneration !== generation) return;
    if (update.qr) updateState({ status: 'qr', connected: false, qr: await QRCode.toDataURL(update.qr), detail: 'Escaneie o QR Code no WhatsApp' });
    if (update.connection === 'open') updateState({ status: 'connected', connected: true, qr: null, detail: 'WhatsApp conectado', profile: nextSocket.user || null });
    if (update.connection === 'close') {
      const code = update.lastDisconnect?.error?.output?.statusCode || 0;
      socket = null;
      updateState({ status: 'disconnected', connected: false, qr: null, detail: `Desconectado (${code || 'sem código'})`, profile: null });
      if (code !== DisconnectReason.loggedOut) scheduleReconnect(currentGeneration);
    }
  });
  nextSocket.ev.on('messages.upsert', async ({ messages, type }) => {
    if (type !== 'notify') return;
    for (const message of messages || []) {
      const remote = message?.key?.remoteJid || '';
      const alternate = message?.key?.remoteJidAlt || '';
      if (!message?.message || message?.key?.fromMe || remote.endsWith('@g.us') || remote === 'status@broadcast' || remote.endsWith('@broadcast')) continue;
      let directJid = remote.endsWith('@s.whatsapp.net') ? remote : alternate.endsWith('@s.whatsapp.net') ? alternate : '';
      if (!directJid && remote.endsWith('@lid')) {
        directJid = await nextSocket.signalRepository?.lidMapping?.getPNForLID(remote) || '';
      }
      const senderJid = directJid || (remote.endsWith('@lid') ? remote : '');
      if (!senderJid) continue;
      await postInbound({ external_id: message.key.id, channel_external_id: channelExternalId, from: directJid ? directJid.split('@')[0] : '', from_jid: senderJid, push_name: message.pushName || '', type: Object.keys(message.message)[0] || 'text', body: messageText(message.message), timestamp: Number(message.messageTimestamp || Math.floor(Date.now() / 1000)) });
    }
  });
}

const server = app.listen(port, host, () => logger.info({ host, port, inbound: movesInbound }, 'Moves Talk WhatsApp bridge iniciado'));
startWhatsApp().catch(handleStartError);

function shutdown() {
  clearTimeout(reconnectTimer);
  generation += 1;
  socket?.end?.(new Error('Bridge encerrado'));
  server.close(() => process.exit(0));
}
process.once('SIGINT', shutdown);
process.once('SIGTERM', shutdown);
