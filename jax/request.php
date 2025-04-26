<?php

declare(strict_types=1);

namespace Jax;

use function setcookie;

final class Request
{
    public function both(string $property)
    {
        return $_GET[$property] ?? $_POST[$property] ?? null;
    }

    public function get(string $property)
    {
        return $_GET[$property] ?? null;
    }

    public function post(string $property)
    {
        return $_POST[$property] ?? null;
    }

    public function cookie(string $cookieName)
    {
        return $_COOKIE[$cookieName] ?? null;
    }

    public function hasCookies(): bool
    {
        return $_COOKIE !== [];
    }

    public function setCookie(
        string $cookieName,
        ?string $cookieValue,
        $expires = false,
        $httponly = true,
    ): void {
        setcookie($cookieName, $cookieValue ?? 'false', ['expires' => $expires, 'path' => null, 'domain' => null, 'secure' => true, 'httponly' => $httponly]);
    }

    public function hasPostData(): bool
    {
        return $_POST !== [];
    }
}
