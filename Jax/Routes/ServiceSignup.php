<?php

declare(strict_types=1);

namespace Jax\Routes;

use Carbon\Carbon;
use Jax\Database\Database;
use Jax\Database\Utils as DatabaseUtils;
use Jax\FileSystem;
use Jax\IPAddress;
use Jax\Models\Member;
use Jax\Models\Service\Directory;
use Jax\Request;
use Jax\ServiceConfig;
use Jax\Template;

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
        private Database $database,
        private DatabaseUtils $databaseUtils,
        private FileSystem $fileSystem,
        private IPAddress $ipAddress,
        private Request $request,
        private ServiceConfig $serviceConfig,
        private Template $template,
    ) {
        $this->template->setThemePath('Service');
    }

    public function render(): string
    {
        if (!$this->serviceConfig->hasInstalled()) {
            return 'Jaxboards not installed!';
        }

        if (!$this->serviceConfig->getSetting('service')) {
            return 'Service mode not enabled';
        }

        $error = null;
        if ($this->request->post('submit') !== null) {
            $error = $this->signup();
        }

        return $this->template->render(
            'signup',
            [
                'error' => $error,
                'domain' => $this->serviceConfig->getSetting('domain')
            ]
        );
    }

    private function signup(): string
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

        $this->database->setPrefix('');

        $directoryCount = Directory::count(
            'WHERE `registrarIP`=? AND `date`>?',
            $this->ipAddress->asBinary(),
            $this->database->datetime(Carbon::now('UTC')->subWeeks(1)->getTimestamp()),
        );

        if ($directoryCount > 3) {
            return 'You may only register 3 boards per week.';
        }

        $result = Directory::selectOne('WHERE `boardname`=?', $boardURL);
        if ($result !== null) {
            return ' that board already exists';
        }

        $directory = new Directory();
        $directory->boardname = $boardURL;
        $directory->date = $this->database->datetime();
        $directory->referral = $this->request->asString->both('r');
        $directory->registrarEmail = $email;
        $directory->registrarIP = $this->ipAddress->asBinary();
        $directory->insert();

        $this->database->setPrefix($boardURLLowercase . '_');

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
        $member->insert();

        $this->fileSystem->copyDirectory('Service/blueprint', 'boards/' . $boardURLLowercase);

        $redirect = 'https://' . $boardURL . '.' . $this->serviceConfig->getSetting('domain');
        header("Location: {$redirect}");

        return "Error redirecting you to Location: {$redirect}";
    }
}
