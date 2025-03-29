<?php

if (!defined(INACP)) {
    exit;
}

new themes();

final class themes
{
    public function __construct()
    {
        global $PAGE,$JAX,$CFG;
        if (!defined('DTHEMEPATH')) {
            define('DTHEMEPATH', JAXBOARDS_ROOT . '/' . $CFG['dthemepath']);
        }

        $links = [
            'create' => 'Create New Skin',
            'manage' => 'Manage Skins',
        ];
        $sidebarLinks = '';
        foreach ($links as $do => $title) {
            $sidebarLinks .= $PAGE->parseTemplate(
                'sidebar-list-link.html',
                [
                    'title' => $title,
                    'url' => '?act=themes&do=' . $do,
                ],
            ) . PHP_EOL;
        }

        $PAGE->sidebar(
            $PAGE->parseTemplate(
                'sidebar-list.html',
                [
                    'content' => $sidebarLinks,
                ],
            ),
        );

        if (isset($JAX->g['editcss']) && $JAX->g['editcss']) {
            $this->editcss($JAX->g['editcss']);
        } elseif (isset($JAX->g['editwrapper']) && $JAX->g['editwrapper']) {
            $this->editwrapper($JAX->g['editwrapper']);
        } elseif (
            isset($JAX->g['deleteskin'])
            && is_numeric($JAX->g['deleteskin'])
        ) {
            $this->deleteskin($JAX->g['deleteskin']);
        } elseif (isset($JAX->g['do']) && $JAX->g['do'] === 'create') {
            $this->createskin();
        } else {
            $this->showskinindex();
        }
    }

    /**
     * @return array<string>
     */
    public function getwrappers(): array
    {
        $wrappers = [];
        $o = opendir(BOARDPATH . 'Wrappers');
        while ($f = readdir($o)) {
            if ($f === '.') {
                continue;
            }

            if ($f === '..') {
                continue;
            }

            $wrappers[] = mb_substr($f, 0, -4);
        }

        closedir($o);

        return $wrappers;
    }

