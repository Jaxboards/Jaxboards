<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\Interfaces\Route;
use Jax\Models\Member;
use Jax\Page;
use Jax\Request;
use Jax\TextFormatting;

use function array_keys;
use function array_values;
use function htmlspecialchars;
use function json_encode;
use function str_replace;

use const ENT_QUOTES;
use const JSON_THROW_ON_ERROR;

final readonly class API implements Route
{
    public function __construct(
        private Page $page,
        private Request $request,
        private TextFormatting $textFormatting,
    ) {}

    public function route(array $params): void
    {
        $this->page->earlyFlush(
            match ($params['method']) {
                'searchmembers' => $this->searchMembers(),
                'emotes' => $this->emotes(),
                default => '',
            },
        );
    }

    private function searchMembers(): string
    {
        $members = Member::selectMany(
            'WHERE `displayName` LIKE ? ORDER BY `displayName` LIMIT 10',
            htmlspecialchars(
                str_replace('_', '\_', $this->request->asString->get('term') ?? ''),
                ENT_QUOTES,
            ) . '%',
        );

        $list = [[], []];
        foreach ($members as $member) {
            $list[0][] = $member->id;
            $list[1][] = $member->displayName;
        }

        return json_encode($list, JSON_THROW_ON_ERROR);
    }

    private function emotes(): string
    {
        $rules = $this->textFormatting->rules->getEmotes();
        foreach ($rules as $text => $image) {
            $safeText = $this->textFormatting->blockhtml($text);
            $rules[$text] = "<img src=\"{$image}\" data-emoji=\"{$safeText}\" alt=\"{$safeText}\">";
        }

        return json_encode([array_keys($rules), array_values($rules)], JSON_THROW_ON_ERROR);
    }
}
