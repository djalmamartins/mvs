<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Logger;
use Moves\Core\Request;
use Moves\Core\Response;
use MovesCode\Storage\Image;
use PDO;
use Throwable;

/** Small, secured CMS and commercial modules for Moves Studio. */
final class StudioModulesController extends Controller
{
    /** @var array<string, array{type: string, title: string, singular: string}> */
    private const CONTENT_MODULES = [
        'pages' => ['type' => 'page', 'title' => 'Páginas', 'singular' => 'Página'],
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

            if ($action === 'delete') {
                $statement = $pdo->prepare('DELETE FROM studio_content WHERE id = ? AND type = ?');
                $statement->execute([$id, $definition['type']]);
                Flash::set('success', $definition['singular'] . ' excluído(a).');
                Logger::info('Conteúdo do Studio excluído.', ['module' => $module, 'record_id' => $id]);
                Response::to('/admin/' . $module);
            }

            $title = mb_substr(trim(strip_tags((string) Request::post('title', ''))), 0, 180);
            $slug = $this->slug((string) Request::post('slug', $title));
            $excerpt = mb_substr(trim(strip_tags((string) Request::post('excerpt', ''))), 0, 1000);
            $content = mb_substr(trim(strip_tags((string) Request::post('content', ''))), 0, 50000);
            $status = in_array(Request::post('status'), ['draft', 'published', 'archived'], true)
                ? (string) Request::post('status')
                : 'draft';
            $position = max(0, min(9999, (int) Request::post('position', 0)));

            if (mb_strlen($title) < 3 || $slug === '') {
                Flash::set('error', 'Informe um título válido.');
                Response::to('/admin/' . $module . ($id ? '?edit=' . $id : ''));
            }

            try {
                if ($id > 0) {
                    $statement = $pdo->prepare('UPDATE studio_content SET title=?,slug=?,excerpt=?,content=?,status=?,position=?,published_at=? WHERE id=? AND type=?');
                    $statement->execute([$title, $slug, $excerpt ?: null, $content ?: null, $status, $position, $status === 'published' ? date('Y-m-d H:i:s') : null, $id, $definition['type']]);
                } else {
                    $statement = $pdo->prepare('INSERT INTO studio_content(type,title,slug,excerpt,content,status,position,published_at,created_by) VALUES(?,?,?,?,?,?,?,?,?)');
                    $statement->execute([$definition['type'], $title, $slug, $excerpt ?: null, $content ?: null, $status, $position, $status === 'published' ? date('Y-m-d H:i:s') : null, Auth::user()?->id]);
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

        echo $this->view->render('pages/content-module', [
            'title' => $definition['title'], 'module' => $module, 'singular' => $definition['singular'],
            'records' => $statement->fetchAll(PDO::FETCH_ASSOC), 'edit' => $edit, 'search' => $search, 'status' => $status,
        ]);
    }

    public function media(): void
    {
        $pdo = Connection::getInstance();
        if (Request::isMethod('POST')) {
            $this->validateCsrf();
            $action = (string) Request::post('action', 'upload');
            if ($action === 'delete') {
                $id = max(0, (int) Request::post('id', 0));
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
            try {
                $upload = $_FILES['image'] ?? [];
                if ((int) ($upload['size'] ?? 0) > 8 * 1024 * 1024) { throw new \RuntimeException('A imagem deve ter no máximo 8 MB.'); }
                $path = (new Image(dirname(__DIR__, 2) . '/storage', 'media'))->upload($upload, (string) ($upload['name'] ?? 'imagem'), 2000);
                $info = getimagesize($path);
                $statement = $pdo->prepare('INSERT INTO studio_media(name,path,mime,size,width,height,created_by) VALUES(?,?,?,?,?,?,?)');
                $statement->execute([basename($path), $path, (string) ($info['mime'] ?? 'application/octet-stream'), filesize($path), $info[0] ?? null, $info[1] ?? null, Auth::user()?->id]);
                Flash::set('success', 'Imagem enviada com segurança.');
            } catch (Throwable $exception) {
                Logger::warning('Upload de mídia rejeitado.', ['reason' => $exception->getMessage()]);
                Flash::set('error', $exception->getMessage());
            }
            Response::to('/admin/media');
        }
        $search = mb_substr(trim(strip_tags((string) Request::get('q', ''))), 0, 100);
        $statement = $pdo->prepare('SELECT id,name,mime,size,width,height,created_at FROM studio_media' . ($search !== '' ? ' WHERE name LIKE ?' : '') . ' ORDER BY id DESC LIMIT 200');
        $statement->execute($search !== '' ? ['%' . $search . '%'] : []);
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

    public function proposals(): void
    {
        $pdo = Connection::getInstance();
        if (Request::isMethod('POST')) {
            $this->validateCsrf();
            $status = in_array(Request::post('status'), ['new','contacted','qualified','won','lost','archived'], true) ? Request::post('status') : 'new';
            $pdo->prepare('UPDATE proposals SET status=? WHERE id=?')->execute([$status, (int) Request::post('id', 0)]);
            Flash::set('success', 'Proposta atualizada.'); Response::to('/admin/proposals');
        }
        $status = in_array(Request::get('status'), ['new','contacted','qualified','won','lost','archived'], true) ? (string) Request::get('status') : '';
        $statement = $pdo->prepare('SELECT * FROM proposals' . ($status ? ' WHERE status=?' : '') . ' ORDER BY id DESC LIMIT 200'); $statement->execute($status ? [$status] : []);
        echo $this->view->render('pages/proposals', ['title'=>'Propostas','records'=>$statement->fetchAll(PDO::FETCH_ASSOC),'status'=>$status]);
    }

    public function notifications(): void
    {
        $pdo = Connection::getInstance();
        if (Request::isMethod('POST')) { $this->validateCsrf(); $pdo->prepare('UPDATE notifications SET read_at=NOW() WHERE id=?')->execute([(int) Request::post('id',0)]); Response::to('/admin/notifications'); }
        echo $this->view->render('pages/notifications', ['title'=>'Notificações','records'=>$pdo->query('SELECT * FROM notifications ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function reports(): void
    {
        $pdo = Connection::getInstance();
        $counts = [];
        foreach (['page','article','highlight','testimonial','faq'] as $type) {
            $statement = $pdo->prepare('SELECT COUNT(*) total, SUM(status=\'published\') published FROM studio_content WHERE type=?');
            $statement->execute([$type]);
            $counts[$type] = $statement->fetch(PDO::FETCH_ASSOC);
        }
        $counts['media'] = ['total'=>(int)$pdo->query('SELECT COUNT(*) FROM studio_media')->fetchColumn(),'published'=>null];
        $counts['proposal'] = ['total'=>(int)$pdo->query('SELECT COUNT(*) FROM proposals')->fetchColumn(),'published'=>null];
        echo $this->view->render('pages/reports', ['title'=>'Relatórios','counts'=>$counts]);
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
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower(trim($value)));
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii ?: $value)), '-');
    }
}
