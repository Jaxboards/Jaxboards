<?php

declare(strict_types=1);

namespace Jax\Page;

use Exception;
use Jax\Config;
use Jax\Database;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Page;
use Jax\Session;
use Jax\TextFormatting;
use Jax\User;

use function base64_encode;
use function count;
use function curl_exec;
use function curl_init;
use function curl_setopt;
use function gmdate;
use function json_decode;
use function mb_strlen;
use function mb_substr;
use function openssl_random_pseudo_bytes;
use function password_hash;
use function preg_match;
use function rawurlencode;
use function rtrim;
use function session_destroy;
use function session_unset;
use function time;
use function trim;
use function urlencode;

use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_URL;
use const PASSWORD_DEFAULT;

final class LogReg
{
    private $registering = false;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly IPAddress $ipAddress,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {
        $this->page->loadmeta('logreg');
    }

    public function route(): void
    {
        match ((int) mb_substr((string) $this->jax->b['act'], 6)) {
            1 => $this->register(),
            2 => $this->logout(),
            4 => $this->loginpopup(),
            5 => $this->toggleinvisible(),
            6 => $this->forgotpassword($this->jax->b['uid'], $this->jax->b['id']),
            default => $this->login($this->jax->p['user'], $this->jax->p['pass']),
        };
    }

