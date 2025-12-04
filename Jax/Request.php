<?php

declare(strict_types=1);

namespace Jax;

use Jax\Constants\JSAccess;

use function setcookie;

final class Request
{
    // phpcs:ignore SlevomatCodingStandard.Classes.ForbiddenPublicProperty.ForbiddenPublicProperty
    public RequestStringGetter $asString;

    /**
     * @var array<mixed>
     */
    private array $get = [];

    /**
     * @var array<mixed>
     */
    private array $post = [];

    /**
     * @var array<mixed>
     */
    private array $cookie = [];

    /**
     * @var array<mixed>
     */
    private array $files = [];

    /**
     * @var array<mixed>
     */
    private array $server = [];

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     *
     * @param array<mixed> $get
     * @param array<mixed> $post
     * @param array<mixed> $cookie
     * @param array<mixed> $files
     * @param array<mixed> $server
     */
    public function __construct(
        ?array $get = null,
        ?array $post = null,
        ?array $cookie = null,
        ?array $files = null,
        ?array $server = null,
    ) {
        $this->get = $get ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->cookie = $cookie ?? $_COOKIE;
        $this->files = $files ?? $_FILES;
        $this->server = $server ?? $_SERVER;

        $this->asString = new RequestStringGetter($this);
    }

    /**
     * Access $_GET and $_POST together. Prioritizes $_POST.
     *
     * @return null|array<mixed>|string
     */
    public function both(string $property): array|string|null
    {
        return $this->post[$property] ?? $this->get[$property] ?? null;
    }

    /**
     * Access $_GET.
     *
     * @return null|array<mixed>|string
     */
    public function get(string $property): array|string|null
    {
        return $this->get[$property] ?? null;
    }

    /**
     * Access $_POST.
     *
     * @return null|array<mixed>|string
     */
    public function post(string $property): array|string|null
    {
        return $this->post[$property] ?? null;
    }

    /**
     * Access $_COOKIE.
     */
    public function cookie(string $cookieName): ?string
    {
        return $this->cookie[$cookieName] ?? null;
    }

    /**
     * Access $_FILES.
     *
     * @return null|array{error:0,full_path:string,name:string,size:int<0,max>,tmp_name:string,type:string}
     */
    public function file(string $fieldName): ?array
    {
        $file = $this->files[$fieldName] ?? null;

        return $file && !$file['error'] ? $file : null;
    }

    /**
     * Access $_SERVER.
     */
    public function server (string $fieldName): ?string
    {
        return $this->server[$fieldName] ?? null;
    }

    public function hasCookies(): bool
    {
        return $this->cookie !== [];
    }

    /**
     * @param int $expires
     */
    public function setCookie(
        string $cookieName,
        ?string $cookieValue,
        $expires = 0,
    ): void {
        setcookie(
            $cookieName,
            $cookieValue ?? 'false',
            [
                'expires' => $expires,
                'httponly' => true,
                'samesite' => 'Strict',
                'secure' => true,
            ],
        );
        $this->cookie[$cookieName] = $cookieValue;
    }

    public function hasPostData(): bool
    {
        return $this->post !== [];
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
        return $this->jsAccess() === JSAccess::UPDATING->value && !$this->hasPostData();
    }

    /**
     * Did a page transition occur?
     */
    public function isJSNewLocation(): bool
    {
        if ($this->jsAccess() === JSAccess::ACTING->value) {
            return true;
        }

        return $this->jsAccess() === JSAccess::DIRECTLINK->value;
    }

    public function isJSDirectLink(): bool
    {
        return $this->jsAccess() === JSAccess::DIRECTLINK->value;
    }

    public function getUserAgent(): ?string
    {
        return $this->server['HTTP_USER_AGENT'] ?? null;
    }

    private function jsAccess(): int
    {
        return (int) ($this->server['HTTP_X_JSACCESS'] ?? 0);
    }
}
