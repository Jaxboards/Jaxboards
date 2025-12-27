<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\BBCode;
use Jax\Models\Page as ModelsPage;
use Jax\Page;
use Jax\Request;

final class CustomPage
{
    public function __construct(
        private readonly BBCode $bbCode,
        private readonly Page $page,
        private readonly Request $request,
    ) {}

    /**
     * Attempts to load a custom page.
     *
     * @return bool true on success or false on failure
     */
    public function route(string $pageID): bool
    {
        $page = ModelsPage::selectOne('WHERE `act`=?', $pageID);

        if ($page !== null) {
            $pageContents = $this->bbCode->toHTML($page->page);
            $this->page->append('PAGE', $pageContents);
            if ($this->request->isJSNewLocation()) {
                $this->page->command('update', 'page', $pageContents);
            }

            return true;
        }

        return false;
    }
}
