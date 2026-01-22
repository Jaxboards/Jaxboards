<?php

declare(strict_types=1);

namespace Jax\Routes;

use Carbon\Carbon;
use Jax\Config;
use Jax\Database\Database;
use Jax\Interfaces\Route;
use Jax\IPAddress;
use Jax\Mailer;
use Jax\Models\Member;
use Jax\Models\Stats;
use Jax\Models\Token;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function base64_encode;
use function curl_exec;
use function curl_init;
use function curl_setopt;
use function filter_var;
use function http_build_query;
use function is_string;
use function json_decode;
use function mb_strlen;
use function openssl_random_pseudo_bytes;
use function password_hash;
use function preg_match;
use function rawurlencode;
use function session_destroy;
use function session_unset;
use function trim;

use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_URL;
use const FILTER_VALIDATE_EMAIL;
use const JSON_THROW_ON_ERROR;
use const PASSWORD_DEFAULT;

final class LogReg implements Route
{
    private bool $registering = false;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly IPAddress $ipAddress,
        private readonly Mailer $mailer,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Router $router,
        private readonly Session $session,
        private readonly Template $template,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {}

    public function route($params): void
    {
        match ($this->request->both('path')) {
            'register' => $this->register(),
            'logout' => $this->logout(),
            'toggleInvisible' => $this->toggleInvisible(),
            'forgotPassword' => $this->forgotPassword(),
            default => $this->login(
                $this->request->asString->post('user'),
                $this->request->asString->post('pass'),
            ),
        };
    }

    private function didPassCaptcha(): bool
    {
        $hCaptchaSecret = $this->config->getSetting('hcaptcha_secret');

        if (!$hCaptchaSecret) {
            return true;
        }

        $data = [
            'secret' => $this->config->getSetting('hcaptcha_secret'),
            'response' => $this->request->post('h-captcha-response'),
        ];

        $verify = curl_init();
        curl_setopt($verify, CURLOPT_URL, 'https://hcaptcha.com/siteverify');
        curl_setopt($verify, CURLOPT_POST, true);
        curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($verify);
        if (!is_string($response)) {
            return false;
        }

        $responseData = json_decode($response, flags: JSON_THROW_ON_ERROR);

        return (bool) $responseData->success;
    }

    private function register(): void
    {
        $this->registering = true;

        $name = trim($this->request->asString->post('name') ?? '');
        $dispname = trim($this->request->asString->post('display_name') ?? '');
        $pass1 = $this->request->asString->post('pass1') ?? '';
        $pass2 = $this->request->asString->post('pass2') ?? '';
        $email = $this->request->asString->post('email') ?? '';

        $page = $this->template->render('logreg/register-form', [
            'hCaptchaSitekey' => $this->config->getSetting('hcaptcha_sitekey'),
        ]);

        // Show registration form.
        if ($this->request->post('register') === null) {
            if ($this->request->isJSUpdate()) {
                return;
            }

            $this->page->append('PAGE', $page);

            $this->page->command('update', 'page', $page);

            // HCaptcha does not work with the ajax stuff, do a normal page load for this page
            if ($this->request->isJSNewLocation()) {
                $this->page->command('reload');
            }

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
            mb_strlen($dispname) > 30 || mb_strlen(
                $name,
            ) > 30 => 'Display name and username must be under 30 characters.',
            ($badNameChars && preg_match($badNameChars, $name))
                || $this->textFormatting->blockhtml(
                    $name,
                ) !== $name => 'Invalid characters in username!',
            $badNameChars && preg_match(
                $badNameChars,
                $dispname,
            ) => 'Invalid characters in display name!',
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL,
            ) => "That isn't a valid email!",
            $this->ipAddress->isBanned() => 'You have been banned from registering on this board.',
            !$this->didPassCaptcha() => 'You did not pass the captcha. Are you a bot?',
            default => null,
        };

        if ($error !== null) {
            $this->page->command('error', $error);
            $this->page->append(
                'PAGE',
                $this->template->render('error', ['message' => $error]),
            );

            return;
        }

