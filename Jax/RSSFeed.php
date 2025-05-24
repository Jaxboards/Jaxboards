<?php

declare(strict_types=1);

namespace Jax;

use function array_map;
use function array_merge;
use function gmdate;
use function header;
use function implode;
use function is_array;

final class RSSFeed
{
    /**
     * @var array<string,array<string,string>|string>
     */
    private array $feed = [];

    /**
     * @param array<string,array<string,string>|string> $feed
     */
    public function __construct(array $feed)
    {
        $this->feed = array_merge($this->feed, $feed);
    }

    public function additem(array $item): void
    {
        $this->feed['item'][] = $item;
    }

    public function publish(): never
    {
        $this->feed['pubDate'] = gmdate('r');
        $xmlFeed = $this->makeXML($this->feed);
        header('Content-type: application/rss+xml');
        echo <<<EOT
            <?xml version="1.0" encoding="UTF-8" ?>
            <rss version="2.0">
                <channel>
                    {$xmlFeed}
                </channel>
            </rss>
            EOT;

        exit(0);
    }

    /**
     * @param array<string,array<string,string>|string> $feed
     */
    public function makeXML(array $feed): string
    {
        $xml = '';

        foreach ($feed as $property => $value) {
            if (is_array($value)) {
                $xml .= implode('', array_map(
                    fn($content): string => "<{$property}>" . $this->makeXML($content) . "</{$property}>",
                    $value,
                ));

                continue;
            }

            $attributes = ($property === 'content' ? ' type="html"' : '');
            $xml .= "<{$property}{$attributes}>{$value}</{$property}>";
        }

        return $xml;
    }
}
