<?php

declare(strict_types=1);

namespace Jax\Modules;

use Curl\Curl;
use Jax\Config;
use Jax\DomainDefinitions;
use Jax\Hooks;
use Jax\Interfaces\Module;
use Jax\Models\Post;
use Jax\Models\Topic;
use Jax\Router;
use Jax\TextFormatting;
use Jax\User;

use function json_encode;
use function mb_strlen;

use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const JSON_THROW_ON_ERROR;

final readonly class WebHooks implements Module
{
    public function __construct(
        private Config $config,
        private DomainDefinitions $domainDefinitions,
        private Hooks $hooks,
        private Router $router,
        private TextFormatting $textFormatting,
        private User $user,
        // for testing
        private ?Curl $curl
    ) {
        $this->curl = $curl ?? new Curl();
    }

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
        $this->sendJSON($discord, [
            'username' => $member->displayName,
            'avatar_url' => $member->avatar ?? $boardURL . '/Service/Themes/Default/avatars/default.gif',
            'content' => <<<MARKDOWN
                [{$topic->title}](<{$topicURL}>)

                {$postContent}
                MARKDOWN,
        ]);
    }

    /**
     * @param array<mixed> $payload
     */
    public function sendJSON(string $url, array $payload): void
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $curl = $this->curl;
        $curl->setUrl($url);
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('Content-Length', mb_strlen(
            $json,
        ));
        $curl->setOpt(CURLOPT_CUSTOMREQUEST, 'POST');
        $curl->setOpt(CURLOPT_POSTFIELDS, $json);
        $curl->setOpt(CURLOPT_RETURNTRANSFER, true);
        $curl->exec();
        $curl->reset();
    }
}
