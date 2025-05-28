<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\DomainDefinitions;
use Jax\Models\File;
use Jax\Request;

use function array_pop;
use function count;
use function explode;
use function file_exists;
use function header;
use function in_array;
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
        $this->downloadFile((int) $this->request->asString->both('id'));
    }

    private function downloadFile(int $id): void
    {
        $file = File::selectOne($this->database, Database::WHERE_ID_EQUALS, $id);

        if ($file === null) {
            return;
        }

        $this->database->special(
            <<<'SQL'
                UPDATE %t
                SET `downloads` = `downloads` + 1
                WHERE `id`=?
                SQL
            ,
            ['files'],
            $id,
        );
        $ext = explode('.', $file->name);
        $ext = count($ext) === 1 ? '' : mb_strtolower(array_pop($ext));

        if (in_array($ext, ['jpeg', 'jpg', 'png', 'gif', 'bmp'])) {
            $file->hash .= '.' . $ext;
        }

        $filePath = $this->domainDefinitions->getBoardPath() . '/Uploads/' . $file->hash;
        if (file_exists($filePath)) {
            if ($file->name === '' || $file->name === '0') {
                $file->name = 'unknown';
            }

            header('Content-type:application/idk');
            header(
                'Content-disposition:attachment;filename="'
                . $file->name . '"',
            );
            readfile($filePath);
        }

        exit;
    }
}
