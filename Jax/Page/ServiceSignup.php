<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Request;
use Jax\ServiceConfig;

use function array_map;
use function dirname;
use function file;
use function gmdate;
use function header;
use function implode;
use function Jax\FileUtils\copyDirectory;
use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function password_hash;
use function preg_match;
use function str_replace;
use function time;
use function trim;

use const PASSWORD_DEFAULT;

/**
 * Service signup file, for users to create their own JaxBoards forum.
 *
 * PHP Version 8
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */
final readonly class ServiceSignup
{
    public function __construct(
        private ServiceConfig $serviceConfig,
        private Database $database,
        private IPAddress $ipAddress,
        private Jax $jax,
        private Request $request,
    ) {}

    public function render(): void
    {
        if (!$this->serviceConfig->getSetting('service')) {
            echo 'Service mode not enabled';

            return;
        }

        $errors = [];
        if ($this->request->post('submit') !== null) {
            if ($this->request->post('post') !== null) {
                header('Location: https://test.' . $this->serviceConfig->getSetting('domain'));
            }

            $boardURL = mb_strtolower((string) $this->request->both('boardurl'));
            if (
                !$boardURL
                || !$this->request->post('username')
                || !$this->request->post('password')
                || !$this->request->post('email')
            ) {
                $errors[] = 'all fields required.';
            } elseif (mb_strlen($boardURL) > 30) {
                $errors[] = 'board url too long';
            } elseif ($boardURL === 'www') {
                $errors[] = 'WWW is reserved.';
            } elseif (preg_match('@\W@', $boardURL)) {
                $errors[] = 'board url needs to consist of letters, '
                    . 'numbers, and underscore only';
            }

            $result = $this->database->safeselect(
                ['id'],
                'directory',
                'WHERE `registrar_ip`=? AND `date`>?',
                $this->ipAddress->asBinary(),
                $this->database->datetime(time() - 7 * 24 * 60 * 60),
            );
            if ($this->database->numRows($result) > 3) {
                $errors[] = 'You may only register 3 boards per week.';
            }

            $this->database->disposeresult($result);

            if (!$this->jax->isemail($this->request->post('email'))) {
                $errors[] = 'invalid email';
            }

            if (mb_strlen((string) $this->request->post('username')) > 50) {
                $errors[] = 'username too long';
            } elseif (preg_match('@\W@', (string) $this->request->post('username'))) {
                $errors[] = 'username needs to consist of letters, '
                    . 'numbers, and underscore only';
            }

            $result = $this->database->safeselect(
                ['id'],
                'directory',
                'WHERE `boardname`=?',
                $this->database->basicvalue($this->request->post('boardurl')),
            );
            if ($this->database->arow($result)) {
                $errors[] = 'that board already exists';
            }

            $this->database->disposeresult($result);

            if ($errors === []) {
                $board = $this->request->post('boardurl');
                $boardPrefix = $board . '_';

                $this->database->setPrefix('');
                // Add board to directory.
                $this->database->safeinsert(
                    'directory',
                    [
                        'boardname' => $board,
                        'date' => $this->database->datetime(),
                        'referral' => $this->request->both('r') ?? '',
                        'registrar_email' => $this->request->post('email'),
                        'registrar_ip' => $this->ipAddress->asBinary(),
                    ],
                );
                $this->database->setPrefix($boardPrefix);

                // Create the directory and blueprint tables
                // Import sql file and run it with php from this:
                // https://stackoverflow.com/a/19752106
                // It's not pretty or perfect but it'll work for our use case...
                $query = '';
                $lines = file(SERVICE_ROOT . '/blueprint.sql');
                foreach ($lines as $line) {
                    // Skip comments.
                    if (mb_substr($line, 0, 2) === '--') {
                        continue;
                    }

                    if ($line === '') {
                        continue;
                    }

                    // Replace blueprint_ with board name.
                    $line = str_replace('blueprint_', $boardPrefix, $line);

                    // Add line to current query.
                    $query .= $line;

                    // If it has a semicolon at the end, it's the end of the query.
                    if (mb_substr(trim((string) $line), -1, 1) !== ';') {
                        continue;
                    }

                    // Perform the query.
                    $result = $this->database->safequery($query);
                    $this->database->disposeresult($result);
                    // Reset temp variable to empty.
                    $query = '';
                }

                // Don't forget to create the admin.
                $this->database->safeinsert(
                    'members',
                    [
                        'display_name' => $this->request->post('username'),
                        'email' => $this->request->post('email'),
                        'group_id' => 2,
                        'join_date' => $this->database->datetime(),
                        'last_visit' => $this->database->datetime(),
                        'name' => $this->request->post('username'),
                        'pass' => password_hash((string) $this->request->post('password'), PASSWORD_DEFAULT),
                        'posts' => 0,
                        'sig' => '',
                    ],
                );

                $dbError = $this->database->error();
                if ($dbError !== '' && $dbError !== '0') {
                    $errors[] = $dbError;
                } else {
                    copyDirectory('blueprint', dirname(__DIR__) . '/boards/' . $board);

                    header('Location: https://' . $this->request->post('boardurl') . '.' . $this->serviceConfig->getSetting('domain'));
                }
            }
        }

        $currentYear = gmdate('Y');
        $errorDisplay = implode('', array_map(static fn($error): string => "<div class='error'>{$error}</div>", $errors));

        echo <<<HTML
            <!doctype html>
            <html lang="en">
                <head>
                    <link media="all" rel="stylesheet" href="./css/main.css">
                    <meta name="description" content="The world's very first instant forum.">
                    <title>Jaxboards - The free AJAX powered forum host</title>
                </head>
                <body onload="if(top.location!=self.location) top.location=self.location">
                    <div id='container'>
                    <div id='logo'><a href="https://{$this->serviceConfig->getSetting('domain')}">&nbsp;</a></div>
                    <div id='bar'>
                        <a href="https://support.{$this->serviceConfig->getSetting('domain')}" class="support">
                        Support Forum
                        </a>
                        <a href="https://test.{$this->serviceConfig->getSetting('domain')}" class="test">
                        Test Forum
                        </a>
                        <a href="https://support.{$this->serviceConfig->getSetting('domain')}" class="resource">
                        Resources
                        </a>
                    </div>
                    <div id='content'>
                        <div class='box'>
                            <div class='content flex'>
                                <div class="flex1">
                                    <strong>So, you want a community. You've come to the right place.</strong>
                                    <p>
                                        JaxBoards has been built from the ground up: utilizing feedback from
                                        members and forum gurus along the way to create the world's first
                                        real-time, AJAX-powered forum - the first bulletin board software
                                        to utilize modern technology to make each user's experience as
                                        easy and as enjoyable as possible.
                                    </p>
                                </div>
                                <div id="signup" class="flex1">
                                    <form  method="post">
                                        {$errorDisplay}
                                        <input type="text" name="boardurl" id="boardname">.{$this->serviceConfig->getSetting('domain')}<br>
                                        <label for="username">Username:</label>
                                        <input type="text" id="username" name="username"><br>
                                        <label for="password">Password:</label>
                                        <input type="password" id="password" name="password"><br>
                                        <label for="email">Email:</label>
                                        <input type="text" name="email" id="email"><br>
                                        <input type="text" name="post" id="post">
                                        <div class="center">
                                            <input type="submit" name="submit" value="Register a Forum!">
                                        </div>
                                    </form>
                                 </div>
                            </div>
                        </div>
                        <div class="flex">
                            <div class='box mini box1'>
                                <div class='title'>Customizable</div>
                                <div class='content'>
                                    Jaxboards offers entirely new ways to make your forum look exactly the way you want:
                                    <ul>
                                        <li>Easy CSS</li>
                                        <li>Template access</li>
                                    </ul>
                                </div>
                            </div>
                            <div class='box mini box2'>
                                <div class='title'>Stable &amp; Secure</div>
                                <div class='content'>
                                Jaxboards maintains the highest standards of efficient, optimized software
                                that can handle anything you throw at it, and a support forum that will back
                                you up 100%.
                                </div>
                            </div>
                            <div class='box mini box3'>
                                <div class='title'>Real Time!</div>
                                    <div class='content'>
                                    In an age where communication is becoming ever more terse, we know how
                                    valuable you and your members' time is. Everything that is posted, messaged,
                                    or shared shows up instantly on the screen.<br><br>
                                    Save your refresh button.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id='copyright'>
                            JaxBoards &copy; 2007-{$currentYear}, All Rights Reserved
                    </div>
                </body>
            </html>
            HTML;
    }
}
