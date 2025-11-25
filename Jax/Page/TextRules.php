<?php

declare(strict_types=1);

namespace Jax\Page;

use DI\Container;
use Jax\Config;
use Jax\Database;
use Jax\Models\TextRule;

final class TextRules
{
    /**
     * @var array<string,string>
     */
    private array $badwords = [];

    /**
     * Map of emojis to their URL replacements.
     * This is a merge of both emote pack and custom emotes.
     *
     * @var array<string,string>
     */
    private array $emotes = [];

    private ?string $emotePack = null;

    /**
     * Emotes from the emote pack.
     *
     * @var array<string, string>
     */
    private array $emotePackRules = [];

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
        private readonly Database $database,
    ) {
        $this->getEmotePack();
        $this->fetchCustomRules();
    }

    /**
     * @return array<string,string>
     */
    public function getBadwords(): array
    {
        return $this->badwords;
    }

    /**
     * Get emote pack rules.
     *
     * @return array<string,string> map with keys of emojis to their URL replacements
     */
    public function getEmotePack(?string $emotePack = null): array
    {
        $emotePack = $emotePack ?: $this->config->getSetting('emotepack');

        if ($this->emotePack === $emotePack && $this->emotePackRules) {
            return $this->emotePackRules;
        }

        // Load emoticon pack.
        if (!$emotePack) {
            return [];
        }

        $rules = $this->container->get("emoticons\\{$emotePack}\\Rules")->get();

        $emotes = [];
        foreach ($rules as $emote => $path) {
            $emotes[$emote] = "emoticons/{$emotePack}/{$path}";
        }

        $this->emotePack = $emotePack;

        return $this->emotePackRules = $this->emotes = $emotes;
    }

    /**
     * Get emote pack + custom rules.
     *
     * @return array<string,string> map with keys of emojis to their URL replacements
     */
    public function getEmotes(): array
    {
        return $this->emotes;
    }

    private function fetchCustomRules(): void
    {
        $textRules = TextRule::selectMany();
        foreach ($textRules as $textRule) {
            if ($textRule->type === 'emote') {
                $this->emotes[$textRule->needle] = $textRule->replacement;

                continue;
            }

            if ($textRule->type !== 'badword') {
                continue;
            }

            $this->badwords[$textRule->needle] = $textRule->replacement;
        }
    }
}
