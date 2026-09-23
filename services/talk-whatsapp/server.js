import 'dotenv/config';
import express from 'express';
import path from 'path';
import {fileURLToPath} from 'url';
import QRCode from 'qrcode';
import pino from 'pino';
import makeWASocket,{DisconnectReason,fetchLatestBaileysVersion,useMultiFileAuthState} from '@whiskeysockets/baileys';

const __dirname=path.dirname(fileURLToPath(import.meta.url));
const app=express(),port=Number(process.env.TALK_BAILEYS_PORT||3011);
const authDir=process.env.TALK_BAILEYS_AUTH_DIR||path.join(__dirname,'baileys_auth');
const bridgeToken=String(process.env.TALK_BAILEYS_BRIDGE_TOKEN||'');
if(!bridgeToken) throw new Error('TALK_BAILEYS_BRIDGE_TOKEN é obrigatório');
const movesInbound=String(process.env.TALK_MOVES_INBOUND_URL||'http://127.0.0.1/talk/bridge/inbound');
const logger=pino({level:process.env.TALK_BAILEYS_LOG_LEVEL||'silent'});
let sock=null,state={status:'starting',connected:false,qr:null,detail:'Inicializando WhatsApp',profile:null};

app.use(express.json({limit:'12mb'}));
app.use((req,res,next)=>{if(bridgeToken&&req.headers.authorization!==`Bearer ${bridgeToken}`)return res.status(401).json({error:'unauthorized'});next()});
app.get('/status',(req,res)=>res.json(state));
app.post('/send/text',async(req,res)=>{try{if(!sock||!state.connected)throw new Error('WhatsApp desconectado');const to=jid(req.body?.to);const sent=await sock.sendMessage(to,{text:String(req.body?.text||'')});res.json({message_id:sent?.key?.id||'',status:'sent'})}catch(e){res.status(503).json({error:e.message})}});
app.post('/logout',async(req,res)=>{try{await sock?.logout();state={status:'disconnected',connected:false,qr:null,detail:'Sessão encerrada',profile:null};res.json({ok:true})}catch(e){res.status(500).json({error:e.message})}});

function phoneFromJid(value){const user=String(value||'').split('@')[0].split(':')[0];const digits=user.replace(/\D/g,'');return digits||''}
function jid(value){const digits=phoneFromJid(value);if(!digits)throw new Error('Destinatário inválido');return digits+'@s.whatsapp.net'}
async function resolveInboundPhone(m){
 const key=m?.key||{},remote=String(key.remoteJid||''),alt=String(key.remoteJidAlt||'');
 if(remote.endsWith('@s.whatsapp.net'))return phoneFromJid(remote);
 if(alt.endsWith('@s.whatsapp.net'))return phoneFromJid(alt);
 if(remote.endsWith('@lid')){
  try{const pn=await sock?.signalRepository?.lidMapping?.getPNForLID(remote);if(pn)return phoneFromJid(pn)}catch(e){logger.warn({err:e,lid:remote},'LID mapping lookup failed')}
 }
 return '';
}
function textOf(m={}){return m.conversation||m.extendedTextMessage?.text||m.imageMessage?.caption||m.videoMessage?.caption||(m.audioMessage?'🎵 Áudio':'')||(m.documentMessage?`📄 ${m.documentMessage.fileName||'Documento'}`:'')||(m.stickerMessage?'🖼️ Figurinha':'')}
async function postInbound(payload){try{await fetch(movesInbound,{method:'POST',headers:{'content-type':'application/json',...(bridgeToken?{authorization:`Bearer ${bridgeToken}`}:{})},body:JSON.stringify(payload)})}catch(e){logger.warn({err:e},'Moves inbound failed')}}
async function start(){
 const {state:auth,saveCreds}=await useMultiFileAuthState(authDir);const {version}=await fetchLatestBaileysVersion();
 sock=makeWASocket({auth,version,logger,printQRInTerminal:false,syncFullHistory:false,markOnlineOnConnect:false});
 sock.ev.on('creds.update',saveCreds);
 sock.ev.on('connection.update',async u=>{if(u.qr){state={...state,status:'qr',connected:false,qr:await QRCode.toDataURL(u.qr),detail:'Escaneie o QR Code no WhatsApp'}}if(u.connection==='open'){state={status:'connected',connected:true,qr:null,detail:'WhatsApp conectado',profile:sock.user||null}}if(u.connection==='close'){const code=u.lastDisconnect?.error?.output?.statusCode||0;state={...state,status:'disconnected',connected:false,qr:null,detail:`Desconectado (${code||'sem código'})`};if(code!==DisconnectReason.loggedOut)setTimeout(start,1500)}});
 sock.ev.on('messages.upsert',async({messages})=>{for(const m of messages||[]){const remote=String(m?.key?.remoteJid||'');if(!m?.message||m?.key?.fromMe||(!remote.endsWith('@s.whatsapp.net')&&!remote.endsWith('@lid')))continue;const phone=await resolveInboundPhone(m);if(!phone){logger.warn({remote,remoteJidAlt:m?.key?.remoteJidAlt||null,id:m?.key?.id||null},'Inbound WhatsApp message has no resolvable phone yet');continue}await postInbound({external_id:m.key.id,from:phone,lid:remote.endsWith('@lid')?remote:null,push_name:m.pushName||'',type:Object.keys(m.message)[0]||'text',body:textOf(m.message),timestamp:Number(m.messageTimestamp||Math.floor(Date.now()/1000))})}});
}
start().catch(e=>{state={status:'error',connected:false,qr:null,detail:e.message,profile:null};logger.error(e)});
app.listen(port,'127.0.0.1',()=>logger.info(`Moves Talk WhatsApp bridge on 127.0.0.1:${port}`));
