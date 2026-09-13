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

    public function content(): void
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
                if (mb_strlen($name) < 2) { Flash::set('error', 'Informe o nome da categoria.'); Response::to('/admin/' . $module); }
                $taxonomyType = match ($module) { 'articles' => 'article_category', 'projects' => 'project_category', default => 'faq_category' };
                try { $pdo->prepare('INSERT INTO studio_taxonomies(type,name,slug) VALUES(?,?,?)')->execute([$taxonomyType, $name, $this->slug($name)]); Flash::set('success', 'Categoria criada.'); }
                catch (Throwable $exception) { Flash::set('error', 'A categoria já existe ou não pôde ser criada.'); }
                Response::to('/admin/' . $module);
            }

            if ($action === 'delete') {
                $statement = $pdo->prepare('DELETE FROM studio_content WHERE id = ? AND type = ?');
                $statement->execute([$id, $definition['type']]);
                Flash::set('success', $definition['singular'] . ' excluído(a).');
                Logger::info('Conteúdo do Studio excluído.', ['module' => $module, 'record_id' => $id]);
                Response::to('/admin/' . $module);
            }

            $title = mb_substr(trim(strip_tags((string) Request::post('title', ''))), 0, 180);
            $requestedSlug = trim((string) Request::post('slug', ''));
            $excerpt = mb_substr(trim(strip_tags((string) Request::post('excerpt', ''))), 0, 1000);
            $submittedContent = mb_substr(trim((string) Request::post('content', '')), 0, 50000);
            $content = in_array($module, ['articles', 'pages', 'projects'], true) ? HtmlSanitizer::clean($submittedContent) : trim(strip_tags($submittedContent));
            $status = in_array(Request::post('status'), ['draft', 'published', 'archived'], true)
                ? (string) Request::post('status')
                : 'draft';
            $position = max(0, min(9999, (int) Request::post('position', 0)));
            $seoTitle = mb_substr(trim(strip_tags((string) Request::post('seo_title', ''))), 0, 160);
            $seoDescription = mb_substr(trim(strip_tags((string) Request::post('seo_description', ''))), 0, 320);
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
            if ($module === 'articles' && $categoryId === null) { Flash::set('error', 'Selecione uma categoria válida.'); Response::to('/admin/articles' . ($id ? '?edit=' . $id : '')); }
            if ($module === 'projects' && $categoryId !== null && !$this->taxonomyExists($categoryId, 'project_category')) { $categoryId = null; }
            if ($module === 'projects' && $categoryId === null) { Flash::set('error', 'Selecione uma categoria válida.'); Response::to('/admin/projects' . ($id ? '?edit=' . $id : '')); }
            if ($module === 'projects' && ($meta['project_url'] ?? '') !== '') {
                $projectUrl = (string) $meta['project_url'];
                $scheme = strtolower((string) parse_url($projectUrl, PHP_URL_SCHEME));
                $isInternal = str_starts_with($projectUrl, '/') && !str_starts_with($projectUrl, '//');
                $isExternal = filter_var($projectUrl, FILTER_VALIDATE_URL) !== false && in_array($scheme, ['http', 'https'], true);
                if (!$isInternal && !$isExternal) { Flash::set('error', 'Informe uma URL HTTP(S) ou um caminho interno válido para o projeto.'); Response::to('/admin/projects' . ($id ? '?edit=' . $id : '')); }
            }
            if ($module === 'faq' && $categoryId !== null && !$this->taxonomyExists($categoryId, 'faq_category')) { $categoryId = null; }
            if ($module === 'faq' && $categoryId === null) { Flash::set('error', 'Selecione uma categoria válida.'); Response::to('/admin/faq' . ($id ? '?edit=' . $id : '')); }
            if ($startsAt && $endsAt && $startsAt > $endsAt) { Flash::set('error', 'O fim da exibição deve ocorrer depois do início.'); Response::to('/admin/highlights' . ($id ? '?edit=' . $id : '')); }
            if (($meta['cta_url'] ?? '') !== '' && filter_var($meta['cta_url'], FILTER_VALIDATE_URL) === false && !str_starts_with((string) $meta['cta_url'], '/')) { Flash::set('error', 'Informe uma URL de CTA válida.'); Response::to('/admin/highlights' . ($id ? '?edit=' . $id : '')); }

            if (mb_strlen($title) < 3 || $slug === '') {
                Flash::set('error', 'Informe um título válido.');
                Response::to('/admin/' . $module . ($id ? '?edit=' . $id : ''));
            }

            try {
                if ($id > 0) {
                    $statement = $pdo->prepare('UPDATE studio_content SET title=?,slug=?,excerpt=?,content=?,seo_title=?,seo_description=?,media_id=?,category_id=?,template=?,meta_json=?,status=?,position=?,starts_at=?,ends_at=?,published_at=? WHERE id=? AND type=?');
                    $statement->execute([$title, $slug, $excerpt ?: null, $content ?: null, $seoTitle ?: null, $seoDescription ?: null, $mediaId, $categoryId, $template, json_encode($meta, JSON_UNESCAPED_UNICODE), $status, $position, $startsAt, $endsAt, $status === 'published' ? date('Y-m-d H:i:s') : null, $id, $definition['type']]);
                } else {
                    $statement = $pdo->prepare('INSERT INTO studio_content(type,title,slug,excerpt,content,seo_title,seo_description,media_id,category_id,template,meta_json,status,position,starts_at,ends_at,published_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $statement->execute([$definition['type'], $title, $slug, $excerpt ?: null, $content ?: null, $seoTitle ?: null, $seoDescription ?: null, $mediaId, $categoryId, $template, json_encode($meta, JSON_UNESCAPED_UNICODE), $status, $position, $startsAt, $endsAt, $status === 'published' ? date('Y-m-d H:i:s') : null, Auth::user()?->id]);
                }
            } catch (Throwable $exception) {
                Logger::exception($exception);
                Flash::set('error', 'Não foi possível salvar. Verifique se o slug já está em uso.');
                Response::to('/admin/' . $module . ($id ? '?edit=' . $id : ''));
            }

            Flash::set('success', $definition['singular'] . ' salvo(a).');
            Response::to('/admin/' . $module);
        }

        $search = mb_substr(trim(strip_tags((string) Request::get('q', ''))), 0, 100);
        $status = in_array(Request::get('status'), ['draft', 'published', 'archived'], true) ? (string) Request::get('status') : '';
        $where = ['type = :type'];
        $params = ['type' => $definition['type']];
        if ($search !== '') { $where[] = '(title LIKE :search OR slug LIKE :search)'; $params['search'] = '%' . $search . '%'; }
        if ($status !== '') { $where[] = 'status = :status'; $params['status'] = $status; }
        $statement = $pdo->prepare('SELECT * FROM studio_content WHERE ' . implode(' AND ', $where) . ' ORDER BY position,id DESC LIMIT 200');
        $statement->execute($params);
        $edit = null;
        $editId = max(0, (int) Request::get('edit', 0));
        if ($editId) {
            $editStatement = $pdo->prepare('SELECT * FROM studio_content WHERE id=? AND type=?');
            $editStatement->execute([$editId, $definition['type']]);
            $edit = $editStatement->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $media = $pdo->query('SELECT id,name,width,height FROM studio_media ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
        $taxonomyType = match ($module) { 'faq' => 'faq_category', 'projects' => 'project_category', default => 'article_category' };
        $categoryStatement = $pdo->prepare('SELECT id,name FROM studio_taxonomies WHERE type=? ORDER BY name'); $categoryStatement->execute([$taxonomyType]);
        $categories = $categoryStatement->fetchAll(PDO::FETCH_ASSOC);
        if ($edit) { $edit['meta'] = json_decode((string) ($edit['meta_json'] ?? ''), true) ?: []; }
        echo $this->view->render('pages/content-module', [
            'title' => $definition['title'], 'module' => $module, 'singular' => $definition['singular'],
            'records' => $statement->fetchAll(PDO::FETCH_ASSOC), 'edit' => $edit, 'search' => $search, 'status' => $status, 'media' => $media, 'categories' => $categories,
        ]);
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
                $usage = $pdo->prepare('SELECT COUNT(*) FROM studio_content WHERE media_id=?');
                $usage->execute([$id]);
                if ((int) $usage->fetchColumn() > 0) { Flash::set('error', 'A imagem está associada a conteúdo. Remova os vínculos antes de excluir.'); Response::to('/admin/media'); }
                $statement = $pdo->prepare('SELECT path FROM studio_media WHERE id=?');
                $statement->execute([$id]);
                $path = $statement->fetchColumn();
                $root = realpath(dirname(__DIR__, 2) . '/storage/media');
                $file = is_string($path) ? realpath($path) : false;
                if ($root && $file && str_starts_with($file, $root . DIRECTORY_SEPARATOR)) { @unlink($file); }
                $pdo->prepare('DELETE FROM studio_media WHERE id=?')->execute([$id]);
                Flash::set('success', 'Arquivo removido.');
                Response::to('/admin/media');
            }
            if ($action === 'metadata') {
                $id = max(0, (int) Request::post('id', 0));
                $alt = mb_substr(trim(strip_tags((string) Request::post('alt_text', ''))), 0, 255);
                $pdo->prepare('UPDATE studio_media SET alt_text=? WHERE id=?')->execute([$alt ?: null, $id]);
                Flash::set('success', 'Metadados da imagem atualizados.');
                Response::to('/admin/media');
            }
            if ($action === 'crop') {
                $this->cropMedia($pdo, max(0, (int) Request::post('id', 0)));
                Response::to('/admin/media');
            }
            try {
                $upload = $_FILES['image'] ?? [];
                if ((int) ($upload['size'] ?? 0) > 8 * 1024 * 1024) { throw new \RuntimeException('A imagem deve ter no máximo 8 MB.'); }
                $path = (new Image(dirname(__DIR__, 2) . '/storage', 'media'))->upload($upload, (string) ($upload['name'] ?? 'imagem'), 2000);
                $info = getimagesize($path);
                $statement = $pdo->prepare('INSERT INTO studio_media(name,path,mime,size,width,height,created_by) VALUES(?,?,?,?,?,?,?)');
                $statement->execute([basename($path), $path, (string) ($info['mime'] ?? 'application/octet-stream'), filesize($path), $info[0] ?? null, $info[1] ?? null, Auth::user()?->id]);
                $mediaId = (int) $pdo->lastInsertId();
                if ($isEditorUpload) { Response::json(['id'=>$mediaId, 'url'=>'/media/'.$mediaId, 'name'=>basename($path), 'width'=>$info[0]??null, 'height'=>$info[1]??null]); }
                Flash::set('success', 'Imagem enviada com segurança.');
            } catch (Throwable $exception) {
                Logger::warning('Upload de mídia rejeitado.', ['reason' => $exception->getMessage()]);
                if ($isEditorUpload) { Response::json(['error'=>$exception->getMessage()], 422); }
                Flash::set('error', $exception->getMessage());
            }
            Response::to('/admin/media');
        }
        $search = mb_substr(trim(strip_tags((string) Request::get('q', ''))), 0, 100);
        $statement = $pdo->prepare('SELECT m.id,m.name,m.alt_text,m.mime,m.size,m.width,m.height,m.parent_id,m.created_at,(SELECT COUNT(*) FROM studio_content c WHERE c.media_id=m.id) usage_count FROM studio_media m' . ($search !== '' ? ' WHERE m.name LIKE ? OR m.alt_text LIKE ?' : '') . ' ORDER BY m.id DESC LIMIT 200');
        $statement->execute($search !== '' ? ['%' . $search . '%', '%' . $search . '%'] : []);
        echo $this->view->render('pages/media', ['title' => 'Mídia', 'files' => $statement->fetchAll(PDO::FETCH_ASSOC), 'search' => $search]);
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
        if (!Csrf::validate(is_string(Request::post('_token')) ? Request::post('_token') : null)) { Flash::set('error', 'Sessão expirada.'); Response::to('/admin'); }
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
        Flash::set('success', 'Recorte criado como nova imagem; o original foi preservado.');
    }
}
