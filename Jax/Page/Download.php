<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\DomainDefinitions;
use Jax\Request;

use function array_pop;
use function count;
use function explode;
use function file_exists;
use function header;
use function in_array;
use function is_numeric;
use function mb_strtolower;
use function readfile;

final readonly class Download
{
    public function __construct(
        private Database $database,
        private DomainDefinitions $domainDefinitions,
        private Request $request,
    ) {}

    public function render(): void
    {
        $this->downloadFile($this->request->both('id'));
    }

    public function downloadFile($id): void
    {
        if (is_numeric($id)) {
            $result = $this->database->safeselect(
                [
                    'name',
                    'hash',
                ],
                'files',
                'WHERE `id`=?',
                $id,
            );
            $data = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        if (!$data) {
            return;
        }

        $this->database->safespecial(
            <<<'SQL'
                UPDATE %t
                SET `downloads` = `downloads` + 1
                WHERE `id`=?
                SQL
            ,
            ['files'],
            $id,
        );
        $ext = explode('.', (string) $data['name']);
        $ext = count($ext) === 1 ? '' : mb_strtolower(array_pop($ext));

        if (in_array($ext, ['jpeg', 'jpg', 'png', 'gif', 'bmp'])) {
            $data['hash'] .= '.' . $ext;
        }

        $file = $this->domainDefinitions->getBoardPath() . '/Uploads/' . $data['hash'];
        if (file_exists($file)) {
            if (!$data['name']) {
                $data['name'] = 'unknown';
            }

            header('Content-type:application/idk');
            header(
                'Content-disposition:attachment;filename="'
                . $data['name'] . '"',
            );
            readfile($file);
        }

        exit;
    }
}
