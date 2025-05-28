<?php

declare(strict_types=1);

namespace ACP\Page\Tools;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\FileUtils;
use Jax\Models\File;
use Jax\Models\Member;
use Jax\Models\Post;
use Jax\Request;

use function _\keyBy;
use function array_key_exists;
use function array_map;
use function implode;
use function in_array;
use function is_array;
use function is_writable;
use function mb_strtolower;
use function pathinfo;
use function preg_match_all;
use function unlink;

use const PATHINFO_EXTENSION;

final readonly class FileManager
{
    public function __construct(
        private Config $config,
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
            $file = File::selectOne($this->database, Database::WHERE_ID_EQUALS, $delete);
            if ($file !== null) {
                $ext = mb_strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp'], true)) {
                    $file->hash .= '.' . $ext;
                }

                if (is_writable($this->domainDefinitions->getBoardPath() . '/Uploads/' . $file->hash)) {
                    $page .= unlink($this->domainDefinitions->getBoardPath() . '/Uploads/' . $file->hash)
                        ? $this->page->success('File deleted')
                        : $this->page->error(
                            "Error deleting file, maybe it's already been "
                            . 'deleted? Removed from DB',
                        );
                }

                $file->delete($this->database);
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
            $this->database,
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

        $files = File::selectMany($this->database, 'ORDER BY size');

        $memberIds = array_map(static fn($file): int => $file->uid, $files);
        $members = keyBy(
            Member::selectMany($this->database, Database::WHERE_ID_IN, $memberIds),
            static fn($member) => $member->id,
        );

        $table = '';
        foreach ($files as $file) {
            $ext = pathinfo((string) $file->name, PATHINFO_EXTENSION);

            $file->name = in_array($ext, $this->config->getSetting('images'), true) ? '<a href="'
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
                    'username' => $members[$file->uid]->display_name,
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