        // Are they attempting to use an existing username/display name?
        $member = Member::selectOne(
            'WHERE `name`=? OR `displayName`=?',
            $name,
            $dispname,
        );

        $error = match (true) {
            $member?->name === $name => 'That username is taken!',
            $member?->displayName === $dispname => 'That display name is already used by another member.',
            default => null,
        };

        if ($error !== null) {
            $this->page->command('error', $error);
            $this->page->append(
                'PAGE',
                $this->template->render('error', ['message' => $error]),
            );

            return;
        }

        $newMember = new Member();
        $newMember->displayName = $dispname;
        $newMember->email = $email;
        $newMember->groupID = $this->config->getSetting('membervalidation')
            ? 5
            : 1;
        $newMember->ip = $this->ipAddress->asBinary() ?? '';
        $newMember->joinDate = $this->database->datetime();
        $newMember->lastVisit = $this->database->datetime();
        $newMember->name = $name;
        $newMember->pass = password_hash(
            $pass1,
            PASSWORD_DEFAULT,
        );
        $newMember->insert();

        $stats = Stats::selectOne();
        if ($stats !== null) {
            ++$stats->members;
            $stats->last_register = $newMember->id;
            $stats->update();
        }

        $this->login($name, $pass1);
    }

    private function login(
        ?string $username = null,
        ?string $password = null,
    ): void {
        if ($username && $password) {
            if ($this->session->get()->isBot !== 0) {
                return;
            }

            $member = Member::selectOne(
                'WHERE `name`=?',
                $username,
            );

            $this->user->login($member->id ?? null, $password);

            if (!$this->user->isGuest()) {
                if ($this->request->post('popup') !== null) {
                    $this->page->command('closewindow', '#loginform');
                }

                $this->session->setPHPSessionValue(
                    'uid',
                    $this->user->get()->id,
                );
                $loginToken = base64_encode(openssl_random_pseudo_bytes(128));

                $token = new Token();
                $token->expires = $this->database->datetime(
                    Carbon::now('UTC')->addMonth()->getTimestamp(),
                );
                $token->token = $loginToken;
                $token->type = 'login';
                $token->uid = $this->user->get()->id;
                $token->insert();

                $this->request->setCookie(
                    'utoken',
                    $loginToken,
                    Carbon::now('UTC')->addMonth()->getTimestamp(),
                );
                $this->session->clean($this->user->get()->id);
                $this->session->set('uid', $this->user->get()->id);
                if ($this->registering) {
                    $this->router->redirect('index');
                } elseif ($this->request->isJSAccess()) {
                    $this->page->command('reload');
                } else {
                    $this->router->redirect('index');

                    return;
                }
            } else {
                $this->page->append(
                    'PAGE',
                    $this->template->render(
                        'error',
                        ['message' => 'Incorrect username/password'],
                    ),
                );
                $this->page->command('error', 'Incorrect username/password');
            }

            $this->session->erase('location');
        }

        $this->page->append(
            'PAGE',
            $this->template->render('logreg/login-form'),
        );
    }

    private function logout(): void
    {
        // Just make a new session rather than fuss with the old one,
        // to maintain users online.
        $uToken = $this->request->cookie('utoken');
        if ($uToken !== null) {
            $this->database->delete(
                'tokens',
                'WHERE `token`=?',
                $uToken,
            );
            $this->request->setCookie('utoken', null, -1);
        }

        $this->session->set('hide', 1);
        $this->session->applyChanges();
        session_unset();
        session_destroy();
        $this->template->reset(
            'USERBOX',
            $this->template->render(
                'global/userbox-logged-out',
            ),
        );
        $this->page->command(
            'update',
            'userbox',
            $this->template->render(
                'global/userbox-logged-out',
            ),
        );
        $this->page->command('softurl');
        $this->page->append(
            'PAGE',
            $this->template->render(
                'success',
                ['message' => 'Logged out successfully'],
            ),
        );
        if ($this->request->isJSAccess()) {
            return;
        }

        $this->login();
    }

    private function toggleInvisible(): void
    {
        $this->session->set('hide', $this->session->get()->hide !== 0 ? 0 : 1);

        $this->session->applyChanges();

        $this->page->command(
            'setstatus',
            $this->session->get()->hide !== 0 ? 'invisible' : 'online',
        );
        $this->page->command('softurl');
    }

    private function forgotPasswordHasToken(string $tokenId): ?string
    {
        $token = Token::selectOne(
            'WHERE `token`=? AND expires>=NOW()',
            $tokenId,
        );

        $pass1 = $this->request->asString->post('pass1');
        $pass2 = $this->request->asString->post('pass2');

        if ($token === null) {
            return 'This link has expired. Please try again.';
        }

        if ($pass1 === null || $pass2 === null) {
            return null;
        }

        if ($pass1 !== $pass2) {
            return 'The passwords did not match, please try again!';
        }

        // Get member.
        $member = Member::selectOne($token->uid);

        if ($member === null) {
            return 'The associated account could not be found';
        }

        $member->pass = password_hash(
            $pass1,
            PASSWORD_DEFAULT,
        );
        $member->update();

        // Delete all forgotpassword tokens for this user.
        $this->database->delete(
            'tokens',
            "WHERE `uid`=? AND `type`='forgotpassword'",
            $token->uid,
        );

        // Just making use of the way
        // registration redirects to the index.
        $this->registering = true;

        $this->login($member->name, $pass1);

        return null;
    }

    private function forgotPassword(): void
    {
        $uid = $this->request->asString->both('uid');
        $tokenId = $this->request->asString->both('tokenId');

        $page = '';

        if ($this->request->isJSUpdate()) {
            return;
        }

        $user = $this->request->asString->post('user');

        if ($tokenId !== null && $tokenId !== '') {
            $error = $this->forgotPasswordHasToken($tokenId);
            $page .= ($error !== null ? $this->page->error($error) : '')
                . $this->template->render(
                    'logreg/forgot-password2-form',
                    [
                        'hiddenFields' => Template::hiddenFormFields(
                            [
                                'id' => $tokenId,
                                'uid' => $uid ?? '',
                            ],
                        ),
                    ],
                );
        } else {
            if ($user) {
                $member = Member::selectOne(
                    'WHERE `name`=?',
                    $user,
                );

                if ($member === null) {
                    $error = "There is no user registered as <strong>{$user}</strong>, sure this is correct?";
                    $page .= $this->template->render(
                        'error',
                        ['message' => $error],
                    );
                } else {
                    // Generate token.
                    $forgotpasswordtoken
                        = base64_encode(openssl_random_pseudo_bytes(128));

                    $token = new Token();
                    $token->expires = $this->database->datetime(
                        Carbon::now('UTC')->getTimestamp() + 3600 * 24,
                    );
                    $token->token = $forgotpasswordtoken;
                    $token->type = 'forgotpassword';
                    $token->uid = $member->id;
                    $token->insert();

                    $link = $this->router->getRootURL() . $this->router->url(
                        'forgotPassword',
                        [
                            'uid' => $member->id,
                            'tokenId' => rawurlencode($forgotpasswordtoken),
                        ],
                    );
                    $mailResult = $this->mailer->mail(
                        $member->email,
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
                        $page .= $this->template->render(
                            'error',
                            [
                                'message' => 'There was a problem sending the email. '
                                    . 'Please contact the administrator.',
                            ],
                        );
                    } else {
                        $page .= $this->template->render(
                            'success',
                            [
                                'message' => 'An email has been sent to the email associated '
                                    . 'with this account. Please check your email and '
                                    . 'follow the instructions in order to recover '
                                    . 'your password.',
                            ],
                        );
                    }
                }
            }

            $page .= $this->template->render('logreg/forgot-password-form');
        }

        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
    }
}
