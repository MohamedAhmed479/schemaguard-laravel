<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures;

use SchemaGuard\Tests\Fixtures\Models\User;

function relationTypeResolverFixture(User $user): void
{
    $posts = $user->posts;

    foreach ($user->posts as $post) {
        $postTitle = $post->title;
    }
}
