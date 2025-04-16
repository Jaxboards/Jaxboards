<?php

declare(strict_types=1);

if (!defined(INACP)) {
    exit;
}

final class Posting
{
    public function route()
    {
        global $JAX, $PAGE;

        $links = [
            'emoticons' => 'Emoticons',
            'postrating' => 'Post Rating',
            'wordfilter' => 'Word Filter',
        ];
        $sidebarLinks = '';
        foreach ($links as $do => $title) {
            $sidebarLinks .= $PAGE->parseTemplate(
                'sidebar-list-link.html',
                [
                    'title' => $title,
                    'url' => '?act=posting&do=' . $do,
                ],
            ) . PHP_EOL;
        }

        $PAGE->sidebar(
            $PAGE->parseTemplate(
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
        global $PAGE,$JAX,$DB;
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
                $page .= $PAGE->error('All fields required.');
            } elseif (
                isset($wordfilter[$JAX->p['badword']])
                && $wordfilter[$JAX->p['badword']]
            ) {
                $page .= $PAGE->error(
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
            $table = $PAGE->parseTemplate(
                'posting/word-filter-empty.html',
            ) . PHP_EOL . $PAGE->parseTemplate(
                'posting/word-filter-submit-row.html',
            );
        } else {
            $table = $PAGE->parseTemplate(
                'posting/word-filter-heading.html',
            ) . PHP_EOL . $PAGE->parseTemplate(
                'posting/word-filter-submit-row.html',
            );
            $currentFilters = array_reverse($wordfilter, true);
            foreach ($currentFilters as $filter => $result) {
                $resultCode = $JAX->blockhtml($result);
                $filterUrlEncoded = rawurlencode($filter);
                $table .= $PAGE->parseTemplate(
                    'posting/word-filter-row.html',
                    [
                        'filter' => $filter,
                        'filter_url_encoded' => $filterUrlEncoded,
                        'result_code' => $resultCode,
                    ],
                ) . PHP_EOL;
            }
        }

        $page .= $PAGE->parseTemplate(
            'posting/word-filter.html',
            [
                'content' => $table,
            ],
        );

        $PAGE->addContentBox('Word Filter', $page);
    }

    public function emoticons(): void
    {
        global $PAGE,$JAX,$DB;

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
                $page .= $PAGE->error('All fields required.');
            } elseif (isset($emoticons[$JAX->blockhtml($JAX->p['emoticon'])])) {
                $page .= $PAGE->error('That emoticon is already being used.');
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
            $PAGE->writeCFG(['emotepack' => $JAX->p['baseset']]);
        }

        if ($emoticons === []) {
            $table = $PAGE->parseTemplate(
                'posting/emoticon-heading.html',
            ) . PHP_EOL . $PAGE->parseTemplate(
                'posting/emoticon-submit-row.html',
            ) . PHP_EOL . $PAGE->parseTemplate(
                'posting/emoticon-empty-row.html',
            );
        } else {
            $table = $PAGE->parseTemplate(
                'posting/emoticon-heading.html',
            ) . PHP_EOL . $PAGE->parseTemplate(
                'posting/emoticon-submit-row.html',
            );
            $emoticons = array_reverse($emoticons, true);

            foreach ($emoticons as $emoticon => $smileyFile) {
                $smileyFile = $JAX->blockhtml($smileyFile);
                $emoticonUrlEncoded = rawurlencode($emoticon);
                $table .= $PAGE->parseTemplate(
                    'posting/emoticon-row.html',
                    [
                        'emoticon' => $emoticon,
                        'emoticon_url_encoded' => rawurlencode($emoticon),
                        'smiley_url' => $smileyFile,
                    ],
                ) . PHP_EOL;
            }
        }

        $page .= $PAGE->parseTemplate(
            'posting/emoticons.html',
            [
                'content' => $table,
            ],
        );

        $PAGE->addContentBox('Custom Emoticons', $page);

        $emoticonpath = $PAGE->getCFGSetting('emotepack');
        $emoticonsetting = $emoticonpath;
        $emoticonPackOptions = '';
        foreach ($basesets as $packId => $packName) {
            $emoticonPackOptions .= $PAGE->parseTemplate(
                'select-option.html',
                [
                    'label' => $packName,
                    'selected' => $emoticonsetting === $packId
                ? ' selected="selected"' : '',
                    'value' => $packId,
                ],
            );
        }

        include JAXBOARDS_ROOT . "/emoticons/{$emoticonpath}/rules.php";
        $emoticonRows = '';
        foreach ($rules as $emoticon => $smileyFile) {
            $emoticonRows .= $PAGE->parseTemplate(
                'posting/emoticon-packs-row.html',
                [
                    'emoticon' => $emoticon,
                    'smiley_url' => "/emoticons/{$emoticonpath}/{$smileyFile}",
                ],
            ) . PHP_EOL;
        }

        $page = $PAGE->parseTemplate(
            'posting/emoticon-packs.html',
            [
                'emoticon_packs' => $emoticonPackOptions,
                'emoticon_rows' => $emoticonRows,
            ],
        );

        $PAGE->addContentBox('Base Emoticon Set', $page);
    }

    public function postrating(): void
    {
        global $PAGE,$JAX,$DB;
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
                $page .= $PAGE->error('All fields required.');
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
            $cfg = [
                'ratings' => (isset($JAX->p['renabled']) ? 1 : 0)
                + (isset($JAX->p['ranon']) ? 2 : 0),
            ];
            $PAGE->writeCFG($cfg);
            $page2 .= $PAGE->success('Settings saved!');
        }

        $ratingsettings = $PAGE->getCFGSetting('ratings');

        $page2 .= $PAGE->parseTemplate(
            'posting/post-rating-settings.html',
            [
                'ratings_anonymous' => ($ratingsettings & 2) !== 0 ? ' checked="checked"' : '',
                'ratings_enabled' => ($ratingsettings & 1) !== 0 ? ' checked="checked"' : '',
            ],
        );
        $table = $PAGE->parseTemplate(
            'posting/post-rating-heading.html',
        );
        if ($niblets === []) {
            $table .= $PAGE->parseTemplate(
                'posting/post-rating-empty-row.html',
            );
        } else {
            krsort($niblets);
            foreach ($niblets as $ratingId => $rating) {
                $table .= $PAGE->parseTemplate(
                    'posting/post-rating-row.html',
                    [
                        'id' => $ratingId,
                        'image_url' => $JAX->blockhtml($rating['img']),
                        'title' => $JAX->blockhtml($rating['title']),
                    ],
                );
            }
        }

        $page .= $PAGE->parseTemplate(
            'posting/post-rating.html',
            [
                'content' => $table,
            ],
        );
        $PAGE->addContentBox('Post Rating System', $page2);
        $PAGE->addContentBox('Post Rating Niblets', $page);
    }
}
