<?php

namespace Jax;

class Request {

    public function get(string $property) {
        return $_GET[$property] ?? null;
    }

    public function post(string $property) {
        return $_POST[$property] ?? null;
    }

    public function both(string $property) {
        return $_GET[$property] ?? $_POST[$property] ?? null;
    }
}
