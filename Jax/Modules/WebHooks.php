<?php

declare(strict_types=1);

namespace Jax\Modules;

use Override;
use Curl\Curl;
use Jax\BBCode;
use Jax\Config;
use Jax\Hooks;
use Jax\Interfaces\Module;
use Jax\Models\Post;
use Jax\Models\Topic;
use Jax\Router;
use Jax\User;

use function json_encode;
use function mb_strlen;
use function mb_substr;

use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const JSON_THROW_ON_ERROR;

final class WebHooks implements Module
{
    public const int DISCORD_CHARACTER_LIMIT = 2_000;

    /**
     * @var ?array<mixed>
     */
    private ?array $webhooks = null;

    public function __construct(
        private readonly BBCode $bbcode,
        private readonly Config $config,
        private readonly Hooks $hooks,
        private readonly Router $router,
        private readonly User $user,
        // for testing
        private readonly ?Curl $curl = null,
    ) {
        $this->webhooks = $this->config->get()['webhooks'] ?? [];
    }

    #[Override]
    public function init(): void
    {
        if ($this->webhooks === []) {
            return;
        }

        $this->hooks->addListener('post', $this->hookPost(...));
    }

    private function hookPost(Post $post, Topic $topic): void
    {
        $discord = $this->webhooks['discord'] ?? null;

        if (!$discord) {
            return;
        }

        $rootURL = $this->router->getRootURL();
        $topicURL =
            $rootURL
            . $this->router->url('topic', [
                'id' => $topic->id,
                'findpost' => $post->id,
            ]);

        $postContent = $this->bbcode->toMarkdown($post->post);

        // Trim content to remain under discord's character limit
        $content = mb_substr(<<<MARKDOWN
            [{$topic->title}](<{$topicURL}>)

            {$postContent}
            MARKDOWN, 0, self::DISCORD_CHARACTER_LIMIT);

        $member = $this->user->get();
        $this->sendJSON($discord, [
            'username' => $member->displayName,
            'avatar_url' => $member->avatar,
            'content' => $content,
        ]);
    }

    /**
     * @param array<mixed> $payload
     */
    private function sendJSON(string $url, array $payload): void
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $curl = $this->curl ?? new Curl();
        $curl->setUrl($url);
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('Content-Length', mb_strlen($json));
        $curl->setOpt(CURLOPT_CUSTOMREQUEST, 'POST');
        $curl->setOpt(CURLOPT_POSTFIELDS, $json);
        $curl->setOpt(CURLOPT_RETURNTRANSFER, true);
        $curl->exec();
        $curl->reset();
    }
}
