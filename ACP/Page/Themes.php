<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\FileUtils;
use Jax\Request;
use Jax\TextFormatting;

use function array_key_exists;
use function array_map;
use function dirname;
use function fclose;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function fopen;
use function fwrite;
use function glob;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function is_writable;
use function mb_strlen;
use function mkdir;
use function pathinfo;
use function preg_match;
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
        private FileUtils $fileUtils,
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

        $editCSS = (int) $this->request->get('editcss');
        $editWrapper = $this->request->get('editwrapper');
        $deleteSkin = (int) $this->request->get('deleteskin');
        $do = (int) $this->request->get('do');

        match (true) {
            (bool) $editCSS => $this->editCSS($editCSS),
            (bool) $editWrapper => $this->editWrapper($editWrapper),
            $deleteSkin !== 0 => $this->deleteSkin($deleteSkin),
            $do === 'create' => $this->createSkin(),
            default => $this->showSkinIndex(),
        };
    }

    /**
     * Get skins from database.
     *
     * @return array<array<string,mixed>>
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

        return $this->database->arows($result) ?? [];
    }

    /**
     * @return array<string>
     */
    private function getWrappers(): array
    {
        return array_map(
            static fn($path) => pathinfo($path, PATHINFO_FILENAME),
            glob($this->wrappersPath . '/*'),
        );
    }

    /**
     * Delete a wrapper. Returns error string upon failure.
     */
    private function deleteWrapper(string $wrapper): ?string
    {
        $wrapperPath = $this->pathToWrapper($wrapper);
        if (
            $this->isValidFilename($wrapper)
            && file_exists($wrapperPath)
        ) {
            unlink($wrapperPath);
            $this->page->location('?act=Themes');

            return null;
        }

        return 'The wrapper you are trying to delete does not exist.';
    }

    /**
     * Create a wrapper. Returns error string upon failure.
     */
    private function createWrapper(string $wrapper): ?string
    {
        $newWrapperPath = $this->pathToWrapper($wrapper);

        return match (true) {
            !$this->isValidFilename($wrapper) => 'Wrapper name must consist of letters, '
                . 'numbers, spaces, and underscore.',
            mb_strlen((string) $wrapper) > 50 => 'Wrapper name must be less than 50 characters.',
            file_exists($newWrapperPath) => 'That wrapper already exists.',
            !is_writable(dirname($newWrapperPath)) => 'Wrapper directory is not writable.',

            file_put_contents(
                $newWrapperPath,
                file_get_contents($this->domainDefinitions->getDefaultThemePath() . '/wrappers.html'),
            ) === false => 'Wrapper could not be created.',
            default => null,
        };
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
                Database::WHERE_ID_EQUALS,
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

            if (!$this->isValidFilename($oldName)) {
                continue;
            }

            if (!is_dir($this->themesPath . $oldName)) {
                continue;
            }

            if (
                !$this->isValidFilename($newName)
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

            if (!$this->isValidFilename($wrapperName)) {
                continue;
            }

            if (!is_file($this->pathToWrapper($wrapperName))) {
                continue;
            }

            if (
                !$this->isValidFilename($wrapperNewName)
                || mb_strlen((string) $wrapperNewName) > 50
            ) {
                return 'Wrapper name must consist of letters, numbers, spaces, and underscore, and be
                    under 50 characters long.';
            }

            if (is_file($this->pathToWrapper($wrapperNewName))) {
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
                $this->pathToWrapper($wrapperName),
                $this->pathToWrapper($wrapperNewName),
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
            Database::WHERE_ID_EQUALS,
            $skinID,
        );
    }

    private function showSkinIndex(): void
    {
        $skinError = null;
        $wrapperError = null;

        $deleteWrapper = $this->request->both('deletewrapper');
        $newWrapper = $this->request->both('newwrapper');
        $updateWrappers = $this->request->both('wrapper');
        $renameSkins = $this->request->both('renameskin');
        $renameWrappers = $this->request->both('renamewrapper');

        $wrapperError = match (true) {
            is_string($deleteWrapper) => $this->deleteWrapper($deleteWrapper),
            is_string($newWrapper) && $newWrapper !== '' => $this->createWrapper($newWrapper),
            is_array($updateWrappers) => $this->updateWrappers($updateWrappers),
            is_array($renameSkins) => $this->renameSkin($renameSkins),
            is_array($renameWrappers) => $this->renameWrappers($renameWrappers),
            default => null,
        };

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

    private function editCSS(int $id): void
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
            Database::WHERE_ID_EQUALS,
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

    private function editWrapper(string $wrapper): void
    {
        $saved = '';
        $wrapperPath = $this->pathToWrapper($wrapper);
        if (!$this->isValidFilename($wrapper) || !is_file($wrapperPath)) {
            $this->page->addContentBox(
                'Error',
                "The theme you're trying to edit does not exist.",
            );

            return;
        }

        $wrapperContents = $this->request->post('newwrapper');
        $saved = match (true) {
            !is_string($wrapperContents) => '',
            default => file_put_contents($wrapperPath, $wrapperContents)
                ? $this->page->success('Wrapper saved successfully.')
                : $this->page->error('Error saving wrapper.'),
        };

        $this->page->addContentBox(
            "Editing Wrapper: {$wrapper}",
            $saved . $this->page->parseTemplate(
                'themes/edit-wrapper.html',
                [
                    'content' => $this->textFormatting->blockhtml(file_get_contents($wrapperPath)),
                ],
            ),
        );
    }

    private function createSkin(): void
    {
        $page = '';
        $skinName = $this->request->post('skinname');
        if ($this->request->post('submit') !== null) {
            $error = match (true) {
                !$this->request->post('skinname') => 'No skin name supplied!',
                !$this->isValidFilename($skinName) => 'Skinname must only consist of letters, numbers, and spaces.',
                mb_strlen($skinName) > 50 => 'Skin name must be less than 50 characters.',
                is_dir($this->themesPath . $skinName) => 'A skin with that name already exists.',
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

    private function deleteSkin(int $id): void
    {
        $result = $this->database->safeselect(
            '`id`,`using`,`title`,`custom`,`wrapper`,`default`,`hidden`',
            'skins',
            Database::WHERE_ID_EQUALS,
            $id,
        );
        $skin = $this->database->arow($result);
        $this->database->disposeresult($result);
        $skindir = $this->themesPath . $skin['title'];
        if (is_dir($skindir)) {
            foreach (glob($skindir . '/*') as $v) {
                unlink($v);
            }

            $this->fileUtils->removeDirectory($skindir);
        }

        $this->database->safedelete(
            'skins',
            Database::WHERE_ID_EQUALS,
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

    private function pathToWrapper(string $wrapperName): string
    {
        return $this->wrappersPath . $wrapperName . '.html';
    }

    /**
     * Validates if $filename has all valid characters.
     * $filename passed should not include extension.
     */
    private function isValidFilename(string $filename): bool
    {
        return !preg_match('@[^\w ]@', $filename);
    }
}
