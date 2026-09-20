<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Services\Support\ArticleService;
use Moves\Services\Support\CategoryService;
use Moves\Services\Support\ProductService;
use MovesCode\Model\Connection as ModelConnection;

Environment::load(dirname(__DIR__));
$pdo = Connection::getInstance();
ModelConnection::configure($pdo);

$authorId = (int) $pdo->query("SELECT id FROM users WHERE status = 'active' ORDER BY (role = 'admin') DESC, id ASC LIMIT 1")->fetchColumn();
if ($authorId <= 0) {
    throw new RuntimeException('Nenhum autor ativo foi encontrado.');
}

// Remove exclusivamente os registros descartáveis conhecidos das rodadas de homologação anteriores.
$testArticleSlugs = ['tes', 'sadasdas', 'teste-estabilizacao-base', 'teste-de-integracao-taxonomy-2a'];
$placeholders = implode(',', array_fill(0, count($testArticleSlugs), '?'));
$statement = $pdo->prepare("DELETE FROM support_articles WHERE slug IN ({$placeholders})");
$statement->execute($testArticleSlugs);
foreach ([
    ['support_categories', ['test', 'categoria-2a-taxonomy-teste']],
    ['support_products', ['teste', 'produto-2a-taxonomy-teste']],
    ['support_tags', ['teste-estabilizacao', 'support', 'tag-2a-taxonomy-atualizada']],
] as [$table, $slugs]) {
    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
    $statement = $pdo->prepare("DELETE FROM {$table} WHERE slug IN ({$placeholders})");
    $statement->execute($slugs);
}

$productService = new ProductService();
$categoryService = new CategoryService();
$articleService = new ArticleService();

$findId = static function (string $table, string $slug) use ($pdo): ?int {
    $statement = $pdo->prepare("SELECT id FROM {$table} WHERE slug = ? LIMIT 1");
    $statement->execute([$slug]);
    $id = $statement->fetchColumn();
    return $id === false ? null : (int) $id;
};

$product = static function (string $name, string $slug, string $description) use ($findId, $productService, $authorId): int {
    return $findId('support_products', $slug)
        ?? (int) $productService->create($name, $description, $authorId)->id;
};

$category = static function (string $name, string $slug, int $productId, int $position, string $description) use ($findId, $categoryService): int {
    return $findId('support_categories', $slug)
        ?? (int) $categoryService->create($name, $productId, null, $description, $position)->id;
};

$products = [
    'platform' => $product('Moves Platform', 'moves-platform', 'Visão geral, acesso, usuários e administração da plataforma Moves.'),
    'support' => $product('Moves Support', 'moves-support', 'Base de conhecimento e workspace de atendimento do Moves Support.'),
    'editor' => $product('Moves Editor', 'moves-editor', 'Editor visual oficial usado para produzir conteúdo no ecossistema Moves.'),
];

$categories = [
    'start' => $category('Primeiros passos', 'primeiros-passos', $products['platform'], 10, 'Orientações iniciais para conhecer e acessar o Moves.'),
    'users' => $category('Usuários', 'usuarios', $products['platform'], 20, 'Gestão e consulta de usuários da plataforma.'),
    'kb' => $category('Base de conhecimento', 'base-de-conhecimento', $products['support'], 10, 'Conceitos e operação da Knowledge Base.'),
    'articles' => $category('Artigos', 'artigos', $products['support'], 20, 'Criação, publicação, pesquisa e ciclo editorial.'),
    'organization' => $category('Organização do conteúdo', 'organizacao-do-conteudo', $products['support'], 30, 'Produtos, categorias, tags, revisões e lixeira.'),
    'service' => $category('Atendimento', 'atendimento', $products['support'], 40, 'Interface preparada para o futuro motor de chamados.'),
    'editor' => $category('Moves Editor', 'moves-editor', $products['editor'], 10, 'Uso do editor visual oficial.'),
    'media' => $category('Imagens e mídia', 'imagens-e-midia', $products['editor'], 20, 'Upload, biblioteca e apresentação de imagens.'),
];

