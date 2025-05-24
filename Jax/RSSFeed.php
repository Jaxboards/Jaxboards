<?php

declare(strict_types=1);

namespace Jax;

use function array_merge;
use function gmdate;
use function header;

use const PHP_EOL;

final class RSSFeed
{
    /**
     * @var array<string,int|string>
     */
    private array $feed = [];

    /**
     * @var array<array<int|string>>
     */
    private array $items = [];

    /**
     * @param array<string,int|string> $feed
     */
    public function __construct(array $feed)
    {
        $this->feed = $feed;
    }

    /**
     * @param array<string,int|string> $item
     */
    public function additem(array $item): void
    {
        $this->items[] = $item;
    }

    public function publish(): never
    {
        $this->feed['pubDate'] = gmdate('r');
        $xmlFeed = $this->makeXML($this->feed);
        foreach ($this->items as $item) {
            $xmlFeed .= "<item>{$this->makeXML($item)}</item>";
        }

        header('Content-type: application/rss+xml');
        echo <<<EOT
            <?xml version="1.0" encoding="UTF-8" ?>
            <rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
                <channel>
                    <atom:link href="{$this->feed['link']}&fmt=RSS" rel="self" type="application/rss+xml" />
                    {$xmlFeed}
                </channel>
            </rss>
            EOT;

        exit(0);
    }

    /**
     * @param array<string,int|string> $feed
     */
    public function makeXML(array $feed): string
    {
        $xml = '';

        foreach ($feed as $property => $value) {
            $attributes = ($property === 'content' ? ' type="html"' : '');
            $xml .= PHP_EOL . "<{$property}{$attributes}>{$value}</{$property}>";
        }

        return $xml;
    }
}
