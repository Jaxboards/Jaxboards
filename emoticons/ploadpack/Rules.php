<?php

declare(strict_types=1);

namespace emoticons\ploadpack;

final class Rules
{
    /**
     * @return array<string,string>
     */
    public function get(): array
    {
        return [
            '&gt;:(' => 'mad.png',
            '&lt;3' => 'love.png',
            '8D' => 'awesome.png',
            ':(' => 'sad.png',
            ':)' => 'happy.png',
            ':/' => 'slant.png',
            ':D' => 'grin.png',
            ':O' => 'oh.png',
            ':o' => 'oh.png',
            ':P' => 'tongue.png',
            ':p' => 'tongue.png',
            ':|' => 'blank.png',
            'B)' => 'cool.png',
            'X(' => 'sick.png',
        ];
    }
}
