<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\Jax;
use Jax\Request;
use Jax\TextFormatting;

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
            $this->editCSS($this->request->get('editcss'));
        } elseif (
            $this->request->get('editwrapper')
        ) {
            $this->editWrapper($this->request->get('editwrapper'));
        } elseif (
            is_numeric($this->request->get('deleteskin'))
        ) {
            $this->deleteSkin($this->request->get('deleteskin'));
        } elseif (
            $this->request->get('do') === 'create'
        ) {
            $this->createSkin();
        } else {
            $this->showSkinIndex();
        }
    }

    /**
     * Get skins from database.
     *
     * @return array<string,mixed>
     */
    private function getSkins(): array
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
            'ORDER BY title ASC',
        );

        return $this->database->arows($result);
    }

    /**
     * @return array<string>
     */
    private function getWrappers(): array
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

    /**
     * Delete a wrapper. Returns error string upon failure.
     *
     * @param mixed $wrapper
     */
    private function deleteWrapper($wrapper): ?string
    {
        $wrapperPath = $this->wrappersPath . $wrapper . '.html';
        if (
            !preg_match('@[^\w ]@', (string) $wrapper)
            && file_exists($wrapperPath)
        ) {
            unlink($this->wrappersPath . $wrapper . '.html');
            $this->page->location('?act=Themes');

            return null;
        }

        return 'The wrapper you are trying to delete does not exist.';
    }

    /**
     * Create a wrapper. Returns error string upon failure.
     *
     * @param mixed $wrapper
     */
    private function createWrapper($wrapper): ?string
    {
        $newWrapperPath
            = $this->wrappersPath . $wrapper . '.html';
        if (preg_match('@[^\w ]@', (string) $wrapper)) {
            return 'Wrapper name must consist of letters, numbers, spaces, and underscore.';
        }
        if (mb_strlen((string) $wrapper) > 50) {
            return 'Wrapper name must be less than 50 characters.';
        }
        if (file_exists($newWrapperPath)) {
            return 'That wrapper already exists.';
        }
        if (!is_writable(dirname($newWrapperPath))) {
            return 'Wrapper directory is not writable.';
        }
        $o = fopen($newWrapperPath, 'w');
        if ($o !== false) {
            fwrite(
                $o,
                file_get_contents($this->domainDefinitions->getDefaultThemePath() . '/wrappers.html'),
            );
            fclose($o);

            return null;
        }

        return 'Wrapper could not be created.';


    }

    /**
     * Update wrapper properties. Returns error string upon failure.
     *
     * @param mixed $wrappers
     */
    private function updateWrappers($wrappers): ?string
    {
        foreach ($wrappers as $wrapperId => $wrapperName) {
            if ($wrapperName && !in_array($wrapperName, $wrappers)) {
                continue;
            }

            $hidden = $this->request->post('hidden') ?? [];
            $this->database->safeupdate(
                'skins',
                [
                    'hidden' => array_key_exists($wrapperId, $hidden) ? 1 : 0,
                    'wrapper' => $wrapperName,
                ],
                'WHERE `id`=?',
                $wrapperId,
            );
        }

        return null;
    }

    /**
     * Renames skins. Returns error string upon failure.
     *
     * @param array<string,string> $renameSkins
     */
    private function renameSkin(array $renameSkins): ?string
    {
        foreach ($renameSkins as $oldName => $newName) {
            if ($oldName === $newName) {
                continue;
            }

            if (preg_match('@[^\w ]@', $oldName)) {
                continue;
            }

            if (!is_dir($this->themesPath . $oldName)) {
                continue;
            }

            if (
                preg_match('@[^\w ]@', (string) $newName)
                || mb_strlen((string) $newName) > 50
            ) {
                return 'Skin name must consist of letters, numbers, spaces, and underscore, and be under 50 characters long.';
            }
            if (is_dir($this->themesPath . $newName)) {
                return 'That skin name is already being used.';
            }

            $this->database->safeupdate(
                'skins',
                [
                    'title' => $newName,
                ],
                'WHERE `title`=? AND `custom`=1',
                $this->database->basicvalue($oldName),
            );
            rename($this->themesPath . $oldName, $this->themesPath . $newName);
        }

        return null;
    }

    /**
     * Rename wrappers. Returns error string upon failure.
     *
     * @param array<string,string> $wrappers
     */
    private function renameWrappers($wrappers): ?string
    {
        foreach ($wrappers as $wrapperName => $wrapperNewName) {
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
                return 'Wrapper name must consist of letters, numbers, spaces, and underscore, and be
                    under 50 characters long.';
            }
            if (is_file($this->wrappersPath . $wrapperNewName . '.html')) {
                return "That wrapper name ({$wrapperNewName}) is already being used.";
            }

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

        return null;
    }

    private function setDefaultSkin($skinID): void
    {
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
            $skinID,
        );
    }

    private function showSkinIndex(): void
    {
        $skinError = null;
        $wrapperError = null;

        $deleteWrapper = $this->request->both('deletewrapper');
        if ($deleteWrapper) {
            $wrapperError = $this->deleteWrapper($deleteWrapper);
        }

        $newWrapper = $this->request->both('newwrapper');
        if ($newWrapper !== null && $newWrapper !== '') {
            $wrapperError = $this->createWrapper($newWrapper);
        }

        $updateWrappers = $this->request->both('wrapper');
        if (is_array($updateWrappers)) {
            $wrapperError = $this->updateWrappers($updateWrappers);
        }

        $renameSkins = $this->request->both('renameskin');
        if (is_array($renameSkins)) {
            $skinError = $this->renameSkin($renameSkins);
        }

        $renameWrappers = $this->request->both('renamewrapper');
        if (is_array($renameWrappers)) {
            $wrapperError = $this->renameWrappers($renameWrappers);
        }

        $defaultSkin = $this->request->both('default');
        if ($defaultSkin !== null) {
            $this->setDefaultSkin($defaultSkin);
        }

        $usedwrappers = [];
        $skins = '';
        $wrappers = $this->getWrappers();
        foreach ($this->getSkins() as $f) {
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
                );
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
            );
            $usedwrappers[] = $f['wrapper'];
        }

        $skins = ($skinError !== null ? $this->page->error($skinError) : '')
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
            );
        }

        $wrap = $this->page->parseTemplate(
            'themes/show-skin-index-wrapper.html',
            [
                'content' => $wrap,
            ],
        );
        $this->page->addContentBox(
            'Wrappers',
            ($wrapperError !== null ? $this->page->error($wrapperError) : '') . $wrap,
        );
    }

    private function editCSS($id): void
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

    private function editWrapper($wrapper): void
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

    private function createSkin(): void
    {
        $page = '';
        if ($this->request->post('submit') !== null) {
            $error = match (true) {
                !$this->request->post('skinname') => 'No skin name supplied!',
                (bool) preg_match('@[^\w ]@', (string) $this->request->post('skinname')) => 'Skinname must only consist of letters, numbers, and spaces.',
                mb_strlen((string) $this->request->post('skinname')) > 50 => 'Skin name must be less than 50 characters.',
                is_dir($this->themesPath . $this->request->post('skinname')) => 'A skin with that name already exists.',
                !in_array($this->request->post('wrapper'), $this->getWrappers()) => 'Invalid wrapper.',
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

                $this->page->location('?act=Themes');
            }

            if ($error !== null) {
                $page = $this->page->error($error);
            }
        }

        $wrapperOptions = '';
        foreach ($this->getWrappers() as $wrapper) {
            $wrapperOptions .= $this->page->parseTemplate(
                'select-option.html',
                [
                    'label' => $wrapper,
                    'selected' => '',
                    'value' => $wrapper,
                ],
            );
        }

        $page .= $this->page->parseTemplate(
            'themes/create-skin.html',
            [
                'wrapper_options' => $wrapperOptions,
            ],
        );
        $this->page->addContentBox('Create New Skin', $page);
    }

    private function deleteSkin(string $id): void
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

        $this->page->location('?act=Themes');
    }
}
