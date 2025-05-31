<?php

declare(strict_types=1);

namespace Jax;

use Jax\Models\Member;

use function array_keys;
use function array_values;
use function header;
use function htmlspecialchars;
use function json_encode;
use function str_replace;

use const ENT_QUOTES;

final readonly class API
{
    public function __construct(
        private Database $database,
        private Request $request,
        private TextFormatting $textFormatting,
    ) {}

    public function render(): void
    {
        match ($this->request->get('act')) {
            'searchmembers' => $this->searchMembers(),
            'emotes' => $this->emotes(),
            default => header('Location: /'),
        };
    }

    private function searchMembers(): void
    {
        $members = Member::selectMany(
            $this->database,
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

        echo json_encode($list);
    }

    private function emotes(): void
    {
        $rules = $this->textFormatting->rules->getEmotes();
        foreach ($rules as $text => $image) {
            $rules[$text] = '<img src="' . $image . '" alt="' . $this->textFormatting->blockhtml($text) . '" />';
        }

        echo json_encode([array_keys($rules), array_values($rules)]);
    }
}
