<?php

declare(strict_types=1);

namespace Jax;

use function setcookie;

final class Request
{
    /**
     * Access $_GET and $_POST together. Prioritizes $_POST.
     *
     * @SuppressWarnings("PHPMD.Superglobals")
     *
     * @return
     */
    public function both(string $property): array|string|null
    {
        return $_POST[$property] ?? $_GET[$property] ?? null;
    }

    /**
     * Access $_GET.
     *
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function get(string $property): array|string|null
    {
        return $_GET[$property] ?? null;
    }

    /**
     * Access $_POST.
     *
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function post(string $property): array|string|null
    {
        return $_POST[$property] ?? null;
    }

    /**
     * Access $_COOKIE.
     *
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function cookie(string $cookieName): ?string
    {
        return $_COOKIE[$cookieName] ?? null;
    }

    /**
     * Access $_FILES.
     *
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function files(string $property): array
    {
        return $_FILES[$property] ?? null;
    }

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
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

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function hasPostData(): bool
    {
        return $_POST !== [];
    }

    public function isJSAccess(): bool
    {
        return $this->jsAccess() !== 0;
    }

    public function isJSUpdate(): bool
    {
        return $this->jsAccess() === 1;
    }

    public function isJSNewLocation(): bool
    {
        if ($this->jsAccess() === 2) {
            return true;
        }

        return $this->jsAccess() === 3;
    }

    public function isJSDirectLink(): bool
    {
        return $this->jsAccess() === 3;
    }

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    private function jsAccess(): int
    {
        return (int) ($_SERVER['HTTP_X_JSACCESS'] ?? 0);
    }
}
