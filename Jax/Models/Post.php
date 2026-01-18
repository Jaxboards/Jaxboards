<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class Post extends Model
{
    public const TABLE = 'posts';

    #[Column(
        name: 'id',
        type: 'int',
        nullable: false,
        autoIncrement: true,
        unsigned: true,
    )]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'author', type: 'int', unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'null')]
    public int $author = 0;

    #[Column(name: 'post', type: 'text', nullable: false)]
    #[Key(fulltext: true)]
    public string $post = '';

    #[Column(
        name: 'openGraphMetadata',
        type: 'json',
        default: '{}',
        nullable: false,
    )]
    public string $openGraphMetadata = '{}';

    #[Column(name: 'date', type: 'datetime')]
    public ?string $date = null;

    #[Column(name: 'showsig', type: 'bool', default: true)]
    public int $showsig = 1;

    #[Column(name: 'showemotes', type: 'bool', default: true)]
    public int $showemotes = 1;

    #[Column(name: 'tid', type: 'int', nullable: false, unsigned: true)]
    #[ForeignKey(table: 'topics', field: 'id', onDelete: 'cascade')]
    public int $tid = 0;

    #[Column(name: 'newtopic', type: 'bool')]
    public int $newtopic = 0;

    #[Column(
        name: 'ip',
        type: 'binary',
        default: '',
        length: 16,
        nullable: false,
    )]
    #[Key]
    public string $ip = '';

    #[Column(name: 'editDate', type: 'datetime')]
    public ?string $editDate = null;

    #[Column(name: 'editby', type: 'int', unsigned: true)]
    public ?int $editby = null;

    #[Column(name: 'rating', type: 'text', default: '', nullable: false)]
    public string $rating = '';
}
