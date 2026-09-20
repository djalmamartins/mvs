<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Logger;
use Moves\Core\HtmlSanitizer;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Core\Seo;
use Moves\Services\CmsService;
use MovesCode\Storage\Image;
use PDO;
use Throwable;

/** Small, secured CMS and commercial modules for Moves Studio. */
final class StudioModulesController extends Controller
{
    /** @var array<string, array{type: string, title: string, singular: string}> */
    private const CONTENT_MODULES = [
        'pages' => ['type' => 'page', 'title' => 'Páginas', 'singular' => 'Página'],
        'projects' => ['type' => 'project', 'title' => 'Projetos', 'singular' => 'Projeto'],
        'articles' => ['type' => 'article', 'title' => 'Artigos', 'singular' => 'Artigo'],
        'highlights' => ['type' => 'highlight', 'title' => 'Destaques', 'singular' => 'Destaque'],
        'testimonials' => ['type' => 'testimonial', 'title' => 'Depoimentos', 'singular' => 'Depoimento'],
        'faq' => ['type' => 'faq', 'title' => 'FAQ', 'singular' => 'Pergunta'],
    ];

    public function content(array $routeData = []): void
    {
        $module = $this->currentModule();
        $definition = self::CONTENT_MODULES[$module];
        $pdo = Connection::getInstance();

        if (Request::isMethod('POST')) {
            $this->validateCsrf();
            $action = (string) Request::post('action', 'save');
            $id = max(0, (int) Request::post('id', 0));

            if (in_array($module, ['articles', 'faq', 'projects'], true) && $action === 'category') {
                $name = mb_substr(trim(strip_tags((string) Request::post('category_name', ''))), 0, 120);
                if (mb_strlen($name) < 2) { Flash::set('error', 'Informe o nome da categoria.'); Response::to('/studio/' . $module); }
                $taxonomyType = match ($module) { 'articles' => 'article_category', 'projects' => 'project_category', default => 'faq_category' };
                try { $pdo->prepare('INSERT INTO studio_taxonomies(type,name,slug) VALUES(?,?,?)')->execute([$taxonomyType, $name, $this->slug($name)]); Logger::info('Categoria de conteúdo criada.', ['action'=>'category_created','module'=>$module,'record_id'=>(int)$pdo->lastInsertId(),'actor_id'=>Auth::user()?->id]); Flash::set('success', 'Categoria criada.'); }
                catch (Throwable $exception) { Flash::set('error', 'A categoria já existe ou não pôde ser criada.'); }
                Response::to('/studio/' . $module);
            }

            if ($action === 'restore_revision' && in_array($module, ['articles', 'pages'], true)) {
                $this->restoreRevision($pdo, $id, max(0, (int) Request::post('revision_id', 0)), $definition['type'], $module);
            }

            if ($action === 'delete') {
                (new CmsService())->trash($id, $definition['type'], (int) Auth::user()?->id);
                Flash::set('success', $definition['singular'] . ' movido(a) para a lixeira.');
                Logger::info('Conteúdo do Studio movido para a lixeira.', ['action'=>'trashed','module' => $module, 'record_id' => $id,'actor_id'=>Auth::user()?->id]);
                Response::to('/studio/' . $module);
            }

            $title = mb_substr(trim(strip_tags((string) Request::post('title', ''))), 0, 180);
            $requestedSlug = trim((string) Request::post('slug', ''));
            $excerpt = mb_substr(trim(strip_tags((string) Request::post('excerpt', ''))), 0, 1000);
            $submittedContent = mb_substr(trim((string) Request::post('content', '')), 0, 50000);
            $content = in_array($module, ['articles', 'pages', 'projects', 'faq'], true) ? HtmlSanitizer::clean($submittedContent) : trim(strip_tags($submittedContent));
            $status = in_array(Request::post('status'), ['draft', 'published', 'archived'], true)
                ? (string) Request::post('status')
                : 'draft';
            $position = max(0, min(9999, (int) Request::post('position', 0)));
            $seoTitle = mb_substr(trim(strip_tags((string) Request::post('seo_title', ''))), 0, 160);
            $seoDescription = mb_substr(trim(strip_tags((string) Request::post('seo_description', ''))), 0, 320);
            $seoFocusKeyword = mb_substr(trim(strip_tags((string) Request::post('seo_focus_keyword', ''))), 0, 120);
            $canonicalUrl = mb_substr(trim((string) Request::post('canonical_url', '')), 0, 500);
            if ($canonicalUrl !== '' && (filter_var($canonicalUrl, FILTER_VALIDATE_URL) === false || !in_array(strtolower((string) parse_url($canonicalUrl, PHP_URL_SCHEME)), ['http', 'https'], true))) { Flash::set('error', 'Informe uma URL canonical HTTP(S) válida.'); Response::to('/studio/' . $module . ($id ? '/edit/' . $id : '/create/1')); }
            $robotsIndex = Request::post('robots_index') === '1' ? 1 : 0;
            $robotsFollow = Request::post('robots_follow') === '1' ? 1 : 0;
            $automaticSeo = Seo::contentFields($title, $excerpt, $content, $requestedSlug, $seoTitle, $seoDescription);
            $slug = $automaticSeo['slug'];
            $seoTitle = $automaticSeo['title'];
            $seoDescription = $automaticSeo['description'];
            $mediaId = max(0, (int) Request::post('media_id', 0)) ?: null;
            $categoryId = in_array($module, ['articles', 'faq', 'projects'], true) ? (max(0, (int) Request::post('category_id', 0)) ?: null) : null;
            $template = $module === 'pages' && in_array(Request::post('template'), ['default', 'landing', 'wide'], true) ? (string) Request::post('template') : null;
            $startsAt = $module === 'highlights' ? $this->dateTime((string) Request::post('starts_at', '')) : null;
            $endsAt = $module === 'highlights' ? $this->dateTime((string) Request::post('ends_at', '')) : null;
            $meta = match ($module) {
                'articles' => ['video' => mb_substr(trim(strip_tags((string) Request::post('video', ''))), 0, 255)],
                'projects' => [
                    'client' => mb_substr(trim(strip_tags((string) Request::post('client', ''))), 0, 160),
                    'project_url' => mb_substr(trim((string) Request::post('project_url', '')), 0, 500),
                    'kind' => mb_substr(trim(strip_tags((string) Request::post('kind', ''))), 0, 120),
                    'image' => mb_substr(trim((string) Request::post('image', '')), 0, 500),
                    'backdrop' => mb_substr(trim((string) Request::post('backdrop', '')), 0, 500),
                ],
                'highlights' => ['cta_label' => mb_substr(trim(strip_tags((string) Request::post('cta_label', ''))), 0, 80), 'cta_url' => mb_substr(trim((string) Request::post('cta_url', '')), 0, 500), 'alignment' => in_array(Request::post('alignment'), ['left', 'center', 'right'], true) ? Request::post('alignment') : 'left'],
                'testimonials' => ['company' => mb_substr(trim(strip_tags((string) Request::post('company', ''))), 0, 160), 'job_title' => mb_substr(trim(strip_tags((string) Request::post('job_title', ''))), 0, 120)],
                default => [],
            };

            if ($mediaId !== null && !$this->mediaExists($mediaId)) { $mediaId = null; }
            if ($module === 'articles' && $categoryId !== null && !$this->taxonomyExists($categoryId, 'article_category')) { $categoryId = null; }
            if ($module === 'articles' && $categoryId === null) { Flash::set('error', 'Selecione uma categoria válida.'); Response::to('/studio/articles' . ($id ? '?edit=' . $id : '')); }
            if ($module === 'projects' && $categoryId !== null && !$this->taxonomyExists($categoryId, 'project_category')) { $categoryId = null; }
            if ($module === 'projects' && $categoryId === null) { Flash::set('error', 'Selecione uma categoria válida.'); Response::to('/studio/projects' . ($id ? '?edit=' . $id : '')); }
            if ($module === 'projects' && ($meta['project_url'] ?? '') !== '') {
                $projectUrl = (string) $meta['project_url'];
                $scheme = strtolower((string) parse_url($projectUrl, PHP_URL_SCHEME));
                $isInternal = str_starts_with($projectUrl, '/') && !str_starts_with($projectUrl, '//');
                $isExternal = filter_var($projectUrl, FILTER_VALIDATE_URL) !== false && in_array($scheme, ['http', 'https'], true);
                if (!$isInternal && !$isExternal) { Flash::set('error', 'Informe uma URL HTTP(S) ou um caminho interno válido para o projeto.'); Response::to('/studio/projects' . ($id ? '?edit=' . $id : '')); }
            }
            if ($module === 'faq' && $categoryId !== null && !$this->taxonomyExists($categoryId, 'faq_category')) { $categoryId = null; }
            if ($module === 'faq' && $categoryId === null) { Flash::set('error', 'Selecione uma categoria válida.'); Response::to('/studio/faq' . ($id ? '?edit=' . $id : '')); }
            if ($startsAt && $endsAt && $startsAt > $endsAt) { Flash::set('error', 'O fim da exibição deve ocorrer depois do início.'); Response::to('/studio/highlights' . ($id ? '?edit=' . $id : '')); }
            if (($meta['cta_url'] ?? '') !== '' && filter_var($meta['cta_url'], FILTER_VALIDATE_URL) === false && !str_starts_with((string) $meta['cta_url'], '/')) { Flash::set('error', 'Informe uma URL de CTA válida.'); Response::to('/studio/highlights' . ($id ? '?edit=' . $id : '')); }

            if (mb_strlen($title) < 3 || $slug === '') {
                Flash::set('error', 'Informe um título válido.');
                Response::to('/studio/' . $module . ($id ? '?edit=' . $id : ''));
            }

            try {
                $previousStatus = null;
                if ($id > 0) {
                    $statusStatement = $pdo->prepare('SELECT status FROM studio_content WHERE id=? AND type=? AND deleted_at IS NULL');
                    $statusStatement->execute([$id, $definition['type']]);
                    $previousStatus = $statusStatement->fetchColumn() ?: null;
                }
                if ($id > 0) {
                    $statement = $pdo->prepare('UPDATE studio_content SET title=?,slug=?,excerpt=?,content=?,seo_title=?,seo_description=?,seo_focus_keyword=?,canonical_url=?,robots_index=?,robots_follow=?,media_id=?,category_id=?,template=?,meta_json=?,status=?,position=?,starts_at=?,ends_at=?,published_at=? WHERE id=? AND type=? AND deleted_at IS NULL');
                    $statement->execute([$title, $slug, $excerpt ?: null, $content ?: null, $seoTitle ?: null, $seoDescription ?: null, $seoFocusKeyword ?: null, $canonicalUrl ?: null, $robotsIndex, $robotsFollow, $mediaId, $categoryId, $template, json_encode($meta, JSON_UNESCAPED_UNICODE), $status, $position, $startsAt, $endsAt, $status === 'published' ? date('Y-m-d H:i:s') : null, $id, $definition['type']]);
                } else {
                    $statement = $pdo->prepare('INSERT INTO studio_content(type,title,slug,excerpt,content,seo_title,seo_description,seo_focus_keyword,canonical_url,robots_index,robots_follow,media_id,category_id,template,meta_json,status,position,starts_at,ends_at,published_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $statement->execute([$definition['type'], $title, $slug, $excerpt ?: null, $content ?: null, $seoTitle ?: null, $seoDescription ?: null, $seoFocusKeyword ?: null, $canonicalUrl ?: null, $robotsIndex, $robotsFollow, $mediaId, $categoryId, $template, json_encode($meta, JSON_UNESCAPED_UNICODE), $status, $position, $startsAt, $endsAt, $status === 'published' ? date('Y-m-d H:i:s') : null, Auth::user()?->id]);
                    $id = (int) $pdo->lastInsertId();
                }
                if (in_array($module, ['articles', 'pages', 'projects'], true)) {
                    $tagIds = array_values(array_filter(array_map('intval', (array) Request::post('tag_ids', []))));
                    (new CmsService())->syncTags($id, $tagIds);
                }
                if (in_array($module, ['articles', 'pages'], true)) { $this->recordRevision($pdo, $id, $definition['type'], $previousStatus === null ? 'created' : 'updated'); }
                $auditAction = $previousStatus === null ? 'created' : ($previousStatus !== $status ? 'status_changed' : 'updated');
                Logger::info('Conteúdo do Studio alterado.', ['action'=>$auditAction,'module'=>$module,'record_id'=>$id,'actor_id'=>Auth::user()?->id,'previous_status'=>$previousStatus,'status'=>$status]);
            } catch (Throwable $exception) {
                Logger::exception($exception);
                Flash::set('error', 'Não foi possível salvar. Verifique se o slug já está em uso.');
                Response::to('/studio/' . $module . ($id ? '?edit=' . $id : ''));
            }

            Flash::set('success', $definition['singular'] . ' salvo(a).');
            $savedDraftKey = mb_substr((string) Request::post('autosave_key', $module . ':' . $id), 0, 100);
            Response::to('/studio/' . $module . '/edit/' . $id . '?saved_key=' . rawurlencode($savedDraftKey));
        }

        $search = mb_substr(trim(strip_tags((string) Request::get('q', ''))), 0, 100);
        $status = in_array(Request::get('status'), ['draft', 'published', 'archived'], true) ? (string) Request::get('status') : '';
        $authorId = max(0, (int) Request::get('author', 0));
        $categoryFilter = max(0, (int) Request::get('category', 0));
        $page = max(1, (int) Request::get('page', 1));
        $cms = new CmsService();
        $pagination = $cms->contentPage($definition['type'], ['q'=>$search,'status'=>$status,'author'=>$authorId,'category'=>$categoryFilter], $page);
        $edit = null;
        $routeId = max(0, (int) ($routeData['id'] ?? 0));
        $editId = str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '/edit/') ? $routeId : max(0, (int) Request::get('edit', 0));
        if ($editId) {
            $editStatement = $pdo->prepare('SELECT * FROM studio_content WHERE id=? AND type=? AND deleted_at IS NULL');
            $editStatement->execute([$editId, $definition['type']]);
            $edit = $editStatement->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $media = $pdo->query('SELECT id,name,width,height FROM studio_media ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $taxonomyType = match ($module) { 'faq' => 'faq_category', 'projects' => 'project_category', default => 'article_category' };
        $categoryStatement = $pdo->prepare('SELECT id,name FROM studio_taxonomies WHERE type=? ORDER BY name'); $categoryStatement->execute([$taxonomyType]);
        $categories = $categoryStatement->fetchAll(PDO::FETCH_ASSOC);
        if ($edit) { $edit['meta'] = json_decode((string) ($edit['meta_json'] ?? ''), true) ?: []; }
        $tags = $cms->tags();
        $selectedTagIds = $edit ? array_map(static fn(array $tag): int => (int) $tag['id'], $cms->tagsForContent((int) $edit['id'])) : [];
        $revisions = [];
        $selectedRevision = null;
        if ($edit && in_array($module, ['articles', 'pages'], true)) {
            $revisionStatement = $pdo->prepare('SELECT r.id,r.revision_number,r.reason,r.title,r.slug,r.status,r.created_at,r.created_by,u.name author_name,LENGTH(r.content) content_size FROM studio_content_revisions r LEFT JOIN users u ON u.id=r.created_by WHERE r.content_id=? ORDER BY r.revision_number DESC LIMIT 30');
            $revisionStatement->execute([(int) $edit['id']]);
            $revisions = $revisionStatement->fetchAll(PDO::FETCH_ASSOC);
            $revisionId = max(0, (int) Request::get('revision', 0));
            if ($revisionId) {
                $selectedStatement = $pdo->prepare('SELECT r.*,u.name author_name FROM studio_content_revisions r LEFT JOIN users u ON u.id=r.created_by WHERE r.id=? AND r.content_id=?');
                $selectedStatement->execute([$revisionId, (int) $edit['id']]);
                $selectedRevision = $selectedStatement->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        }
        echo $this->view->render('pages/content-module', [
            'title' => $definition['title'], 'module' => $module, 'singular' => $definition['singular'],
            'records' => $pagination['items'], 'edit' => $edit, 'search' => $search, 'status' => $status, 'authorId'=>$authorId, 'categoryFilter'=>$categoryFilter, 'authors'=>$cms->authors(), 'pagination'=>$pagination, 'media' => $media, 'categories' => $categories, 'tags'=>$tags, 'selectedTagIds'=>$selectedTagIds, 'revisions' => $revisions, 'selectedRevision' => $selectedRevision,
        ]);
    }

    public function categories(): void
    {
        $cms=new CmsService();
        if(Request::isMethod('POST')){
            $this->validateCsrf();
            try{
                if(Request::post('action')==='delete'){$cms->deleteCategory(max(0,(int)Request::post('id',0)));Flash::set('success','Categoria excluída.');}
                else{$cms->saveCategory(max(0,(int)Request::post('id',0)),(string)Request::post('name',''),(string)Request::post('type',''));Flash::set('success','Categoria salva.');}
            }catch(Throwable $exception){Flash::set('error',$exception->getMessage());}
            Response::to('/studio/categories');
        }
        $q=mb_substr(trim(strip_tags((string)Request::get('q',''))),0,100);
        $type=in_array(Request::get('type'),['article_category','project_category','faq_category'],true)?(string)Request::get('type'):'';
        echo $this->view->render('pages/cms-categories',['title'=>'Categorias','currentPage'=>'categories','categories'=>$cms->categories($q,$type),'q'=>$q,'type'=>$type]);
    }

    public function trash(): void
    {
        $cms = new CmsService();
        if (Request::isMethod('POST')) {
            $this->validateCsrf();
            $id = max(0, (int) Request::post('id', 0));
            $action = (string) Request::post('action', '');
            $changed = $action === 'restore' ? $cms->restore($id, (int) Auth::user()?->id) : ($action === 'delete' ? $cms->deletePermanently($id) : false);
            Flash::set($changed ? 'success' : 'error', $changed ? ($action === 'restore' ? 'Conteúdo restaurado.' : 'Conteúdo excluído permanentemente.') : 'O conteúdo não está mais disponível na lixeira.');
            Logger::info('Ação executada na lixeira do CMS.', ['action'=>'trash_' . $action,'record_id'=>$id,'actor_id'=>Auth::user()?->id]);
            Response::to('/studio/trash');
        }
        $q = mb_substr(trim(strip_tags((string) Request::get('q', ''))), 0, 100);
        $type = in_array(Request::get('type'), array_column(self::CONTENT_MODULES, 'type'), true) ? (string) Request::get('type') : '';
        $page = max(1, (int) Request::get('page', 1));
        $pagination = $cms->trashPage($q, $type, $page);
        echo $this->view->render('pages/cms-trash', ['title'=>'Lixeira','currentPage'=>'trash','items'=>$pagination['items'],'pagination'=>$pagination,'q'=>$q,'type'=>$type,'modules'=>self::CONTENT_MODULES]);
    }

    public function tags(): void
    {
        $cms = new CmsService();
        if (Request::isMethod('POST')) {
            $this->validateCsrf();
            try {
                if (Request::post('action') === 'delete') { $cms->deleteTag(max(0, (int) Request::post('id', 0))); Flash::set('success', 'Tag excluída.'); }
                else { $cms->saveTag(max(0, (int) Request::post('id', 0)), (string) Request::post('name', '')); Flash::set('success', 'Tag salva.'); }
            } catch (Throwable $exception) { Flash::set('error', $exception->getMessage()); }
            Response::to('/studio/tags');
        }
        $q = mb_substr(trim(strip_tags((string) Request::get('q', ''))), 0, 100);
        echo $this->view->render('pages/cms-tags', ['title'=>'Tags','currentPage'=>'tags','tags'=>$cms->tags($q),'q'=>$q]);
    }

    /** @param array<string,string> $data */
    public function preview(array $data): void
    {
        $id = max(0, (int) ($data['id'] ?? 0));
        $statement = Connection::getInstance()->prepare('SELECT c.*,m.alt_text FROM studio_content c LEFT JOIN studio_media m ON m.id=c.media_id WHERE c.id=? AND c.deleted_at IS NULL LIMIT 1');
        $statement->execute([$id]);
        $content = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$content) { http_response_code(404); return; }
        header('X-Robots-Tag: noindex, nofollow');
        $content['rendered_content'] = HtmlSanitizer::clean((string) ($content['content'] ?? ''));
        echo $this->view->render('pages/content-preview', ['title'=>'Pré-visualização · ' . $content['title'],'content'=>$content,'canonical'=>null,'robots'=>'noindex, nofollow']);
    }

    public function menus(): void
    {
        $pdo = Connection::getInstance();
        $cms = new CmsService();
        if (Request::isMethod('POST')) {
            $this->validateCsrf();
            $action = (string) Request::post('action', 'save_menu');
            try {
                if ($action === 'save_menu') {
                    $id=max(0,(int)Request::post('id',0)); $name=mb_substr(trim(strip_tags((string)Request::post('name',''))),0,120); $location=Seo::slug((string)Request::post('location','')); $status=Request::post('status')==='inactive'?'inactive':'active';
                    if (mb_strlen($name)<2||$location==='') { throw new \RuntimeException('Informe nome e localização válidos.'); }
                    if($id){$pdo->prepare('UPDATE studio_menus SET name=?,location=?,status=? WHERE id=?')->execute([$name,$location,$status,$id]);}else{$pdo->prepare('INSERT INTO studio_menus(name,location,status) VALUES(?,?,?)')->execute([$name,$location,$status]);}
                    Flash::set('success','Menu salvo.');
                } elseif ($action === 'delete_menu') {
                    $id=max(0,(int)Request::post('id',0)); $count=$pdo->prepare('SELECT COUNT(*) FROM studio_menu_items WHERE menu_id=?');$count->execute([$id]);if((int)$count->fetchColumn()>0){throw new \RuntimeException('Remova os itens antes de excluir o menu.');}$pdo->prepare('DELETE FROM studio_menus WHERE id=?')->execute([$id]);Flash::set('success','Menu excluído.');
                } elseif ($action === 'delete_item') {
                    $pdo->prepare('DELETE FROM studio_menu_items WHERE id=?')->execute([max(0,(int)Request::post('id',0))]);Flash::set('success','Item removido.');
                } elseif ($action === 'save_item') {
                    $id=max(0,(int)Request::post('id',0));$menuId=max(0,(int)Request::post('menu_id',0));$label=mb_substr(trim(strip_tags((string)Request::post('label',''))),0,120);$type=in_array(Request::post('type'),['page','external'],true)?(string)Request::post('type'):'external';$pageId=$type==='page'?(max(0,(int)Request::post('page_id',0))?:null):null;$url=$type==='external'?mb_substr(trim((string)Request::post('url','')),0,500):null;$parentId=max(0,(int)Request::post('parent_id',0))?:null;$position=max(0,min(9999,(int)Request::post('position',0)));$target=Request::post('target')==='_blank'?'_blank':'_self';$status=Request::post('status')==='inactive'?'inactive':'active';
                    $urlScheme=strtolower((string)parse_url((string)$url,PHP_URL_SCHEME));
                    if(mb_strlen($label)<2){throw new \RuntimeException('Informe um rótulo válido.');}if($type==='external'&&($url===null||filter_var($url,FILTER_VALIDATE_URL)===false||!in_array($urlScheme,['http','https'],true))){throw new \RuntimeException('Informe uma URL externa HTTP(S) válida.');}
                    if($id>0&&$parentId===$id){throw new \RuntimeException('Um item não pode ser pai de si mesmo.');}if($parentId){$check=$pdo->prepare('SELECT 1 FROM studio_menu_items WHERE id=? AND menu_id=?');$check->execute([$parentId,$menuId]);if(!$check->fetchColumn()){throw new \RuntimeException('Item pai inválido.');}}
                    $values=[$menuId,$parentId,$label,$type,$pageId,$url,$target,$status,$position];
                    if($id){$values[]=$id;$pdo->prepare('UPDATE studio_menu_items SET menu_id=?,parent_id=?,label=?,type=?,page_id=?,url=?,target=?,status=?,position=? WHERE id=?')->execute($values);}else{$pdo->prepare('INSERT INTO studio_menu_items(menu_id,parent_id,label,type,page_id,url,target,status,position) VALUES(?,?,?,?,?,?,?,?,?)')->execute($values);}Flash::set('success','Item de menu salvo.');
                }
                Logger::info('Navegação do CMS alterada.',['action'=>$action,'actor_id'=>Auth::user()?->id]);
            } catch(Throwable $exception){Flash::set('error',$exception->getMessage());}
            Response::to('/studio/menus' . ((int)Request::post('menu_id',0)>0?'?menu='.(int)Request::post('menu_id',0):''));
        }
        $menuId=max(0,(int)Request::get('menu',0));$menus=$cms->menus();if(!$menuId&&$menus!==[]){$menuId=(int)$menus[0]['id'];}
        $selectedMenu=null;foreach($menus as $candidate){if((int)$candidate['id']===$menuId){$selectedMenu=$candidate;break;}}
        $pages=$pdo->query("SELECT id,title FROM studio_content WHERE type='page' AND deleted_at IS NULL ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
        echo $this->view->render('pages/cms-menus',['title'=>'Menus','currentPage'=>'menus','menus'=>$menus,'selectedMenu'=>$selectedMenu,'menuId'=>$menuId,'items'=>$menuId?$cms->menuItems($menuId):[],'pages'=>$pages]);
    }

    public function media(): void
    {
        $pdo = Connection::getInstance();
        $isEditorUpload = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        if (Request::isMethod('POST')) {
            $this->validateCsrf();
            $action = (string) Request::post('action', 'upload');
            if ($action === 'delete') {
                $id = max(0, (int) Request::post('id', 0));
                $uses = (new CmsService())->mediaUsage($id);
                if ($uses !== []) { Flash::set('error', 'Esta mídia está sendo utilizada em ' . count($uses) . ' local(is). Remova os vínculos antes de excluir.'); Response::to('/studio/media'); }
                $statement = $pdo->prepare('SELECT path FROM studio_media WHERE id=?');
                $statement->execute([$id]);
                $path = $statement->fetchColumn();
                $root = realpath(dirname(__DIR__, 2) . '/storage/media');
                $file = is_string($path) ? realpath($path) : false;
                if ($root && $file && str_starts_with($file, $root . DIRECTORY_SEPARATOR)) { @unlink($file); }
                $pdo->prepare('DELETE FROM studio_media WHERE id=?')->execute([$id]);
                Logger::info('Arquivo de mídia excluído.', ['action'=>'media_deleted','record_id'=>$id,'actor_id'=>Auth::user()?->id]);
                Flash::set('success', 'Arquivo removido.');
                Response::to('/studio/media');
            }
            if ($action === 'metadata') {
                $id = max(0, (int) Request::post('id', 0));
                $alt = mb_substr(trim(strip_tags((string) Request::post('alt_text', ''))), 0, 255);
                $pdo->prepare('UPDATE studio_media SET alt_text=? WHERE id=?')->execute([$alt ?: null, $id]);
                Logger::info('Metadados de mídia alterados.', ['action'=>'media_metadata_updated','record_id'=>$id,'actor_id'=>Auth::user()?->id]);
                Flash::set('success', 'Metadados da imagem atualizados.');
                Response::to('/studio/media');
            }
            if ($action === 'crop') {
                $this->cropMedia($pdo, max(0, (int) Request::post('id', 0)));
                Response::to('/studio/media');
            }
            try {
                $upload = $_FILES['image'] ?? [];
                if ((int) ($upload['size'] ?? 0) > 8 * 1024 * 1024) { throw new \RuntimeException('A imagem deve ter no máximo 8 MB.'); }
                $path = (new Image(dirname(__DIR__, 2) . '/storage', 'media'))->upload($upload, (string) ($upload['name'] ?? 'imagem'), 2000);
                $info = getimagesize($path);
                $statement = $pdo->prepare('INSERT INTO studio_media(name,path,mime,size,width,height,created_by) VALUES(?,?,?,?,?,?,?)');
                $statement->execute([basename($path), $path, (string) ($info['mime'] ?? 'application/octet-stream'), filesize($path), $info[0] ?? null, $info[1] ?? null, Auth::user()?->id]);
                $mediaId = (int) $pdo->lastInsertId();
                Logger::info('Arquivo de mídia criado.', ['action'=>'media_uploaded','record_id'=>$mediaId,'mime'=>(string)($info['mime']??''),'size'=>(int)filesize($path),'actor_id'=>Auth::user()?->id]);
                if ($isEditorUpload) { Response::json(['id'=>$mediaId, 'url'=>'/media/'.$mediaId, 'name'=>basename($path), 'width'=>$info[0]??null, 'height'=>$info[1]??null]); }
                Flash::set('success', 'Imagem enviada com segurança.');
            } catch (Throwable $exception) {
                Logger::warning('Upload de mídia rejeitado.', ['reason' => $exception->getMessage()]);
                if ($isEditorUpload) { Response::json(['error'=>$exception->getMessage()], 422); }
                Flash::set('error', $exception->getMessage());
            }
            Response::to('/studio/media');
        }
        $search = mb_substr(trim(strip_tags((string) Request::get('q', ''))), 0, 100);
        $pagination=(new CmsService())->mediaPage($search,max(1,(int)Request::get('page',1)));
        echo $this->view->render('pages/media', ['title'=>'Mídia','files'=>$pagination['items'],'pagination'=>$pagination,'search'=>$search]);
    }

    /** Read-only data source used by the Moves Editor media picker. */
    public function mediaLibrary(): void
    {
        $search = mb_substr(trim(strip_tags((string) Request::get('q', ''))), 0, 100);
        $statement = Connection::getInstance()->prepare(
            'SELECT id,name,alt_text,mime,width,height FROM studio_media'
            . ($search !== '' ? ' WHERE name LIKE ? OR alt_text LIKE ?' : '')
            . ' ORDER BY id DESC'
        );
        $statement->execute($search !== '' ? ['%' . $search . '%', '%' . $search . '%'] : []);
        $files = array_map(static fn (array $file): array => [
            'id' => (int) $file['id'],
            'name' => (string) $file['name'],
            'alt' => (string) ($file['alt_text'] ?? ''),
            'mime' => (string) $file['mime'],
            'width' => (int) ($file['width'] ?? 0),
            'height' => (int) ($file['height'] ?? 0),
            'url' => '/media/' . (int) $file['id'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
        Response::json(['files' => $files]);
    }

    public function mediaFile(array $data): void
    {
        $statement = Connection::getInstance()->prepare('SELECT path,mime FROM studio_media WHERE id=?');
        $statement->execute([(int) ($data['id'] ?? 0)]);
        $media = $statement->fetch(PDO::FETCH_ASSOC);
        $root = realpath(dirname(__DIR__, 2) . '/storage/media');
        $file = $media ? realpath((string) $media['path']) : false;
        if (!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) { http_response_code(404); return; }
        header('Content-Type: ' . $media['mime']); header('Content-Length: ' . filesize($file)); header('Content-Disposition: inline'); readfile($file);
    }

    private function currentModule(): string
    {
        $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        $module = basename($path);
        if (!isset(self::CONTENT_MODULES[$module])) { throw new \RuntimeException('Módulo inválido.'); }
        return $module;
    }

    private function validateCsrf(): void
    {
        if (!Csrf::validate(is_string(Request::post('_token')) ? Request::post('_token') : null)) { Flash::set('error', 'Sessão expirada.'); Response::to('/studio'); }
    }

    private function slug(string $value): string
    {
        return Seo::slug($value);
    }

    private function mediaExists(int $id): bool
    {
        $statement = Connection::getInstance()->prepare('SELECT 1 FROM studio_media WHERE id=?');
        $statement->execute([$id]);
        return (bool) $statement->fetchColumn();
    }

    private function taxonomyExists(int $id, string $type): bool
    {
        $statement = Connection::getInstance()->prepare('SELECT 1 FROM studio_taxonomies WHERE id=? AND type=?');
        $statement->execute([$id, $type]);
        return (bool) $statement->fetchColumn();
    }

    private function dateTime(string $value): ?string
    {
        $timestamp = $value === '' ? false : strtotime($value);
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function recordRevision(PDO $pdo, int $contentId, string $type, string $reason): void
    {
        $statement = $pdo->prepare('SELECT * FROM studio_content WHERE id=? AND type=?');
        $statement->execute([$contentId, $type]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) { return; }
        $numberStatement = $pdo->prepare('SELECT COALESCE(MAX(revision_number),0)+1 FROM studio_content_revisions WHERE content_id=? FOR UPDATE');
        $numberStatement->execute([$contentId]);
        $insert = $pdo->prepare('INSERT INTO studio_content_revisions(content_id,type,revision_number,reason,title,slug,excerpt,content,seo_title,seo_description,seo_focus_keyword,canonical_url,robots_index,robots_follow,media_id,category_id,template,meta_json,status,position,published_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $insert->execute([$contentId,$type,(int)$numberStatement->fetchColumn(),$reason,$row['title'],$row['slug'],$row['excerpt'],$row['content'],$row['seo_title'],$row['seo_description'],$row['seo_focus_keyword'],$row['canonical_url'],$row['robots_index'],$row['robots_follow'],$row['media_id'],$row['category_id'],$row['template'],$row['meta_json'],$row['status'],$row['position'],$row['published_at'],Auth::user()?->id]);
    }

    private function restoreRevision(PDO $pdo, int $contentId, int $revisionId, string $type, string $module): never
    {
        $statement = $pdo->prepare('SELECT * FROM studio_content_revisions WHERE id=? AND content_id=? AND type=?');
        $statement->execute([$revisionId, $contentId, $type]);
        $revision = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$revision) { Flash::set('error', 'Revisão inválida ou indisponível.'); Response::to('/studio/' . $module . '?edit=' . $contentId); }
        try {
            $pdo->beginTransaction();
            $update = $pdo->prepare('UPDATE studio_content SET title=?,slug=?,excerpt=?,content=?,seo_title=?,seo_description=?,seo_focus_keyword=?,canonical_url=?,robots_index=?,robots_follow=?,media_id=?,category_id=?,template=?,meta_json=?,status=?,position=?,published_at=? WHERE id=? AND type=? AND deleted_at IS NULL');
            $update->execute([$revision['title'],$revision['slug'],$revision['excerpt'],$revision['content'],$revision['seo_title'],$revision['seo_description'],$revision['seo_focus_keyword'],$revision['canonical_url'],$revision['robots_index'],$revision['robots_follow'],$revision['media_id'],$revision['category_id'],$revision['template'],$revision['meta_json'],$revision['status'],$revision['position'],$revision['published_at'],$contentId,$type]);
            $this->recordRevision($pdo, $contentId, $type, 'restored');
            $pdo->commit();
            Logger::info('Revisão de conteúdo restaurada.', ['action'=>'revision_restored','module'=>$module,'record_id'=>$contentId,'revision_id'=>$revisionId,'actor_id'=>Auth::user()?->id]);
            Flash::set('success', 'Revisão restaurada e registrada como uma nova versão.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            Logger::exception($exception);
            Flash::set('error', 'Não foi possível restaurar a revisão.');
        }
        Response::to('/studio/' . $module . '?edit=' . $contentId);
    }

    private function cropMedia(PDO $pdo, int $id): void
    {
        $statement = $pdo->prepare('SELECT * FROM studio_media WHERE id=?'); $statement->execute([$id]);
        $media = $statement->fetch(PDO::FETCH_ASSOC);
        $root = realpath(dirname(__DIR__, 2) . '/storage/media');
        $file = $media ? realpath((string) $media['path']) : false;
        if (!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) { Flash::set('error', 'Imagem não encontrada.'); return; }
        $info = getimagesize($file); $width = (int) ($info[0] ?? 0); $height = (int) ($info[1] ?? 0);
        $x = max(0, min($width - 1, (int) Request::post('crop_x', 0))); $y = max(0, min($height - 1, (int) Request::post('crop_y', 0)));
        $cropWidth = max(1, min($width - $x, (int) Request::post('crop_width', $width))); $cropHeight = max(1, min($height - $y, (int) Request::post('crop_height', $height)));
        $source = match ($media['mime']) { 'image/jpeg' => imagecreatefromjpeg($file), 'image/png' => imagecreatefrompng($file), 'image/gif' => imagecreatefromgif($file), 'image/webp' => imagecreatefromwebp($file), default => false };
        if (!$source) { Flash::set('error', 'Não foi possível processar a imagem.'); return; }
        $target = imagecreatetruecolor($cropWidth, $cropHeight); imagealphablending($target, false); imagesavealpha($target, true);
        imagecopy($target, $source, 0, 0, $x, $y, $cropWidth, $cropHeight);
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION)); $targetPath = $root . DIRECTORY_SEPARATOR . pathinfo($file, PATHINFO_FILENAME) . '-crop-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $saved = match ($media['mime']) { 'image/jpeg' => imagejpeg($target, $targetPath, 82), 'image/png' => imagepng($target, $targetPath, 5), 'image/gif' => imagegif($target, $targetPath), 'image/webp' => imagewebp($target, $targetPath, 82), default => false };
        if (!$saved) { Flash::set('error', 'Não foi possível salvar o recorte.'); return; }
        $insert = $pdo->prepare('INSERT INTO studio_media(name,alt_text,path,mime,size,width,height,parent_id,crop_data,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)');
        $insert->execute([basename($targetPath), $media['alt_text'], $targetPath, $media['mime'], filesize($targetPath), $cropWidth, $cropHeight, $id, json_encode(compact('x','y','cropWidth','cropHeight')), Auth::user()?->id]);
        Logger::info('Derivação de mídia criada.', ['action'=>'media_cropped','record_id'=>(int)$pdo->lastInsertId(),'source_id'=>$id,'width'=>$cropWidth,'height'=>$cropHeight,'actor_id'=>Auth::user()?->id]);
        Flash::set('success', 'Recorte criado como nova imagem; o original foi preservado.');
    }
}
