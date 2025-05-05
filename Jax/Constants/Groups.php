<?php

namespace Jax\Constants;

enum Groups: int {
    case Member = 1;
    case Admin = 2;
    case Guest = 3;
    case Banned = 4;
    case Validating = 5;
}
