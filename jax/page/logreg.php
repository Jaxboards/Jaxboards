<?php

declare(strict_types=1);

namespace Jax\Page;

use Exception;
use Jax\Config;
use Jax\IPAddress;
use Jax\Jax;

use function array_key_exists;
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
    public $registering = false;

    /**
     * @var Config
     */
    public function __construct(
        /**
         * @var IPAddress
         */
        private Config $config,
        private IPAddress $ipAddress,
    ) {
        global $PAGE;

        $PAGE->loadmeta('logreg');
    }

    public function route(): void
    {
        global $JAX,$PAGE;

        match ((int) mb_substr((string) $JAX->b['act'], 6)) {
            1 => $this->register(),
            2 => $this->logout(),
            4 => $this->loginpopup(),
            5 => $this->toggleinvisible(),
            6 => $this->forgotpassword($JAX->b['uid'], $JAX->b['id']),
            default => $this->login($JAX->p['user'], $JAX->p['pass']),
        };
    }

    public function register()
    {
        global $CFG,$PAGE,$JAX,$DB;

        $this->registering = true;

        if (isset($JAX->p['username']) && $JAX->p['username']) {
            $PAGE->location('?');
        }

        $name = isset($JAX->p['name']) ? trim($JAX->p['name']) : '';
        $dispname = isset($JAX->p['display_name'])
            ? trim($JAX->p['display_name']) : '';
        $pass1 = $JAX->p['pass1'] ?? '';
        $pass2 = $JAX->p['pass2'] ?? '';
        $email = $JAX->p['email'] ?? '';

        $recaptcha = '';
        if ($CFG['recaptcha']) {
            $recaptcha = $PAGE->meta('anti-spam', $CFG['recaptcha']['public_key']);
        }

        $p = $PAGE->meta('register-form', $recaptcha);

        // Show registration form.
        if (!isset($JAX->p['register'])) {
            if (!$PAGE->jsupdate) {
                $PAGE->JS('update', 'page', $p);
            }

            return $PAGE->append('PAGE', $p);
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

            $badNameChars = $CFG['badnamechars'];
            if (
                ($badNameChars && preg_match($badNameChars, $name))
                || $JAX->blockhtml($name) !== $name
            ) {
                throw new Exception('Invalid characters in username!');
            }

            if ($badNameChars && preg_match($badNameChars, $dispname)) {
                throw new Exception('Invalid characters in display name!');
            }

            if (!$JAX->isemail($email)) {
                throw new Exception("That isn't a valid email!");
            }

            if ($this->ipAddress->isBanned()) {
                throw new Exception('You have been banned from registering on this board.');
            }

            if (!$this->isHuman()) {
                throw new Exception('reCAPTCHA failed. Are you a bot?');
            }

            // Are they attempting to use an existing username/display name?
            $dispname = $JAX->blockhtml($dispname);
            $name = $JAX->blockhtml($name);
            $result = $DB->safeselect(
                ['name', 'display_name'],
                'members',
                'WHERE `name`=? OR `display_name`=?',
                $DB->basicvalue($name),
                $DB->basicvalue($dispname),
            );
            $member = $DB->arow($result);
            $DB->disposeresult($result);
            if ($member) {
                if ($member['name'] === $name) {
                    throw new Exception('That username is taken!');
                }

                if ($member['display_name'] === $dispname) {
                    throw new Exception('That display name is already used by another member.');
                }
            }


            // All clear!
            $DB->safeinsert(
                'members',
                [
                    'display_name' => $dispname,
                    'email' => $email,
                    'group_id' => array_key_exists('membervalidation', $CFG) && $CFG['membervalidation'] ? 5 : 1,
                    'ip' => $this->ipAddress->asBinary(),
                    'join_date' => gmdate('Y-m-d H:i:s'),
                    'last_visit' => gmdate('Y-m-d H:i:s'),
                    'name' => $name,
                    'pass' => password_hash(
                        $pass1,
                        PASSWORD_DEFAULT,
                    ),
                    'posts' => 0,
                    'wysiwyg' => 1,
                ],
            );
            $DB->safespecial(
                <<<'EOT'
                    UPDATE %t
                    SET `members` = `members` + 1, `last_register` = ?
                    EOT
                ,
                ['stats'],
                $DB->insert_id(1),
            );
            $this->login($name, $pass1);
        } catch (Exception $e) {
            $e = $e->getMessage();
            $PAGE->JS('alert', $e);
            $PAGE->append('page', $PAGE->meta('error', $e));
        }

        return null;
    }

    public function login($u = false, $p = false): void
    {
        global $PAGE,$JAX,$SESS,$DB,$_SESSION;
        if ($u && $p) {
            if ($SESS->is_bot) {
                return;
            }

            $result = $DB->safeselect(
                ['id'],
                'members',
                'WHERE `name`=?',
                $DB->basicvalue($u),
            );
            $user = $DB->arow($result);
            $u = $user['id'] ?? 0;

            $user = $DB->getUser($u, $p);

            if ($user) {
                if (isset($JAX->p['popup']) && $JAX->p['popup']) {
                    $PAGE->JS('closewindow', '#loginform');
                }

                $_SESSION['uid'] = $user['id'];
                $logintoken = base64_encode(openssl_random_pseudo_bytes(128));
                $DB->safeinsert(
                    'tokens',
                    [
                        'expires' => gmdate('Y-m-d H:i:s', time() + 3600 * 24 * 30),
                        'token' => $logintoken,
                        'type' => 'login',
                        'uid' => $user['id'],
                    ],
                );

                $JAX->setCookie(
                    ['utoken' => $logintoken],
                    time() + 3600 * 24 * 30,
                );
                $SESS->clean($user['id']);
                $SESS->user = $u;
                $SESS->uid = $user['id'];
                $SESS->act();
                $perms = $DB->getPerms($user['group_id']);
                if ($this->registering) {
                    $PAGE->JS('location', '/');
                } elseif ($PAGE->jsaccess) {
                    $PAGE->JS('reload');
                } else {
                    $PAGE->location('?');
                }
            } else {
                $PAGE->append(
                    'page',
                    $PAGE->meta('error', 'Incorrect username/password'),
                );
                $PAGE->JS('error', 'Incorrect username/password');
            }

            $SESS->erase('location');
        }

        $PAGE->append('page', $PAGE->meta('login-form'));
    }

    public function logout(): void
    {
        global $DB,$PAGE,$JAX,$SESS;
        // Just make a new session rather than fuss with the old one,
        // to maintain users online.
        if (isset($JAX->c['utoken'])) {
            $DB->safedelete(
                'tokens',
                'WHERE `token`=?',
                $DB->basicvalue($JAX->c['utoken']),
            );
            unset($JAX->c['utoken']);
            $JAX->setCookie(
                [
                    'utoken' => null,
                ],
                -1,
            );
        }

        $SESS->hide = 1;
        $SESS->applyChanges();
        $SESS->getSess(false);
        session_unset();
        session_destroy();
        $PAGE->reset('USERBOX', $PAGE->meta('userbox-logged-out'));
        $PAGE->JS('update', 'userbox', $PAGE->meta('userbox-logged-out'));
        $PAGE->JS('softurl');
        $PAGE->append('page', $PAGE->meta('success', 'Logged out successfully'));
        if ($PAGE->jsaccess) {
            return;
        }

        $this->login();
    }

    public function loginpopup(): void
    {
        global $PAGE;
        $PAGE->JS('softurl');
        $PAGE->JS(
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
        global $PAGE,$SESS;
        $SESS->hide = $SESS->hide ? 0 : 1;

        $SESS->applyChanges();

        $PAGE->JS('setstatus', $SESS->hide !== 0 ? 'invisible' : 'online');
        $PAGE->JS('softurl');
    }

    public function forgotpassword($uid, $id): void
    {
        global $PAGE,$JAX,$DB;
        $page = '';

        if ($PAGE->jsupdate && empty($JAX->p)) {
            return;
        }

        if ($id) {
            $result = $DB->safeselect(
                'uid AS id',
                'tokens',
                'WHERE `token`=?
                AND `expires`>=NOW()',
                $DB->basicvalue($id),
            );
            $udata = $DB->arow($result);
            if (!$udata) {
                $e = 'This link has expired. Please try again.';
            }

            $DB->disposeresult($result);

            if ($e !== '') {
                $page = $PAGE->meta('error', $e);
            } elseif ($JAX->p['pass1'] && $JAX->p['pass2']) {
                if ($JAX->p['pass1'] === $JAX->p['pass2']) {
                    $DB->safeupdate(
                        'members',
                        [
                            'pass' => password_hash(
                                (string) $JAX->p['pass1'],
                                PASSWORD_DEFAULT,
                            ),
                        ],
                        'WHERE `id`=?',
                        $DB->basicvalue($udata['id']),
                    );
                    // Delete all forgotpassword tokens for this user.
                    $DB->safedelete(
                        'tokens',
                        "WHERE `uid`=? AND `type`='forgotpassword'",
                        $DB->basicvalue($udata['id']),
                    );

                    // Get username.
                    $result = $DB->safeselect(
                        ['id', 'name'],
                        'members',
                        'WHERE `id`=?',
                        $DB->basicvalue($udata['id']),
                    );
                    $udata = $DB->arow($result);

                    // Just making use of the way
                    // registration redirects to the index.
                    $this->registering = true;

                    $this->login($udata['name'], $JAX->p['pass1']);

                    return;
                }

                $page .= $PAGE->meta(
                    'error',
                    'The passwords did not match, please try again!',
                );
            } else {
                $page .= $PAGE->meta(
                    'forgot-password2-form',
                    Jax::hiddenFormFields(
                        [
                            'act' => 'logreg6',
                            'id' => $id,
                            'uid' => $uid,
                        ],
                    ),
                );
            }
        } else {
            if ($JAX->p['user']) {
                $result = $DB->safeselect(
                    ['id', 'email'],
                    'members',
                    'WHERE `name`=?',
                    $DB->basicvalue($JAX->p['user']),
                );
                if (!($udata = $DB->arow($result))) {
                    $e = 'There is no user registered as <strong>'
                        . $JAX->b['user']
                        . '</strong>, sure this is correct?';
                }

                $DB->disposeresult($result);

                if ($e !== '0') {
                    $page .= $PAGE->meta('error', $e);
                } else {
                    // Generate token.
                    $forgotpasswordtoken
                        = base64_encode(openssl_random_pseudo_bytes(128));
                    $DB->safeinsert(
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
                    $mailResult = $JAX->mail(
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
                        $page .= $PAGE->meta(
                            'error',
                            'There was a problem sending the email. '
                            . 'Please contact the administrator.',
                        );
                    } else {
                        $page .= $PAGE->meta(
                            'success',
                            'An email has been sent to the email associated '
                            . 'with this account. Please check your email and '
                            . 'follow the instructions in order to recover '
                            . 'your password.',
                        );
                    }
                }
            }

            $page .= $PAGE->meta(
                'forgot-password-form',
                $PAGE->jsaccess
                ? Jax::hiddenFormFields(
                    [
                        'act' => 'logreg6',
                    ],
                ) : '',
            );
        }

        $PAGE->append('PAGE', $page);
        $PAGE->JS('update', 'page', $page);
    }

    private function isHuman()
    {
        global $CFG,$JAX;

        if ($CFG['recaptcha']) {
            // Validate reCAPTCHA.
            $url = 'https://www.google.com/recaptcha/api/siteverify';
            $fields = [
                'response' => $JAX->p['g-recaptcha-response'],
                'secret' => $CFG['recaptcha']['private_key'],
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
