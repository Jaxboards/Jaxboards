<?php

declare(strict_types=1);

namespace Jax\Page;

use Carbon\Carbon;
use Jax\Database;
use Jax\DatabaseUtils;
use Jax\FileUtils;
use Jax\IPAddress;
use Jax\Models\Member;
use Jax\Request;
use Jax\ServiceConfig;
use Service\Blueprint;

use function count;
use function dirname;
use function filter_var;
use function gmdate;
use function header;
use function mb_strlen;
use function mb_strtolower;
use function password_hash;
use function preg_match;

use const FILTER_VALIDATE_EMAIL;
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
        private Blueprint $blueprint,
        private Database $database,
        private DatabaseUtils $databaseUtils,
        private FileUtils $fileUtils,
        private IPAddress $ipAddress,
        private Request $request,
        private ServiceConfig $serviceConfig,
    ) {}

    public function render(): void
    {
        if (!$this->serviceConfig->getSetting('service')) {
            echo 'Service mode not enabled';

            return;
        }

        $error = null;
        if ($this->request->post('submit') !== null) {
            $error = $this->signup();
        }

        $currentYear = gmdate('Y');
        $errorDisplay = $error ? "<div class='error'>{$error}</div>" : '';

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

    private function signup(): ?string
    {
        if ($this->request->post('post') !== null) {
            header('Location: https://test.' . $this->serviceConfig->getSetting('domain'));
        }

        $username = $this->request->asString->post('username');
        $password = $this->request->asString->post('password');
        $email = $this->request->asString->post('email');
        $boardURL = $this->request->asString->both('boardurl');
        $boardURLLowercase = mb_strtolower((string) $boardURL);
        if (
            !$boardURL
            || !$username
            || !$password
            || !$email
        ) {
            return 'all fields required.';
        }

        if (mb_strlen($boardURL) > 30) {
            return 'board url too long';
        }

        if ($boardURL === 'www') {
            return 'WWW is reserved.';
        }

        if (preg_match('@\W@', $boardURL)) {
            return 'board url needs to consist of letters, '
                . 'numbers, and underscore only';
        }

        $result = $this->database->select(
            ['id'],
            'directory',
            'WHERE `registrar_ip`=? AND `date`>?',
            $this->ipAddress->asBinary(),
            $this->database->datetime(Carbon::now('UTC')->subWeeks(1)->getTimestamp()),
        );
        if (count($this->database->arows($result)) > 3) {
            return 'You may only register 3 boards per week.';
        }

        $this->database->disposeresult($result);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'invalid email';
        }

        if (mb_strlen($username) > 50) {
            return 'username too long';
        }

        if (preg_match('@\W@', $username)) {
            return 'username needs to consist of letters, '
                . 'numbers, and underscore only';
        }

        $result = $this->database->select(
            ['id'],
            'directory',
            'WHERE `boardname`=?',
            $boardURL,
        );
        if ($this->database->arow($result)) {
            return' that board already exists';
        }

        $this->database->disposeresult($result);
        $boardPrefix = $boardURLLowercase . '_';

        $this->database->setPrefix('');
        // Add board to directory.
        $this->database->insert(
            'directory',
            [
                'boardname' => $boardURL,
                'date' => $this->database->datetime(),
                'referral' => $this->request->asString->both('r'),
                'registrar_email' => $email,
                'registrar_ip' => $this->ipAddress->asBinary(),
            ],
        );
        $this->database->setPrefix($boardPrefix);

        $this->databaseUtils->install();

        // Don't forget to create the admin.
        $member = new Member();
        $member->displayName = $username;
        $member->email = $email;
        $member->groupID = 2;
        $member->joinDate = $this->database->datetime();
        $member->lastVisit = $this->database->datetime();
        $member->name = $username;
        $member->pass = password_hash($password, PASSWORD_DEFAULT);
        $member->insert($this->database);

        $this->fileUtils->copyDirectory($this->blueprint->getDirectory(), dirname(__DIR__) . '/boards/' . $boardURLLowercase);

        header('Location: https://' . $boardURL . '.' . $this->serviceConfig->getSetting('domain'));

        return null;
    }
}
