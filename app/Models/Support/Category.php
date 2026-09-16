<?php

declare(strict_types=1);

namespace Moves\Models\Support;

use MovesCode\Model\Model;

final class Category extends Model
{
    public function __construct()
    {
        parent::__construct(
            'support_categories',
            [],
            ['name', 'slug']
        );
    }
}
