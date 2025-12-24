<?php

declare(strict_types=1);

namespace emoticons\keshaemotes;

final class Rules
{
    /**
     * @return array<string,string>
     */
    public function get(): array
    {
        return [
            ':angry:' => 'angry.gif',
            '(!)' => 'exclamation.gif',
            '(?)' => 'question.gif',
            '-_-' => 'unamused.gif',
            '8D' => 'awesome.gif',
            ':(' => 'frown.gif',
            ':)' => 'smile.gif',
            ':-.' => 'unsure.gif',
            ':3' => 'kittyface.gif',
            ':cookie:' => 'cookie.gif',
            ':D' => 'grin.gif',
            ':gt:' => 'gt.gif',
            ':lt:' => 'lt.gif',
            ':o' => 'shocked.gif',
            ':p' => 'tongue.gif',
            ':P' => 'tongue.gif',
            ':S' => 'wacky.gif',
            ':wub:' => 'love.gif',
            ':|' => 'blank.gif',
            ';)' => 'wink.gif',
            'B)' => 'cool.gif',
            'O_o' => 'O_o.gif',
            'X(' => 'sick.gif',
            'X)' => 'x).gif',
            'x)' => 'x).gif',
            'XD' => 'XD.gif',
            '^_^' => '^_^.gif',
        ];
    }
}
