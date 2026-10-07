# Design system do Moves Studio

As fundações visuais do Studio ficam em `public/themes/admin/css/design-system.css`. A implementação foi consolidada a partir dos ativos locais do Studio original do ERP, sem importar regras de negócio ou dependências legadas.

## Tipografia

- Texto normal, interface, formulários, inputs e botões usam `Gotham Book` em peso 400.
- Títulos, métricas e destaques usam `Gotham Medium` em peso 500. Não sintetizar negrito sobre `Gotham Book`.
- As fontes são servidas localmente; o fallback é Arial/sans-serif. Inter e Manrope não são fontes da interface administrativa Moves.
- A escala compartilhada do Meu Dia, ERP, Talk, Support, Studio e Application Shell é: 12/16, 14/20, 16/22, 18/24, 20/26 e 28/34 (tamanho/line-height, em pixels).
- Metadados usam 12/16; interface e texto forte usam 14/20; títulos de seção/cartão usam 16/22; destaques usam 20/26; títulos de página usam 28/34.
- `moves-typography.css` aplica a escala compartilhada depois das folhas dos produtos; `application-shell.css` mantém os tokens canônicos `--moves-*`.

## Ícones

O sprite SVG local e o helper `studio_icon()` são o único padrão de ícones do shell e dos módulos. Os ícones usam traço, alinhamento e escala comuns de 16, 18, 20 e 24 px. A fonte de ícones do ERP e os elementos `ion-icon` foram removidos.

O Moves Editor usa controles nativos e locais compatíveis com a CSP. Botões somente com ícone devem manter `aria-label`; `title` é complementar, não substituto.

## Tokens

Os tokens `--studio-*` representam cores, tipografia, escala de espaçamento, raios, sombras, dimensões estruturais e duração de movimentos. Componentes e módulos devem consumir esses tokens em vez de repetir valores literais.

- texto de interface: 14/20 px;
- metadados: 12/16 px;
- controles: 44 px de altura mínima;
- raio padrão: 6 px;
- sidebar e topbar: superfícies brancas;
- foco: anel roxo visível, sem depender apenas de cor.

O design system respeita `prefers-reduced-motion`, reduzindo as durações sem remover feedback de estado.

## Camadas CSS

- `design-system.css`: fontes, tokens e preferências globais;
- `studio-reference.css`: base, shell, navegação e componentes autoritativos;
- `compat.css`: adaptação temporária dos templates já existentes, sem regras de negócio.

O antigo `app.css` deixou de ser carregado e foi removido porque duplicava tokens, sidebar, topbar, forms e tabelas com outro padrão visual.

## Interações

`js/app.js` centraliza menu desktop/mobile, tema, perfil, Escape, fechamento de overlays e confirmações declarativas. `js/moves-editor.js` fornece a edição rica e integra o Moves Media sem CDN. Não são permitidos handlers inline em templates do Studio.

## Rotas

`/studio` é a base canônica. URLs GET antigas sob `/admin` respondem com redirect permanente `308`, preservando caminho e query string. Mutações só são registradas sob `/studio`.
