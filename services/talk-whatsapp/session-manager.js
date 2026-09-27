import path from 'node:path';
import fs from 'node:fs/promises';

const KEY_PATTERN = /^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/;
const JID_PATTERN = /^\d+(?::\d+)?@(s\.whatsapp\.net|lid)$/;

export function normalizeJid(value) {
  const jid=String(value||'').trim();
  return JID_PATTERN.test(jid)?jid.replace(/:\d+(?=@)/,''):'';
}

export function normalizePhoneNumber(value) {
  const raw=String(value||'').trim();
  if(raw.endsWith('@lid'))return '';
  let digits=raw.replace(/\D/g,'');
  if(/^55\d{2}[6-9]\d{7}$/.test(digits))digits=`${digits.slice(0,4)}9${digits.slice(4)}`;
  return /^\d{8,15}$/.test(digits)?digits:'';
}

export function profileIdentity(user={}) {
  const rawId=String(user?.id||'').trim(),jid=normalizeJid(rawId);
  const lid=normalizeJid(user?.lid)||(jid.endsWith('@lid')?jid:'');
  const phoneJid=normalizeJid(user?.phoneNumber)||(jid.endsWith('@s.whatsapp.net')?jid:'');
  return {id:rawId||null,jid:jid||null,lid:lid||null,phone_jid:phoneJid||null,phone_number:phoneJid?normalizePhoneNumber(phoneJid.split('@')[0]):null,name:String(user?.name||user?.notify||'').trim()||null};
}

