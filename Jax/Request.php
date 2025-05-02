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
     */
    public function both(string $property): null|array|string
    {
        return $_POST[$property] ?? $_GET[$property] ?? null;
    }

    /**
     * Access $_GET.
     *
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function get(string $property): null|array|string
    {
        return $_GET[$property] ?? null;
    }

    /**
     * Access $_POST.
     *
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function post(string $property): null|array|string
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
     *
     * @return array<string,array<string,mixed>>
     */
    public function file(string $fieldName): ?array
    {
        $file = $_FILES[$fieldName] ?? null;

        return $file && !$file['error'] ? $file : null;
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
        setcookie(
            $cookieName,
            $cookieValue ?? 'false',
            [
                'domain' => null,
                'expires' => $expires,
                'httponly' => $httponly,
                'path' => null,
                'secure' => true,
            ],
        );
    }

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function hasPostData(): bool
    {
        return $_POST !== [];
    }

    /**
     * Was the page accessed through javascript?
     */
    public function isJSAccess(): bool
    {
        return $this->jsAccess() !== 0;
    }

    /**
     * Is the client just polling for updates?
     */
    public function isJSUpdate(): bool
    {
        return $this->jsAccess() === 1 && !$this->hasPostData();
    }

    /**
     * Did a page transition occur?
     */
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

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function getUserAgent(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?: null;
    }
}
