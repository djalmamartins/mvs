# Moves Editor

## Objetivo e decisão

O Moves Editor é o editor editorial oficial do Moves Studio. Ele é implementação própria do projeto, escrita em JavaScript do navegador e sem TinyMCE, Organic Editor, CDN, telemetria, chave ou licença externa. Esta é a decisão posterior e vigente: a proposta anterior de uma camada sobre TinyMCE foi substituída para evitar incompatibilidade entre a licença proprietária do Moves e a GPLv2+ da distribuição comunitária self-hosted.

As responsabilidades permanecem separadas: Moves Editor edita; Moves Media armazena arquivos; Moves CMS persiste/publica; Moves SEO produz metadados; `HtmlSanitizer` aplica a política de segurança no servidor.

## Estrutura e uso

- `public/themes/admin/js/moves-editor.js`: API, instâncias, comandos, eventos, mídia, autosave e lifecycle.
- `public/themes/admin/css/moves-editor.css`: interface clara, foco, fullscreen, diálogos, dark mode preparado e responsividade.
- `public/themes/site/css/moves-content.css`: linguagem editorial compartilhada pelo Studio, preview e site público.
- `app/Core/HtmlSanitizer.php`: whitelist final do HTML persistido e renderizado.
- `GET /studio/media/library`: consulta autenticada à biblioteca existente.

Um módulo ativa o componente apenas com `data-editor="moves"`. O layout recebe `hasMovesEditor` e só então carrega os assets. A API global oferece `MovesEditor.init(root)`, `get(id)`, `destroy(id)` e `reinitialize(root)` e suporta várias instâncias.

## Recursos

Formatos sem H1; negrito, itálico, sublinhado e tachado; cores; alinhamentos; listas; recuos; citação; links; imagem; YouTube/Vimeo; tabela responsiva; linha; caracteres; código inline e bloco; limpeza; localizar/substituir; colar como texto; preview; HTML; fullscreen; ajuda; atalhos; spellcheck do navegador; palavras/caracteres; estado dirty; aviso ao sair; autosave temporário local com recuperação confirmada.

O autosave local não é apresentado como rascunho persistido. A chave inclui usuário, módulo e documento para impedir colisões entre edições. O envio do formulário sincroniza o HTML no `textarea`; o status rascunho/publicado continua sendo salvo pelo CMS no banco.

## Auditoria e revisões

Criação, edição, transição de status, exclusão, restauração, categorias e mutações de mídia geram eventos com ação, registro e autor, sem corpo editorial, credenciais ou tokens. Artigos e Páginas recebem snapshots versionados no banco a cada salvamento. A interface permite identificar data/autor, visualizar conteúdo e metadados básicos, comparar título/slug com o estado atual e restaurar uma revisão; a restauração cria uma nova revisão e não destrói o histórico.

## Mídia

O seletor consulta e pesquisa a biblioteca oficial, solicita texto alternativo, permite alt vazio intencional e insere dimensões reais e `loading="lazy"`. Novos uploads passam pelo mesmo endpoint, CSRF, limites, validação e Storage usados pela tela Mídia. Nenhum armazenamento paralelo foi criado.

## Segurança

O tratamento no cliente reduz markup de Word, Google Docs e web, mas nunca é considerado barreira de segurança. No servidor, somente tags e atributos editoriais explícitos sobrevivem. Eventos, estilos arbitrários, scripts, forms, objetos, embeds e protocolos executáveis são removidos. Imagens somente podem usar `/media/{id}`. Iframes somente aceitam URLs normalizadas de `youtube-nocookie.com` e `player.vimeo.com`; a CSP libera exclusivamente esses dois destinos. Links em nova aba recebem `noopener noreferrer`.

## Extensão

Funcionalidades próprias futuras devem entrar como comandos encapsulados na classe `Editor`, sem chamadas espalhadas pelos módulos. Blocos possíveis incluem CTA, callout, galeria e projeto; não fazem parte desta versão para evitar transformar o editor em page builder.

## Recursos não incluídos

Não existem recursos premium: o editor não depende de produto comercial. Revisão colaborativa, comentários, sugestões, verificador remoto de links, correção ortográfica em nuvem, importação DOCX, exportação PDF/Word e IA ficam para versões futuras e exigem arquitetura própria. A limpeza de conteúdo colado é local e deliberadamente mais conservadora que soluções comerciais especializadas.
