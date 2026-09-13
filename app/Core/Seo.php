<?php

declare(strict_types=1);

namespace Moves\Core;

/** Centraliza os padrões automáticos de SEO editorial e público. */
final class Seo
{
    /** @return array{slug:string,title:string,description:string} */
    public static function contentFields(string $title, string $excerpt, string $content, string $slug = '', string $seoTitle = '', string $seoDescription = ''): array
    {
        return [
            'slug' => self::slug($slug !== '' ? $slug : $title),
            'title' => self::limit(self::plain($seoTitle !== '' ? $seoTitle : $title), 60),
            'description' => self::limit(self::plain($seoDescription !== '' ? $seoDescription : ($excerpt !== '' ? $excerpt : $content)), 160),
        ];
    }

    /** @return array{canonical:?string,robots:string,ogType:string,image:?string,schema:array<string,mixed>} */
    public static function metadata(string $path, string $title, string $description, string $type = 'website', ?int $mediaId = null, ?string $publishedAt = null): array
    {
        $base = rtrim((string) Config::get('APP_URL', ''), '/');
        $canonical = Config::isProduction() && $base !== '' ? $base . ($path === '/' ? '/' : $path) : null;
        $image = $mediaId && $base !== '' ? $base . '/media/' . $mediaId : null;
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $type === 'article' ? 'Article' : 'WebPage',
            'name' => self::plain($title),
            'description' => self::limit(self::plain($description), 160),
        ];
        if ($canonical !== null) { $schema['url'] = $canonical; }
        if ($image !== null) { $schema['image'] = $image; }
        if ($publishedAt !== null && $type === 'article') { $schema['datePublished'] = date(DATE_ATOM, strtotime($publishedAt) ?: time()); }

        return ['canonical'=>$canonical, 'robots'=>Config::isProduction()?'index, follow':'noindex, nofollow', 'ogType'=>$type, 'image'=>$image, 'schema'=>$schema];
    }

    public static function slug(string $value): string
    {
        $value = strtr(trim($value), [
            'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
            'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','ç'=>'c','Ç'=>'C','ñ'=>'n','Ñ'=>'N',
        ]);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return mb_substr(trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii ?: $value)), '-'), 0, 190);
    }

    private static function plain(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
    }

    private static function limit(string $value, int $length): string
    {
        if (mb_strlen($value) <= $length) { return $value; }
        return rtrim(mb_substr($value, 0, $length - 1), " \t\n\r\0\x0B.,;:-") . '…';
    }
}
