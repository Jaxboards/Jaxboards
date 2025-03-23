<?php

class rssfeed
{
    public $feed = array();

    public function __construct($settings)
    {
        $this->feed = array_merge($this->feed, $settings);
    }

    public function additem($settings)
    {
        $this->feed['item'][] = $settings;
    }

    public function publish()
    {
        $this->feed['pubDate'] = date('r');
        $xmlFeed = $this->make_xml($this->feed);
        echo <<<EOT
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0">
    <channel>
        {$xmlFeed}
    </channel>
</rss>
EOT;
    }

    public function make_xml($array, $k2 = false)
    {
        $r = '';
        foreach ($array as $k => $v) {
            $isn = is_numeric($k);
            if (is_array($v) && $v[0]) {
                foreach ($v as $v2) {
                    $r .= "<{$k}>" . $this->make_xml($v2) . "</{$k}>";
                }
            } else {
                $r .= "<{$k}" . ('content' == $k ? ' type="html"' : '') . '>' .
                    (is_array($v) ? $this->make_xml($v, $k) : $v) . "</{$k}>";
            }
        }

        return $r;
    }
}
