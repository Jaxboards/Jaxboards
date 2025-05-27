<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\Models\RatingNiblet;
use Jax\Models\TextRule;
use Jax\Request;
use Jax\TextFormatting;

use function _\keyBy;
use function array_key_exists;
use function array_reverse;
use function krsort;
use function rawurlencode;

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
            'postRating' => 'Post Rating',
            'wordfilter' => 'Word Filter',
        ]);

        match ($this->request->both('do')) {
            'emoticons' => $this->emoticons(),
            'postRating' => $this->postRating(),
            'wordfilter' => $this->wordfilter(),
            default => $this->emoticons(),
        };
    }

    private function wordfilter(): void
    {
        $page = '';
        $badWords = keyBy(
            TextRule::selectMany($this->database, "WHERE `type`='badword'"),
            static fn($textRule) => $textRule->needle,
        );

        // Delete.
        $delete = $this->request->asString->get('d');
        if (array_key_exists($delete, $badWords)) {
            $badWords[$delete]->delete($this->database);
            unset($badWords[$delete]);
        }

        // Insert.
        if ($this->request->post('submit') !== null) {
            $badword = $this->textFormatting->blockhtml($this->request->asString->post('badword') ?? '');
            $replacement = $this->request->asString->post('replacement');
            if (!$badword || !$replacement) {
                $page .= $this->page->error('All fields required.');
            } elseif (
                isset($badWords[$badword])
                && $badWords[$badword]
            ) {
                $page .= $this->page->error(
                    "'" . $badword . "' is already used.",
                );
            } else {
                $textRule = new TextRule();
                $textRule->needle = $badword;
                $textRule->replacement = $replacement;
                $textRule->type = 'badword';
                $textRule->enabled = 1;
                $textRule->insert($this->database);
                $badWords[$badword] = $textRule;
            }
        }

        if ($badWords === []) {
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
            foreach (array_reverse($badWords, true) as $textRule) {
                $table .= $this->page->parseTemplate(
                    'posting/word-filter-row.html',
                    [
                        'filter' => $textRule->needle,
                        'filter_url_encoded' => rawurlencode((string) $textRule->needle),
                        'result_code' => $this->textFormatting->blockhtml($textRule->replacement),
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
        $delete = $this->request->asString->get('d');
        if ($delete) {
            $this->database->delete(
                'textrules',
                "WHERE `type`='emote' AND `needle`=?",
                $delete,
            );
        }

        // Select emoticons.
        $emoticons = keyBy(
            TextRule::selectMany($this->database, "WHERE `type`='emote'"),
            static fn($textRule) => $textRule->needle,
        );

        // Insert emoticon.
        if ($this->request->post('submit') !== null) {
            $emoticonInput = $this->request->asString->post('emoticon');
            $emoticonNoHTML = $this->textFormatting->blockhtml($emoticonInput);
            $imageInput = $this->request->asString->post('image');
            if (!$emoticonInput || !$imageInput) {
                $page .= $this->page->error('All fields required.');
            } elseif (array_key_exists($emoticonNoHTML, $emoticons)) {
                $page .= $this->page->error('That emoticon is already being used.');
            } else {
                $textRule = new TextRule();
                $textRule->enabled = 1;
                $textRule->needle = $emoticonNoHTML;
                $textRule->replacement = $imageInput;
                $textRule->type = 'emote';
                $textRule->insert($this->database);
                $emoticons[$emoticonNoHTML] = $textRule;
            }
        }

        $baseset = $this->request->asString->post('baseset');
        if ($baseset !== null && array_key_exists($baseset, $basesets)) {
            $this->config->write(['emotepack' => $baseset]);
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

            foreach ($emoticons as $emoticon) {
                $table .= $this->page->parseTemplate(
                    'posting/emoticon-row.html',
                    [
                        'emoticon' => $emoticon->needle,
                        'emoticon_url_encoded' => rawurlencode((string) $emoticon->needle),
                        'smiley_url' => $this->textFormatting->blockhtml($emoticon->replacement),
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
        foreach ($this->textFormatting->rules->getEmotePack($emotepack) as $emoticon => $smileyFile) {
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

    private function postRating(): void
    {
        $page = '';
        $page2 = '';
        $niblets = keyBy(
            RatingNiblet::selectMany($this->database, 'ORDER BY `id` DESC'),
            static fn($niblet) => $niblet->id,
        );

        // Delete.
        $delete = (int) $this->request->asString->get('d');
        if ($delete !== 0) {
            $niblets[$delete]->delete($this->database);
            unset($niblets[$delete]);
        }

        // Insert.
        if ($this->request->post('submit') !== null) {
            $img = $this->request->asString->post('img');
            $title = $this->request->asString->post('title');
            if (!$img || !$title) {
                $page .= $this->page->error('All fields required.');
            } else {
                $ratingNiblet = new RatingNiblet();
                $ratingNiblet->img = $img;
                $ratingNiblet->title = $title;
                $ratingNiblet->insert($this->database);

                $niblets[$ratingNiblet->id] = $ratingNiblet;
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
            foreach ($niblets as $niblet) {
                $table .= $this->page->parseTemplate(
                    'posting/post-rating-row.html',
                    [
                        'id' => $niblet->id,
                        'image_url' => $this->textFormatting->blockhtml($niblet->img),
                        'title' => $this->textFormatting->blockhtml($niblet->title),
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
