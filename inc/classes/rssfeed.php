<?php

declare(strict_types=1);

final class rssfeed
{
    public function __construct($settings)
    {
        $this->feed = array_merge($this->feed, $settings);
    }

    public $feed = [];

    public function additem($settings): void
    {
        $this->feed['item'][] = $settings;
    }

    public function publish(): void
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
    }

    public function make_xml($array): string
    {
        $r = '';
        foreach ($array as $k => $v) {
            $isn = is_numeric($k);
            if (is_array($v) && $v[0]) {
                foreach ($v as $v2) {
                    $r .= "<{$k}>" . $this->make_xml($v2) . "</{$k}>";
                }
            } else {
                $r .= "<{$k}" . ($k === 'content' ? ' type="html"' : '') . '>'
                    . (is_array($v) ? $this->make_xml($v) : $v) . "</{$k}>";
            }
        }

        return $r;
    }
}
