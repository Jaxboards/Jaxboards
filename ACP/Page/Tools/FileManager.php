<?php

declare(strict_types=1);

namespace ACP\Page\Tools;

use ACP\Page;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\FileUtils;
use Jax\Jax;
use Jax\Models\File;
use Jax\Models\Member;
use Jax\Models\Post;
use Jax\Request;
use SplFileInfo;

use function array_key_exists;
use function implode;
use function in_array;
use function is_array;
use function mb_strtolower;
use function preg_match_all;

final readonly class FileManager
{
    public function __construct(
        private DomainDefinitions $domainDefinitions,
        private Database $database,
        private FileUtils $fileUtils,
        private Page $page,
        private Request $request,
    ) {}

    public function render(): void
    {
        $page = '';
        $delete = (int) $this->request->asString->both('delete');
        if ($delete !== 0) {
            $file = File::selectOne($delete);
            if ($file !== null) {
                $fileInfo = new SplFileInfo($file->name);
                $ext = mb_strtolower($fileInfo->getExtension());
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp'], true)) {
                    $file->hash .= '.' . $ext;
                }

                $uploadFilePath = $this->domainDefinitions->getBoardPath() . '/Uploads/' . $file->hash;

                if ($this->fileUtils->getFileInfo($uploadFilePath)->isWritable()) {
                    $page .= $this->fileUtils->unlink($uploadFilePath)
                        ? $this->page->success('File deleted')
                        : $this->page->error(
                            "Error deleting file, maybe it's already been "
                            . 'deleted? Removed from DB',
                        );
                }

                $file->delete();
            }
        }

        $filesToUpdate = $this->request->post('dl');
        if (is_array($filesToUpdate)) {
            foreach ($filesToUpdate as $fileId => $downloads) {
                $downloads = (int) $downloads;
                $fileId = (int) $fileId;

                if ($fileId === 0) {
                    continue;
                }

                $this->database->update(
                    'files',
                    [
                        'downloads' => $downloads,
                    ],
                    Database::WHERE_ID_EQUALS,
                    $fileId,
                );
            }

            $page .= $this->page->success('Changes saved.');
        }

        $posts = Post::selectMany(
            "WHERE MATCH (`post`) AGAINST ('attachment') "
            . "AND post LIKE '%[attachment]%'",
        );
        $linkedIn = [];

        foreach ($posts as $post) {
            preg_match_all(
                '@\[attachment\](\d+)\[/attachment\]@',
                $post->post,
                $matches,
            );
            foreach ($matches[1] as $attachmentId) {
                $linkedIn[(int) $attachmentId][] = $this->page->parseTemplate(
                    'tools/attachment-link.html',
                    [
                        'post_id' => $post->id,
                        'topic_id' => $post->tid,
                    ],
                );
            }
        }

        $files = File::selectMany('ORDER BY size');

        $members = Member::joinedOn(
            $files,
            static fn($file): int => $file->uid,
        );

        $table = '';
        foreach ($files as $file) {
            $fileInfo = new SplFileInfo($file->name);
            $ext = $fileInfo->getExtension();

            $file->name = in_array($ext, Jax::IMAGE_EXTENSIONS, true) ? '<a href="'
                    . $this->domainDefinitions->getBoardPathUrl() . 'Uploads/' . $file->hash . '.' . $ext . '">'
                    . $file->name . '</a>' : '<a href="../?act=download&id='
                    . $file->id . '">' . $file->name . '</a>';

            $table .= $this->page->parseTemplate(
                'tools/file-manager-row.html',
                [
                    'downloads' => $file->downloads,
                    'filesize' => $this->fileUtils->fileSizeHumanReadable($file->size),
                    'id' => $file->id,
                    'linked_in' => array_key_exists($file->id, $linkedIn)
                        ? implode(', ', $linkedIn[$file->id]) : 'Not linked!',
                    'title' => $file->name,
                    'username' => $members[$file->uid]->displayName,
                    'user_id' => $file->uid,
                ],
            );
        }

        $page .= $table !== '' ? $this->page->parseTemplate(
            'tools/file-manager.html',
            [
                'content' => $table,
            ],
        ) : $this->page->error('No files to show.');
        $this->page->addContentBox('File Manager', $page);
    }
}
