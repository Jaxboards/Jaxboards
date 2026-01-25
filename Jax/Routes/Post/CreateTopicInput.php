<?php

declare(strict_types=1);

namespace Jax\Routes\Post;

use Jax\Request;

use function array_filter;
use function preg_split;
use function trim;

final class CreateTopicInput
{
    public ?int $fid;

    public ?string $pollQuestion;

    public ?string $pollType;

    public ?string $topicDescription;

    public ?string $topicTitle;

    /**
     * @var array<string>
     */
    public array $pollChoices;

    public function __construct(Request $request)
    {
        $this->fid = (int) $request->both('fid');
        $this->pollQuestion = $request->asString->post('pollq');
        $this->pollType = $request->asString->post('pollType');
        $this->topicDescription = $request->asString->post('tdesc');
        $this->topicTitle = $request->asString->post('ttitle');

        $pollChoices = $request->asString->post('pollchoices');
        $this->pollChoices = $pollChoices !== null ? array_filter(
            preg_split("@[\r\n]+@", $pollChoices) ?: [],
            static fn(string $line): bool => trim($line) !== '',
        ) : [];
    }
}