$articles = [
    ['Conhecendo o Moves', 'conhecendo-o-moves', 'platform', 'start', ['primeiros-passos'], 'Uma visão geral da plataforma Moves e de como seus produtos compartilham a mesma experiência.', '<h2>Para que serve</h2><p>O Moves reúne produtos administrativos em uma plataforma única, com navegação, identidade e autenticação consistentes. Cada produto mantém responsabilidades próprias, mas utiliza a mesma base segura.</p><h2>Como acessar</h2><ol><li>Entre na aplicação com uma conta ativa.</li><li>Use o seletor de produtos no topo para escolher o workspace.</li><li>Confirme o nome do produto na navegação lateral antes de alterar dados.</li></ol><h2>Comportamento esperado</h2><p>Somente módulos habilitados aparecem para o usuário. O Moves Support concentra documentação e, futuramente, atendimento. O conteúdo exibido sempre deve refletir registros reais.</p>'],
    ['Conhecendo o Moves Support', 'conhecendo-o-moves-support', 'support', 'kb', ['primeiros-passos','artigos'], 'Entenda o workspace de documentação e atendimento do Moves Support.', '<h2>Visão geral</h2><p>O Moves Support organiza a Base de Conhecimento em Artigos, Produtos, Categorias e Tags. O dashboard resume os registros publicados, rascunhos e alterações recentes.</p><h2>Como acessar</h2><p>Abra o produto Support pelo seletor e use a barra lateral. A seção Conhecimento contém todo o ciclo editorial; a seção Atendimento apresenta as áreas que receberão o motor de chamados.</p><h2>Importante</h2><p>A Knowledge Base está operacional. Caixa de entrada, Meus chamados, Todos os chamados e SLA possuem layout final, mas o backend de atendimento será habilitado em uma etapa posterior.</p>'],
    ['Como funciona a Base de Conhecimento', 'como-funciona-a-base-de-conhecimento', 'support', 'kb', ['artigos','categorias','produtos','tags'], 'Conheça a estrutura que conecta artigos, produtos, categorias, tags e pesquisa.', '<h2>Estrutura</h2><p>Artigos são a unidade editorial. Cada artigo pode pertencer a um Produto e a uma Categoria, receber várias Tags e armazenar informações de SEO, autoria e publicação.</p><h2>Fluxo recomendado</h2><ol><li>Cadastre ou escolha o Produto.</li><li>Defina uma Categoria coerente.</li><li>Escreva e revise o Artigo.</li><li>Preencha SEO e Tags.</li><li>Salve como rascunho ou publique.</li></ol><h2>Resultados</h2><p>Busca, filtros, dashboard e relatórios consultam o banco em tempo real. Itens enviados à lixeira deixam de participar das métricas normais.</p>'],
    ['Como criar um artigo', 'como-criar-um-artigo', 'support', 'articles', ['artigos','editor','seo'], 'Passo a passo para cadastrar um artigo completo e seguro no Moves Support.', '<h2>Como acessar</h2><p>Acesse Support, Artigos e selecione Novo artigo.</p><h2>Passo a passo</h2><ol><li>Informe título, slug e resumo.</li><li>Escreva o conteúdo no Moves Editor.</li><li>Escolha autor, Produto, Categoria e Tags.</li><li>Defina capa e dados de SEO quando aplicável.</li><li>Selecione Rascunho ou Publicado e salve.</li></ol><h2>Comportamento esperado</h2><p>O slug é normalizado e precisa ser único. O conteúdo é sanitizado no servidor. Após salvar, o artigo aparece nas listas e nas métricas compatíveis com seu status.</p>'],
    ['Como editar e publicar um artigo', 'como-editar-e-publicar-um-artigo', 'support', 'articles', ['artigos','revisoes'], 'Edite conteúdo, altere o estado editorial e publique com segurança.', '<h2>Editar</h2><p>Na lista de Artigos, abra o registro desejado. Revise título, resumo, conteúdo, classificação, SEO e capa antes de salvar.</p><h2>Publicar e arquivar</h2><p>Publicado torna o registro elegível para consumo público quando essa integração estiver ativa. Rascunho mantém o trabalho interno. Arquivado preserva o conteúdo sem mantê-lo como publicação ativa.</p><h2>Revisões</h2><p>Quando título, resumo ou conteúdo mudam, o Support salva um snapshot anterior em Revisões. Esse snapshot é uma revisão de conteúdo e não um backup integral do artigo.</p>'],
    ['Como organizar artigos com Produtos e Categorias', 'como-organizar-artigos-com-produtos-e-categorias', 'support', 'organization', ['produtos','categorias'], 'Use Produtos e Categorias para criar uma taxonomia clara e sustentável.', '<h2>Produtos</h2><p>Produto identifica a área principal sobre a qual o artigo fala, como Moves Support ou Moves Editor.</p><h2>Categorias</h2><p>Categoria agrupa assuntos dentro do mesmo Produto e pode formar uma hierarquia. A categoria pai precisa pertencer ao mesmo Produto.</p><h2>Boas práticas</h2><ul><li>Evite nomes duplicados ou genéricos.</li><li>Escolha uma única categoria principal por artigo.</li><li>Não exclua estruturas em uso; primeiro reorganize seus artigos.</li></ul>'],
    ['Como utilizar Tags', 'como-utilizar-tags', 'support', 'organization', ['tags','artigos'], 'Classifique temas transversais com tags úteis e consistentes.', '<h2>Para que servem</h2><p>Tags conectam artigos por temas que atravessam Produtos e Categorias, como SEO, imagens ou revisões.</p><h2>Como usar</h2><p>No formulário do artigo, selecione as tags existentes ou informe as previstas pela interface. Ao salvar, as relações são sincronizadas sem apagar os artigos.</p><h2>Boas práticas</h2><p>Prefira termos curtos, reutilizáveis e sem duplicações. Categoria define o assunto principal; tag acrescenta contexto.</p>'],
    ['Como utilizar o Moves Editor', 'como-utilizar-o-moves-editor', 'editor', 'editor', ['editor','artigos'], 'Conheça os recursos de edição visual e HTML do Moves Editor 1.0.0.', '<h2>Modos de edição</h2><p>O modo Visual oferece formatação direta. O modo HTML permite revisar a marcação produzida. Alterne entre eles sem sair do formulário.</p><h2>Recursos</h2><p>Use títulos, negrito, itálico, sublinhado, listas, citação, código, links, alinhamento, recuo, tabelas e imagens. O contador e a estimativa de leitura acompanham o texto.</p><h2>Segurança</h2><p>O servidor remove scripts, eventos e URLs perigosas. Salve e reabra o artigo para confirmar a persistência do resultado final.</p>'],
    ['Como inserir e configurar imagens', 'como-inserir-e-configurar-imagens', 'editor', 'media', ['editor','imagens'], 'Envie ou selecione imagens e configure acessibilidade, legenda e dimensões.', '<h2>Inserir imagem</h2><p>No Moves Editor, use a ação de imagem e selecione um arquivo da biblioteca oficial. Para a capa, use Escolher da biblioteca no painel lateral.</p><h2>Configuração</h2><p>Informe texto alternativo objetivo, legenda quando necessária, largura em porcentagem ou pixels e alinhamento. O texto ALT descreve a informação visual para acessibilidade.</p><h2>Verificação</h2><p>Salve, reabra o artigo e confirme imagem, ALT, legenda, dimensão e alinhamento. A mídia continua administrada pela tabela studio_media.</p>'],
    ['Como funciona o SEO dos artigos', 'como-funciona-o-seo-dos-artigos', 'support', 'articles', ['seo','artigos'], 'Preencha título, descrição, palavra-chave, canonical e diretivas de indexação.', '<h2>Campos</h2><p>Meta title identifica a página nos resultados de busca. Meta description resume o benefício. Focus keyword registra o tema principal. Canonical aponta para a URL preferencial quando necessário.</p><h2>Boas práticas</h2><p>Use textos naturais, específicos e coerentes com o conteúdo. Não repita palavras-chave artificialmente e não informe canonical sem necessidade.</p><h2>Robots</h2><p>As opções index e follow controlam as diretivas editoriais armazenadas. O SEO não substitui conteúdo útil, hierarquia clara e URLs estáveis.</p>'],
    ['Como funcionam os Rascunhos', 'como-funcionam-os-rascunhos', 'support', 'articles', ['rascunhos','artigos'], 'Entenda rascunhos salvos e a recuperação local do Moves Editor.', '<h2>Rascunho editorial</h2><p>Um artigo com status Rascunho fica salvo no banco e pode ser localizado pela página Rascunhos e pelos filtros.</p><h2>Rascunho local</h2><p>Durante a edição, o navegador mantém uma cópia temporária vinculada ao documento. A recuperação só é oferecida para o mesmo artigo quando a cópia é relevante e diferente.</p><h2>Após salvar</h2><p>O rascunho local é removido quando o servidor confirma o salvamento. Em caso de erro, ele permanece disponível para evitar perda de trabalho.</p>'],
    ['Como funcionam as Revisões', 'como-funcionam-as-revisoes', 'support', 'organization', ['revisoes','artigos'], 'Consulte snapshots anteriores de título, resumo e conteúdo.', '<h2>Quando são criadas</h2><p>Uma revisão é criada antes de salvar mudanças em título, resumo ou conteúdo de um artigo existente.</p><h2>O que contém</h2><p>O snapshot guarda título, resumo, conteúdo, autor da alteração e data. A listagem permite localizar e visualizar essas informações.</p><h2>Limitação atual</h2><p>Revisões são histórico de conteúdo, não backup completo. A restauração automática de snapshot fica planejada para uma versão posterior após validação transacional específica.</p>'],
    ['Como funciona a Lixeira', 'como-funciona-a-lixeira', 'support', 'organization', ['lixeira','artigos'], 'Remova, restaure ou exclua definitivamente artigos com segurança.', '<h2>Mover para a lixeira</h2><p>A ação remove o artigo das listagens, métricas e relatórios normais sem apagar imediatamente seus dados.</p><h2>Restaurar</h2><p>Restaurar devolve o artigo com slug, conteúdo, autor, classificação, tags, SEO, mídia e revisões preservados.</p><h2>Excluir definitivamente</h2><p>A exclusão permanente apaga o artigo e seus relacionamentos dependentes. Use apenas em registros descartáveis ou quando houver certeza de que não será necessário recuperar.</p>'],
    ['Como pesquisar e filtrar artigos', 'como-pesquisar-e-filtrar-artigos', 'support', 'articles', ['artigos','produtos','categorias'], 'Combine busca, Produto, Categoria, status e paginação sem perder o contexto.', '<h2>Busca</h2><p>Informe parte do título, resumo ou slug na caixa de pesquisa.</p><h2>Filtros</h2><p>Combine Produto, Categoria e status editorial. A consulta é executada no servidor e a paginação preserva os parâmetros ativos.</p><h2>Resultado esperado</h2><p>A lista e o total devem refletir somente os registros compatíveis. Limpe os campos ou use a ação correspondente para retornar à visão completa.</p>'],
    ['Como funciona a Caixa de entrada', 'como-funciona-a-caixa-de-entrada', 'support', 'service', ['atendimento'], 'Conheça o layout preparado para a futura entrada operacional de chamados.', '<h2>Estado atual</h2><p>A Caixa de entrada possui layout final com lista de chamados, área de atendimento e painel de detalhes.</p><h2>Funcionamento planejado</h2><p>Quando o motor de atendimento for habilitado, novos chamados aparecerão na lista, a conversa ocupará o painel central e solicitante, status e histórico serão exibidos nos detalhes.</p><h2>Disponibilidade</h2><p>O backend de tickets ainda não está habilitado. O estado vazio é intencional e não representa falha da Knowledge Base.</p>'],
    ['Como funcionarão Meus chamados', 'como-funcionarao-meus-chamados', 'support', 'service', ['atendimento'], 'Entenda a visão preparada para chamados atribuídos ao usuário autenticado.', '<h2>Objetivo</h2><p>Meus chamados exibirá somente tickets sob responsabilidade do usuário autenticado.</p><h2>Layout preparado</h2><p>A página já reserva busca, filtros e colunas de chamado, assunto, solicitante, prioridade, status e atualização.</p><h2>Disponibilidade</h2><p>A atribuição e a consulta de tickets dependem do futuro motor de atendimento. Nenhum registro fictício é exibido enquanto essa integração não estiver ativa.</p>'],
    ['Como funcionará a visão Todos os chamados', 'como-funcionara-a-visao-todos-os-chamados', 'support', 'service', ['atendimento'], 'Conheça a visão administrativa planejada para supervisão de tickets.', '<h2>Objetivo</h2><p>Todos os chamados será a visão de supervisão para pesquisar e acompanhar tickets de toda a operação autorizada.</p><h2>Layout preparado</h2><p>A tela prevê filtros por status, prioridade, Produto e responsável, além de paginação e colunas operacionais.</p><h2>Disponibilidade</h2><p>O layout está concluído; permissões, filas e dados dependerão do backend de tickets futuro.</p>'],
    ['Como funcionará o SLA', 'como-funcionara-o-sla', 'support', 'service', ['sla','atendimento'], 'Entenda o espaço preparado para políticas reais de tempo de atendimento.', '<h2>Objetivo</h2><p>SLA organizará compromissos de primeira resposta e resolução por prioridade e horário de atendimento.</p><h2>Layout preparado</h2><p>A interface prevê prioridade, primeira resposta, resolução, horário e status.</p><h2>Disponibilidade</h2><p>Nenhuma política fictícia foi criada. O backend de SLA será habilitado junto ao motor de chamados, após definição das regras operacionais reais.</p>'],
    ['Como funciona a área de Usuários', 'como-funciona-a-area-de-usuarios', 'platform', 'users', ['usuarios'], 'Consulte usuários reais e refine a lista por busca, perfil e situação.', '<h2>Como acessar</h2><p>No Moves Support, abra Usuários na navegação lateral.</p><h2>Recursos</h2><p>A página consulta usuários reais e permite buscar por nome ou e-mail, além de filtrar perfil e status.</p><h2>Segurança</h2><p>Senhas e dados sensíveis não aparecem na listagem. Alterações de identidade e permissões continuam sujeitas às regras centrais da plataforma.</p>'],
    ['Como funcionam os Relatórios do Support', 'como-funcionam-os-relatorios-do-support', 'support', 'kb', ['artigos','revisoes','produtos'], 'Acompanhe métricas reais da Knowledge Base por status e classificação.', '<h2>Indicadores</h2><p>Relatórios consolida artigos ativos, publicados, rascunhos, arquivados, Produtos, Categorias, Tags e Revisões.</p><h2>Distribuições</h2><p>Os painéis agrupam artigos por status, Produto e Categoria usando consultas reais ao banco.</p><h2>Regras</h2><p>Artigos na lixeira não entram nas métricas normais. Os números mudam após criar, publicar, arquivar, restaurar ou excluir registros.</p>'],
];

$created = 0;
$updated = 0;
foreach ($articles as [$title, $slug, $productKey, $categoryKey, $tags, $excerpt, $content]) {
    $existing = $articleService->findBySlug($slug);
    $arguments = [
        $title, $products[$productKey], $categories[$categoryKey], $excerpt, $content,
        $authorId, $slug, null, $title . ' | Moves', $excerpt,
        $tags[0] ?? null, null, true, true, $tags, 'published',
    ];
    if ($existing === null) {
        $articleService->create(...$arguments);
        $created++;
        continue;
    }
    $articleService->update(
        (int) $existing->id, $title, $products[$productKey], $categories[$categoryKey],
        $excerpt, $content, 'published', $authorId, $authorId, $slug, null,
        $title . ' | Moves', $excerpt, $tags[0] ?? null, null, true, true, $tags
    );
    $updated++;
}

echo "Moves Support KB: {$created} artigo(s) criado(s), {$updated} atualizado(s)." . PHP_EOL;
