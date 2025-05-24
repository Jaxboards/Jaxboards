<?php

declare(strict_types=1);

namespace ACP\Page\Tools;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\FileUtils;
use Jax\Request;

use function array_key_exists;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
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
        if (
            is_numeric($this->request->both('delete'))
        ) {
            $result = $this->database->safeselect(
                [
                    'hash',
                    'name',
                ],
                'files',
                Database::WHERE_ID_EQUALS,
                $this->database->basicvalue($this->request->both('delete')),
            );
            $file = $this->database->arow($result);
            $this->database->disposeresult($result);
            if ($file) {
                $ext = mb_strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp'], true)) {
                    $file['hash'] .= '.' . $ext;
                }

                if (is_writable($this->domainDefinitions->getBoardPath() . '/Uploads/' . $file['hash'])) {
                    $page .= unlink($this->domainDefinitions->getBoardPath() . '/Uploads/' . $file['hash'])
                        ? $this->page->success('File deleted')
                        : $this->page->error(
                            "Error deleting file, maybe it's already been "
                            . 'deleted? Removed from DB',
                        );
                }

                $this->database->safedelete(
                    'files',
                    Database::WHERE_ID_EQUALS,
                    $this->database->basicvalue($this->request->both('delete')),
                );
            }
        }

        if (is_array($this->request->post('dl'))) {
            foreach ($this->request->post('dl') as $fileId => $downloads) {
                $downloads = (int) $downloads;

                $this->database->safeupdate(
                    'files',
                    [
                        'downloads' => $downloads,
                    ],
                    Database::WHERE_ID_EQUALS,
                    $this->database->basicvalue($fileId),
                );
            }

            $page .= $this->page->success('Changes saved.');
        }

        $result = $this->database->safeselect(
            '`id`,`tid`,`post`',
            'posts',
            "WHERE MATCH (`post`) AGAINST ('attachment') "
            . "AND post LIKE '%[attachment]%'",
        );
        $linkedIn = [];
        while ($post = $this->database->arow($result)) {
            preg_match_all(
                '@\[attachment\](\d+)\[/attachment\]@',
                (string) $post['post'],
                $matches,
            );
            foreach ($matches[1] as $attachmentId) {
                $linkedIn[(int) $attachmentId][] = $this->page->parseTemplate(
                    'tools/attachment-link.html',
                    [
                        'post_id' => $post['id'],
                        'topic_id' => $post['tid'],
                    ],
                );
            }
        }

        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT
                    f.`id` AS `id`,
                    f.`name` AS `name`,
                    f.`hash` AS `hash`,
                    f.`uid` AS `uid`,
                    f.`size` AS `size`,
                    f.`downloads` AS `downloads`,
                    m.`display_name` AS `uname`
                FROM %t f
                LEFT JOIN %t m
                    ON f.`uid`=m.`id`
                ORDER BY f.`size` DESC
                SQL
            ,
            ['files', 'members'],
        );
        $table = '';
        foreach ($this->database->arows($result) as $file) {
            $ext = pathinfo((string) $file['name'], PATHINFO_EXTENSION);

            $file['name'] = in_array($ext, $this->config->getSetting('images'), true) ? '<a href="'
                    . $this->domainDefinitions->getBoardPathUrl() . 'Uploads/' . $file['hash'] . '.' . $ext . '">'
                    . $file['name'] . '</a>' : '<a href="../?act=download&id='
                    . $file['id'] . '">' . $file['name'] . '</a>';

            $table .= $this->page->parseTemplate(
                'tools/file-manager-row.html',
                [
                    'downloads' => $file['downloads'],
                    'filesize' => $this->fileUtils->fileSizeHumanReadable($file['size']),
                    'id' => $file['id'],
                    'linked_in' => array_key_exists($file['id'], $linkedIn)
                        ? implode(', ', $linkedIn[$file['id']]) : 'Not linked!',
                    'title' => $file['name'],
                    'username' => $file['uname'],
                    'user_id' => $file['uid'],
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
