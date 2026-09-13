# Design system do Moves Studio

As fundações visuais do Studio ficam em `public/themes/admin/css/design-system.css`. A implementação foi consolidada a partir dos ativos locais do Studio original do ERP, sem importar regras de negócio ou dependências legadas.

## Tipografia

- Família principal: Gotham.
- Peso 400: `GothamBookRegular.otf`.
- Pesos 500 a 700: `GothamMedium.otf`.
- Fallbacks: Inter, fontes de sistema e Arial.
- Os arquivos são servidos localmente e pré-carregados pelo layout administrativo.

## Ícones

O sprite SVG local e o helper `studio_icon()` são o único padrão de ícones do shell e dos módulos. Os ícones usam traço, alinhamento e escala comuns de 16, 18, 20 e 24 px. A fonte de ícones do ERP e os elementos `ion-icon` foram removidos.

O Moves Editor usa controles nativos e locais compatíveis com a CSP. Botões somente com ícone devem manter `aria-label`; `title` é complementar, não substituto.

## Tokens

Os tokens `--studio-*` representam cores, tipografia, escala de espaçamento, raios, sombras, dimensões estruturais e duração de movimentos. Componentes e módulos devem consumir esses tokens em vez de repetir valores literais.

- base tipográfica: 16 px;
- texto funcional e auxiliar: mínimo de 14 px;
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
