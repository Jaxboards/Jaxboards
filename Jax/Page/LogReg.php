<?php

declare(strict_types=1);

namespace Jax\Page;

use Carbon\Carbon;
use Jax\Config;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function base64_encode;
use function filter_var;
use function mb_strlen;
use function mb_substr;
use function openssl_random_pseudo_bytes;
use function password_hash;
use function preg_match;
use function rawurlencode;
use function session_destroy;
use function session_unset;
use function trim;

use const FILTER_VALIDATE_EMAIL;
use const PASSWORD_DEFAULT;

final class LogReg
{
    private bool $registering = false;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly IPAddress $ipAddress,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly Template $template,
        private readonly User $user,
    ) {
        $this->template->loadMeta('logreg');
    }

    public function render(): void
    {
        match ((int) mb_substr((string) $this->request->both('act'), 6)) {
            1 => $this->register(),
            2 => $this->logout(),
            4 => $this->loginpopup(),
            5 => $this->toggleinvisible(),
            6 => $this->forgotpassword($this->request->both('uid'), $this->request->both('id')),
            default => $this->login($this->request->post('user'), $this->request->post('pass')),
        };
    }

    private function register(): void
    {
        $this->registering = true;

        if ($this->request->post('username') !== null) {
            $this->page->location('?');
        }

        $name = $this->request->post('name') !== null
            ? trim((string) $this->request->post('name'))
            : '';
        $dispname = $this->request->post('display_name') !== null
            ? trim((string) $this->request->post('display_name')) : '';
        $pass1 = $this->request->post('pass1') ?? '';
        $pass2 = $this->request->post('pass2') ?? '';
        $email = $this->request->post('email') ?? '';


        $p = $this->template->meta('register-form');

        // Show registration form.
        if ($this->request->post('register') === null) {
            if (!$this->request->isJSUpdate()) {
                $this->page->command('update', 'page', $p);
            }

            $this->page->append('PAGE', $p);

            return;
        }

        // Validate input and actually register the user.
        $badNameChars = $this->config->getSetting('badnamechars');
        $error = match (true) {
            $this->ipAddress->isServiceBanned() => 'You have been banned from registration on all boards. If'
                . ' you feel that this is in error, please contact the'
                . ' administrator.',
            !$name || !$dispname => 'Name and display name required.',
            $pass1 !== $pass2 => 'The passwords do not match.',
            mb_strlen($dispname) > 30 || mb_strlen($name) > 30 => 'Display name and username must be under 30 characters.',
            ($badNameChars && preg_match($badNameChars, $name))
                || $this->textFormatting->blockhtml($name) !== $name => 'Invalid characters in username!',
            $badNameChars && preg_match($badNameChars, $dispname) => 'Invalid characters in display name!',
            !filter_var($email, FILTER_VALIDATE_EMAIL) => "That isn't a valid email!",
            $this->ipAddress->isBanned() => 'You have been banned from registering on this board.',
            default => null,
        };

        if ($error !== null) {
            $this->page->command('alert', $error);
            $this->page->append('PAGE', $this->template->meta('error', $error));

            return;
        }

        // Are they attempting to use an existing username/display name?
        $dispname = $this->textFormatting->blockhtml($dispname);
        $name = $this->textFormatting->blockhtml($name);
        $result = $this->database->safeselect(
            ['name', 'display_name'],
            'members',
            'WHERE `name`=? OR `display_name`=?',
            $this->database->basicvalue($name),
            $this->database->basicvalue($dispname),
        );
        $member = $this->database->arow($result);
        $this->database->disposeresult($result);

        $error = match (true) {
            $member && $member['name'] === $name => 'That username is taken!',
            $member && $member['display_name'] === $dispname => 'That display name is already used by another member.',
            default => null,
        };

        if ($error !== null) {
            $this->page->command('alert', $error);
            $this->page->append('PAGE', $this->template->meta('error', $error));

            return;
        }

        // All clear!
        $this->database->safeinsert(
            'members',
            [
                'display_name' => $dispname,
                'email' => $email,
                'group_id' => $this->config->getSetting('membervalidation') ? 5 : 1,
                'ip' => $this->ipAddress->asBinary(),
                'join_date' => $this->database->datetime(),
                'last_visit' => $this->database->datetime(),
                'name' => $name,
                'pass' => password_hash(
                    (string) $pass1,
                    PASSWORD_DEFAULT,
                ),
                'posts' => 0,
                'wysiwyg' => 1,
            ],
        );
        $this->database->safespecial(
            <<<'SQL'
                UPDATE %t
                SET `members` = `members` + 1, `last_register` = ?
                SQL,
            ['stats'],
            $this->database->insertId(),
        );
        $this->login($name, $pass1);
    }

    private function login(
        ?string $username = null,
        ?string $password = null,
    ): void {
        if ($username && $password) {
            if ($this->session->get('is_bot')) {
                return;
            }

            $result = $this->database->safeselect(
                ['id'],
                'members',
                'WHERE `name`=?',
                $this->database->basicvalue($username),
            );
            $member = $this->database->arow($result);

            $user = $this->user->getUser($member['id'] ?? null, $password);

            if ($user) {
                if ($this->request->post('popup') !== null) {
                    $this->page->command('closewindow', '#loginform');
                }

                $this->session->setPHPSessionValue('uid', $user['id']);
                $loginToken = base64_encode(openssl_random_pseudo_bytes(128));
                $this->database->safeinsert(
                    'tokens',
                    [
                        'expires' => $this->database->datetime(Carbon::now()->getTimestamp() + 3600 * 24 * 30),
                        'token' => $loginToken,
                        'type' => 'login',
                        'uid' => $user['id'],
                    ],
                );

                $this->request->setCookie(
                    'utoken',
                    $loginToken,
                    Carbon::now()->getTimestamp() + 3600 * 24 * 30,
                );
                $this->session->clean($user['id']);
                $this->session->set('user', $username);
                $this->session->set('uid', $user['id']);
                $this->session->act();
                if ($this->registering) {
                    $this->page->command('location', '/');
                } elseif ($this->request->isJSAccess()) {
                    $this->page->command('reload');
                } else {
                    $this->page->location('?');
                }
            } else {
                $this->page->append(
                    'PAGE',
                    $this->template->meta('error', 'Incorrect username/password'),
                );
                $this->page->command('error', 'Incorrect username/password');
            }

            $this->session->erase('location');
        }

        $this->page->append('PAGE', $this->template->meta('login-form'));
    }

    private function logout(): void
    {
        // Just make a new session rather than fuss with the old one,
        // to maintain users online.
        if ($this->request->cookie('utoken') !== null) {
            $this->database->safedelete(
                'tokens',
                'WHERE `token`=?',
                $this->database->basicvalue($this->request->cookie('utoken')),
            );
            $this->request->setCookie('utoken', null, -1);
        }

        $this->session->set('hide', 1);
        $this->session->applyChanges();
        session_unset();
        session_destroy();
        $this->template->reset('USERBOX', $this->template->meta('userbox-logged-out'));
        $this->page->command('update', 'userbox', $this->template->meta('userbox-logged-out'));
        $this->page->command('softurl');
        $this->page->append('PAGE', $this->template->meta('success', 'Logged out successfully'));
        if ($this->request->isJSAccess()) {
            return;
        }

        $this->login();
    }

    private function loginpopup(): void
    {
        $this->page->command('softurl');
        $this->page->command(
            'window',
            [
                'content' => <<<'HTML'
                    <form method="post" data-ajax-form="resetOnSubmit">
                        <input type="hidden" name="act" value="logreg3" />
                        <input type="hidden" name="popup" value="1" />
                        <label for="user">Username:</label>
                        <input type="text" name="user" id="user" />
                        <br>
                        <label for="pass">
                            Password
                            (
                            <a href="?act=logreg6" title="Forgot your password?"
                                data-use-tooltip="true"
                                data-window-close="true">
                                ?
                            </a>
                            ):
                        </label>
                        <input type="password" name="pass" id="pass" />
                        <br>
                        <input type="submit" value="Login" />
                        <a href="?act=logreg1" data-window-close="true">Register</a>
                    </form>
                    HTML,
                'id' => 'loginform',
                'title' => 'Login',
            ],
        );
    }

    private function toggleinvisible(): void
    {
        $this->session->set('hide', $this->session->get('hide') ? 0 : 1);

        $this->session->applyChanges();

        $this->page->command('setstatus', $this->session->get('hide') !== 0 ? 'invisible' : 'online');
        $this->page->command('softurl');
    }

    private function forgotpassword(
        null|array|string $uid,
        null|array|string $id,
    ): void {
        $page = '';

        if ($this->request->isJSUpdate()) {
            return;
        }

        if ($id) {
            $result = $this->database->safeselect(
                'uid AS id',
                'tokens',
                'WHERE `token`=?
                AND `expires`>=NOW()',
                $this->database->basicvalue($id),
            );
            $udata = $this->database->arow($result);

            $this->database->disposeresult($result);

            if (!$udata) {
                $page = $this->template->meta('error', 'This link has expired. Please try again.');
            } elseif (
                $this->request->post('pass1')
                && $this->request->post('pass2')
            ) {
                if ($this->request->post('pass1') === $this->request->post('pass2')) {
                    $this->database->safeupdate(
                        'members',
                        [
                            'pass' => password_hash(
                                (string) $this->request->post('pass1'),
                                PASSWORD_DEFAULT,
                            ),
                        ],
                        Database::WHERE_ID_EQUALS,
                        $this->database->basicvalue($udata['id']),
                    );
                    // Delete all forgotpassword tokens for this user.
                    $this->database->safedelete(
                        'tokens',
                        "WHERE `uid`=? AND `type`='forgotpassword'",
                        $this->database->basicvalue($udata['id']),
                    );

                    // Get username.
                    $result = $this->database->safeselect(
                        ['id', 'name'],
                        'members',
                        Database::WHERE_ID_EQUALS,
                        $this->database->basicvalue($udata['id']),
                    );
                    $udata = $this->database->arow($result);

                    // Just making use of the way
                    // registration redirects to the index.
                    $this->registering = true;

                    $this->login($udata['name'], $this->request->post('pass1'));

                    return;
                }

                $page .= $this->template->meta(
                    'error',
                    'The passwords did not match, please try again!',
                );
            } else {
                $page .= $this->template->meta(
                    'forgot-password2-form',
                    $this->jax->hiddenFormFields(
                        [
                            'act' => 'logreg6',
                            'id' => $id,
                            'uid' => $uid,
                        ],
                    ),
                );
            }
        } else {
            if ($this->request->post('user')) {
                $result = $this->database->safeselect(
                    ['id', 'email'],
                    'members',
                    'WHERE `name`=?',
                    $this->database->basicvalue($this->request->post('user')),
                );
                $error = null;
                if (!($udata = $this->database->arow($result))) {
                    $error = 'There is no user registered as <strong>'
                        . $this->request->both('user')
                        . '</strong>, sure this is correct?';
                }

                $this->database->disposeresult($result);

                if ($error !== null) {
                    $page .= $this->template->meta('error', $error);
                } else {
                    // Generate token.
                    $forgotpasswordtoken
                        = base64_encode(openssl_random_pseudo_bytes(128));
                    $this->database->safeinsert(
                        'tokens',
                        [
                            'expires' => $this->database->datetime(Carbon::now()->getTimestamp() + 3600 * 24),
                            'token' => $forgotpasswordtoken,
                            'type' => 'forgotpassword',
                            'uid' => $udata['id'],
                        ],
                    );
                    $link = $this->domainDefinitions->getBoardURL() . '?act=logreg6&uid='
                        . $udata['id'] . '&id=' . rawurlencode($forgotpasswordtoken);
                    $mailResult = $this->jax->mail(
                        $udata['email'],
                        'Recover Your Password!',
                        <<<HTML
                            You have received this email because a password
                            request was received at {BOARDLINK}
                            <br>
                            <br>
                            If you did not request a password change, simply
                            ignore this email and no actions will be taken.
                            If you would like to change your password, please
                            visit the following page and follow the on-screen
                            instructions:
                            <a href='{$link}'>{$link}</a>
                            <br>
                            <br>
                            Thanks!
                            HTML,
                    );

                    if (!$mailResult) {
                        $page .= $this->template->meta(
                            'error',
                            'There was a problem sending the email. '
                                . 'Please contact the administrator.',
                        );
                    } else {
                        $page .= $this->template->meta(
                            'success',
                            'An email has been sent to the email associated '
                                . 'with this account. Please check your email and '
                                . 'follow the instructions in order to recover '
                                . 'your password.',
                        );
                    }
                }
            }

            $page .= $this->template->meta(
                'forgot-password-form',
                $this->request->isJSAccess()
                    ? $this->jax->hiddenFormFields(
                        [
                            'act' => 'logreg6',
                        ],
                    ) : '',
            );
        }

        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
    }
}