export class SessionManager {
  constructor({ authRoot, socketFactory, authFactory, versionFactory, qrFactory, postInbound, logger, loggedOutCode, reconnectDelay = 2000 }) {
    this.authRoot = path.resolve(authRoot); this.socketFactory = socketFactory; this.authFactory = authFactory;
    this.versionFactory = versionFactory; this.qrFactory = qrFactory; this.postInbound = postInbound;
    this.logger = logger; this.loggedOutCode = loggedOutCode; this.reconnectDelay = reconnectDelay; this.sessions = new Map();
  }
  validateKey(value) { const key=String(value||'').trim(); if(!KEY_PATTERN.test(key))throw new Error('Canal inválido'); const directory=path.resolve(this.authRoot,key); if(!directory.startsWith(`${this.authRoot}${path.sep}`))throw new Error('Canal inválido'); return {key,directory}; }
  state(key) { const safe=this.validateKey(key).key; return this.sessions.get(safe)?.state||{status:'disconnected',connected:false,qr:null,detail:'Sessão não iniciada',profile:null,updated_at:new Date().toISOString()}; }
  async connect(key, externalId) {
    const safe=this.validateKey(key); externalId=String(externalId||'').trim(); if(!externalId||externalId.length>190)throw new Error('Identificador externo inválido');
    let session=this.sessions.get(safe.key); if(!session){session={key:safe.key,directory:safe.directory,externalId,socket:null,timer:null,generation:0,startPromise:null,state:{},receipts:null,inFlight:new Map()};this.sessions.set(safe.key,session);}
    if(session.externalId!==externalId&&session.socket)throw new Error('Sessão já vinculada a outro canal'); session.externalId=externalId;
    await fs.mkdir(session.directory,{recursive:true,mode:0o700}); await this.migrateLegacyAuth(session); await fs.writeFile(path.join(session.directory,'channel.json'),JSON.stringify({channel_external_id:externalId}),{mode:0o600});if(session.receipts===null)session.receipts=await this.loadReceipts(session);
    if(!session.startPromise&&!session.state.connected)session.startPromise=this.start(session).catch((error)=>{this.fail(session,error);throw error;}).finally(()=>{session.startPromise=null;});
    if(session.startPromise)await session.startPromise; return session.state;
  }
  async start(session) {
    const generation=++session.generation; this.update(session,{status:'starting',connected:false,qr:null,detail:'Inicializando WhatsApp',profile:null});
    const {state:auth,saveCreds}=await this.authFactory(session.directory); const {version}=await this.versionFactory();
    const socket=this.socketFactory({auth,version,logger:this.logger,printQRInTerminal:false,syncFullHistory:false,markOnlineOnConnect:false,generateHighQualityLinkPreview:false}); session.socket=socket;
    socket.ev.on('creds.update',saveCreds); socket.ev.on('connection.update',async(update)=>{if(generation!==session.generation)return;if(update.qr){this.logger.info({channel:session.key},'QR do canal disponível');this.update(session,{status:'qr',connected:false,qr:await this.qrFactory(update.qr),detail:'Escaneie o QR Code no WhatsApp',profile:null});}if(update.connection==='open'){const profile=profileIdentity(socket.user);this.logger.info({channel:session.key,jid:profile.jid,lid:profile.lid,phone_number:profile.phone_number},'Canal WhatsApp conectado');this.update(session,{status:'connected',connected:true,qr:null,detail:'WhatsApp conectado',profile});}if(update.connection==='close'){const code=update.lastDisconnect?.error?.output?.statusCode||0;session.socket=null;this.logger.warn({channel:session.key,code},'Canal WhatsApp desconectado');this.update(session,{status:code===this.loggedOutCode?'disconnected':'reconnecting',connected:false,qr:null,detail:`Desconectado (${code||'sem código'})`,profile:null});if(code!==this.loggedOutCode)this.scheduleReconnect(session,generation);}});
    socket.ev.on('messages.upsert',({messages,type})=>{if(generation!==session.generation||!['notify','append'].includes(type))return;this.logger.info({channel:session.key,type,count:messages?.length||0},'messages.upsert recebido');for(const message of messages||[])this.handleMessage(session,socket,message).catch((error)=>this.logger.error({channel:session.key,message_id:message?.key?.id||null,stage:error?.stage||'inbound',err:error},'Mensagem inbound não entregue'));});
  }
  async sendText(key,to,text,idempotencyKey){const session=this.sessions.get(this.validateKey(key).key);if(!session?.socket||!session.state.connected)throw new Error('WhatsApp desconectado');text=String(text||'').trim();if(!text)throw new Error('Mensagem vazia');idempotencyKey=String(idempotencyKey||'').trim();if(!/^[A-Za-z0-9][A-Za-z0-9._:-]{15,99}$/.test(idempotencyKey))throw new Error('Chave de idempotência inválida');if(session.receipts?.[idempotencyKey])return session.receipts[idempotencyKey];if(session.inFlight.has(idempotencyKey))return session.inFlight.get(idempotencyKey);const operation=this.performSend(session,to,text,idempotencyKey).finally(()=>session.inFlight.delete(idempotencyKey));session.inFlight.set(idempotencyKey,operation);return operation;}
  async performSend(session,to,text,idempotencyKey){const sent=await session.socket.sendMessage(toJid(to),{text});const result={message_id:sent?.key?.id||'',status:'sent'};if(!result.message_id)throw new Error('WhatsApp não confirmou o envio');session.receipts[idempotencyKey]=result;const keys=Object.keys(session.receipts);if(keys.length>10000)for(const old of keys.slice(0,keys.length-10000))delete session.receipts[old];const target=path.join(session.directory,'outbound-receipts.json'),temporary=`${target}.tmp`;await fs.writeFile(temporary,JSON.stringify(session.receipts),{mode:0o600});await fs.rename(temporary,target);return result;}
  async loadReceipts(session){try{const value=JSON.parse(await fs.readFile(path.join(session.directory,'outbound-receipts.json'),'utf8'));return value&&typeof value==='object'&&!Array.isArray(value)?value:{};}catch(error){if(error?.code!=='ENOENT')this.logger.warn({channel:session.key},'Recibos outbound inválidos foram ignorados');return{};}}
  async logout(key){const safe=this.validateKey(key);const session=this.sessions.get(safe.key);if(!session)return{ok:true,status:'disconnected'};clearTimeout(session.timer);session.generation+=1;const socket=session.socket;session.socket=null;try{await socket?.logout();}finally{this.update(session,{status:'disconnected',connected:false,qr:null,detail:'Sessão encerrada',profile:null});await fs.rm(safe.directory,{recursive:true,force:true});this.sessions.delete(safe.key);}return{ok:true,status:'disconnected'};}
  async disconnect(key){const safe=this.validateKey(key);const session=this.sessions.get(safe.key);if(!session)return{ok:true,status:'disconnected'};clearTimeout(session.timer);session.timer=null;session.generation+=1;const socket=session.socket;session.socket=null;socket?.end?.(new Error('Desconexão solicitada pelo Moves Talk'));this.update(session,{status:'disconnected',connected:false,qr:null,detail:'Canal desconectado',profile:null});return{ok:true,status:'disconnected'};}
  async restore(){await fs.mkdir(this.authRoot,{recursive:true,mode:0o700});try{await fs.access(path.join(this.authRoot,'creds.json'));await this.connect('whatsapp-default','whatsapp-default');}catch(error){if(error?.code!=='ENOENT')this.logger.warn({err:error},'Sessão legada não restaurada');}for(const entry of await fs.readdir(this.authRoot,{withFileTypes:true})){if(!entry.isDirectory()||!KEY_PATTERN.test(entry.name)||this.sessions.has(entry.name))continue;try{const metadata=JSON.parse(await fs.readFile(path.join(this.authRoot,entry.name,'channel.json'),'utf8'));await this.connect(entry.name,metadata.channel_external_id);}catch(error){this.logger.warn({channel:entry.name,err:error},'Sessão não restaurada');}}}
  async migrateLegacyAuth(session){if(session.key!=='whatsapp-default'||session.externalId!=='whatsapp-default')return;for(const entry of await fs.readdir(this.authRoot,{withFileTypes:true})){if(!entry.isFile()||entry.name==='channel.json')continue;await fs.copyFile(path.join(this.authRoot,entry.name),path.join(session.directory,entry.name));}}
  async shutdown(){for(const session of this.sessions.values()){clearTimeout(session.timer);session.generation+=1;session.socket?.end?.(new Error('Bridge encerrado'));}}
  scheduleReconnect(session,generation){if(session.timer||generation!==session.generation)return;session.timer=setTimeout(()=>{session.timer=null;this.start(session).catch((error)=>this.fail(session,error));},this.reconnectDelay);}
  fail(session,error){this.update(session,{status:'error',connected:false,qr:null,detail:error.message,profile:null});this.logger.error({channel:session.key,err:error},'Falha na sessão Baileys');this.scheduleReconnect(session,session.generation);}
  update(session,next){session.state={...session.state,...next,updated_at:new Date().toISOString()};}
  async handleMessage(session,socket,message){const key=message?.key||{},remote=normalizeJid(key.remoteJid),alternate=normalizeJid(key.remoteJidAlt),participant=normalizeJid(key.participant),participantAlt=normalizeJid(key.participantAlt);if(!message?.message||key.fromMe||String(key.remoteJid||'').endsWith('@g.us')||key.remoteJid==='status@broadcast'||String(key.remoteJid||'').endsWith('@broadcast'))return;const candidates=[remote,alternate,participant,participantAlt].filter(Boolean);let phoneJid=candidates.find((jid)=>jid.endsWith('@s.whatsapp.net'))||'',lid=candidates.find((jid)=>jid.endsWith('@lid'))||'';if(!phoneJid&&lid)phoneJid=normalizeJid(await socket.signalRepository?.lidMapping?.getPNForLID(lid));const sender=phoneJid||lid;if(!sender){const error=new Error('Remetente sem PN ou LID resolvível');error.stage='identity';throw error;}const payload={external_id:key.id,channel_external_id:session.externalId,session_key:session.key,from_jid:sender,phone_jid:phoneJid||null,from_lid:lid||null,phone_number:phoneJid?normalizePhoneNumber(phoneJid.split('@')[0]):null,push_name:message.pushName||'',type:Object.keys(message.message)[0]||'text',body:messageText(message.message),timestamp:Number(message.messageTimestamp||Math.floor(Date.now()/1000))};this.logger.info({channel:session.key,message_id:key.id,has_phone:Boolean(payload.phone_number),has_lid:Boolean(lid)},'Mensagem aceita para entrega inbound');try{await this.postInbound(payload);}catch(error){error.stage=error.stage||'moves_api';throw error;}}
}
export function toJid(value){const address=String(value||'').trim();if(/^\d+@(s\.whatsapp\.net|lid)$/.test(address))return address;const digits=address.replace(/\D/g,'');if(!digits)throw new Error('Destinatário inválido');return `${digits}@s.whatsapp.net`;}
function messageText(message={}){const c=message.ephemeralMessage?.message||message.viewOnceMessage?.message||message.viewOnceMessageV2?.message||message;return c.conversation||c.extendedTextMessage?.text||c.imageMessage?.caption||c.videoMessage?.caption||(c.audioMessage?'🎵 Áudio':'')||(c.documentMessage?`📄 ${c.documentMessage.fileName||'Documento'}`:'')||(c.stickerMessage?'🖼️ Figurinha':'');}
