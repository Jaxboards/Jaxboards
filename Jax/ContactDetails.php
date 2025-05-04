<?php

declare(strict_types=1);

namespace Jax;

use function array_filter;
use function array_keys;
use function array_reduce;
use function mb_substr;
use function sprintf;
use function str_starts_with;

final class ContactDetails
{
    private const CONTACT_URLS = [
        'aim' => 'aim:goaim?screenname=%s',
        'bluesky' => 'https://bsky.app/profile/%s.bsky.social',
        'discord' => 'discord:%s',
        'googlechat' => 'gchat:chat?jid=%s',
        'msn' => 'msnim:chat?contact=%s',
        'skype' => 'skype:%s',
        'steam' => 'https://steamcommunity.com/id/%s',
        'twitter' => 'https://twitter.com/%s',
        'yim' => 'ymsgr:sendim?%s',
        'youtube' => 'https://youtube.com/%s',
    ];

    /**
     * Given a user's profile, returns an associative array formatted as:
     * 'twitter' => ['https://twitter.com/jax', 'jax']
     *
     * @return array<string,array{string,string}
     */
    public function getContactLinks(array $profile): array
    {
        $contactFields = array_filter(
            array_keys($profile),
            static fn($field): bool => str_starts_with((string) $field, 'contact') && $profile[$field],
        );

        return array_reduce($contactFields, static function ($links, $field) use ($profile) {
            $type = mb_substr($field, 8);
            $value = $profile[$field];
            $href = sprintf(self::CONTACT_URLS[$type], $value);
            $links[$type] = [$href, $value];

            return $links;
        }, []);
    }
}
