<?php

declare(strict_types=1);

namespace Jax\Models\Service;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class Directory extends Model
{
    public const TABLE = 'directory';

    #[Column(name: 'id', type: 'int', nullable: false, autoIncrement: true, unsigned: true)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'registrarEmail', type: 'string', length: 255, nullable: false)]
    public string $registrarEmail = '';

    #[Column(name: 'registrarIP', type: 'binary', default: '', length: 16, nullable: false)]
    public string $registrarIP = '';

    #[Column(name: 'date', type: 'datetime', default: null)]
    public ?string $date = null;

    #[Column(name: 'boardname', type: 'string', length: 30, nullable: false)]
    public string $boardname = '';

    #[Column(name: 'referral', type: 'string', length: 255, nullable: true)]
    public ?string $referral = null;
}
