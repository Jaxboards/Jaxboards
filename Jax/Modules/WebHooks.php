<?php

declare(strict_types=1);

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

use function curl_exec;
use function curl_init;
use function curl_setopt;
use function json_encode;
use function mb_strlen;

use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_URL;

final readonly class WebHooks implements Module
{
    public function __construct(
        private Config $config,
        private DomainDefinitions $domainDefinitions,
        private Hooks $hooks,
        private Router $router,
        private TextFormatting $textFormatting,
        private User $user,
    ) {}

    public function init(): void
    {
        $this->hooks->addListener('post', $this->hookPost(...));
    }

    private function hookPost(Post $post, Topic $topic): void
    {
        $discord = $this->config->get()['webhooks']['discord'] ?? null;

        if (!$discord) {
            return;
        }

        $boardURL = $this->domainDefinitions->getBoardURL();
        $topicURL = $boardURL
            . $this->router->url('topic', [
                'id' => $topic->id,
                'findpost' => $post->id,
            ]);

        $postContent = $this->textFormatting->textOnly($post->post);

        $member = $this->user->get();
        $this->sendJSON($discord, json_encode([
            'username' => $member->displayName,
            'avatar_url' => $member->avatar ?? $boardURL . '/Service/Themes/Default/avatars/default.gif',
            'content' => <<<MARKDOWN
                [{$topic->title}](<{$topicURL}>)

                {$postContent}
                MARKDOWN,
        ]));
    }

    private function sendJSON(string $url, string $json): void
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . mb_strlen($json)]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_exec($ch);
    }
}
