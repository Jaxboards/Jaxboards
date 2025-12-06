<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\DomainDefinitions;
use Jax\FileUtils;
use Jax\Models\File;
use Jax\Page;
use Jax\Request;

use function array_pop;
use function count;
use function explode;
use function header;
use function in_array;
use function mb_strtolower;

final readonly class Download
{
    public function __construct(
        private DomainDefinitions $domainDefinitions,
        private FileUtils $fileUtils,
        private Request $request,
        private Page $page,
    ) {}

    public function render(): void
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

        if (in_array($ext, ['jpeg', 'jpg', 'png', 'gif', 'bmp'])) {
            $file->hash .= '.' . $ext;
        }

        $filePath = $this->domainDefinitions->getBoardPath() . '/Uploads/' . $file->hash;
        if ($this->fileUtils->getFileInfo($filePath)->isFile()) {
            header('Content-type:application/idk');
            header(
                'Content-disposition:attachment;filename="'
                . ($file->name || 'unknown') . '"',
            );
            $this->page->earlyFlush($this->fileUtils->getContents($filePath));
        }

        exit;
    }
}
