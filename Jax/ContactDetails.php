<?php

declare(strict_types=1);

namespace Jax;

use Jax\Models\Member;

use function array_filter;
use function array_reduce;
use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function sprintf;
use function str_starts_with;

final class ContactDetails
{
    private const CONTACT_URLS = [
        'aim' => 'aim:goaim?screenname=%s',
        'bluesky' => 'https://bsky.app/profile/%s.bsky.social',
        'discord' => 'discord://-/users/%s',
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
     * @return array<string, object{href:string,username:string}>
     */
    public function getContactLinks(Member $member): array
    {
        $contactFieldPrefix = 'contact';
        $contactFields = array_filter(
            Member::getFields(),
            static fn(string $field): bool => str_starts_with($field, $contactFieldPrefix) && $member->{$field},
        );

        return array_reduce($contactFields, static function (array $links, $field) use ($contactFieldPrefix, $member): array {
            $type = mb_strtolower(mb_substr($field, mb_strlen($contactFieldPrefix)));
            $username = $member->{$field};
            $href = sprintf(self::CONTACT_URLS[$type], $username);
            $links[$type] = (object) ['href' => $href, 'username' => $username];

            return $links;
        }, []);
    }
}
