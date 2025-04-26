<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\Jax;
use Jax\Request;
use Jax\TextFormatting;

use function array_reverse;
use function krsort;
use function rawurlencode;

use const PHP_EOL;

/**
 * @psalm-api
 */
final readonly class Posting
{
    public function __construct(
        private Config $config,
        private Database $database,
        private Jax $jax,
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

        match ($this->jax->b['do'] ?? '') {
            'emoticons' => $this->emoticons(),
            'postrating' => $this->postrating(),
            'wordfilter' => $this->wordfilter(),
            default => $this->emoticons(),
        };
    }

    public function wordfilter(): void
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
        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            $this->jax->p['badword'] = $this->textFormatting->blockhtml($this->jax->p['badword']);
            if (!$this->jax->p['badword'] || !$this->jax->p['replacement']) {
                $page .= $this->page->error('All fields required.');
            } elseif (
                isset($wordfilter[$this->jax->p['badword']])
                && $wordfilter[$this->jax->p['badword']]
            ) {
                $page .= $this->page->error(
                    "'" . $this->jax->p['badword'] . "' is already used.",
                );
            } else {
                $this->database->safeinsert(
                    'textrules',
                    [
                        'needle' => $this->jax->p['badword'],
                        'replacement' => $this->jax->p['replacement'],
                        'type' => 'badword',
                    ],
                );
                $wordfilter[$this->jax->p['badword']] = $this->jax->p['replacement'];
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
                $resultCode = $this->textFormatting->blockhtml($result);
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
                $this->database->basicvalue($_GET['d']),
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
        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            if (!$this->jax->p['emoticon'] || !$this->jax->p['image']) {
                $page .= $this->page->error('All fields required.');
            } elseif (isset($emoticons[$this->textFormatting->blockhtml($this->jax->p['emoticon'])])) {
                $page .= $this->page->error('That emoticon is already being used.');
            } else {
                $this->database->safeinsert(
                    'textrules',
                    [
                        'enabled' => 1,
                        'needle' => $this->textFormatting->blockhtml($this->jax->p['emoticon']),
                        'replacement' => $this->jax->p['image'],
                        'type' => 'emote',
                    ],
                );
                $emoticons[$this->textFormatting->blockhtml($this->jax->p['emoticon'])] = $this->jax->p['image'];
            }
        }

        if (
            isset($this->jax->p['baseset'])
            && $basesets[$this->jax->p['baseset']]
        ) {
            $this->config->write(['emotepack' => $this->jax->p['baseset']]);
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
                $smileyFile = $this->textFormatting->blockhtml($smileyFile);
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
        foreach ($this->textFormatting->getEmotePackRules($emotepack) as $emoticon => $smileyFile) {
            $emoticonRows .= $this->page->parseTemplate(
                'posting/emoticon-packs-row.html',
                [
                    'emoticon' => $emoticon,
                    'smiley_url' => '/' . $smileyFile,
                ],
            ) . PHP_EOL;
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
        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            if (!$this->jax->p['img'] || !$this->jax->p['title']) {
                $page .= $this->page->error('All fields required.');
            } else {
                $this->database->safeinsert(
                    'ratingniblets',
                    [
                        'img' => $this->jax->p['img'],
                        'title' => $this->jax->p['title'],
                    ],
                );
                $niblets[$this->database->insertId()] = [
                    'img' => $this->jax->p['img'],
                    'title' => $this->jax->p['title'],
                ];
            }
        }

        if (isset($this->jax->p['rsubmit']) && $this->jax->p['rsubmit']) {
            $this->config->write([
                'ratings' => (isset($this->jax->p['renabled']) ? 1 : 0)
                + (isset($this->jax->p['ranon']) ? 2 : 0),
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
