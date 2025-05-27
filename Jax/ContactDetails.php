<?php

declare(strict_types=1);

namespace Jax;

use Jax\Models\Member;

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
        'gtalk' => 'gchat:chat?jid=%s',
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
     * @param Member|array<string,mixed> $profile
     *
     * @return array<string,array{string,string}>
     */
    public function getContactLinks(Member|array $profile): array
    {
        $profileData = $profile instanceof Member ? $profile->asArray() : $profile;

        $contactFields = array_filter(
            Member::FIELDS,
            static fn($field): bool => str_starts_with($field, 'contact') && $profileData[$field],
        );

        return array_reduce($contactFields, static function (array $links, $field) use ($profileData) {
            $type = mb_substr($field, 8);
            $value = $profileData[$field];
            $href = sprintf(self::CONTACT_URLS[$type], $value);
            $links[$type] = [$href, $value];

            return $links;
        }, []);
    }
}
