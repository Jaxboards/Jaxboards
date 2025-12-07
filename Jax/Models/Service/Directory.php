<?php

declare(strict_types=1);

namespace Jax\Models\Service;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Directory extends Model
{
    public const TABLE = 'directory';

    #[Column(name: 'id', type: 'int', nullable: false, autoIncrement: true, unsigned: true)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'registrar_email', type: 'string', length: 255, nullable: false)]
    public string $registrar_email = '';

    #[Column(name: 'registrar_ip', type: 'binary', default: '', length: 16, nullable: false)]
    public string $registrar_ip = '';

    #[Column(name: 'date', type: 'datetime', default: null)]
    public ?string $date = null;

    #[Column(name: 'boardname', type: 'string', length: 30, nullable: false)]
    public string $boardname = '';

    #[Column(name: 'referral', type: 'string', length: 255, nullable: false)]
    public string $referral = '';
}
