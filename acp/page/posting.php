<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;

use function array_reverse;
use function krsort;
use function rawurlencode;

use const PHP_EOL;

final readonly class Posting
{
    public function __construct(private Config $config, private Page $page) {}

    public function route(): void
    {
        global $JAX;

        $links = [
            'emoticons' => 'Emoticons',
            'postrating' => 'Post Rating',
            'wordfilter' => 'Word Filter',
        ];
        $sidebarLinks = '';
        foreach ($links as $do => $title) {
            $sidebarLinks .= $this->page->parseTemplate(
                'sidebar-list-link.html',
                [
                    'title' => $title,
                    'url' => '?act=posting&do=' . $do,
                ],
            ) . PHP_EOL;
        }

        $this->page->sidebar(
            $this->page->parseTemplate(
                'sidebar-list.html',
                [
                    'content' => $sidebarLinks,
                ],
            ),
        );

        match ($JAX->b['do'] ?? '') {
            'emoticons' => $this->emoticons(),
            'postrating' => $this->postrating(),
            'wordfilter' => $this->wordfilter(),
            default => $this->emoticons(),
        };
    }

    public function wordfilter(): void
    {
        global $JAX,$DB;
        $page = '';
        $wordfilter = [];
        $result = $DB->safeselect(
            [
                'id',
                'enabled',
                'needle',
                'replacement',
                'type',
            ],
            'textrules',
            "WHERE `type`='badword'",
        );
        while ($f = $DB->arow($result)) {
            $wordfilter[$f['needle']] = $f['replacement'];
        }

        // Delete.
        if (isset($JAX->g['d']) && $JAX->g['d']) {
            $DB->safedelete(
                'textrules',
                "WHERE `type`='badword' AND `needle`=?",
                $DB->basicvalue($JAX->g['d']),
            );
            unset($wordfilter[$JAX->g['d']]);
        }

        // Insert.
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            $JAX->p['badword'] = $JAX->blockhtml($JAX->p['badword']);
            if (!$JAX->p['badword'] || !$JAX->p['replacement']) {
                $page .= $this->page->error('All fields required.');
            } elseif (
                isset($wordfilter[$JAX->p['badword']])
                && $wordfilter[$JAX->p['badword']]
            ) {
                $page .= $this->page->error(
                    "'" . $JAX->p['badword'] . "' is already used.",
                );
            } else {
                $DB->safeinsert(
                    'textrules',
                    [
                        'needle' => $JAX->p['badword'],
                        'replacement' => $JAX->p['replacement'],
                        'type' => 'badword',
                    ],
                );
                $wordfilter[$JAX->p['badword']] = $JAX->p['replacement'];
            }
        }

        if ($wordfilter === []) {
            $table = $this->page->parseTemplate(
                'posting/word-filter-empty.html',
            ) . PHP_EOL . $this->page->parseTemplate(
                'posting/word-filter-submit-row.html',
            );
        } else {
            $table = $this->page->parseTemplate(
                'posting/word-filter-heading.html',
            ) . PHP_EOL . $this->page->parseTemplate(
                'posting/word-filter-submit-row.html',
            );
            $currentFilters = array_reverse($wordfilter, true);
            foreach ($currentFilters as $filter => $result) {
                $resultCode = $JAX->blockhtml($result);
                $filterUrlEncoded = rawurlencode($filter);
                $table .= $this->page->parseTemplate(
                    'posting/word-filter-row.html',
                    [
                        'filter' => $filter,
                        'filter_url_encoded' => $filterUrlEncoded,
                        'result_code' => $resultCode,
                    ],
                ) . PHP_EOL;
            }
        }

        $page .= $this->page->parseTemplate(
            'posting/word-filter.html',
            [
                'content' => $table,
            ],
        );

        $this->page->addContentBox('Word Filter', $page);
    }

    public function emoticons(): void
    {
        global $JAX,$DB;

        $basesets = [
            '' => 'None',
            'keshaemotes' => "Kesha's pack",
            'ploadpack' => "Pload's pack",
        ];
        $page = '';
        $emoticons = [];
        // Delete emoticon.
        if (isset($JAX->g['d']) && $JAX->g['d']) {
            $DB->safedelete(
                'textrules',
                "WHERE `type`='emote' AND `needle`=?",
                $DB->basicvalue($_GET['d']),
            );
        }

        // Select emoticons.
        $result = $DB->safeselect(
            [
                'id',
                'type',
                'needle',
                'replacement',
                'enabled',
            ],
            'textrules',
            "WHERE `type`='emote'",
        );
        while ($f = $DB->arow($result)) {
            $emoticons[$f['needle']] = $f['replacement'];
        }

        // Insert emoticon.
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (!$JAX->p['emoticon'] || !$JAX->p['image']) {
                $page .= $this->page->error('All fields required.');
            } elseif (isset($emoticons[$JAX->blockhtml($JAX->p['emoticon'])])) {
                $page .= $this->page->error('That emoticon is already being used.');
            } else {
                $DB->safeinsert(
                    'textrules',
                    [
                        'enabled' => 1,
                        'needle' => $JAX->blockhtml($JAX->p['emoticon']),
                        'replacement' => $JAX->p['image'],
                        'type' => 'emote',
                    ],
                );
                $emoticons[$JAX->blockhtml($JAX->p['emoticon'])] = $JAX->p['image'];
            }
        }

        if (isset($JAX->p['baseset']) && $basesets[$JAX->p['baseset']]) {
            $this->config->write(['emotepack' => $JAX->p['baseset']]);
        }

        if ($emoticons === []) {
            $table = $this->page->parseTemplate(
                'posting/emoticon-heading.html',
            ) . PHP_EOL . $this->page->parseTemplate(
                'posting/emoticon-submit-row.html',
            ) . PHP_EOL . $this->page->parseTemplate(
                'posting/emoticon-empty-row.html',
            );
        } else {
            $table = $this->page->parseTemplate(
                'posting/emoticon-heading.html',
            ) . PHP_EOL . $this->page->parseTemplate(
                'posting/emoticon-submit-row.html',
            );
            $emoticons = array_reverse($emoticons, true);

            foreach ($emoticons as $emoticon => $smileyFile) {
                $smileyFile = $JAX->blockhtml($smileyFile);
                $emoticonUrlEncoded = rawurlencode($emoticon);
                $table .= $this->page->parseTemplate(
                    'posting/emoticon-row.html',
                    [
                        'emoticon' => $emoticon,
                        'emoticon_url_encoded' => rawurlencode($emoticon),
                        'smiley_url' => $smileyFile,
                    ],
                ) . PHP_EOL;
            }
        }

        $page .= $this->page->parseTemplate(
            'posting/emoticons.html',
            [
                'content' => $table,
            ],
        );

        $this->page->addContentBox('Custom Emoticons', $page);

        $emotepack = $this->config->getSetting('emotepack');
        $emoticonPackOptions = '';
        foreach ($basesets as $packId => $packName) {
            $emoticonPackOptions .= $this->page->parseTemplate(
                'select-option.html',
                [
                    'label' => $packName,
                    'selected' => $emotepack === $packId
                ? ' selected="selected"' : '',
                    'value' => $packId,
                ],
            );
        }

        $emoticonRows = '';
        if ($emotepack) {
            require_once JAXBOARDS_ROOT . "/emoticons/{$emotepack}/rules.php";
            foreach ($rules as $emoticon => $smileyFile) {
                $emoticonRows .= $this->page->parseTemplate(
                    'posting/emoticon-packs-row.html',
                    [
                        'emoticon' => $emoticon,
                        'smiley_url' => "/emoticons/{$emotepack}/{$smileyFile}",
                    ],
                ) . PHP_EOL;
            }
        }


        $page = $this->page->parseTemplate(
            'posting/emoticon-packs.html',
            [
                'emoticon_packs' => $emoticonPackOptions,
                'emoticon_rows' => $emoticonRows,
            ],
        );

        $this->page->addContentBox('Base Emoticon Set', $page);
    }

    public function postrating(): void
    {
        global $JAX,$DB;
        $page = '';
        $page2 = '';
        $niblets = [];
        $result = $DB->safeselect(
            ['id', 'img', 'title'],
            'ratingniblets',
            'ORDER BY `id` DESC',
        );
        while ($f = $DB->arow($result)) {
            $niblets[$f['id']] = ['img' => $f['img'], 'title' => $f['title']];
        }

        // Delete.
        if (isset($JAX->g['d']) && $JAX->g['d']) {
            $DB->safedelete(
                'ratingniblets',
                'WHERE `id`=?',
                $DB->basicvalue($JAX->g['d']),
            );
            unset($niblets[$JAX->g['d']]);
        }

        // Insert.
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (!$JAX->p['img'] || !$JAX->p['title']) {
                $page .= $this->page->error('All fields required.');
            } else {
                $DB->safeinsert(
                    'ratingniblets',
                    [
                        'img' => $JAX->p['img'],
                        'title' => $JAX->p['title'],
                    ],
                );
                $niblets[$DB->insert_id(1)] = [
                    'img' => $JAX->p['img'],
                    'title' => $JAX->p['title'],
                ];
            }
        }

        if (isset($JAX->p['rsubmit']) && $JAX->p['rsubmit']) {
            $this->config->write([
                'ratings' => (isset($JAX->p['renabled']) ? 1 : 0)
                + (isset($JAX->p['ranon']) ? 2 : 0),
            ]);
            $page2 .= $this->page->success('Settings saved!');
        }

        $ratingsettings = $this->config->getSetting('ratings');

        $page2 .= $this->page->parseTemplate(
            'posting/post-rating-settings.html',
            [
                'ratings_anonymous' => ($ratingsettings & 2) !== 0 ? ' checked="checked"' : '',
                'ratings_enabled' => ($ratingsettings & 1) !== 0 ? ' checked="checked"' : '',
            ],
        );
        $table = $this->page->parseTemplate(
            'posting/post-rating-heading.html',
        );
        if ($niblets === []) {
            $table .= $this->page->parseTemplate(
                'posting/post-rating-empty-row.html',
            );
        } else {
            krsort($niblets);
            foreach ($niblets as $ratingId => $rating) {
                $table .= $this->page->parseTemplate(
                    'posting/post-rating-row.html',
                    [
                        'id' => $ratingId,
                        'image_url' => $JAX->blockhtml($rating['img']),
                        'title' => $JAX->blockhtml($rating['title']),
                    ],
                );
            }
        }

        $page .= $this->page->parseTemplate(
            'posting/post-rating.html',
            [
                'content' => $table,
            ],
        );
        $this->page->addContentBox('Post Rating System', $page2);
        $this->page->addContentBox('Post Rating Niblets', $page);
    }
}
