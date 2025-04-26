<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\Jax;
use Jax\TextFormatting;

use function array_key_exists;
use function closedir;
use function define;
use function defined;
use function dirname;
use function fclose;
use function file_exists;
use function file_get_contents;
use function fopen;
use function fwrite;
use function glob;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_numeric;
use function is_writable;
use function mb_strlen;
use function mb_strpos;
use function mkdir;
use function opendir;
use function pathinfo;
use function preg_match;
use function readdir;
use function rename;
use function unlink;

use const PATHINFO_FILENAME;
use const PHP_EOL;

/**
 * @psalm-api
 */
final readonly class Themes
{
    private string $wrappersPath;

    private string $boardPath;

    private string $themesPath;

    public function __construct(
        private Config $config,
        private Database $database,
        private DomainDefinitions $domainDefinitions,
        private Jax $jax,
        private Page $page,
        private TextFormatting $textFormatting,
    ) {
        $this->boardPath = $this->domainDefinitions->getBoardPath();
        $this->wrappersPath = $this->boardPath . 'Wrappers/';
        $this->themesPath = $this->boardPath . 'Themes/';
    }

    public function render(): void
    {
        $this->page->sidebar([
            'create' => 'Create New Skin',
            'manage' => 'Manage Skins',
        ]);

        if (isset($this->jax->g['editcss']) && $this->jax->g['editcss']) {
            $this->editcss($this->jax->g['editcss']);
        } elseif (
            isset($this->jax->g['editwrapper'])
            && $this->jax->g['editwrapper']
        ) {
            $this->editwrapper($this->jax->g['editwrapper']);
        } elseif (
            isset($this->jax->g['deleteskin'])
            && is_numeric($this->jax->g['deleteskin'])
        ) {
            $this->deleteskin($this->jax->g['deleteskin']);
        } elseif (
            isset($this->jax->g['do'])
            && $this->jax->g['do'] === 'create'
        ) {
            $this->createskin();
        } else {
            $this->showskinindex();
        }
    }

    /**
     * @return array<string>
     */
    public function getwrappers(): array
    {
        $wrappers = [];
        $o = opendir($this->wrappersPath);
        while ($f = readdir($o)) {
            if ($f === '.') {
                continue;
            }

            if ($f === '..') {
                continue;
            }

            $wrappers[] = pathinfo($f, PATHINFO_FILENAME);
        }

        closedir($o);

        return $wrappers;
    }

    public function showskinindex(): void
    {
        $errorskins = '';
        $errorwrapper = '';

        if (
            isset($this->jax->g['deletewrapper'])
            && $this->jax->g['deletewrapper']
        ) {
            $wrapperPath = $this->wrappersPath . $this->jax->g['deletewrapper'] . '.html';
            if (
                !preg_match('@[^\w ]@', (string) $this->jax->g['deletewrapper'])
                && file_exists($wrapperPath)
            ) {
                unlink($this->wrappersPath . $this->jax->g['deletewrapper'] . '.html');
                $this->page->location('?act=themes');
            } else {
                $errorwrapper
                    = 'The wrapper you are trying to delete does not exist.';
            }
        }

        if (
            isset($this->jax->p['newwrapper'])
            && $this->jax->p['newwrapper']
        ) {
            $newWrapperPath
                = $this->wrappersPath . $this->jax->p['newwrapper'] . '.html';
            if (preg_match('@[^\w ]@', (string) $this->jax->p['newwrapper'])) {
                $errorwrapper
                    = 'Wrapper name must consist of letters, numbers, '
                    . 'spaces, and underscore.';
            } elseif (mb_strlen((string) $this->jax->p['newwrapper']) > 50) {
                $errorwrapper = 'Wrapper name must be less than 50 characters.';
            } elseif (file_exists($newWrapperPath)) {
                $errorwrapper = 'That wrapper already exists.';
            } elseif (!is_writable(dirname($newWrapperPath))) {
                $errorwrappre = 'Wrapper directory is not writable.';
            } else {
                $o = fopen($newWrapperPath, 'w');
                if ($o !== false) {
                    fwrite(
                        $o,
                        file_get_contents($this->domainDefinitions->getDefaultThemePath() . '/wrappers.html'),
                    );
                    fclose($o);
                } else {
                    $errorwrapper = 'Wrapper could not be created.';
                }
            }
        }

        // Make an array of wrappers.
        $wrappers = $this->getwrappers();

        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            // Update wrappers/hidden status.
            if (!isset($this->jax->p['hidden'])) {
                $this->jax->p['hidden'] = [];
            }

            if (
                array_key_exists('wrapper', $this->jax->p)
                && is_array($this->jax->p['wrapper'])
            ) {
                foreach ($this->jax->p['wrapper'] as $k => $v) {
                    if (!isset($this->jax->p['hidden'][$k])) {
                        $this->jax->p['hidden'][$k] = false;
                    }

                    if ($v && !in_array($v, $wrappers)) {
                        continue;
                    }

                    $this->database->safeupdate(
                        'skins',
                        [
                            'hidden' => $this->jax->p['hidden'][$k] ? 1 : 0,
                            'wrapper' => $v,
                        ],
                        'WHERE `id`=?',
                        $k,
                    );
                }
            }

            if (
                isset($this->jax->p['renameskin'])
                && is_array($this->jax->p['renameskin'])
            ) {
                foreach ($this->jax->p['renameskin'] as $k => $v) {
                    if ($k === $v) {
                        continue;
                    }

                    if (preg_match('@[^\w ]@', $k)) {
                        continue;
                    }

                    if (!is_dir($this->themesPath . $k)) {
                        continue;
                    }

                    if (
                        preg_match('@[^\w ]@', (string) $v)
                        || mb_strlen((string) $v) > 50
                    ) {
                        $errorskins = 'Skin name must consist of letters, numbers, spaces, and underscore, and be under 50 characters long.';
                    } elseif (is_dir($this->themesPath . $v)) {
                        $errorskins = 'That skin name is already being used.';
                    } else {
                        $this->database->safeupdate(
                            'skins',
                            [
                                'title' => $v,
                            ],
                            'WHERE `title`=? AND `custom`=1',
                            $this->database->basicvalue($k),
                        );
                        rename($this->themesPath . $k, $this->themesPath . $v);
                    }
                }
            }

            if (
                isset($this->jax->p['renamewrapper'])
                && is_array($this->jax->p['renamewrapper'])
            ) {
                foreach ($this->jax->p['renamewrapper'] as $wrapperName => $wrapperNewName) {
                    if ($wrapperName === $wrapperNewName) {
                        continue;
                    }

                    if (preg_match('@[^\w ]@', $wrapperName)) {
                        continue;
                    }

                    if (!is_file($this->wrappersPath . $wrapperName . '.html')) {
                        continue;
                    }

                    if (
                        preg_match('@[^\w ]@', (string) $wrapperNewName)
                        || mb_strlen((string) $wrapperNewName) > 50
                    ) {
                        $errorwrapper = 'Wrapper name must consist of letters, numbers, spaces, and underscore, and be
                            under 50 characters long.';
                    } elseif (is_file($this->wrappersPath . $wrapperNewName . '.html')) {
                        $errorwrapper = 'That wrapper name is already being used.';
                    } else {
                        $this->database->safeupdate(
                            'skins',
                            [
                                'wrapper' => $wrapperNewName,
                            ],
                            'WHERE `wrapper`=? AND `custom`=1',
                            $this->database->basicvalue($wrapperName),
                        );
                        rename(
                            $this->wrappersPath . $wrapperName . '.html',
                            $this->wrappersPath . $wrapperNewName . '.html',
                        );
                    }

                    $wrappers = $this->getwrappers();
                }
            }

            // Set default.
            if (isset($this->jax->p['default'])) {
                $this->database->safeupdate(
                    'skins',
                    [
                        'default' => 0,
                    ],
                );
                $this->database->safeupdate(
                    'skins',
                    [
                        'default' => 1,
                    ],
                    'WHERE `id`=?',
                    $this->jax->p['default'],
                );
            }
        }

        $result = $this->database->safeselect(
            [
                'id',
                '`using`',
                'title',
                'custom',
                'wrapper',
                '`default`',
                'hidden',
            ],
            'skins',
            'ORDER BY title ASC',
        );
        $usedwrappers = [];
        $skins = '';
        while ($f = $this->database->arow($result)) {
            $wrapperOptions = '';
            foreach ($wrappers as $wrapper) {
                $wrapperOptions .= $this->page->parseTemplate(
                    'select-option.html',
                    [
                        'label' => $wrapper,
                        'selected' => $wrapper === $f['wrapper']
                        ? 'selected="selected"' : '',
                        'value' => $wrapper,
                    ],
                ) . PHP_EOL;
            }

            $skins .= $this->page->parseTemplate(
                'themes/show-skin-index-css-row.html',
                [
                    'custom' => $f['custom']
                        ? $this->page->parseTemplate(
                            'themes/show-skin-index-css-row-custom.html',
                        ) : '',
                    'default_checked' => $f['default'] ? "checked='checked'" : '',
                    'default_option' => $f['custom'] ? '' : $this->page->parseTemplate(
                        'select-option.html',
                        [
                            'label' => 'Skin Default',
                            'selected' => '',
                            'value' => '',
                        ],
                    ),
                    'delete' => $f['custom'] ? $this->page->parseTemplate(
                        'themes/show-skin-index-css-row-delete.html',
                        [
                            'id' => $f['id'],
                        ],
                    ) : '',
                    'hidden_checked' => $f['hidden'] ? 'checked="checked"' : '',
                    'id' => $f['id'],
                    'title' => $f['title'],
                    'view_or_edit' => $f['custom'] ? 'Edit' : 'View',
                    'wrapper_options' => $wrapperOptions,
                ],
            ) . PHP_EOL;
            $usedwrappers[] = $f['wrapper'];
        }

        $skins = ($errorskins !== '' && $errorskins !== '0' ? $this->page->error($errorskins) : '')
            . $this->page->parseTemplate(
                'themes/show-skin-index-css.html',
                [
                    'content' => $skins,
                ],
            );
        $this->page->addContentBox('Themes', $skins);

        $wrap = '';
        foreach ($wrappers as $wrapper) {
            $wrap .= $this->page->parseTemplate(
                'themes/show-skin-index-wrapper-row.html',
                [
                    'delete' => in_array($wrapper, $usedwrappers) ? 'In use'
                    : $this->page->parseTemplate(
                        'themes/show-skin-index-wrapper-row-delete.html',
                        [
                            'title' => $wrapper,
                        ],
                    ),
                    'title' => $wrapper,
                ],
            ) . PHP_EOL;
        }

        $wrap = $this->page->parseTemplate(
            'themes/show-skin-index-wrapper.html',
            [
                'content' => $wrap,
            ],
        );
        $this->page->addContentBox(
            'Wrappers',
            ($errorwrapper !== '' && $errorwrapper !== '0' ? $this->page->error($errorwrapper) : '') . $wrap,
        );
    }

    public function editcss($id): void
    {
        $result = $this->database->safeselect(
            [
                'id',
                '`using`',
                'title',
                'custom',
                'wrapper',
                '`default`',
                'hidden',
            ],
            'skins',
            'WHERE `id`=?',
            $id,
        );
        $skin = $this->database->arow($result);
        $this->database->disposeresult($result);
        if (!isset($this->jax->p['newskindata'])) {
            $this->jax->p['newskindata'] = false;
        }

        if ($skin && $skin['custom'] && $this->jax->p['newskindata']) {
            $o = fopen($this->themesPath . $skin['title'] . '/css.css', 'w');
            fwrite($o, (string) $this->jax->p['newskindata']);
            fclose($o);
        }

        $this->page->addContentBox(
            ($skin['custom'] ? 'Editing' : 'Viewing') . ' Skin: ' . $skin['title'],
            $this->page->parseTemplate(
                'themes/edit-css.html',
                [
                    'content' => $this->textFormatting->blockhtml(
                        file_get_contents(
                            (
                                $skin['custom']
                                ? $this->themesPath : $this->domainDefinitions->getServiceThemePath()
                            ) . "/{$skin['title']}/css.css",
                        ),
                    ),
                    'save' => $skin['custom'] ? $this->page->parseTemplate(
                        'save-changes.html',
                    ) : '',
                ],
            ),
        );
    }

    public function editwrapper($wrapper): void
    {
        $saved = '';
        $wrapperf = $this->wrappersPath . $wrapper . '.html';
        if (preg_match('@[^ \w]@', (string) $wrapper) && !is_file($wrapperf)) {
            $this->page->addContentBox(
                'Error',
                "The theme you're trying to edit does not exist.",
            );
        } else {
            if (isset($this->jax->p['newwrapper'])) {
                if (mb_strpos((string) $this->jax->p['newwrapper'], '<!--FOOTER-->') === false) {
                    $saved = $this->page->error(
                        '&lt;!--FOOTER--&gt; must not be removed from the wrapper.',
                    );
                } else {
                    $fileHandle = fopen($wrapperf, 'w');
                    if ($fileHandle !== false) {
                        fwrite($fileHandle, (string) $this->jax->p['newwrapper']);
                        fclose($fileHandle);
                        $saved = $this->page->success('Wrapper saved successfully.');
                    } else {
                        $saved = $this->page->error('Error saving wrapper.');
                    }
                }
            }

            $this->page->addContentBox(
                "Editing Wrapper: {$wrapper}",
                $saved . $this->page->parseTemplate(
                    'themes/edit-wrapper.html',
                    [
                        'content' => $this->textFormatting->blockhtml(file_get_contents($wrapperf)),
                    ],
                ),
            );
        }
    }

    public function createskin(): void
    {
        $page = '';
        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            $error = match (true) {
                !isset($this->jax->p['skinname']) || !$this->jax->p['skinname'] => 'No skin name supplied!',
                (bool) preg_match('@[^\w ]@', (string) $this->jax->p['skinname']) => 'Skinname must only consist of letters, numbers, and spaces.',
                mb_strlen((string) $this->jax->p['skinname']) > 50 => 'Skin name must be less than 50 characters.',
                is_dir($this->themesPath . $this->jax->p['skinname']) => 'A skin with that name already exists.',
                !in_array($this->jax->p['wrapper'], $this->getwrappers()) => 'Invalid wrapper.',
                default => null,
            };

            if ($error === null) {
                if (!isset($this->jax->p['hidden'])) {
                    $this->jax->p['hidden'] = false;
                }

                if (!isset($this->jax->p['default'])) {
                    $this->jax->p['default'] = false;
                }

                $this->database->safeinsert(
                    'skins',
                    [
                        'custom' => 1,
                        'default' => $this->jax->p['default'] ? 1 : 0,
                        'hidden' => $this->jax->p['hidden'] ? 1 : 0,
                        'title' => $this->jax->p['skinname'],
                        'wrapper' => $this->jax->p['wrapper'],
                    ],
                );
                if ($this->jax->p['default']) {
                    $this->database->safeupdate(
                        'skins',
                        [
                            'default' => 0,
                        ],
                        'WHERE `id`!=?',
                        $this->database->insertId(),
                    );
                }

                if (
                    !is_dir($this->boardPath . 'Themes')
                    && is_writable($this->boardPath)
                ) {
                    mkdir($this->boardPath . 'Themes');
                }

                if (is_dir($this->boardPath . 'Themes')) {
                    mkdir($this->boardPath . 'Themes');
                    mkdir($this->themesPath . $this->jax->p['skinname']);
                    $o = fopen($this->themesPath . $this->jax->p['skinname'] . '/css.css', 'w');
                    fwrite(
                        $o,
                        file_get_contents(
                            $this->domainDefinitions->getDefaultThemePath() . '/css.css',
                        ),
                    );
                    fclose($o);
                }

                $this->page->location('?act=themes');
            }

            if ($error !== null) {
                $page = $this->page->error($error);
            }
        }

        $wrapperOptions = '';
        foreach ($this->getwrappers() as $wrapper) {
            $wrapperOptions .= $this->page->parseTemplate(
                'select-option.html',
                [
                    'label' => $wrapper,
                    'selected' => '',
                    'value' => $wrapper,
                ],
            ) . PHP_EOL;
        }

        $page .= $this->page->parseTemplate(
            'themes/create-skin.html',
            [
                'wrapper_options' => $wrapperOptions,
            ],
        );
        $this->page->addContentBox('Create New Skin', $page);
    }

    public function deleteskin($id): void
    {
        $result = $this->database->safeselect(
            '`id`,`using`,`title`,`custom`,`wrapper`,`default`,`hidden`',
            'skins',
            'WHERE `id`=?',
            $id,
        );
        $skin = $this->database->arow($result);
        $this->database->disposeresult($result);
        $skindir = $this->themesPath . $skin['title'];
        if (is_dir($skindir)) {
            foreach (glob($skindir . '/*') as $v) {
                unlink($v);
            }

            $this->jax->rmdir($skindir);
        }

        $this->database->safedelete(
            'skins',
            'WHERE `id`=?',
            $id,
        );
        // Make a random skin default if it's the default.
        if ($skin['default']) {
            $this->database->safeupdate(
                'skins',
                [
                    'default' => 1,
                ],
                'LIMIT 1',
            );
        }

        $this->page->location('?act=themes');
    }
}
