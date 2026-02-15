<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\Interfaces\Route;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Models\File;
use Jax\Models\Member;
use Jax\Page;
use Jax\Request;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;
use Override;

use function array_keys;
use function array_values;
use function filesize;
use function hash_file;
use function htmlspecialchars;
use function in_array;
use function json_encode;
use function move_uploaded_file;
use function str_replace;

use const ENT_QUOTES;
use const JSON_THROW_ON_ERROR;

final readonly class API implements Route
{
    public function __construct(
        private DomainDefinitions $domainDefinitions,
        private FileSystem $fileSystem,
        private IPAddress $ipAddress,
        private Page $page,
        private Request $request,
        private TextFormatting $textFormatting,
        private Template $template,
        private User $user,
    ) {}

    #[Override]
    public function route(array $params): void
    {
        $this->page->earlyFlush(match ($params['method']) {
            'searchmembers' => $this->searchMembers(),
            'emotes' => $this->emotes(),
            'upload' => $this->upload(),
            default => '',
        });
    }

    /**
     * 1) Compute a hash of the file to use as the filename on the server
     * 2) If it's an image, keep the extension so we can show it. Otherwise remove it.
     * 3) If the file has already been uploaded (based on hash) then don't replace it.
     *
     * @return string file ID
     */
    public function upload(): string
    {
        $fileobj = $this->request->file('Filedata');

        if ($fileobj === null || !$this->user->getGroup()?->canAttach) {
            return 'You do not have permission to attach files';
        }

        $uid = $this->user->get()->id;

        $size = (int) filesize($fileobj['tmp_name']);
        $hash = hash_file('sha1', $fileobj['tmp_name']) ?: 'hash_error';
        $uploadPath = $this->domainDefinitions->getBoardPath() . '/Uploads/';

        $ext = $this->fileSystem->getFileInfo($fileobj['name'])->getExtension();

        $imageExtension = in_array($ext, Jax::IMAGE_EXTENSIONS, true) ? ".{$ext}" : null;

        $filePath = $uploadPath . $hash . $imageExtension;

        if (!$this->fileSystem->getFileInfo($filePath)->isFile()) {
            move_uploaded_file($fileobj['tmp_name'], $filePath);

            $file = new File();
            $file->hash = $hash;
            $file->ip = $this->ipAddress->asBinary() ?? '';
            $file->name = $fileobj['name'];
            $file->size = $size;
            $file->uid = $uid;
            $file->insert();

            return (string) $file->id;
        }

        $fileRecord = File::selectOne('WHERE `hash`=?', $hash);
        if ($fileRecord === null) {
            return 'Error inserting file record';
        }

        return (string) $fileRecord->id;
    }

    private function searchMembers(): string
    {
        $members = Member::selectMany(
            'WHERE `displayName` LIKE ? ORDER BY `displayName` LIMIT 10',
            htmlspecialchars(str_replace('_', '\_', $this->request->asString->get('term') ?? ''), ENT_QUOTES) . '%',
        );

        $list = [[], []];
        foreach ($members as $member) {
            $list[0][] = $member->id;
            $list[1][] = $member->displayName;
        }

        return json_encode($list, JSON_THROW_ON_ERROR);
    }

    private function emotes(): string
    {
        $rules = $this->textFormatting->rules->getEmotes();
        foreach ($rules as $text => $image) {
            $rules[$text] = $this->template->render('bbcode/emote', [
                'image' => $image,
                'text' => $text,
            ]);
        }

        return json_encode([array_keys($rules), array_values($rules)], JSON_THROW_ON_ERROR);
    }
}
