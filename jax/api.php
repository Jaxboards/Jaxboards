<?php

declare(strict_types=1);

namespace Jax;

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
        private Jax $jax,
        private Request $request,
        private TextFormatting $textFormatting,
    ) {}

    public function render(): void
    {
        match ($this->request->get('act')) {
            'searchmembers' => $this->searchmembers(),
            'emotes' => $this->emotes(),
            default => header('Location: /'),
        };
    }

    private function searchmembers(): void
    {
        $result = $this->database->safeselect(
            [
                'id',
                'display_name',
            ],
            'members',
            'WHERE `display_name` LIKE ? ORDER BY `display_name` LIMIT 10',
            $this->database->basicvalue(
                htmlspecialchars(
                    str_replace('_', '\_', $this->request->get('term')),
                    ENT_QUOTES,
                ) . '%',
            ),
        );

        $list = [[], []];
        while ($member = $this->database->arow($result)) {
            $list[0][] = $member['id'];
            $list[1][] = $member['display_name'];
        }

        echo json_encode($list);
    }

    private function emotes(): void
    {
        $rules = $this->textFormatting->getEmoteRules();
        foreach ($rules as $k => $v) {
            $rules[$k] = '<img src="' . $v . '" alt="' . $this->textFormatting->blockhtml($k) . '" />';
        }

        echo json_encode([array_keys($rules), array_values($rules)]);
    }
}
