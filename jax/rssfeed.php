<?php

declare(strict_types=1);

namespace Jax;

use function array_merge;
use function gmdate;
use function header;
use function is_array;

final class RSSFeed
{
    /**
     * @var array<string, array|string>
     */
    private $feed = [];

    public function __construct($settings)
    {
        $this->feed = array_merge($this->feed, $settings);
    }

    public function additem($settings): void
    {
        $this->feed['item'][] = $settings;
    }

    public function publish(): never
    {
        $this->feed['pubDate'] = gmdate('r');
        $xmlFeed = $this->make_xml($this->feed);
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

    public function make_xml(array $array): string
    {
        $xml = '';
        foreach ($array as $property => $value) {
            if (is_array($value)) {
                $xml .= implode('', array_map(
                    fn($content): string => "<{$property}>" . $this->make_xml($content) . "</{$property}>",
                    $value,
                ));
            } else {
                $xml .= "<{$property}" . ($property === 'content' ? ' type="html"' : '') . '>'
                    . $value . "</{$property}>";
            }
        }

        return $xml;
    }
}
