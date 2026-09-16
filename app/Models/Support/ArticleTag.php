<?php

declare(strict_types=1);

namespace Moves\Models\Support;

use MovesCode\Model\Model;

final class ArticleTag extends Model
{
    public function __construct()
    {
        parent::__construct(
            'support_article_tags',
            [],
            ['article_id', 'tag_id']
        );
    }
}