    public function register()
    {
        $this->registering = true;

        if (isset($this->jax->p['username']) && $this->jax->p['username']) {
            $this->page->location('?');
        }

        $name = isset($this->jax->p['name'])
            ? trim((string) $this->jax->p['name'])
            : '';
        $dispname = isset($this->jax->p['display_name'])
            ? trim((string) $this->jax->p['display_name']) : '';
        $pass1 = $this->jax->p['pass1'] ?? '';
        $pass2 = $this->jax->p['pass2'] ?? '';
        $email = $this->jax->p['email'] ?? '';

        $recaptcha = '';
        if ($this->config->getSetting('recaptcha')) {
            $recaptcha = $this->page->meta('anti-spam', $this->config->getSetting('recaptcha')['public_key']);
        }

        $p = $this->page->meta('register-form', $recaptcha);

        // Show registration form.
        if (!isset($this->jax->p['register'])) {
            if (!$this->page->jsupdate) {
                $this->page->JS('update', 'page', $p);
            }

            return$this->page->append('PAGE', $p);
        }

        // Validate input and actually register the user.
        try {
            if ($this->ipAddress->isServiceBanned()) {
                throw new Exception(
                    'You have been banned from registration on all boards. If'
                    . ' you feel that this is in error, please contact the'
                    . ' administrator.',
                );
            }

            if (!$name || !$dispname) {
                throw new Exception('Name and display name required.');
            }

            if ($pass1 !== $pass2) {
                throw new Exception('The passwords do not match.');
            }

            if (mb_strlen($dispname) > 30 || mb_strlen($name) > 30) {
                throw new Exception('Display name and username must be under 30 characters.');
            }

            $badNameChars = $this->config->getSetting('badnamechars');
            if (
                ($badNameChars && preg_match($badNameChars, $name))
                || $this->textFormatting->blockhtml($name) !== $name
            ) {
                throw new Exception('Invalid characters in username!');
            }

            if ($badNameChars && preg_match($badNameChars, $dispname)) {
                throw new Exception('Invalid characters in display name!');
            }

            if (!$this->jax->isemail($email)) {
                throw new Exception("That isn't a valid email!");
            }

            if ($this->ipAddress->isBanned()) {
                throw new Exception('You have been banned from registering on this board.');
            }

            if (!$this->isHuman()) {
                throw new Exception('reCAPTCHA failed. Are you a bot?');
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
            if ($member) {
                if ($member['name'] === $name) {
                    throw new Exception('That username is taken!');
                }

                if ($member['display_name'] === $dispname) {
                    throw new Exception('That display name is already used by another member.');
                }
            }


            // All clear!
            $this->database->safeinsert(
                'members',
                [
                    'display_name' => $dispname,
                    'email' => $email,
                    'group_id' => $this->config->getSetting('membervalidation') ? 5 : 1,
                    'ip' => $this->ipAddress->asBinary(),
                    'join_date' => gmdate('Y-m-d H:i:s'),
                    'last_visit' => gmdate('Y-m-d H:i:s'),
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
                <<<'EOT'
                    UPDATE %t
                    SET `members` = `members` + 1, `last_register` = ?
                    EOT
                ,
                ['stats'],
                $this->database->insert_id(),
            );
            $this->login($name, $pass1);
        } catch (Exception $e) {
            $e = $e->getMessage();
            $this->page->JS('alert', $e);
            $this->page->append('page', $this->page->meta('error', $e));
        }

        return null;
    }

    public function login($u = false, $p = false): void
    {
        if ($u && $p) {
            if ($this->session->is_bot) {
                return;
            }

            $result = $this->database->safeselect(
                ['id'],
                'members',
                'WHERE `name`=?',
                $this->database->basicvalue($u),
            );
            $user = $this->database->arow($result);
            $u = $user['id'] ?? 0;

            $user = $this->user->getUser($u, $p);

            if ($user) {
                if (isset($this->jax->p['popup']) && $this->jax->p['popup']) {
                    $this->page->JS('closewindow', '#loginform');
                }

                $_SESSION['uid'] = $user['id'];
                $logintoken = base64_encode(openssl_random_pseudo_bytes(128));
                $this->database->safeinsert(
                    'tokens',
                    [
                        'expires' => gmdate('Y-m-d H:i:s', time() + 3600 * 24 * 30),
                        'token' => $logintoken,
                        'type' => 'login',
                        'uid' => $user['id'],
                    ],
                );

                $this->jax->setCookie(
                    ['utoken' => $logintoken],
                    time() + 3600 * 24 * 30,
                );
                $this->session->clean($user['id']);
                $this->session->user = $u;
                $this->session->uid = $user['id'];
                $this->session->act();
                $perms = $this->user->getPerms($user['group_id']);
                if ($this->registering) {
                    $this->page->JS('location', '/');
                } elseif ($this->page->jsaccess) {
                    $this->page->JS('reload');
                } else {
                    $this->page->location('?');
                }
            } else {
                $this->page->append(
                    'page',
                    $this->page->meta('error', 'Incorrect username/password'),
                );
                $this->page->JS('error', 'Incorrect username/password');
            }

            $this->session->erase('location');
        }

        $this->page->append('page', $this->page->meta('login-form'));
    }

    public function logout(): void
    {
        // Just make a new session rather than fuss with the old one,
        // to maintain users online.
        if (isset($this->jax->c['utoken'])) {
            $this->database->safedelete(
                'tokens',
                'WHERE `token`=?',
                $this->database->basicvalue($this->jax->c['utoken']),
            );
            unset($this->jax->c['utoken']);
            $this->jax->setCookie(
                [
                    'utoken' => null,
                ],
                -1,
            );
        }

        $this->session->hide = 1;
        $this->session->applyChanges();
        $this->session->getSess(false);
        session_unset();
        session_destroy();
        $this->page->reset('USERBOX', $this->page->meta('userbox-logged-out'));
        $this->page->JS('update', 'userbox', $this->page->meta('userbox-logged-out'));
        $this->page->JS('softurl');
        $this->page->append('page', $this->page->meta('success', 'Logged out successfully'));
        if ($this->page->jsaccess) {
            return;
        }

        $this->login();
    }

    public function loginpopup(): void
    {
        $this->page->JS('softurl');
        $this->page->JS(
            'window',
            [
                'content' => <<<'EOT'
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
                    EOT,
                'id' => 'loginform',
                'title' => 'Login',
                'useoverlay' => 1,
            ],
        );
    }

    public function toggleinvisible(): void
    {
        $this->session->hide = $this->session->hide ? 0 : 1;

        $this->session->applyChanges();

        $this->page->JS('setstatus', $this->session->hide !== 0 ? 'invisible' : 'online');
        $this->page->JS('softurl');
    }

    public function forgotpassword($uid, $id): void
    {
        $page = '';

        if ($this->page->jsupdate && empty($this->jax->p)) {
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
            if (!$udata) {
                $e = 'This link has expired. Please try again.';
            }

            $this->database->disposeresult($result);

            if ($e !== '') {
                $page = $this->page->meta('error', $e);
            } elseif ($this->jax->p['pass1'] && $this->jax->p['pass2']) {
                if ($this->jax->p['pass1'] === $this->jax->p['pass2']) {
                    $this->database->safeupdate(
                        'members',
                        [
                            'pass' => password_hash(
                                (string) $this->jax->p['pass1'],
                                PASSWORD_DEFAULT,
                            ),
                        ],
                        'WHERE `id`=?',
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
                        'WHERE `id`=?',
                        $this->database->basicvalue($udata['id']),
                    );
                    $udata = $this->database->arow($result);

                    // Just making use of the way
                    // registration redirects to the index.
                    $this->registering = true;

                    $this->login($udata['name'], $this->jax->p['pass1']);

                    return;
                }

                $page .= $this->page->meta(
                    'error',
                    'The passwords did not match, please try again!',
                );
            } else {
                $page .= $this->page->meta(
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
            if ($this->jax->p['user']) {
                $result = $this->database->safeselect(
                    ['id', 'email'],
                    'members',
                    'WHERE `name`=?',
                    $this->database->basicvalue($this->jax->p['user']),
                );
                if (!($udata = $this->database->arow($result))) {
                    $e = 'There is no user registered as <strong>'
                        . $this->jax->b['user']
                        . '</strong>, sure this is correct?';
                }

                $this->database->disposeresult($result);

                if ($e !== '0') {
                    $page .= $this->page->meta('error', $e);
                } else {
                    // Generate token.
                    $forgotpasswordtoken
                        = base64_encode(openssl_random_pseudo_bytes(128));
                    $this->database->safeinsert(
                        'tokens',
                        [
                            'expires' => gmdate('Y-m-d H:i:s', time() + 3600 * 24),
                            'token' => $forgotpasswordtoken,
                            'type' => 'forgotpassword',
                            'uid' => $udata['id'],
                        ],
                    );
                    $link = BOARDURL . '?act=logreg6&uid='
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
                        $page .= $this->page->meta(
                            'error',
                            'There was a problem sending the email. '
                            . 'Please contact the administrator.',
                        );
                    } else {
                        $page .= $this->page->meta(
                            'success',
                            'An email has been sent to the email associated '
                            . 'with this account. Please check your email and '
                            . 'follow the instructions in order to recover '
                            . 'your password.',
                        );
                    }
                }
            }

            $page .= $this->page->meta(
                'forgot-password-form',
                $this->page->jsaccess
                ? $this->jax->hiddenFormFields(
                    [
                        'act' => 'logreg6',
                    ],
                ) : '',
            );
        }

        $this->page->append('PAGE', $page);
        $this->page->JS('update', 'page', $page);
    }

    private function isHuman()
    {
        if ($this->config->getSetting('recaptcha')) {
            // Validate reCAPTCHA.
            $url = 'https://www.google.com/recaptcha/api/siteverify';
            $fields = [
                'response' => $this->jax->p['g-recaptcha-response'],
                'secret' => $this->config->getSetting('recaptcha')['private_key'],
            ];

            $fields_string = '';
            foreach ($fields as $k => $v) {
                $fields_string .= $k . '=' . urlencode((string) $v) . '&';
            }

            $fields_string = rtrim($fields_string, '&');

            $curl_request = curl_init();
            // Set the url, number of POST vars, POST data.
            curl_setopt($curl_request, CURLOPT_URL, $url);
            curl_setopt($curl_request, CURLOPT_POST, count($fields));
            curl_setopt($curl_request, CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, true);

            // Execute post.
            $result = json_decode(curl_exec($curl_request), true);

            return $result['success'];
        }

        // If recaptcha is not configured, we have to assume that they are in fact human.
        return true;
    }
}
