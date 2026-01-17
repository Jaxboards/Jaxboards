<?php

namespace Jax\Modules;

use Jax\Config;
use Jax\Hooks;
use Jax\Interfaces\Module;
use Jax\Models\Post;
use Jax\User;

class WebHooks implements Module
{
    public function __construct(
        private Config $config,
        private Hooks $hooks,
        private User $user
    ) {}

    public function init(): void
    {
        $this->hooks->addListener('post', $this->hookPost(...));
    }

    private function hookPost(Post $post)
    {
        $discord = $this->config->get()['webhooks']['discord'] ?? null;

        if ($discord) {
            $this->sendJSON($discord, json_encode([
                'username' => $this->user->get()->displayName,
                'content' => $post->post,
            ]));
        }
    }

    private function sendJSON(string $url, string $json)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($json)));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_exec($ch);
    }
}
