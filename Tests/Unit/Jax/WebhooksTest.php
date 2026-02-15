<?php

declare(strict_types=1);

namespace Tests\Unit\Jax;

use Curl\Curl;
use Jax\Hooks;
use Jax\Models\Member;
use Jax\Models\Post;
use Jax\Models\Topic;
use Jax\Modules\WebHooks;
use Jax\TextRules;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\UnitTestCase;

use function DI\autowire;
use function implode;
use function json_encode;
use function PHPUnit\Framework\once;

use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;

/**
 * @internal
 */
#[CoversNothing]
final class WebhooksTest extends UnitTestCase
{
    public function testDiscordWebhook(): void
    {
        $this->setBoardConfig([
            'webhooks' => [
                'discord' => 'http://localhost',
            ],
        ]);

        $hooks = $this->container->get(Hooks::class);

        // Mocks
        $curlMock = $this->createMock(Curl::class);
        $this->container->set(User::class, $this->createConfiguredStub(User::class, [
            'get' => new Member(['displayName' => 'Sean', 'avatar' => 'avatar url']),
        ]));
        $this->container->set(TextRules::class, self::createStub(TextRules::class));
        $this->container->set(WebHooks::class, autowire()->constructorParameter('curl', $curlMock));

        $webhook = $this->container->get(WebHooks::class);
        $webhook->init();

        $curlMock
            ->expects($this->exactly(3))
            ->method('setOpt')
            ->willReturnCallback(function (int $option, mixed $value): void {
                match ($option) {
                    CURLOPT_CUSTOMREQUEST => $this->assertEquals($value, 'POST'),
                    CURLOPT_POSTFIELDS => $this->assertEquals($value, json_encode([
                        'username' => 'Sean',
                        'avatar_url' => 'avatar url',
                        'content' => implode("\n", [
                            '[topic title](<https://jaxboards.com/topic/0?findpost=0>)',
                            '',
                            'post content',
                        ]),
                    ])),
                    CURLOPT_RETURNTRANSFER => $this->assertEquals($value, true),
                };
            });

        $curlMock->expects(once())->method('exec');

        $hooks->dispatch('post', new Post(['post' => 'post content']), new Topic(['title' => 'topic title']));
    }
}
