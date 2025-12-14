<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\Interfaces\Route;
use Jax\Models\File;
use Jax\Request;

use function array_pop;
use function count;
use function explode;
use function header;
use function in_array;
use function mb_strtolower;

final readonly class Download implements Route
{
    public function __construct(
        private DomainDefinitions $domainDefinitions,
        private FileSystem $fileSystem,
        private Request $request,
    ) {}

    public function route($params): void
    {
        $this->downloadFile((int) $this->request->asString->both('id'));
    }

    private function downloadFile(int $id): void
    {
        $file = File::selectOne($id);

        if ($file === null) {
            return;
        }

        ++$file->downloads;
        $file->update();

        $ext = explode('.', $file->name);
        $ext = count($ext) === 1 ? '' : mb_strtolower(array_pop($ext));

        $filePath = $file->hash;

        if (in_array($ext, ['jpeg', 'jpg', 'png', 'gif', 'bmp'])) {
            $filePath .= '.' . $ext;
        }

        $filePath = $this->domainDefinitions->getBoardPath() . '/Uploads/' . $filePath;
        if ($this->fileSystem->getFileInfo($filePath)->isFile()) {
            header(
                "Content-Disposition: attachment; filename=\"{$file->name}\";",
            );
            echo $this->fileSystem->getContents($filePath);

            exit;
        }
    }
}
