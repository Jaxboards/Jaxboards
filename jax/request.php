<?php

namespace Jax;

class Request {

    public function get($property) {
        return $_GET[$property] ?? null;
    }

    public function post($property) {
        return $_POST[$property] ?? null;
    }

    public function both($property) {
        return $$_GET[$property] ?? $_GET[$property] ?? null;
    }
}
