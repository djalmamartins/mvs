# Design system do Moves Studio

As fundações visuais do Studio ficam em `public/themes/admin/css/design-system.css`. A implementação foi consolidada a partir dos ativos locais do Studio original do ERP, sem importar regras de negócio ou dependências legadas.

## Tipografia

- Família principal: Gotham.
- Peso 400: `GothamBookRegular.otf`.
- Pesos 500 a 700: `GothamMedium.otf`.
- Fallbacks: Inter, fontes de sistema e Arial.
- Os arquivos são servidos localmente e pré-carregados pelo layout administrativo.

## Ícones

O sprite SVG e o helper `studio_icon()` continuam sendo a escolha padrão para componentes novos, pois permitem título acessível, controle de traço e dimensionamento consistente.

O arquivo `studio.woff` do ERP foi incorporado apenas como ponte de compatibilidade. Para usá-lo em uma tela migrada, combine `moves-font-icon` com um modificador semântico, por exemplo:

```html
<i class="moves-font-icon icon-article" aria-hidden="true"></i>
```

Os modificadores disponíveis cobrem dashboard, relatórios, notificações, páginas, artigos, mídia, destaques, comentários, usuários, configurações e ações comuns. A classe base é deliberadamente escopada dentro de `.studio-v2` para não colidir com o Organic Editor nem com bibliotecas externas.

## Tokens

Os tokens `--studio-*` representam cores, tipografia, escala de espaçamento, raios, sombras, dimensões estruturais e duração de movimentos. Componentes e módulos devem consumir esses tokens em vez de repetir valores literais.

O design system respeita `prefers-reduced-motion`, reduzindo as durações sem remover feedback de estado.
