const apps={
myday:{name:"Meu Dia",desc:"Organize tarefas, compromissos, prioridades e atividades em um único lugar.",nav:[["Hoje",[["Visão do dia","sunny-outline"],["Minhas tarefas","checkbox-outline"],["Agenda","calendar-outline"],["Importante","star-outline"]]],["Organização",[["Planejado","calendar-number-outline"],["Atribuído a mim","person-outline"],["Concluídos","checkmark-circle-outline"]]],["", [["Configurações","settings-outline"]]]]},
connecttalk:{name:"ConnectTalk",desc:"Acompanhe os principais indicadores e atividades do atendimento.",nav:[["Principal",[["Dashboard","grid-outline"],["Fila de atendimento","people-outline"],["Conversas","chatbubbles-outline"],["Contatos","person-outline"]]],["Atendimento",[["Meus chamados","ticket-outline"],["Transferências","swap-horizontal-outline"],["Histórico","time-outline"]]],["Jack",[["Interações","sparkles-outline"],["Configuração","settings-outline"]]],["", [["Relatórios","stats-chart-outline"],["Configurações","settings-outline"]]]]},
erp:{name:"ERP",desc:"Controle financeiro, cadastros, operações e relatórios em um único ambiente.",nav:[["Principal",[["Dashboard","grid-outline"]]],["Financeiro",[["Contas a pagar","arrow-down-circle-outline"],["Contas a receber","arrow-up-circle-outline"],["Bancos","card-outline"],["Conciliação","git-compare-outline"]]],["Gestão",[["Cadastros","people-outline"],["Relatórios","stats-chart-outline"],["Configurações","settings-outline"]]]]},
support:{name:"Suporte",desc:"Centralize chamados, filas, SLA, clientes, base de conhecimento, ocorrências e operação de suporte.",nav:[
["Principal",[["Dashboard","grid-outline"],["Chamados","ticket-outline"],["Minha fila","list-outline"]]],
["Atendimento",[["Equipes","people-outline"],["SLA","timer-outline"],["Categorias","folder-outline"]]],
["Clientes",[["Clientes","business-outline"],["Contatos","person-outline"],["Histórico","time-outline"]]],
["Conhecimento",[["Base de conhecimento","library-outline"],["Artigos","document-text-outline"]]],
["Operação",[["Ocorrências","alert-circle-outline"],["Ativos","hardware-chip-outline"],["Agenda","calendar-outline"]]],
["",[["Relatórios","stats-chart-outline"],["Configurações","settings-outline"]]]
]},
studio:{name:"Studio",desc:"Base visual, conteúdo, desenvolvimento e publicação dos produtos Moves.",nav:[
["Principal",[["Design System","color-palette-outline"],["Dashboard","grid-outline"]]],
["Conteúdo",[["Páginas do site","document-text-outline"],["Biblioteca de mídia","images-outline"]]],
["Comercial",[["Propostas","briefcase-outline"]]],
["Comunicação",[["Notificações","notifications-outline"]]],
["Análise",[["Relatórios de acesso","stats-chart-outline"]]],
["Desenvolvimento",[["Projetos","folder-outline"],["MovesCode","code-slash-outline"],["Moves Icons","shapes-outline"]]],
["",[["Configurações","settings-outline"]]]
]}
};
const nav=document.querySelector("#productNav"), sidebar=document.querySelector("#productSidebar");
function render(app){const d=apps[app];document.querySelector("#productName").textContent=d.name;document.querySelector("#crumbApp").textContent=d.name;document.querySelector("#pageKicker").textContent=d.name.toUpperCase();document.querySelector("#pageDescription").textContent=d.desc;nav.innerHTML=d.nav.map(([s,items])=>(s?`<div class="nav-section">${s}</div>`:"")+items.map(([n,i],ix)=>`<a href="#" class="${s==="Principal"&&ix===0?"active":""}"><i class="icon-${i}"></i><span>${n}</span></a>`).join("")).join("");document.querySelectorAll(".rail-app").forEach(x=>x.classList.toggle("active",x.dataset.app===app));closeLauncher()}
document.querySelectorAll("[data-app]").forEach(b=>b.addEventListener("click",()=>b.dataset.app&&render(b.dataset.app)));
document.querySelector("#collapseBtn").onclick=()=>sidebar.classList.add("collapsed");
document.querySelector("#openSidebar").onclick=()=>{sidebar.classList.remove("collapsed");sidebar.classList.add("mobile-open")};
const launcher=document.querySelector("#launcher"),overlay=document.querySelector("#overlay");
function openLauncher(){launcher.classList.add("show");overlay.classList.add("show");launcher.setAttribute("aria-hidden","false");setTimeout(()=>document.querySelector("#appSearch").focus(),50)}
function closeLauncher(){launcher.classList.remove("show");overlay.classList.remove("show");launcher.setAttribute("aria-hidden","true")}
document.querySelector("#launcherBtn").onclick=openLauncher;document.querySelector("#launcherClose").onclick=closeLauncher;overlay.onclick=closeLauncher;
document.querySelector("#appSearch").addEventListener("input",e=>{const q=e.target.value.toLowerCase();document.querySelectorAll("#appGrid button").forEach(b=>b.style.display=b.textContent.toLowerCase().includes(q)?"":"none")});
document.addEventListener("keydown",e=>{if(e.key==="Escape")closeLauncher()});render("studio");
document.querySelectorAll("[data-tabs] button").forEach(b=>b.addEventListener("click",()=>{b.parentElement.querySelectorAll("button").forEach(x=>x.classList.remove("active"));b.classList.add("active")}));
const qs=document.querySelector("#tableSearch"),sf=document.querySelector("#statusFilter"),rows=[...document.querySelectorAll("#proposalRows tr")],rc=document.querySelector("#resultCount");
function filterRows(){let q=(qs?.value||"").toLowerCase(),st=sf?.value||"",n=0;rows.forEach(r=>{let ok=(!q||r.textContent.toLowerCase().includes(q))&&(!st||r.dataset.status===st);r.style.display=ok?"":"none";if(ok)n++});if(rc)rc.textContent=n+" registro(s)"}
qs?.addEventListener("input",filterRows);sf?.addEventListener("change",filterRows);
const checks=[...document.querySelectorAll(".media-check")],all=document.querySelector("#selectAll"),count=document.querySelector("#selectedCount"),remove=document.querySelector("#removeSelected");
function syncMedia(){let n=checks.filter(c=>c.checked).length;if(count)count.textContent=n;if(remove)remove.disabled=!n;if(all)all.checked=n===checks.length&&n>0}
checks.forEach(c=>c.addEventListener("change",syncMedia));all?.addEventListener("change",()=>{checks.forEach(c=>c.checked=all.checked);syncMedia()});