    public function showskinindex(): void
    {
        global $PAGE,$DB,$JAX,$CFG;
        $errorskins = '';
        $errorwrapper = '';

        if (isset($JAX->g['deletewrapper']) && $JAX->g['deletewrapper']) {
            $wrapperPath = BOARDPATH . 'Wrappers/' . $JAX->g['deletewrapper'] . '.txt';
            if (
                !preg_match('@[^\w ]@', (string) $JAX->g['deletewrapper'])
                && file_exists($wrapperPath)
            ) {
                unlink(BOARDPATH . 'Wrappers/' . $JAX->g['deletewrapper'] . '.txt');
                $PAGE->location('?act=themes');
            } else {
                $errorwrapper
                    = 'The wrapper you are trying to delete does not exist.';
            }
        }

        if (isset($JAX->p['newwrapper']) && $JAX->p['newwrapper']) {
            $newWrapperPath
                = BOARDPATH . 'Wrappers/' . $JAX->p['newwrapper'] . '.txt';
            if (preg_match('@[^\w ]@', (string) $JAX->p['newwrapper'])) {
                $errorwrapper
                    = 'Wrapper name must consist of letters, numbers, '
                    . 'spaces, and underscore.';
            } elseif (mb_strlen((string) $JAX->p['newwrapper']) > 50) {
                $errorwrapper = 'Wrapper name must be less than 50 characters.';
            } elseif (file_exists($newWrapperPath)) {
                $errorwrapper = 'That wrapper already exists.';
            } elseif (!is_writable(dirname($newWrapperPath))) {
                $errorwrappre = 'Wrapper directory is not writable.';
            } else {
                $o = fopen($newWrapperPath, 'w');
                if ($o !== false) {
                    fwrite(
                        $o,
                        file_get_contents(DTHEMEPATH . 'wrappers.txt'),
                    );
                    fclose($o);
                } else {
                    $errorwrapper = 'Wrapper could not be created.';
                }
            }
        }

        // Make an array of wrappers.
        $wrappers = $this->getwrappers();

        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            // Update wrappers/hidden status.
            if (!isset($JAX->p['hidden'])) {
                $JAX->p['hidden'] = [];
            }

            if (is_array($JAX->p['wrapper'])) {
                foreach ($JAX->p['wrapper'] as $k => $v) {
                    if (!isset($JAX->p['hidden'][$k])) {
                        $JAX->p['hidden'][$k] = false;
                    }

                    if ($v && !in_array($v, $wrappers)) {
                        continue;
                    }

                    $DB->safeupdate(
                        'skins',
                        [
                            'hidden' => $JAX->p['hidden'][$k] ? 1 : 0,
                            'wrapper' => $v,
                        ],
                        'WHERE `id`=?',
                        $k,
                    );
                }
            }

            if (
                isset($JAX->p['renameskin'])
                && is_array($JAX->p['renameskin'])
            ) {
                foreach ($JAX->p['renameskin'] as $k => $v) {
                    if ($k === $v) {
                        continue;
                    }

                    if (preg_match('@[^\w ]@', $k)) {
                        continue;
                    }

                    if (!is_dir(BOARDPATH . 'Themes/' . $k)) {
                        continue;
                    }

                    if (
                        preg_match('@[^\w ]@', (string) $v)
                        || mb_strlen((string) $v) > 50
                    ) {
                        $errorskins = <<<'EOT'
                            Skin name must consist of letters, numbers, spaces, and underscore, and be
                            under 50 characters long.
                            EOT;
                    } elseif (is_dir(BOARDPATH . 'Themes/' . $v)) {
                        $errorskins = 'That skin name is already being used.';
                    } else {
                        $DB->safeupdate(
                            'skins',
                            [
                                'title' => $v,
                            ],
                            'WHERE `title`=? AND `custom`=1',
                            $DB->basicvalue($k),
                        );
                        rename(BOARDPATH . 'Themes/' . $k, BOARDPATH . 'Themes/' . $v);
                    }
                }
            }

            if (
                isset($JAX->p['renamewrapper'])
                && is_array($JAX->p['renamewrapper'])
            ) {
                foreach ($JAX->p['renamewrapper'] as $k => $v) {
                    if ($k === $v) {
                        continue;
                    }

                    if (preg_match('@[^\w ]@', $k)) {
                        continue;
                    }

                    if (!is_file(BOARDPATH . 'Wrappers/' . $k . '.txt')) {
                        continue;
                    }

                    if (
                        preg_match('@[^\w ]@', (string) $v)
                        || mb_strlen((string) $v) > 50
                    ) {
                        $errorwrapper = <<<'EOT'
                            Wrapper name must consist of letters, numbers, spaces, and underscore, and be
                            under 50 characters long.
                            EOT;
                    } elseif (is_file(BOARDPATH . 'Wrappers/' . $v . '.txt')) {
                        $errorwrapper = 'That wrapper name is already being used.';
                    } else {
                        $DB->safeupdate(
                            'skins',
                            [
                                'wrapper' => $v,
                            ],
                            'WHERE `wrapper`=? AND `custom`=1',
                            $DB->basicvalue($k),
                        );
                        rename(
                            BOARDPATH . 'Wrappers/' . $k . '.txt',
                            BOARDPATH . 'Wrappers/' . $v . '.txt',
                        );
                    }

                    $wrappers = $this->getwrappers();
                }
            }

            // Set default.
            if (isset($JAX->p['default'])) {
                $DB->safeupdate(
                    'skins',
                    [
                        'default' => 0,
                    ],
                );
                $DB->safeupdate(
                    'skins',
                    [
                        'default' => 1,
                    ],
                    'WHERE `id`=?',
                    $JAX->p['default'],
                );
            }
        }

        $result = $DB->safeselect(
            [
                'id',
                '`using`',
                'title',
                'custom',
                'wrapper',
                '`default`',
                'hidden'
            ],
            'skins',
            'ORDER BY title ASC',
        );
        $usedwrappers = [];
        $skins = '';
        while ($f = $DB->arow($result)) {
            $wrapperOptions = '';
            foreach ($wrappers as $wrapper) {
                $wrapperOptions .= $PAGE->parseTemplate(
                    'select-option.html',
                    [
                        'label' => $wrapper,
                        'selected' => $wrapper === $f['wrapper']
                        ? 'selected="selected"' : '',
                        'value' => $wrapper,
                    ],
                ) . PHP_EOL;
            }

            $skins .= $PAGE->parseTemplate(
                'themes/show-skin-index-css-row.html',
                [
                    'custom' => $f['custom']
                        ? $PAGE->parseTemplate(
                            'themes/show-skin-index-css-row-custom.html',
                        ) : '',
                    'default_checked' => $f['default'] ? "checked='checked'" : '',
                    'default_option' => $f['custom'] ? '' : $PAGE->parseTemplate(
                        'select-option.html',
                        [
                            'label' => 'Skin Default',
                            'selected' => '',
                            'value' => '',
                        ],
                    ),
                    'delete' => $f['custom'] ? $PAGE->parseTemplate(
                        'themes/show-skin-index-css-row-delete.html',
                        [
                            'id' => $f['id'],
                        ],
                    ) : '',
                    'hidden_checked' => $f['hidden'] ? 'checked="checked"' : '',
                    'id' => $f['id'],
                    'title' => $f['title'],
                    'view_or_edit' => $f['custom'] ? 'Edit' : 'View',
                    'wrapper_options' => $wrapperOptions,
                ],
            ) . PHP_EOL;
            $usedwrappers[] = $f['wrapper'];
        }

