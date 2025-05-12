<?php

declare(strict_types=1);

namespace Jax;

use function is_string;

final class RequestStringGetter
{
    /**
     * Access $_GET and $_POST together. Prioritizes $_POST.
     *
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function both(string $property): ?string
    {
        $post = $_POST[$property] ?? null;
        $get = $_GET[$property] ?? null;
        if (is_string($post)) {
            return $post;
        }

        if (is_string($get)) {
            return $get;
        }

        return null;
    }

    /**
     * Access $_GET.
     *
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function get(string $property): ?string
    {
        $get = $_GET[$property] ?? null;

        return is_string($get) ? $get : null;
    }

    /**
     * Access $_POST.
     *
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function post(string $property): ?string
    {
        $post = $_POST[$property] ?? null;

        return is_string($post) ? $post : null;
    }
}
