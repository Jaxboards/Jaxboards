<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\FileUtils;
use Jax\Models\Skin;
use Jax\Request;
use Jax\TextFormatting;

use function array_key_exists;
use function array_map;
use function copy;
use function dirname;
use function glob;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function mb_strlen;
use function mkdir;
use function pathinfo;
use function preg_match;
use function realpath;
use function rename;
use function str_starts_with;

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

        $editCSS = (int) $this->request->asString->get('editcss');
        $editWrapper = $this->request->asString->get('editwrapper');
        $deleteSkin = (int) $this->request->asString->get('deleteskin');
        $do = $this->request->asString->get('do');

        match (true) {
            (bool) $editCSS => $this->editCSS($editCSS),
            (bool) $editWrapper => $this->editWrapper($editWrapper),
            $deleteSkin !== 0 => $this->deleteSkin($deleteSkin),
            $do === 'create' => $this->createSkin(),
            default => $this->showSkinIndex(),
        };
    }

    /**
     * @return array<string>
     */
    private function getWrappers(): array
    {
        return array_map(
            static fn(string $path): string => pathinfo($path, PATHINFO_FILENAME),
            $this->fileUtils->glob($this->wrappersPath . '/*') ?: [],
        );
    }

    /**
     * Delete a wrapper. Returns error string upon failure.
     */
    private function deleteWrapper(string $wrapper): string
    {
        $wrapperPath = $this->pathToWrapper($wrapper);
        if (
            $this->isValidFilename($wrapper)
            && $this->fileUtils->exists($wrapperPath)
        ) {
            $this->fileUtils->unlink($wrapperPath);
            $this->page->location('?act=Themes');

            return '';
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
            mb_strlen($wrapper) > 50 => 'Wrapper name must be less than 50 characters.',
            $this->fileUtils->exists($newWrapperPath) => 'That wrapper already exists.',
            !$this->fileUtils->isWritable(dirname($newWrapperPath)) => 'Wrapper directory is not writable.',

            $this->fileUtils->putContents(
                $newWrapperPath,
                $this->fileUtils->getContents($this->domainDefinitions->getDefaultThemePath() . '/wrappers.html'),
            ) === false => 'Wrapper could not be created.',
            default => null,
        };
    }

    /**
     * Update wrapper properties. Returns error string upon failure.
     *
     * @param array<string> $wrappers
     */
    private function updateWrappers(array $wrappers): null
    {
        $validWrappers = $this->getWrappers();

        foreach ($wrappers as $skinId => $wrapperName) {
            if (
                $wrapperName
                && !in_array($wrapperName, $validWrappers, true)
            ) {
                continue;
            }

            $hidden = $this->request->post('hidden');
            if (!is_array($hidden)) {
                $hidden = [];
            }

            $this->database->update(
                'skins',
                [
                    'hidden' => array_key_exists($skinId, $hidden) ? 1 : 0,
                    'wrapper' => $wrapperName,
                ],
                Database::WHERE_ID_EQUALS,
                $skinId,
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

            if (
                !$this->isValidFilename($oldName)
                || !is_dir($this->themesPath . $oldName)
            ) {
                return 'Invalid from skin name';
            }

            if (
                !$this->isValidFilename($newName)
                || mb_strlen($newName) > 50
            ) {
                return 'Skin name must consist of letters, numbers, spaces, and underscore, and be under 50 characters long.';
            }

            if (is_dir($this->themesPath . $newName)) {
                return 'That skin name is already being used.';
            }

            $this->database->update(
                'skins',
                [
                    'title' => $newName,
                ],
                'WHERE `title`=? AND `custom`=1',
                $oldName,
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
    private function renameWrappers(array $wrappers): ?string
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
                || mb_strlen($wrapperNewName) > 50
            ) {
                return 'Wrapper name must consist of letters, numbers, spaces, and underscore, and be
                    under 50 characters long.';
            }

            if (is_file($this->pathToWrapper($wrapperNewName))) {
                return "That wrapper name ({$wrapperNewName}) is already being used.";
            }

            $this->database->update(
                'skins',
                [
                    'wrapper' => $wrapperNewName,
                ],
                'WHERE `wrapper`=? AND `custom`=1',
                $wrapperName,
            );
            rename(
                $this->pathToWrapper($wrapperName),
                $this->pathToWrapper($wrapperNewName),
            );
        }

        return null;
    }

    private function setDefaultSkin(int $skinID): void
    {
        $this->database->update(
            'skins',
            [
                'default' => 0,
            ],
        );
        $this->database->update(
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
            is_array($renameWrappers) => $this->renameWrappers($renameWrappers),
            default => null,
        };

        $skinError = is_array($renameSkins)
            ? $this->renameSkin($renameSkins)
            : null;

        $defaultSkin = (int) $this->request->asString->both('default');
        if ($defaultSkin !== 0) {
            $this->setDefaultSkin($defaultSkin);
        }

        $usedwrappers = [];
        $skinsHTML = '';
        $wrappers = $this->getWrappers();

        $skins = Skin::selectMany('ORDER BY title ASC');

        foreach ($skins as $skin) {
            $wrapperOptions = '';
            foreach ($wrappers as $wrapper) {
                $wrapperOptions .= $this->page->parseTemplate(
                    'select-option.html',
                    [
                        'label' => $wrapper,
                        'selected' => $wrapper === $skin->wrapper
                            ? 'selected="selected"' : '',
                        'value' => $wrapper,
                    ],
                );
            }

            $skinsHTML .= $this->page->parseTemplate(
                'themes/show-skin-index-css-row.html',
                [
                    'custom' => $skin->custom
                        ? $this->page->parseTemplate(
                            'themes/show-skin-index-css-row-custom.html',
                        ) : '',
                    'default_checked' => $this->page->checked($skin->default === 1),
                    'default_option' => $skin->custom ? '' : $this->page->parseTemplate(
                        'select-option.html',
                        [
                            'label' => 'Skin Default',
                            'selected' => '',
                            'value' => '',
                        ],
                    ),
                    'delete' => $skin->custom ? $this->page->parseTemplate(
                        'themes/show-skin-index-css-row-delete.html',
                        [
                            'id' => $skin->id,
                        ],
                    ) : '',
                    'hidden_checked' => $this->page->checked($skin->hidden === 1),
                    'id' => $skin->id,
                    'title' => $skin->title,
                    'view_or_edit' => $skin->custom ? 'Edit' : 'View',
                    'wrapper_options' => $wrapperOptions,
                ],
            );
            $usedwrappers[] = $skin->wrapper;
        }

        $skinsHTML = $this->page->parseTemplate(
            'themes/show-skin-index-css.html',
            [
                'content' => $skinsHTML,
            ],
        );

        $this->page->addContentBox(
            'Themes',
            ($skinError !== null ? $this->page->error($skinError) : '') . $skinsHTML,
        );

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
        $skin = Skin::selectOne($id);

        if ($skin === null) {
            $this->page->addContentBox('Error', "Skin id {$id} not found");

            return;
        }

        $newSkinData = $this->request->asString->post('newskindata');
        if ($skin->custom && $newSkinData) {
            $this->fileUtils->putContents(
                $this->themesPath . $skin->title . '/css.css',
                $newSkinData,
            );
        }

        $this->page->addContentBox(
            ($skin->custom !== 0 ? 'Editing' : 'Viewing') . ' Skin: ' . $skin->title,
            $this->page->parseTemplate(
                'themes/edit-css.html',
                [
                    'content' => $this->textFormatting->blockhtml(
                        $this->fileUtils->getContents(
                            (
                                $skin->custom !== 0
                                ? $this->themesPath : $this->domainDefinitions->getServiceThemePath()
                            ) . "/{$skin->title}/css.css",
                        ) ?: '',
                    ),
                    'save' => $skin->custom !== 0 ? $this->page->parseTemplate(
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
            default => $this->fileUtils->putContents($wrapperPath, $wrapperContents)
                ? $this->page->success('Wrapper saved successfully.')
                : $this->page->error('Error saving wrapper.'),
        };

        $this->page->addContentBox(
            "Editing Wrapper: {$wrapper}",
            $saved . $this->page->parseTemplate(
                'themes/edit-wrapper.html',
                [
                    'content' => $this->textFormatting->blockhtml(
                        $this->fileUtils->getContents($wrapperPath) ?: '',
                    ),
                ],
            ),
        );
    }

    private function submitCreateSkin(): ?string
    {
        $skinName = $this->request->asString->post('skinname');
        $wrapperName = $this->request->asString->post('wrapper');
        $error = match (true) {
            !$skinName => 'No skin name supplied!',
            !$this->isValidFilename($skinName) => 'Skinname must only consist of letters, numbers, and spaces.',
            mb_strlen($skinName) > 50 => 'Skin name must be less than 50 characters.',
            is_dir($this->themesPath . $skinName) => 'A skin with that name already exists.',
            !in_array($wrapperName, $this->getWrappers()) => 'Invalid wrapper.',
            default => null,
        };

        if ($error) {
            return $error;
        }

        $skin = new Skin();
        $skin->custom = 1;
        $skin->default = $this->request->post('default') ? 1 : 0;
        $skin->hidden = $this->request->post('hidden') ? 1 : 0;
        $skin->title = $skinName ?? '';
        $skin->wrapper = $wrapperName ?? '';
        $skin->insert();

        if ($this->request->post('default')) {
            $this->database->update(
                'skins',
                [
                    'default' => 0,
                ],
                'WHERE `id`!=?',
                $skin->id,
            );
        }

        $safeThemesPath = realpath($this->themesPath . $skinName);
        if (
            !$safeThemesPath
            || !str_starts_with($safeThemesPath, $this->themesPath)
        ) {
            return 'Invalid skin name';
        }

        mkdir($safeThemesPath, 0o777, true);
        copy(
            $this->domainDefinitions->getDefaultThemePath() . '/css.css',
            $safeThemesPath . '/css.css',
        );

        $this->page->location('?act=Themes');

        return null;
    }

    private function createSkin(): void
    {
        $page = '';

        if ($this->request->post('submit') !== null) {
            $error = $this->submitCreateSkin();
            if ($error) {
                $page .= $this->page->error($error);
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
        $skin = Skin::selectOne($id);

        if ($skin === null) {
            $this->page->addContentBox('Error', "Skin id {$id} not found");

            return;
        }

        $skindir = $this->themesPath . $skin->title;
        if (is_dir($skindir)) {
            $this->fileUtils->removeDirectory($skindir);
        }

        $skin->delete();

        // Make a random skin default if it's the default.
        if ($skin->default !== 0) {
            $this->database->update(
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
