<?php

declare(strict_types=1);

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Services\CmsService;
use PHPUnit\Framework\TestCase;

final class CmsServiceTest extends TestCase
{
    private PDO $pdo;
    private CmsService $cms;
    private string $prefix;
    private int $userId;
    private int $categoryId;

    protected function setUp(): void
    {
        Environment::load(dirname(__DIR__));
        $this->pdo = Connection::getInstance();
        $this->cms = new CmsService();
        $this->prefix = 'cms-' . bin2hex(random_bytes(4));
        $this->pdo->prepare('INSERT INTO users(name,email,password,role,status) VALUES(?,?,?,?,?)')->execute(['CMS Test',$this->prefix.'@example.test',password_hash('test',PASSWORD_DEFAULT),'admin','active']);
        $this->userId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO studio_taxonomies(type,name,slug) VALUES(?,?,?)')->execute(['article_category',$this->prefix,$this->prefix]);
        $this->categoryId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM studio_menu_items WHERE label LIKE ?')->execute([$this->prefix.'%']);
        $this->pdo->prepare('DELETE FROM studio_menus WHERE location LIKE ?')->execute([$this->prefix.'%']);
        $this->pdo->prepare('DELETE FROM studio_content WHERE slug LIKE ?')->execute([$this->prefix.'%']);
        $this->pdo->prepare('DELETE FROM studio_tags WHERE slug LIKE ?')->execute([$this->prefix.'%']);
        $this->pdo->prepare('DELETE FROM studio_taxonomies WHERE id=?')->execute([$this->categoryId]);
        $this->pdo->prepare('DELETE FROM studio_media WHERE name LIKE ?')->execute([$this->prefix.'%']);
        $this->pdo->prepare('DELETE FROM users WHERE id=?')->execute([$this->userId]);
    }

    public function testPaginationAndCombinedFiltersAreServerSide(): void
    {
        $insert=$this->pdo->prepare('INSERT INTO studio_content(type,title,slug,excerpt,status,category_id,created_by) VALUES(?,?,?,?,?,?,?)');
        for($i=1;$i<=23;$i++){$insert->execute(['article',$this->prefix.' Guia '.$i,$this->prefix.'-guia-'.$i,'Resumo filtrável',$i%2?'draft':'published',$this->categoryId,$this->userId]);}
        $first=$this->cms->contentPage('article',['q'=>$this->prefix,'status'=>'','author'=>$this->userId,'category'=>$this->categoryId],1);
        self::assertSame(23,$first['total']); self::assertSame(2,$first['totalPages']); self::assertCount(20,$first['items']);
        $second=$this->cms->contentPage('article',['q'=>$this->prefix,'status'=>'','author'=>$this->userId,'category'=>$this->categoryId],2);
        self::assertCount(3,$second['items']);
        $published=$this->cms->contentPage('article',['q'=>$this->prefix,'status'=>'published','author'=>$this->userId,'category'=>$this->categoryId],1);
        self::assertSame(11,$published['total']);
    }

    public function testTrashPaginationIsServerSide(): void
    {
        $ids=[];
        for($i=0;$i<23;$i++){ $id=$this->content('draft'); $ids[]=$id; self::assertTrue($this->cms->trash($id,'article',$this->userId)); }
        $first=$this->cms->trashPage($this->prefix,'article',1);
        self::assertSame(23,$first['total']); self::assertSame(2,$first['totalPages']); self::assertCount(20,$first['items']);
        $second=$this->cms->trashPage($this->prefix,'article',2);
        self::assertCount(3,$second['items']);
    }

    public function testTrashRestoreAndPermanentDeletePreserveRecord(): void
    {
        $id=$this->content('draft');
        self::assertTrue($this->cms->trash($id,'article',$this->userId));
        self::assertSame(0,$this->cms->contentPage('article',['q'=>$this->prefix],1)['total']);
        self::assertCount(1,$this->cms->trashPage($this->prefix,'article',1)['items']);
        self::assertTrue($this->cms->restore($id,$this->userId));
        self::assertSame('draft',$this->pdo->query('SELECT status FROM studio_content WHERE id='.$id)->fetchColumn());
        self::assertTrue($this->cms->trash($id,'article',$this->userId));
        self::assertTrue($this->cms->deletePermanently($id));
        self::assertFalse((bool)$this->pdo->query('SELECT 1 FROM studio_content WHERE id='.$id)->fetchColumn());
    }