        $skins = ($errorskins !== '' && $errorskins !== '0' ? $PAGE->error($errorskins) : '')
            . $PAGE->parseTemplate(
                'themes/show-skin-index-css.html',
                [
                    'content' => $skins,
                ],
            );
        $PAGE->addContentBox('Themes', $skins);

        $wrap = '';
        foreach ($wrappers as $wrapper) {
            $wrap .= $PAGE->parseTemplate(
                'themes/show-skin-index-wrapper-row.html',
                [
                    'delete' => in_array($wrapper, $usedwrappers) ? 'In use'
                    : $PAGE->parseTemplate(
                        'themes/show-skin-index-wrapper-row-delete.html',
                        [
                            'title' => $wrapper,
                        ],
                    ),
                    'title' => $wrapper,
                ],
            ) . PHP_EOL;
        }

        $wrap = $PAGE->parseTemplate(
            'themes/show-skin-index-wrapper.html',
            [
                'content' => $wrap,
            ],
        );
        $PAGE->addContentBox(
            'Wrappers',
            ($errorwrapper !== '' && $errorwrapper !== '0' ? $PAGE->error($errorwrapper) : '') . $wrap,
        );
    }

    public function editcss($id): void
    {
        global $PAGE,$DB,$JAX;
        $result = $DB->safeselect(
            [
                'id',
                '`using`',
                'title',
                'custom',
                'wrapper',
                '`default`',
                'hidden'
            ],
            'skins',
            'WHERE `id`=?',
            $id,
        );
        $skin = $DB->arow($result);
        $DB->disposeresult($result);
        if (!isset($JAX->p['newskindata'])) {
            $JAX->p['newskindata'] = false;
        }

        if ($skin && $skin['custom'] && $JAX->p['newskindata']) {
            $o = fopen(BOARDPATH . 'Themes/' . $skin['title'] . '/css.css', 'w');
            fwrite($o, (string) $JAX->p['newskindata']);
            fclose($o);
        }

        $PAGE->addContentBox(
            ($skin['custom'] ? 'Editing' : 'Viewing') . ' Skin: ' . $skin['title'],
            $PAGE->parseTemplate(
                'themes/edit-css.html',
                [
                    'content' => $JAX->blockhtml(
                        file_get_contents(
                            (
                                $skin['custom']
                                ? BOARDPATH . 'Themes/' : STHEMEPATH
                            ) . $skin['title'] . '/css.css',
                        ),
                    ),
                    'save' => $skin['custom'] ? $PAGE->parseTemplate(
                        'save-changes.html',
                    ) : '',
                ],
            ),
        );
    }

    public function editwrapper($wrapper): void
    {
        global $PAGE,$JAX;
        $saved = '';
        $wrapperf = BOARDPATH . 'Wrappers/' . $wrapper . '.txt';
        if (preg_match('@[^ \w]@', (string) $wrapper) && !is_file($wrapperf)) {
            $PAGE->addContentBox(
                'Error',
                "The theme you're trying to edit does not exist.",
            );
        } else {
            if (isset($JAX->p['newwrapper'])) {
                if (mb_strpos($JAX->p['newwrapper'], '<!--FOOTER-->') === false) {
                    $saved = $PAGE->error(
                        '&lt;!--FOOTER--&gt; must not be removed from the wrapper.',
                    );
                } else {
                    $o = fopen($wrapperf, 'w');
                    if ($o !== false) {
                        fwrite($o, $JAX->p['newwrapper']);
                        fclose($o);
                        $saved = $PAGE->success('Wrapper saved successfully.');
                    } else {
                        $saved = $PAGE->error('Error saving wrapper.');
                    }
                }
            }

            $PAGE->addContentBox(
                "Editing Wrapper: {$wrapper}",
                $saved . $PAGE->parseTemplate(
                    'themes/edit-wrapper.html',
                    [
                        'content' => $JAX->blockhtml(file_get_contents($wrapperf)),
                    ],
                ),
            );
        }
    }

    public function createskin(): void
    {
        global $PAGE,$JAX,$DB,$CFG;
        $page = '';
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            $e = '';
            if (!isset($JAX->p['skinname']) || !$JAX->p['skinname']) {
                $e = 'No skin name supplied!';
            } elseif (preg_match('@[^\w ]@', (string) $JAX->p['skinname'])) {
                $e = 'Skinname must only consist of letters, numbers, and spaces.';
            } elseif (mb_strlen((string) $JAX->p['skinname']) > 50) {
                $e = 'Skin name must be less than 50 characters.';
            } elseif (is_dir(BOARDPATH . 'Themes/' . $JAX->p['skinname'])) {
                $e = 'A skin with that name already exists.';
            } elseif (!in_array($JAX->p['wrapper'], $this->getwrappers())) {
                $e = 'Invalid wrapper.';
            } else {
                if (!isset($JAX->p['hidden'])) {
                    $JAX->p['hidden'] = false;
                }

                if (!isset($JAX->p['default'])) {
                    $JAX->p['default'] = false;
                }

                $DB->safeinsert(
                    'skins',
                    [
                        'custom' => 1,
                        'default' => $JAX->p['default'] ? 1 : 0,
                        'hidden' => $JAX->p['hidden'] ? 1 : 0,
                        'title' => $JAX->p['skinname'],
                        'wrapper' => $JAX->p['wrapper'],
                    ],
                );
                if ($JAX->p['default']) {
                    $DB->safeupdate(
                        'skins',
                        [
                            'default' => 0,
                        ],
                        'WHERE `id`!=?',
                        $DB->insert_id(1),
                    );
                }

                if (!is_dir(BOARDPATH . 'Themes') && is_writable(BOARDPATH)) {
                    mkdir(BOARDPATH . 'Themes');
                }

                if (is_dir(BOARDPATH . 'Themes')) {
                    mkdir(BOARDPATH . 'Themes');
                    mkdir(BOARDPATH . 'Themes/' . $JAX->p['skinname']);
                    $o = fopen(BOARDPATH . 'Themes/' . $JAX->p['skinname'] . '/css.css', 'w');
                    fwrite(
                        $o,
                        file_get_contents(
                            DTHEMEPATH . 'css.css',
                        ),
                    );
                    fclose($o);
                }

                $PAGE->location('?act=themes');
            }

            if ($e !== '' && $e !== '0') {
                $page = $PAGE->error($e);
            }
        }

        $wrapperOptions = '';
        foreach ($this->getwrappers() as $wrapper) {
            $wrapperOptions .= $PAGE->parseTemplate(
                'select-option.html',
                [
                    'label' => $wrapper,
                    'selected' => '',
                    'value' => $wrapper,
                ],
            ) . PHP_EOL;
        }

        $page .= $PAGE->parseTemplate(
            'themes/create-skin.html',
            [
                'wrapper_options' => $wrapperOptions,
            ],
        );
        $PAGE->addContentBox('Create New Skin', $page);
    }

    public function deleteskin($id): void
    {
        global $PAGE,$DB,$JAX;
        $result = $DB->safeselect(
            '`id`,`using`,`title`,`custom`,`wrapper`,`default`,`hidden`',
            'skins',
            'WHERE `id`=?',
            $id,
        );
        $skin = $DB->arow($result);
        $DB->disposeresult($result);
        $skindir = BOARDPATH . 'Themes/' . $skin['title'];
        if (is_dir($skindir)) {
            foreach (glob($skindir . '/*') as $v) {
                unlink($v);
            }

            $JAX->rmdir($skindir);
        }

        $DB->safedelete(
            'skins',
            'WHERE `id`=?',
            $id,
        );
        // Make a random skin default if it's the default.
        if ($skin['default']) {
            $DB->safeupdate(
                'skins',
                [
                    'default' => 1,
                ],
                'LIMIT 1',
            );
        }

        $PAGE->location('?act=themes');
    }
}
