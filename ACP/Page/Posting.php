<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\Request;
use Jax\TextFormatting;

use function array_reverse;
use function krsort;
use function rawurlencode;

use const PHP_EOL;

final readonly class Posting
{
    public function __construct(
        private Config $config,
        private Database $database,
        private Page $page,
        private Request $request,
        private TextFormatting $textFormatting,
    ) {}

    public function render(): void
    {
        $this->page->sidebar([
            'emoticons' => 'Emoticons',
            'postrating' => 'Post Rating',
            'wordfilter' => 'Word Filter',
        ]);

        match ($this->request->both('do')) {
            'emoticons' => $this->emoticons(),
            'postrating' => $this->postrating(),
            'wordfilter' => $this->wordfilter(),
            default => $this->emoticons(),
        };
    }

    private function wordfilter(): void
    {
        $page = '';
        $wordfilter = [];
        $result = $this->database->safeselect(
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
        while ($f = $this->database->arow($result)) {
            $wordfilter[$f['needle']] = $f['replacement'];
        }

        // Delete.
        if ($this->request->get('d')) {
            $this->database->safedelete(
                'textrules',
                "WHERE `type`='badword' AND `needle`=?",
                $this->database->basicvalue($this->request->get('d')),
            );
            unset($wordfilter[$this->request->get('d')]);
        }

        // Insert.
        if ($this->request->post('submit') !== null) {
            $badword = $this->textFormatting->blockhtml($this->request->post('badword'));
            if (!$badword || !$this->request->post('replacement')) {
                $page .= $this->page->error('All fields required.');
            } elseif (
                isset($wordfilter[$badword])
                && $wordfilter[$badword]
            ) {
                $page .= $this->page->error(
                    "'" . $badword . "' is already used.",
                );
            } else {
                $this->database->safeinsert(
                    'textrules',
                    [
                        'needle' => $badword,
                        'replacement' => $this->request->post('replacement'),
                        'type' => 'badword',
                    ],
                );
                $wordfilter[$badword] = $this->request->post('replacement');
            }
        }

        if ($wordfilter === []) {
            $table = $this->page->parseTemplate(
                'posting/word-filter-empty.html',
            ) . $this->page->parseTemplate(
                'posting/word-filter-submit-row.html',
            );
        } else {
            $table = $this->page->parseTemplate(
                'posting/word-filter-heading.html',
            ) . $this->page->parseTemplate(
                'posting/word-filter-submit-row.html',
            );
            $currentFilters = array_reverse($wordfilter, true);
            foreach ($currentFilters as $filter => $result) {
                $resultCode = $this->textFormatting->blockhtml($result);
                $filterUrlEncoded = rawurlencode($filter);
                $table .= $this->page->parseTemplate(
                    'posting/word-filter-row.html',
                    [
                        'filter' => $filter,
                        'filter_url_encoded' => $filterUrlEncoded,
                        'result_code' => $resultCode,
                    ],
                );
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

    private function emoticons(): void
    {

        $basesets = [
            '' => 'None',
            'keshaemotes' => "Kesha's pack",
            'ploadpack' => "Pload's pack",
        ];
        $page = '';
        $emoticons = [];
        // Delete emoticon.
        if ($this->request->get('d')) {
            $this->database->safedelete(
                'textrules',
                "WHERE `type`='emote' AND `needle`=?",
                $this->database->basicvalue($this->request->get('d')),
            );
        }

        // Select emoticons.
        $result = $this->database->safeselect(
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
        while ($f = $this->database->arow($result)) {
            $emoticons[$f['needle']] = $f['replacement'];
        }

        // Insert emoticon.
        if ($this->request->post('submit') !== null) {
            if (
                !$this->request->post('emoticon')
                || !$this->request->post('image')
            ) {
                $page .= $this->page->error('All fields required.');
            } elseif (isset($emoticons[$this->textFormatting->blockhtml($this->request->post('emoticon'))])) {
                $page .= $this->page->error('That emoticon is already being used.');
            } else {
                $this->database->safeinsert(
                    'textrules',
                    [
                        'enabled' => 1,
                        'needle' => $this->textFormatting->blockhtml($this->request->post('emoticon')),
                        'replacement' => $this->request->post('image'),
                        'type' => 'emote',
                    ],
                );
                $emoticons[$this->textFormatting->blockhtml($this->request->post('emoticon'))] = $this->request->post('image');
            }
        }

        if (
            $this->request->post('baseset') !== null
            && $basesets[$this->request->post('baseset')]
        ) {
            $this->config->write(['emotepack' => $this->request->post('baseset')]);
        }

        if ($emoticons === []) {
            $table = $this->page->parseTemplate(
                'posting/emoticon-heading.html',
            ) . $this->page->parseTemplate(
                'posting/emoticon-submit-row.html',
            ) . $this->page->parseTemplate(
                'posting/emoticon-empty-row.html',
            );
        } else {
            $table = $this->page->parseTemplate(
                'posting/emoticon-heading.html',
            ) . $this->page->parseTemplate(
                'posting/emoticon-submit-row.html',
            );
            $emoticons = array_reverse($emoticons, true);

            foreach ($emoticons as $emoticon => $smileyFile) {
                $smileyFile = $this->textFormatting->blockhtml($smileyFile);
                $emoticonUrlEncoded = rawurlencode($emoticon);
                $table .= $this->page->parseTemplate(
                    'posting/emoticon-row.html',
                    [
                        'emoticon' => $emoticon,
                        'emoticon_url_encoded' => rawurlencode($emoticon),
                        'smiley_url' => $smileyFile,
                    ],
                );
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
        foreach ($this->textFormatting->getEmotePackRules($emotepack) as $emoticon => $smileyFile) {
            $emoticonRows .= $this->page->parseTemplate(
                'posting/emoticon-packs-row.html',
                [
                    'emoticon' => $emoticon,
                    'smiley_url' => '/' . $smileyFile,
                ],
            );
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

    private function postrating(): void
    {
        $page = '';
        $page2 = '';
        $niblets = [];
        $result = $this->database->safeselect(
            ['id', 'img', 'title'],
            'ratingniblets',
            'ORDER BY `id` DESC',
        );
        while ($f = $this->database->arow($result)) {
            $niblets[$f['id']] = ['img' => $f['img'], 'title' => $f['title']];
        }

        // Delete.
        if ($this->request->get('d')) {
            $this->database->safedelete(
                'ratingniblets',
                'WHERE `id`=?',
                $this->database->basicvalue($this->request->get('d')),
            );
            unset($niblets[$this->request->get('d')]);
        }

        // Insert.
        if ($this->request->post('submit') !== null) {
            if (
                !$this->request->post('img')
                || !$this->request->post('title')
            ) {
                $page .= $this->page->error('All fields required.');
            } else {
                $this->database->safeinsert(
                    'ratingniblets',
                    [
                        'img' => $this->request->post('img'),
                        'title' => $this->request->post('title'),
                    ],
                );
                $niblets[$this->database->insertId()] = [
                    'img' => $this->request->post('img'),
                    'title' => $this->request->post('title'),
                ];
            }
        }

        if ($this->request->post('rsubmit') !== null) {
            $this->config->write([
                'ratings' => ($this->request->post('renabled') !== null ? 1 : 0)
                + ($this->request->post('ranon') !== null ? 2 : 0),
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
                        'image_url' => $this->textFormatting->blockhtml($rating['img']),
                        'title' => $this->textFormatting->blockhtml($rating['title']),
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
