<?php

declare(strict_types=1);

namespace Jax\Constants;

enum JSAccess: int
{
    case UPDATING = 1;

    case ACTING = 2;

    case DIRECTLINK = 3;
}
