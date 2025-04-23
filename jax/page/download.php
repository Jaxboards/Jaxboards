<?php

declare(strict_types=1);

namespace Jax\Page;

use function array_pop;
use function count;
use function explode;
use function file_exists;
use function header;
use function in_array;
use function is_numeric;
use function mb_strtolower;
use function readfile;

final class Download
{
    public function route(): void
    {
        global $JAX;
        $this->downloadFile($JAX->b['id']);
    }

    public function downloadFile($id): void
    {
        global $DB;
        if (is_numeric($id)) {
            $result = $DB->safeselect(
                [
                    'name',
                    'hash',
                ],
                'files',
                'WHERE `id`=?',
                $id,
            );
            $data = $DB->arow($result);
            $DB->disposeresult($result);
        }

        if (!$data) {
            return;
        }

        $DB->safespecial(
            <<<'EOT'
                UPDATE %t
                SET `downloads` = `downloads` + 1
                WHERE `id`=?
                EOT
            ,
            ['files'],
            $id,
        );
        $ext = explode('.', (string) $data['name']);
        $ext = count($ext) === 1 ? '' : mb_strtolower(array_pop($ext));

        if (in_array($ext, ['jpeg', 'jpg', 'png', 'gif', 'bmp'])) {
            $data['hash'] .= '.' . $ext;
        }

        $file = BOARDPATH . 'Uploads/' . $data['hash'];
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
