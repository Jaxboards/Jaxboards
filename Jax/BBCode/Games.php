<?php

declare(strict_types=1);

namespace Jax\BBCode;

use function array_key_exists;
use function explode;
use function mb_substr_count;
use function preg_replace_callback;
use function str_repeat;
use function trim;

final class Games
{
    /**
     * @param array<string> $match
     */
    public function bbcodeChessCallback(array $match): string
    {
        [, $fen] = $match;

        $fen = trim($fen);

        $parts = explode(' ', $fen);
        $fen = $parts[0];
        $moveNumber = (int) ($parts[1] ?? '1');

        // If it's empty, start a new game
        $fen = trim(
            $fen,
        ) === '' ? 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR' : $fen;

        // replace numbers with empty squares
        $fen = preg_replace_callback(
            '/[0-8]/',
            static fn($match) => str_repeat(' ', (int) $match[0]),
            $fen,
        );
        $fen = explode('/', (string) $fen);

        $white = [
            // 'R' => '♖',
            // 'N' => '♘',
            // 'B' => '♗',
            // 'Q' => '♕',
            // 'K' => '♔',
            // 'P' => '♙'
            // Decided to use filled (black) unicode pieces instead for visibility
            'R' => '♜',
            'N' => '♞',
            'B' => '♝',
            'Q' => '♛',
            'K' => '♚',
            'P' => '♟',
        ];
        $black = [
            'r' => '♜',
            'n' => '♞',
            'b' => '♝',
            'q' => '♛',
            'k' => '♚',
            'p' => '♟',
        ];

        $characters = [...$white, ...$black];
        $pieces = [];

        for ($row = 0; $row < 8; ++$row) {
            $pieces[$row] = [];

            for ($column = 0; $column < 8; ++$column) {
                $piece = $fen[$row][$column] ?? '';
                $color = array_key_exists($piece, $white)
                    ? 'color:white;-webkit-text-stroke: 1px #222;'
                    : (array_key_exists($piece, $black) ? 'color:black;' : '');
                $character = array_key_exists(
                    $piece,
                    $characters,
                ) ? $characters[$piece] : '';

                $pieces[$row][$column] = (trim(
                    $piece,
                ) !== '' ? "<div class='piece' data-piece='{$piece}' style='{$color}'>{$character}</div>" : '');
            }
        }

        return $this->renderCheckerBoard($pieces, 'chess', $moveNumber);
    }

    /**
     * @param array<string> $match
     */
    public function bbcodeCheckersCallback(array $match): string
    {
        [, $state] = $match;

        $state = trim($state);

        // If it's empty, start a new game
        $state = $state === '' ? 'bbbb/bbbb/bbbb/4/4/rrrr/rrrr/rrrr 1' : $state;

        $parts = explode(' ', $state);
        $state = $parts[0];
        $moveNumber = (int) ($parts[1] ?? '1');

        // replace numbers with empty squares
        $state = preg_replace_callback(
            '/[0-8]/',
            static fn($match) => str_repeat(' ', (int) $match[0]),
            $state,
        );

        $state = explode('/', (string) $state);

        $red = [
            'r' => '🔴',
            'R' => '♛',
        ];
        $black = [
            'b' => '⚫️',
            'B' => '♛',
        ];

        $characters = [...$red, ...$black];
        $pieces = [];

        for ($row = 0; $row < 8; ++$row) {
            $pieces[$row] = [];

            for ($column = 0; $column <= 4; ++$column) {
                $piece = $state[$row][$column] ?? '';
                $color = array_key_exists($piece, $red)
                    ? 'color:#ffbebe;'
                    : (array_key_exists($piece, $black) ? 'color:black;' : '');
                $character = array_key_exists(
                    $piece,
                    $characters,
                ) ? $characters[$piece] : '';

                $offset = -$row % 2;
                $pieces[$row][($column * 2 - $offset + 8) % 8] = '';
                $pieces[$row][$column * 2 + $offset + 1] = (trim(
                    $piece,
                ) !== '' ? "<div class='piece' data-piece='{$piece}' style='{$color}'>{$character}</div>" : '');
            }
        }

        return $this->renderCheckerBoard($pieces, 'checkers', $moveNumber);
    }

    public function bbcodeOthelloCallback(array $match): string
    {
        $state = $match[1] ?: '8/8/8/3bw3/3wb3/8/8/8';

        // replace numbers with empty squares
        $state = preg_replace_callback(
            '/[0-8]/',
            static fn($match) => str_repeat(' ', (int) $match[0]),
            (string) $state,
        );

        $state = explode('/', (string) $state);

        $board = '';

        for ($row = 0; $row < 8; ++$row) {
            $cells = '';
            for ($col = 0; $col < 8; ++$col) {
                $piece = $state[$row][$col] ?? '';

                if (trim($piece) !== '' && trim($piece) !== '0') {
                    $color = $piece === 'b' ? 'black' : 'white';
                    $piece = "<div class=\"piece {$color}\"></div>";
                }

                $cells .= "<td>{$piece}</td>";
            }

            $board .= "<tr>{$cells}</tr>";
        }

        $table = 'table';

        $whiteScore = mb_substr_count($board, 'w');
        $blackScore = mb_substr_count($board, 'b');

        return "<{$table} class=\"othello\">
            <tbody>{$board}</tbody>
            <tfoot>
                <td colspan=\"4\">White: <span class=\"score-white\">{$whiteScore}</span></td>
                <td colspan=\"4\">Black: <span class=\"score-black\">{$blackScore}</span></td>
            </tfoot>
        </{$table}>";
    }

    /**
     * Renders a checkerboard.
     *
     * @param array<array<string>> $pieces
     */
    private function renderCheckerBoard(array $pieces, string $game = 'chess', int $moveNumber = 1): string
    {
        $board = <<<'HTML'
            <tr>
                <th scope="col"></th>
                <th scope="col">A</th>
                <th scope="col">B</th>
                <th scope="col">C</th>
                <th scope="col">D</th>
                <th scope="col">E</th>
                <th scope="col">F</th>
                <th scope="col">G</th>
                <th scope="col">H</th>
            </tr>
            HTML;

        for ($row = 0; $row < 8; ++$row) {
            $cells = '';
            for ($column = 0; $column < 8; ++$column) {
                $cells .= '<td>' . $pieces[$row][$column] . '</td>';
            }

            $board .= "<tr><th scope='row'>" . (8 - $row) . "</th>{$cells}</tr>";
        }

        $table = 'table';

        return "<{$table} data-move-number='{$moveNumber}' class='checkerboard {$game}'>{$board}</table>";
    }
}
