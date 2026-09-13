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
| Serviços | `servicos.html` e seus assets/comportamento | Rota `/servicos` e `pages/servicos.php` no tema público | Integrar a página ao Moves sem alterar conteúdo ou visual |
| Projetos | `projetos.html`, 24 apresentações e filtros | Rota `/projetos`, `pages/projetos.php` e assets locais | Preservar a galeria, filtros e atribuição sem shell duplicado |
| Sobre | Seção institucional `#about` da Home (não existe `sobre.html` na referência) | Rota `/sobre` e `pages/sobre.php` | Publicar apenas o conteúdo institucional aprovado, sem inventar uma página nova |
| Conteúdo | `conteudo.html` estático, com três guias | Rota `/conteudo` e índice editorial sem links quebrados | Criar a base pública do futuro blog em `/conteudo/{slug}` sem antecipar CMS ou páginas de artigo |
| Contato | `contato.html` e preparação local de e-mail | Rota `/contato` e `pages/contato.php` | Manter um endereço simples e conhecido, com foco comercial expresso pelo CTA “Solicitar orçamento ↗” |

## Assets migrados

- logotipo e favicon;
- imagens do hero e da equipe;
- imagens dos serviços, conceitos e artigos utilizadas pelas regras visuais da Home;
- fonte Manrope nos pesos 400, 500, 600, 700 e 800;
- estilos e comportamentos da referência, incluindo abertura, menu, reveal, contadores e movimento reduzido.
- 48 imagens do portfólio usadas nas 24 apresentações da página Projetos.

## SEO

A Home, Serviços, Projetos, Sobre, Conteúdo e Contato fornecem título e descrição próprios, robots, Open Graph básico e Twitter Card. O canonical utiliza `APP_URL` e o caminho da página somente em produção; desenvolvimento permanece `noindex, nofollow` e não publica uma URL local como canônica.

`movescode/seo` não está instalado e não foi adicionado nesta rodada. A API deve ser avaliada antes de uma eventual integração.

## Preparação para minificação

Os templates dependem dos contratos estáveis `css/app.css` e `js/app.js`, mantidos legíveis em desenvolvimento. Uma etapa futura pode gerar `app.min.css` e `app.min.js` para produção e selecionar esses nomes por configuração, sem introduzir uma cadeia Node.

`movescode/compress` não está instalado e não foi adicionado nesta rodada. Sua API e compatibilidade com cache busting devem ser avaliadas antes da integração.

## Diferenças deliberadas

- Os três conteúdos reais do protótipo foram estruturados semanticamente como artigos. Como `/conteudo/{slug}` ainda não foi implementado, os cards informam “Em breve” sem publicar links quebrados.
- O formulário de `/contato` prepara localmente uma mensagem e abre o cliente de e-mail; não envia dados ao servidor e não introduz backend improvisado.
- A área “Privacidade” do rodapé estático não foi exposta porque sua rota ainda não foi implementada.
- O CTA principal do header é “Solicitar orçamento ↗”; “Área do cliente” aponta para `/login` com tratamento visual secundário.

## Navegação oficial

| Item | Destino definitivo | Estado atual |
| --- | --- | --- |
| Início | `/` | ativo |
| Serviços | `/servicos` | ativo |
| Projetos | `/projetos` | ativo |
| Conteúdo | `/conteudo` | ativo; base editorial/blog |
| Solicitar orçamento ↗ | `/contato` | ativo; contato comercial |
| Área do cliente | `/login` | ativo |

Sobre está publicado em `/sobre`, mas intencionalmente não pertence à navbar principal; seu acesso institucional fica no footer. A evolução editorial planejada é `GET /conteudo/{slug}`, sem banco, CRUD, editor ou CMS nesta etapa.

## Roadmap após Conteúdo e Contato

A evolução administrativa seguirá esta ordem, mantendo cada etapa pequena e testável:

1. shell do Moves Studio;
2. dashboard;
3. usuários, configurações e log;
4. mídia;
5. artigos e leitura de `/conteudo` pelo banco;
6. páginas;
7. destaques;
8. depoimentos;
9. FAQ;
10. propostas e envio de `/contato` ao Studio;
11. relatórios e notificações.

Esta sequência é planejamento, não funcionalidade antecipada nesta entrega.

## Responsividade e acessibilidade

Os breakpoints originais de 1100, 980, 800, 640 e 520 pixels foram preservados. O menu mantém estado com `aria-expanded`, fecha por Escape, clique externo e seleção de item. `prefers-reduced-motion` desativa a abertura e animações não essenciais.

## Permissões de storage no XAMPP

**Antigo:** `storage/` pertencia a `djalmamartins:admin` com modo `755`; o PHP CLI escrevia como proprietário, mas o Apache `daemon:daemon` não conseguia escrever na raiz.

**Novo:** uma ACL permite ao usuário `daemon` criar e remover itens somente em `storage/` e `storage/uploads/`, com herança para novos itens. `storage/logs/` continua em `djalmamartins:daemon` com modo `775`.

**Motivo:** CLI e Apache executam com usuários diferentes. A ACL limita escrita às áreas de runtime e evita tornar o restante do projeto gravável.

Comandos usados neste ambiente macOS:

```bash
chmod +a 'daemon allow list,search,add_file,add_subdirectory,delete_child,file_inherit,directory_inherit,readattr,writeattr,readextattr,writeextattr,readsecurity' storage
chmod +a 'daemon allow list,search,add_file,add_subdirectory,delete_child,file_inherit,directory_inherit,readattr,writeattr,readextattr,writeextattr,readsecurity' storage/uploads
```

Nenhum `chmod 777` foi usado. A configuração é externa ao Git e deve ser reaplicada ao preparar um novo ambiente. Em servidores onde o deploy pode definir grupos, a alternativa preferencial é atribuir somente `storage/` ao grupo do processo web e conceder `g+rwX` com setgid nos diretórios.
