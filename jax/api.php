<?php

declare(strict_types=1);

namespace Jax;

use Jax\Database;
use Jax\TextFormatting;

class API {
    public function __construct(
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly TextFormatting $textFormatting,
    ) {}

    public function render() {
        $list = [[], []];

        match($this->jax->g['act'] ?? '') {
            'searchmembers' => $this->searchmembers(),
            'emotes' => $this->emotes(),
            default => header('Location: /'),
        };
    }

    private function searchmembers() {
        $result = $this->database->safeselect(
            [
                'id',
                'display_name',
            ],
            'members',
            'WHERE `display_name` LIKE ? ORDER BY `display_name` LIMIT 10',
            $this->database->basicvalue(
                htmlspecialchars(
                    str_replace('_', '\_', $this->jax->g['term']),
                    ENT_QUOTES,
                ) . '%',
            ),
        );
        while ($f = $this->database->arow($result)) {
            $list[0][] = $f['id'];
            $list[1][] = $f['display_name'];
        }

        echo json_encode($list);
    }

    private function emotes() {
        $rules = $this->textFormatting->getEmoteRules();
        foreach ($rules as $k => $v) {
            $rules[$k] = '<img src="' . $v . '" alt="' . $this->textFormatting->blockhtml($k) . '" />';
        }

        echo json_encode([array_keys($rules), array_values($rules)]);
    }
}