    public function testCmsTagsPersistRelationsAndProtectDeletion(): void
    {
        $contentId=$this->content('draft');
        $tagId=$this->cms->saveTag(0,$this->prefix.' Estratégia');
        $this->cms->syncTags($contentId,[$tagId]);
        self::assertSame($tagId,(int)$this->cms->tagsForContent($contentId)[0]['id']);
        self::assertSame(1,(int)$this->cms->tags($this->prefix)[0]['usage_count']);
        $this->expectException(RuntimeException::class);
        $this->cms->deleteTag($tagId);
    }

    public function testAdvancedSeoPersistsAndPreviewRouteIsProtected(): void
    {
        $id=$this->content('draft');
        $canonical='https://example.test/'.$this->prefix;
        $this->pdo->prepare('UPDATE studio_content SET seo_focus_keyword=?,canonical_url=?,robots_index=0,robots_follow=1 WHERE id=?')->execute(['CMS seguro',$canonical,$id]);
        $row=$this->pdo->query('SELECT seo_focus_keyword,canonical_url,robots_index,robots_follow FROM studio_content WHERE id='.$id)->fetch(PDO::FETCH_ASSOC);
        self::assertSame('CMS seguro',$row['seo_focus_keyword']); self::assertSame($canonical,$row['canonical_url']); self::assertSame(0,(int)$row['robots_index']); self::assertSame(1,(int)$row['robots_follow']);
        $routes=file_get_contents(dirname(__DIR__).'/app/Boot/Routes.php');
        self::assertStringContainsString("get('/studio/content/preview/{id}'",(string)$routes);
        self::assertStringContainsString("'StudioModulesController:preview'",(string)$routes);
        $controller=file_get_contents(dirname(__DIR__).'/app/Controllers/StudioModulesController.php');
        self::assertStringContainsString("X-Robots-Tag: noindex, nofollow",(string)$controller);
    }

    public function testMediaUsageDetectsExactHtmlReferencesWithoutIdCollision(): void
    {
        $this->pdo->prepare('INSERT INTO studio_media(name,path,mime,size,created_by) VALUES(?,?,?,?,?)')->execute([$this->prefix.'.png','/tmp/'.$this->prefix.'.png','image/png',1,$this->userId]);
        $mediaId=(int)$this->pdo->lastInsertId();
        $contentId=$this->content('draft','<figure><img src="/media/'.$mediaId.'"></figure>');
        self::assertTrue($this->cms->containsMedia('<img src="/media/'.$mediaId.'">',$mediaId));
        self::assertFalse($this->cms->containsMedia('<img src="/media/1'.$mediaId.'">',$mediaId));
        self::assertNotEmpty($this->cms->mediaUsage($mediaId));
        $this->pdo->prepare('DELETE FROM studio_content WHERE id=?')->execute([$contentId]);
    }

    public function testMenusItemsAndOrderingArePersisted(): void
    {
        $this->pdo->prepare('INSERT INTO studio_menus(name,location,status) VALUES(?,?,?)')->execute([$this->prefix,$this->prefix.'-principal','active']);
        $menuId=(int)$this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO studio_menu_items(menu_id,label,type,url,target,status,position) VALUES(?,?,?,?,?,?,?)')->execute([$menuId,$this->prefix.' Segundo','external','https://example.test/2','_self','active',20]);
        $this->pdo->prepare('INSERT INTO studio_menu_items(menu_id,label,type,url,target,status,position) VALUES(?,?,?,?,?,?,?)')->execute([$menuId,$this->prefix.' Primeiro','external','https://example.test/1','_self','active',10]);
        $items=$this->cms->menuItems($menuId);
        self::assertCount(2,$items); self::assertSame($this->prefix.' Primeiro',$items[0]['label']);
        $menu = array_values(array_filter($this->cms->menus(), static fn(array $candidate): bool => (int) $candidate['id'] === $menuId))[0] ?? null;
        self::assertNotNull($menu);
        self::assertSame(2,(int)$menu['item_count']);
    }

    private function content(string $status, string $html='<p>Conteúdo</p>'): int
    {
        $slug=$this->prefix.'-'.bin2hex(random_bytes(3));
        $this->pdo->prepare('INSERT INTO studio_content(type,title,slug,content,status,category_id,created_by) VALUES(?,?,?,?,?,?,?)')->execute(['article',$this->prefix.' Conteúdo',$slug,$html,$status,$this->categoryId,$this->userId]);
        return (int)$this->pdo->lastInsertId();
    }
}
