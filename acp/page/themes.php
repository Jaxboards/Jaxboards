<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\Jax;
use Jax\Request;
use Jax\TextFormatting;

use function array_key_exists;
use function closedir;
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
        private Database $database,
        private DomainDefinitions $domainDefinitions,
        private Jax $jax,
        private Request $request,
        private Page $page,
        private TextFormatting $textFormatting,
    ) {
        $this->boardPath = $this->domainDefinitions->getBoardPath();
        $this->wrappersPath = $this->boardPath . '/Wrappers/';
        $this->themesPath = $this->boardPath . '/Themes/';
    }

    public function render(): void
    {
        $this->page->sidebar([
            'create' => 'Create New Skin',
            'manage' => 'Manage Skins',
        ]);

        if ($this->request->get('editcss')) {
            $this->editcss($this->request->get('editcss'));
        } elseif (
            $this->request->get('editwrapper')
        ) {
            $this->editwrapper($this->request->get('editwrapper'));
        } elseif (
            is_numeric($this->request->get('deleteskin'))
        ) {
            $this->deleteskin($this->request->get('deleteskin'));
        } elseif (
            $this->request->get('do') === 'create'
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

        if ($this->request->get('deletewrapper')) {
            $wrapperPath = $this->wrappersPath . $this->request->get('deletewrapper') . '.html';
            if (
                !preg_match('@[^\w ]@', (string) $this->request->get('deletewrapper'))
                && file_exists($wrapperPath)
            ) {
                unlink($this->wrappersPath . $this->request->get('deletewrapper') . '.html');
                $this->page->location('?act=themes');
            } else {
                $errorwrapper
                    = 'The wrapper you are trying to delete does not exist.';
            }
        }

        if (
            $this->request->post('newwrapper') !== null
            && $this->request->post('newrapper') !== ''
        ) {
            $newWrapperPath
                = $this->wrappersPath . $this->request->post('newwrapper') . '.html';
            if (preg_match('@[^\w ]@', (string) $this->request->post('newwrapper'))) {
                $errorwrapper
                    = 'Wrapper name must consist of letters, numbers, '
                    . 'spaces, and underscore.';
            } elseif (mb_strlen((string) $this->request->post('newwrapper')) > 50) {
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

        if ($this->request->post('submit') !== null) {
            // Update wrappers/hidden status.
            if (
                $this->request->post('wrapper') !== null
                && is_array($this->request->post('wrapper'))
            ) {
                foreach ($this->request->post('wrapper') as $k => $v) {
                    if (!isset($this->request->post('hidden')[$k])) {
                        $this->request->post('hidden')[$k] = false;
                    }

                    if ($v && !in_array($v, $wrappers)) {
                        continue;
                    }

                    $this->database->safeupdate(
                        'skins',
                        [
                            'hidden' => $this->request->post('hidden')[$k] ? 1 : 0,
                            'wrapper' => $v,
                        ],
                        'WHERE `id`=?',
                        $k,
                    );
                }
            }

            if (
                is_array($this->request->post('renameskin'))
            ) {
                foreach ($this->request->post('renameskin') as $k => $v) {
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
                is_array($this->request->post('renamewrapper'))
            ) {
                foreach ($this->request->post('renamewrapper') as $wrapperName => $wrapperNewName) {
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
            if ($this->request->post('default') !== null) {
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
                    $this->request->post('default'),
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

        if ($skin && $skin['custom'] && $this->request->post('newskindata')) {
            $o = fopen($this->themesPath . $skin['title'] . '/css.css', 'w');
            fwrite($o, (string) $this->request->post('newskindata'));
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
            if ($this->request->post('newwrapper') !== null) {
                if (mb_strpos((string) $this->request->post('newwrapper'), '<!--FOOTER-->') === false) {
                    $saved = $this->page->error(
                        '&lt;!--FOOTER--&gt; must not be removed from the wrapper.',
                    );
                } else {
                    $fileHandle = fopen($wrapperf, 'w');
                    if ($fileHandle !== false) {
                        fwrite($fileHandle, (string) $this->request->post('newwrapper'));
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
        if ($this->request->post('submit') !== null) {
            $error = match (true) {
                !$this->request->post('skinname') => 'No skin name supplied!',
                (bool) preg_match('@[^\w ]@', (string) $this->request->post('skinname')) => 'Skinname must only consist of letters, numbers, and spaces.',
                mb_strlen((string) $this->request->post('skinname')) > 50 => 'Skin name must be less than 50 characters.',
                is_dir($this->themesPath . $this->request->post('skinname')) => 'A skin with that name already exists.',
                !in_array($this->request->post('wrapper'), $this->getwrappers()) => 'Invalid wrapper.',
                default => null,
            };

            if ($error === null) {
                $this->database->safeinsert(
                    'skins',
                    [
                        'custom' => 1,
                        'default' => $this->request->post('default') ? 1 : 0,
                        'hidden' => $this->request->post('hidden') ? 1 : 0,
                        'title' => $this->request->post('skinname'),
                        'wrapper' => $this->request->post('wrapper'),
                    ],
                );
                if ($this->request->post('default')) {
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
                    mkdir($this->themesPath . $this->request->post('skinname'));
                    $o = fopen($this->themesPath . $this->request->post('skinname') . '/css.css', 'w');
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
