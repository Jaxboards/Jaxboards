<?php

declare(strict_types=1);

namespace Jax;

use function is_string;

final readonly class RequestStringGetter
{
    public function __construct(private Request $request) {}

    /**
     * Access $_GET and $_POST together. Prioritizes $_POST.
     */
    public function both(string $property): ?string
    {
        $post = $this->request->post($property) ?? null;
        $get = $this->request->get($property) ?? null;
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
     */
    public function get(string $property): ?string
    {
        $get = $this->request->get($property) ?? null;

        return is_string($get) ? $get : null;
    }

    /**
     * Access $_POST.
     */
    public function post(string $property): ?string
    {
        $post = $this->request->post($property) ?? null;

        return is_string($post) ? $post : null;
    }
}
