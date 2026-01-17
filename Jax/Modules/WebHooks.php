<?php

namespace Jax\Modules;

use Jax\Config;
use Jax\DomainDefinitions;
use Jax\Hooks;
use Jax\Interfaces\Module;
use Jax\Models\Post;
use Jax\Models\Topic;
use Jax\Router;
use Jax\TextFormatting;
use Jax\User;

class WebHooks implements Module
{
    public function __construct(
        private Config $config,
        private DomainDefinitions $domainDefinitions,
        private Hooks $hooks,
        private Router $router,
        private TextFormatting $textFormatting,
        private User $user
    ) {}

    public function init(): void
    {
        $this->hooks->addListener('post', $this->hookPost(...));
    }

    private function hookPost(Post $post, Topic $topic)
    {
        $discord = $this->config->get()['webhooks']['discord'] ?? null;

        if ($discord) {
            $topicURL = $this->domainDefinitions->getBoardURL()
                . $this->router->url('topic', [
                    'id' => $topic->id,
                    'findpost' => $post->id
                ]);

            $postContent = $this->textFormatting->textOnly($post->post);

            $this->sendJSON($discord, json_encode([
                'username' => $this->user->get()->displayName,
                'content' => <<<MARKDOWN
                    [{$topic->title}]($topicURL)

                    {$postContent}
                    MARKDOWN
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
