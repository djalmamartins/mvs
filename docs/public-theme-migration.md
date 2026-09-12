# Migração inicial do tema público

Esta entrega migra a Home aprovada em `moves-site` para o tema público do Moves sem alterar a fundação de segurança da aplicação.

## Antigo x novo

| Bloco | Antigo | Novo | Motivo |
| --- | --- | --- | --- |
| Shell | `DOCTYPE`, `head`, header, conteúdo e footer no `index.html` estático | Estrutura comum em `resources/themes/site/layouts/default.php` | Reuso entre páginas e metadados centralizados |
| Header | Marcação repetida no HTML, com links para arquivos `.html` | `components/header.php`, com âncoras para seções reais e `/login` | Preservar o visual sem publicar navegação quebrada |
| Footer | Rodapé embutido no `index.html` | `components/footer.php`, incluindo acesso discreto do cliente | Reuso e entrada segura para a área autenticada |
| Home | Página monolítica | Conteúdo específico em `pages/home.php` | Separar conteúdo da estrutura comum |
| Assets | Caminhos como `assets/...`, `styles.css` e `script.js` | `Theme::asset()` em `public/themes/site/` | Resolução pelo tema e cache busting com `filemtime` |
| Tipografia | Manrope carregada pelo Google Fonts | Manrope servida localmente em pesos 400–800 | Manter a tipografia, privacidade e CSP `self` |
| JavaScript | Dois arquivos e bootstrap inline | `js/app.js` externo | Compatibilidade com CSP sem `unsafe-inline` |
| SEO | Metadados fixos no HTML | Dados da Home enviados pelo controller e renderizados pelo layout | Preparar SEO por página sem regra de negócio na view |

## Assets migrados

- logotipo e favicon;
- imagens do hero e da equipe;
- imagens dos serviços, conceitos e artigos utilizadas pelas regras visuais da Home;
- fonte Manrope nos pesos 400, 500, 600, 700 e 800;
- estilos e comportamentos da referência, incluindo abertura, menu, reveal, contadores e movimento reduzido.

## SEO

A Home fornece título, descrição, robots, Open Graph básico e Twitter Card. O canonical utiliza `APP_URL` somente em produção; desenvolvimento permanece `noindex, nofollow` e não publica uma URL local como canônica.

`movescode/seo` não está instalado e não foi adicionado nesta rodada. A API deve ser avaliada antes de uma eventual integração.

## Preparação para minificação

Os templates dependem dos contratos estáveis `css/app.css` e `js/app.js`, mantidos legíveis em desenvolvimento. Uma etapa futura pode gerar `app.min.css` e `app.min.js` para produção e selecionar esses nomes por configuração, sem introduzir uma cadeia Node.

`movescode/compress` não está instalado e não foi adicionado nesta rodada. Sua API e compatibilidade com cache busting devem ser avaliadas antes da integração.

## Diferenças deliberadas

- Páginas internas do site estático ainda não foram migradas. Links globais usam seções reais da Home, e cards ainda sem página detalhada são apresentados sem links.
- O CTA de contato usa `mailto:contato@moves.com.br` enquanto a página `/contato` ainda não existe.
- A área “Privacidade” do rodapé estático não foi exposta porque sua rota ainda não foi implementada.
- O botão principal do header agora é “Área do cliente” e aponta para `/login`, conforme o escopo desta rodada.

## Responsividade e acessibilidade

Os breakpoints originais de 1100, 980, 800, 640 e 520 pixels foram preservados. O menu mantém estado com `aria-expanded`, fecha por Escape, clique externo e seleção de item. `prefers-reduced-motion` desativa a abertura e animações não essenciais.
