<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Post extends Model
{
    public const TABLE = 'posts';

    #[Column(name: 'id', type: 'int', unsigned: true, nullable: false, autoIncrement: true)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'author', type: 'int', unsigned: true)]
    public int $author = 0;

    #[Column(name: 'post', type: 'text', nullable: false)]
    public string $post = '';

    #[Column(name: 'date', type: 'datetime')]
    public ?string $date = null;

    #[Column(name: 'showsig', type: 'bool', default: true)]
    public int $showsig = 1;

    #[Column(name: 'showemotes', type: 'bool', default: true)]
    public int $showemotes = 1;

    #[Column(name: 'tid', type: 'int', unsigned: true, nullable: false)]
    public int $tid = 0;

    #[Column(name: 'newtopic', type: 'bool')]
    public int $newtopic = 0;

    #[Column(name: 'ip', type: 'binary', length: 16, nullable: false, default: '')]
    public string $ip = '';

    #[Column(name: 'editDate', type: 'datetime')]
    public ?string $editDate = null;

    #[Column(name: 'editby', type: 'int', unsigned: true)]
    public ?int $editby = null;

    #[Column(name: 'rating', type: 'text', nullable: false, default: '')]
    public string $rating = '';
}
