import path from 'node:path';
import { fileURLToPath } from 'node:url';
import express from 'express';
import dotenv from 'dotenv';
import QRCode from 'qrcode';
import pino from 'pino';
import makeWASocket,{DisconnectReason,fetchLatestBaileysVersion,useMultiFileAuthState}from'@whiskeysockets/baileys';
import {SessionManager} from './session-manager.js';

const serviceDir=path.dirname(fileURLToPath(import.meta.url));
dotenv.config({path:path.join(serviceDir,'.env'),quiet:true});
const port=Number(process.env.TALK_BAILEYS_PORT||3011),host=String(process.env.TALK_BAILEYS_HOST||'127.0.0.1');
const configuredAuthDir=String(process.env.TALK_BAILEYS_AUTH_DIR||'baileys_auth');
const authRoot=path.isAbsolute(configuredAuthDir)?configuredAuthDir:path.resolve(serviceDir,configuredAuthDir);
const bridgeToken=String(process.env.TALK_BAILEYS_BRIDGE_TOKEN||'').trim();
const movesInbound=String(process.env.TALK_MOVES_INBOUND_URL||'http://127.0.0.1/talk/bridge/inbound');
const logger=pino({level:process.env.TALK_BAILEYS_LOG_LEVEL||'info',redact:['req.headers.authorization','authorization','token']});
const baileysLogger=pino({level:'silent'});
if(!bridgeToken)throw new Error('TALK_BAILEYS_BRIDGE_TOKEN é obrigatório');

async function postInbound(payload){let lastError;for(let attempt=1;attempt<=3;attempt+=1){try{logger.info({stage:'moves_api',attempt,external_id:payload.external_id,channel_external_id:payload.channel_external_id},'Enviando mensagem inbound ao Moves Talk');const response=await fetch(movesInbound,{method:'POST',headers:{'content-type':'application/json',authorization:`Bearer ${bridgeToken}`},body:JSON.stringify(payload),signal:AbortSignal.timeout(10000)});const body=await response.text();if(!response.ok){const error=new Error(`Moves respondeu HTTP ${response.status}: ${body.slice(0,300)}`);error.stage=`moves_http_${response.status}`;throw error;}let result={};try{result=JSON.parse(body);}catch{}logger.info({stage:'persisted',external_id:payload.external_id,channel_external_id:payload.channel_external_id,ticket_id:result.ticket_id||null},'Mensagem persistida no Moves Talk');return result;}catch(error){lastError=error;if(attempt<3)await new Promise((resolve)=>setTimeout(resolve,attempt*500));}}throw lastError;}

const manager=new SessionManager({authRoot,socketFactory:(options)=>makeWASocket({...options,logger:baileysLogger}),authFactory:useMultiFileAuthState,versionFactory:fetchLatestBaileysVersion,qrFactory:(value)=>QRCode.toDataURL(value),postInbound,logger,loggedOutCode:DisconnectReason.loggedOut});
const app=express();app.disable('x-powered-by');app.use(express.json({limit:'1mb'}));
app.use((req,res,next)=>req.headers.authorization===`Bearer ${bridgeToken}`?next():res.status(401).json({error:'unauthorized'}));
app.get('/channels/:channel/status',(req,res)=>respond(res,()=>manager.state(req.params.channel)));
app.post('/channels/:channel/connect',(req,res)=>respond(res,()=>manager.connect(req.params.channel,req.body?.channel_external_id)));
app.post('/channels/:channel/disconnect',(req,res)=>respond(res,()=>manager.disconnect(req.params.channel)));
app.post('/channels/:channel/logout',(req,res)=>respond(res,()=>manager.logout(req.params.channel)));
app.post('/channels/:channel/send/text',(req,res)=>respond(res,()=>manager.sendText(req.params.channel,req.body?.to,req.body?.text,req.body?.idempotency_key)));
// Compatibilidade transitória da sessão histórica; o domínio novo sempre usa /channels/:session_key.
app.get('/status',(req,res)=>respond(res,()=>manager.state('whatsapp-default')));
app.post('/send/text',(req,res)=>respond(res,()=>manager.sendText('whatsapp-default',req.body?.to,req.body?.text,req.body?.idempotency_key||`legacy-${Date.now()}-${Math.random().toString(16).slice(2)}`)));
app.post('/logout',(req,res)=>respond(res,()=>manager.logout('whatsapp-default')));
async function respond(res,action){try{return res.json(await action());}catch(error){return res.status(error.message==='Canal inválido'?400:503).json({error:error.message});}}
const server=app.listen(port,host,()=>logger.info({host,port,inbound:movesInbound},'Moves Talk WhatsApp bridge multissessão iniciado'));
manager.restore().catch((error)=>logger.error({err:error},'Falha ao restaurar sessões'));
async function shutdown(){await manager.shutdown();server.close(()=>process.exit(0));}
process.once('SIGINT',shutdown);process.once('SIGTERM',shutdown);
