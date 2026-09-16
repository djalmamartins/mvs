<?php

declare(strict_types=1);

namespace Moves\Models\Support;

use MovesCode\Model\Model;

final class ArticleRevision extends Model
{
    public function __construct()
    {
        parent::__construct(
            'support_article_revisions',
            [],
            ['article_id', 'title']
        );
    }
}
