(() => {
    'use strict';

    const instances = new Map();
    const commandLabels = {
        undo: 'Desfazer', redo: 'Refazer', bold: 'Negrito', italic: 'Itálico', underline: 'Sublinhado',
        strikeThrough: 'Tachado', justifyLeft: 'Alinhar à esquerda', justifyCenter: 'Centralizar',
        justifyRight: 'Alinhar à direita', justifyFull: 'Justificar', insertUnorderedList: 'Lista com marcadores',
        insertOrderedList: 'Lista numerada', outdent: 'Diminuir recuo', indent: 'Aumentar recuo',
        removeFormat: 'Limpar formatação'
    };
    const icon = {undo:'↶',redo:'↷',bold:'B',italic:'I',underline:'U',strikeThrough:'S',justifyLeft:'≡',justifyCenter:'≡',justifyRight:'≡',justifyFull:'☰',insertUnorderedList:'•≡',insertOrderedList:'1≡',outdent:'⇤',indent:'⇥',removeFormat:'Tx'};

    const element = (tag, attrs = {}, text = '') => {
        const node = document.createElement(tag);
        Object.entries(attrs).forEach(([key, value]) => key === 'class' ? node.className = value : node.setAttribute(key, value));
        if (text) node.textContent = text;
        return node;
    };

    class Editor {
        constructor(textarea, options = {}) {
            this.textarea = textarea;
            this.options = options;
            this.id = textarea.id || `moves-editor-${instances.size + 1}`;
            this.textarea.id = this.id;
            this.storageKey = `moves-editor:draft:${location.pathname}:${this.id}`;
            this.dirty = false;
            this.savedRange = null;
            this.build();
            this.restoreDraft();
            this.bind();
            this.update();
            this.emit('ready');
        }

        build() {
            this.root = element('div', {class:'moves-editor', 'data-moves-editor':this.id});
            this.toolbar = element('div', {class:'moves-editor-toolbar', role:'toolbar', 'aria-label':'Formatação do conteúdo'});
            const format = element('select', {class:'moves-editor-format', 'aria-label':'Formato do bloco', title:'Formato do bloco'});
            [['p','Parágrafo'],['h2','Título 2'],['h3','Título 3'],['h4','Título 4'],['blockquote','Citação'],['pre','Bloco de código']].forEach(([value,label]) => format.append(element('option',{value},label)));
            format.addEventListener('change', () => { this.focus(); document.execCommand('formatBlock', false, format.value); this.changed(); });
            this.toolbar.append(format);
            this.addGroup(['undo','redo']);
            this.addGroup(['bold','italic','underline','strikeThrough']);
            this.addColor('foreColor', 'Cor do texto', 'A');
            this.addColor('hiliteColor', 'Cor de fundo', '▣');
            this.addGroup(['justifyLeft','justifyCenter','justifyRight','justifyFull']);
            this.addGroup(['insertUnorderedList','insertOrderedList','outdent','indent']);
            this.addAction('quote','Citação','❝',() => this.exec('formatBlock','blockquote'));
            this.addAction('link','Inserir link','↗',() => this.link());
            this.addAction('unlink','Remover link','×↗',() => this.exec('unlink'));
            this.addAction('image','Biblioteca de mídia','▧',() => this.openMedia());
            this.addAction('media','Inserir YouTube/Vimeo','▶',() => this.embed());
            this.addAction('table','Inserir tabela','▦',() => this.table());
            this.addAction('line','Linha horizontal','—',() => this.exec('insertHorizontalRule'));
            this.addAction('character','Caractere especial','Ω',() => this.characters());
            this.addAction('code-inline','Código inline','</>',() => this.inlineCode());
            this.addGroup(['removeFormat']);
            this.addAction('find','Localizar e substituir','⌕',() => this.findReplace());
            this.addAction('paste-text','Colar como texto','T',() => this.pasteText());
            this.addAction('preview','Visualizar','◉',() => this.preview());
            this.addAction('source','Código HTML','</>',() => this.source());
            this.addAction('fullscreen','Tela cheia','⛶',() => this.fullscreen());
            this.addAction('help','Ajuda e atalhos','?',() => this.help());

            this.canvas = element('div', {class:'moves-editor-canvas moves-content', contenteditable:'true', role:'textbox', 'aria-multiline':'true', 'aria-label':'Editor de conteúdo', spellcheck:'true'});
            this.canvas.innerHTML = this.textarea.value || '<p><br></p>';
            this.status = element('div', {class:'moves-editor-status'});
            this.counter = element('span', {}, '0 palavras · 0 caracteres');
            this.state = element('span', {}, 'Conteúdo salvo');
            this.status.append(this.counter, this.state);
            this.root.append(this.toolbar, this.canvas, this.status);
            this.textarea.hidden = true;
            this.textarea.insertAdjacentElement('afterend', this.root);
        }

        addGroup(commands) {
            const group = element('span', {class:'moves-editor-group'});
            commands.forEach(command => {
                const button = this.button(commandLabels[command], icon[command]);
                button.addEventListener('click', () => this.exec(command));
                group.append(button);
            });
            this.toolbar.append(group);
        }

        addAction(name, label, symbol, callback) {
            const button = this.button(label, symbol);
            button.dataset.action = name;
            button.addEventListener('click', callback);
            this.toolbar.append(button);
        }

        addColor(command, label, symbol) {
            const holder = element('label', {class:'moves-editor-color', title:label, 'aria-label':label});
            holder.append(element('span',{},symbol));
            const input = element('input', {type:'color', 'aria-label':label});
            input.addEventListener('input', () => this.exec(command, input.value));
            holder.append(input); this.toolbar.append(holder);
        }

        button(label, symbol) {
            const button = element('button', {type:'button', class:'moves-editor-tool', title:label, 'aria-label':label}, symbol);
            button.addEventListener('mousedown', event => { event.preventDefault(); this.rememberSelection(); });
            return button;
        }

        bind() {
            this.canvas.addEventListener('input', () => this.changed());
            this.canvas.addEventListener('blur', () => this.rememberSelection());
            this.canvas.addEventListener('paste', event => this.cleanPaste(event));
            this.canvas.addEventListener('keydown', event => {
                if (event.key === 'Tab') { event.preventDefault(); this.exec(event.shiftKey ? 'outdent' : 'indent'); }
            });
            this.form = this.textarea.closest('form');
            this.form?.addEventListener('submit', () => { this.sync(); this.dirty = false; localStorage.removeItem(this.storageKey); this.emit('save'); });
        }

        cleanPaste(event) {
            event.preventDefault();
            const clipboard = event.clipboardData;
            let html = clipboard?.getData('text/html') || '';
            if (html) {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                doc.querySelectorAll('script,style,meta,link,object,embed,iframe,form,input,button').forEach(node => node.remove());
                doc.querySelectorAll('*').forEach(node => [...node.attributes].forEach(attr => {
                    if (attr.name.startsWith('on') || ['style','id','dir','lang'].includes(attr.name) || attr.name.startsWith('data-')) node.removeAttribute(attr.name);
                }));
                html = doc.body.innerHTML;
                document.execCommand('insertHTML', false, html);
            } else document.execCommand('insertText', false, clipboard?.getData('text/plain') || '');
            this.changed();
        }

        exec(command, value = null) { this.restoreSelection(); this.focus(); document.execCommand(command, false, value); this.changed(); }
        focus() { this.canvas.focus({preventScroll:true}); }
        rememberSelection() { const selection = getSelection(); if (selection?.rangeCount && this.canvas.contains(selection.anchorNode)) this.savedRange = selection.getRangeAt(0).cloneRange(); }
        restoreSelection() { if (!this.savedRange) return; const selection = getSelection(); selection.removeAllRanges(); selection.addRange(this.savedRange); }
        insert(html) { this.restoreSelection(); this.focus(); document.execCommand('insertHTML', false, html); this.changed(); }
        sync() { this.textarea.value = this.canvas.innerHTML; this.textarea.dispatchEvent(new Event('input',{bubbles:true})); }
        changed() {
            this.sync(); this.dirty = true; this.state.textContent = 'Alterações não salvas';
            clearTimeout(this.timer); this.timer = setTimeout(() => { localStorage.setItem(this.storageKey, this.textarea.value); this.state.textContent = 'Rascunho temporário salvo neste navegador'; }, 700);
            this.update(); this.emit('change'); this.emit('dirty');
        }
        update() { const text = this.canvas.textContent.trim(); this.counter.textContent = `${text ? text.split(/\s+/).length : 0} palavras · ${text.length} caracteres`; }
        emit(name) { this.textarea.dispatchEvent(new CustomEvent(`moveseditor:${name}`, {bubbles:true, detail:{editor:this}})); }
        restoreDraft() { const draft = localStorage.getItem(this.storageKey); if (draft && draft !== this.textarea.value && confirm('Existe um rascunho temporário mais recente neste navegador. Deseja recuperá-lo?')) { this.canvas.innerHTML = draft; this.changed(); } }

        link() {
            this.rememberSelection();
            const url = prompt('URL do link (https://, e-mail ou caminho interno):', 'https://');
            if (!url || !/^(https?:\/\/|mailto:|\/|#)/i.test(url)) return;
            this.exec('createLink', url);
            const selection = getSelection(); const anchor = selection?.anchorNode?.parentElement?.closest('a');
            if (anchor && confirm('Abrir o link em uma nova aba?')) { anchor.target = '_blank'; anchor.rel = 'noopener noreferrer'; this.changed(); }
        }
        table() {
            const rows = Math.max(1, Math.min(20, Number(prompt('Número de linhas:', '3')) || 0));
            const columns = Math.max(1, Math.min(10, Number(prompt('Número de colunas:', '3')) || 0));
            if (!rows || !columns) return;
            const cells = tag => `<${tag}>Conteúdo</${tag}>`.repeat(columns);
            this.insert(`<div class="moves-table-scroll"><table><thead><tr>${cells('th')}</tr></thead><tbody>${`<tr>${cells('td')}</tr>`.repeat(rows)}</tbody></table></div><p><br></p>`);
        }
        inlineCode() { const selection = getSelection(); const selected = selection?.toString(); if (!selected) return; this.insert(`<code>${this.escape(selected)}</code>`); }
        characters() { const value = prompt('Digite ou escolha um caractere: © ® ™ € £ ¥ • → ← ✓ ★ — …', '©'); if (value) this.insert(this.escape(value)); }
        pasteText() { const value = prompt('Cole o texto sem formatação:'); if (value) this.insert(`<p>${this.escape(value).replace(/\n{2,}/g,'</p><p>').replace(/\n/g,'<br>')}</p>`); }
        findReplace() { const find = prompt('Localizar:'); if (!find) return; const replace = prompt('Substituir por:', '') ?? ''; this.canvas.innerHTML = this.canvas.innerHTML.split(this.escape(find)).join(this.escape(replace)); this.changed(); }
        embed() {
            const url = prompt('URL de um vídeo do YouTube ou Vimeo:'); if (!url) return;
            let source = '';
            try { const parsed = new URL(url); const host=parsed.hostname.replace(/^www\./,''); if(host==='youtu.be') source=`https://www.youtube-nocookie.com/embed/${parsed.pathname.slice(1)}`; else if(host==='youtube.com'&&parsed.searchParams.get('v')) source=`https://www.youtube-nocookie.com/embed/${parsed.searchParams.get('v')}`; else if(host==='vimeo.com'&&/^\/\d+$/.test(parsed.pathname)) source=`https://player.vimeo.com/video/${parsed.pathname.slice(1)}`; } catch {}
            if (!source) { alert('Use uma URL válida do YouTube ou Vimeo.'); return; }
            this.insert(`<div class="moves-embed"><iframe src="${source}" title="Vídeo incorporado" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div><p><br></p>`);
        }
        source() { this.openDialog('Código HTML', this.textarea.value, value => { this.canvas.innerHTML = this.safeClientHtml(value); this.changed(); }, true); }
        preview() { this.openDialog('Pré-visualização', this.safeClientHtml(this.textarea.value), null, false); }
        help() { alert('Moves Editor\n\nAtalhos: Ctrl/Cmd+B negrito, Ctrl/Cmd+I itálico, Ctrl/Cmd+Z desfazer, Ctrl/Cmd+Shift+Z refazer.\n\nO rascunho temporário fica somente neste navegador; use Salvar para persistir no banco.'); }
        fullscreen() { this.root.classList.toggle('is-fullscreen'); document.body.classList.toggle('moves-editor-open', this.root.classList.contains('is-fullscreen')); }

        openDialog(title, content, save, editable) {
            const dialog = element('dialog', {class:'moves-editor-dialog'});
            const heading = element('h2',{},title); const body = editable ? element('textarea',{class:'moves-editor-source','aria-label':title}) : element('div',{class:'moves-editor-preview moves-content'});
            if (editable) body.value = content; else body.innerHTML = content;
            const actions = element('div',{class:'moves-editor-dialog-actions'}); const close = element('button',{type:'button'},'Fechar'); close.onclick=()=>dialog.close(); actions.append(close);
            if (save) { const apply=element('button',{type:'button',class:'primary'},'Aplicar'); apply.onclick=()=>{ save(body.value); dialog.close(); }; actions.append(apply); }
            dialog.append(heading,body,actions); document.body.append(dialog); dialog.addEventListener('close',()=>dialog.remove()); dialog.showModal();
        }

        async openMedia() {
            this.rememberSelection();
            const dialog = element('dialog',{class:'moves-editor-dialog moves-media-dialog'});
            const heading = element('h2',{},'Biblioteca de mídia');
            const search = element('input',{type:'search',placeholder:'Pesquisar imagens','aria-label':'Pesquisar imagens'});
            const grid = element('div',{class:'moves-media-picker-grid'});
            const alt = element('input',{type:'text',maxlength:'255',placeholder:'Texto alternativo','aria-label':'Texto alternativo'});
            const decorative = element('label',{class:'moves-media-decorative'}); const check=element('input',{type:'checkbox'}); decorative.append(check,document.createTextNode(' Imagem decorativa (alt vazio)'));
            let selected = null;
            const load = async () => {
                grid.textContent='Carregando…';
                try { const response=await fetch(`${document.body.dataset.editorLibrary}?q=${encodeURIComponent(search.value)}`,{headers:{Accept:'application/json'}}); const data=await response.json(); grid.textContent='';
                    data.files.forEach(file=>{ const button=element('button',{type:'button',class:'moves-media-choice','aria-label':`Selecionar ${file.name}`}); const image=element('img',{src:file.url,alt:file.alt||''}); button.append(image,element('span',{},file.name)); button.onclick=()=>{ grid.querySelectorAll('.selected').forEach(node=>node.classList.remove('selected')); button.classList.add('selected'); selected=file; alt.value=file.alt||''; }; grid.append(button); });
                    if (!data.files.length) grid.textContent='Nenhuma imagem encontrada.';
                } catch { grid.textContent='Não foi possível carregar a biblioteca.'; }
            };
            let searchTimer; search.oninput=()=>{clearTimeout(searchTimer);searchTimer=setTimeout(load,250);}; check.onchange=()=>{alt.disabled=check.checked;if(check.checked)alt.value='';};
            const uploadLabel=element('label',{class:'moves-media-upload'},'Enviar nova imagem'); const upload=element('input',{type:'file',accept:'image/jpeg,image/png,image/gif,image/webp'}); uploadLabel.append(upload);
            upload.onchange=async()=>{ if(!upload.files[0])return; const form=new FormData(); form.append('_token',this.form?.querySelector('[name="_token"]')?.value||''); form.append('action','upload'); form.append('image',upload.files[0]); const response=await fetch(document.body.dataset.editorUpload,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body:form}); if(!response.ok){const data=await response.json();alert(data.error||'Upload rejeitado.');return;} await load(); };
            const actions=element('div',{class:'moves-editor-dialog-actions'}); const close=element('button',{type:'button'},'Cancelar'); close.onclick=()=>dialog.close(); const insert=element('button',{type:'button',class:'primary'},'Inserir imagem'); insert.onclick=()=>{if(!selected){alert('Selecione uma imagem.');return;} this.insert(`<figure><img src="${selected.url}" alt="${this.escape(check.checked?'':alt.value)}" width="${selected.width}" height="${selected.height}" loading="lazy"><figcaption></figcaption></figure><p><br></p>`);dialog.close();}; actions.append(close,insert);
            dialog.append(heading,search,uploadLabel,grid,alt,decorative,actions); document.body.append(dialog); dialog.addEventListener('close',()=>dialog.remove()); dialog.showModal(); load();
        }
        escape(value) { const node=document.createElement('span'); node.textContent=value; return node.innerHTML; }
        safeClientHtml(value) { const doc=new DOMParser().parseFromString(value,'text/html'); doc.querySelectorAll('script,style,object,embed,form,input,button').forEach(node=>node.remove()); doc.querySelectorAll('*').forEach(node=>[...node.attributes].forEach(attr=>{if(attr.name.startsWith('on')||attr.name==='style'||/^(javascript|data):/i.test(attr.value))node.removeAttribute(attr.name);})); return doc.body.innerHTML; }
        destroy() { clearTimeout(this.timer); this.sync(); this.root.remove(); this.textarea.hidden=false; instances.delete(this.id); this.emit('destroy'); }
    }

    const MovesEditor = {
        init(root = document, options = {}) { root.querySelectorAll('textarea[data-editor="moves"]').forEach(textarea => { if (!instances.has(textarea.id)) { const editor=new Editor(textarea,options); instances.set(editor.id,editor); } }); return instances; },
        get(id) { return instances.get(id) || null; },
        destroy(id) { if (id) instances.get(id)?.destroy(); else [...instances.values()].forEach(editor=>editor.destroy()); },
        reinitialize(root=document, options={}) { this.destroy(); return this.init(root,options); }
    };
    window.MovesEditor = MovesEditor;
    window.addEventListener('beforeunload', event => { if ([...instances.values()].some(editor=>editor.dirty)) { event.preventDefault(); event.returnValue=''; } });
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded',()=>MovesEditor.init()) : MovesEditor.init();
})();
