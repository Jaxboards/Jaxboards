<?php

declare(strict_types=1);

final class BoardOffline
{
    public function route(): void
    {
        global $PAGE,$JAX,$CFG;
        if ($PAGE->jsupdate) {
            return;
        }

        $PAGE->append(
            'PAGE',
            $PAGE->meta(
                'box',
                '',
                'Error',
                $PAGE->error(
                    "You don't have permission to view the board. "
                    . 'If you have an account that has permission, '
                    . 'please log in.'
                    . ($CFG['boardoffline'] && $CFG['offlinetext']
                    ? '<br /><br />Note:<br />' . nl2br((string) $CFG['offlinetext'])
                    : ''),
                ),
            ),
        );
    }
}
