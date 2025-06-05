<?php

declare(strict_types=1);

namespace Jax;

use function mb_strtolower;
use function str_contains;

final readonly class BotDetector
{
    public const BOTS = [
        'AhrefsBot' => 'Ahrefs',
        'Amazonbot' => 'Amazon',
        'Applebot' => 'Applebot',
        'archive.org_bot' => 'Internet Archive',
        'AwarioBot' => 'Awario',
        'Baiduspider' => 'Baidu',
        'Barkrowler' => 'Babbar.tech',
        'Bingbot' => 'Bing',
        'Bytespider' => 'Bytespider',
        'CensysInspect' => 'CensysInspect',
        'Centurybot' => 'Century',
        'ChatGLM-Spider' => 'ChatGLM',
        'ChatGPT-User' => 'ChatGPT',
        'ClaudeBot' => 'ClaudeBot',
        'DataForSeoBot' => 'DataForSeo',
        'Discordbot' => 'Discord',
        'DotBot' => 'DotBot',
        'DuckDuckBot' => 'DuckDuckGo',
        'Expanse' => 'Expanse',
        'facebookexternalhit' => 'Facebook',
        'Friendly_Crawler' => 'FriendlyCrawler',
        'Googlebot' => 'Google',
        'GoogleOther' => 'GoogleOther',
        'Google-Read-Aloud' => 'Google-Read-Aloud',
        'GPTBot' => 'GPTBot',
        'ia_archiver' => 'Internet Archive Alexa',
        'ImagesiftBot' => 'Imagesift',
        'linkdexbot' => 'Linkdex',
        'Mail.RU_Bot' => 'Mail.RU',
        'meta-externalagent' => 'Meta',
        'mj12bot' => 'Majestic',
        'MojeekBot' => 'Mojeek',
        'OAI-SearchBot' => 'OpenAI',
        'ows.eu' => 'Owler',
        'PerplexityBot' => 'Perplexity',
        'PetalBot' => 'PetalBot',
        'Qwantbot' => 'Qwant',
        'SemrushBot' => 'Semrush',
        'SeznamBot' => 'Seznam',
        'Sogou web spider' => 'Sogou',
        'Teoma' => 'Ask.com',
        'TikTokSpider' => 'TikTok',
        'Turnitin' => 'Turnitin',
        'Twitterbot' => 'Twitter',
        'W3C_Validator' => 'W3C Validator',
        'WhatsApp' => 'WhatsApp',
        'Y!J-WSC' => 'Yahoo Japan',
        'yahoo! slurp' => 'Yahoo',
        'YandexBot' => 'Yandex',
        'YandexRenderResourcesBot' => 'YandexRenders',
    ];

    public function __construct(private Request $request) {}

    public function getBotName(): ?string
    {
        $userAgent = mb_strtolower((string) $this->request->getUserAgent());

        foreach (self::BOTS as $agentName => $friendlyName) {
            if (str_contains($userAgent, mb_strtolower($agentName))) {
                return $friendlyName;
            }
        }

        return null;
    }
}
