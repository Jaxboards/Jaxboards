<?php

declare(strict_types=1);

namespace ACP\Routes\Tools;

use ACP\Page;
use Jax\Database\Database;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\Jax;
use Jax\Models\File;
use Jax\Models\Member;
use Jax\Models\Post;
use Jax\Request;

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
        private FileSystem $fileSystem,
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
                $fileInfo = $this->fileSystem->getFileInfo($file->name);
                $ext = mb_strtolower($fileInfo->getExtension());
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp'], true)) {
                    $file->hash .= '.' . $ext;
                }

                $uploadFilePath = $this->domainDefinitions->getBoardPath() . '/Uploads/' . $file->hash;

                if ($this->fileSystem->getFileInfo($uploadFilePath)->isWritable()) {
                    $page .= $this->fileSystem->unlink($uploadFilePath)
                        ? $this->page->success('File deleted')
                        : $this->page->error("Error deleting file, maybe it's already been "
                        . 'deleted? Removed from DB');
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

        $posts = Post::selectMany("WHERE MATCH (`post`) AGAINST ('attachment') AND post LIKE '%[attachment]%'");
        $linkedIn = [];

        foreach ($posts as $post) {
            preg_match_all('/\[attachment\](\d+)\[\/attachment\]/', $post->post, $matches);
            foreach ($matches[1] as $attachmentId) {
                $linkedIn[(int) $attachmentId][] = $this->page->render('tools/attachment-link.html', [
                    'post_id' => $post->id,
                    'topic_id' => $post->tid,
                ]);
            }
        }

        $files = File::selectMany('ORDER BY size');

        $members = Member::joinedOn($files, static fn($file): int => $file->uid);

        $table = '';
        foreach ($files as $file) {
            $ext = $this->fileSystem->getFileInfo($file->name)->getExtension();
            $fileURL = in_array($ext, Jax::IMAGE_EXTENSIONS, true)
                ? $this->domainDefinitions->getBoardPathUrl()
                . $this->fileSystem->pathJoin('/Uploads', "{$file->hash}.{$ext}")
                : "../download?id={$file->id}";

            $table .= $this->page->render('tools/file-manager-row.html', [
                'downloads' => $file->downloads,
                'filesize' => $this->fileSystem->fileSizeHumanReadable($file->size),
                'id' => $file->id,
                'linked_in' => array_key_exists($file->id, $linkedIn)
                    ? implode(', ', $linkedIn[$file->id])
                    : 'Not linked!',
                'title' => "<a href='{$fileURL}'>{$file->name}</a>",
                'username' => $members[$file->uid]->displayName,
                'user_id' => $file->uid,
            ]);
        }

        $page .= $table !== ''
            ? $this->page->render('tools/file-manager.html', [
                'content' => $table,
            ]) : $this->page->error('No files to show.');
        $this->page->addContentBox('File Manager', $page);
    }
}
