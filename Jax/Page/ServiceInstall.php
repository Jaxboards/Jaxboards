<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\DatabaseUtils;
use Jax\FileSystem;
use Jax\IPAddress;
use Jax\Models\Member;
use Jax\Models\Service\Banlist;
use Jax\Models\Service\Directory;
use Jax\Request;
use Jax\ServiceConfig;

use function array_keys;
use function array_map;
use function filter_var;
use function gmdate;
use function header;
use function implode;
use function mb_strlen;
use function parse_url;
use function password_hash;
use function preg_match;
use function preg_replace;

use const FILTER_VALIDATE_EMAIL;
use const PASSWORD_DEFAULT;
use const PHP_URL_HOST;

final readonly class ServiceInstall
{
    public const FIELDS = [
        'admin_username' => [
            'name' => 'Admin Username',
            'placeholder' => 'admin',
            'type' => 'text',
        ],
        'admin_password' => [
            'name' => 'Admin Password',
            'type' => 'password',
        ],
        'admin_password_2' => [
            'name' => 'Re-Type Admin Password',
            'type' => 'password',
        ],
        'admin_email' => [
            'name' => 'Admin Email Address',
            'placeholder' => 'admin@example.com',
            'type' => 'text',
        ],
        'domain' => [
            'name' => 'Domain',
            'placeholder' => 'example.com',
            'type' => 'text',
        ],
        'sql_db' => [
            'name' => 'MySQL Database',
            'placeholder' => 'jaxboards',
            'type' => 'text',
        ],
        'sql_host' => [
            'name' => 'MySQL Host',
            'placeholder' => 'localhost',
            'type' => 'text',
            'value' => 'localhost',
        ],
        'sql_username' => [
            'name' => 'MySQL Username',
            'placeholder' => 'jaxboards',
            'type' => 'text',
        ],
        'sql_password' => [
            'name' => 'MySQL Password',
            'type' => 'password',
        ],
    ];

    public function __construct(
        private Database $database,
        private DatabaseUtils $databaseUtils,
        private FileSystem $fileSystem,
        private IPAddress $ipAddress,
        private Request $request,
        private ServiceConfig $serviceConfig,
    ) {}

    public function render(): string
    {
        if ($this->fileSystem->getFileInfo('config.php')->isFile()) {
            return implode('<br>', [
                'Detected config.php at root.',
                'Jaxboards has already been installed.',
                'If you would like to reinstall, delete the root config.',
            ]);
        }

        $errors = [];

        if ($this->request->post('submit') !== null) {
            $errors = $this->install();
            if ($errors === []) {
                return 'Redirecting...';
            }
        }

        $errorsHTML = implode('', array_map(static fn(string $error): string => "<div class='error'>{$error}</div>", $errors));
        $formFields = '';
        foreach (self::FIELDS as $field => $attributes) {
            $placeholder = $attributes['placeholder'] ?? '';
            $type = $attributes['type'];
            $value = $attributes['value'] ?? '';
            $formFields .= <<<HTML
                    <label for="{$field}">{$attributes['name']}:</label>
                        <input type="{$type}"
                            name="{$field}" id="{$field}"
                            placeholder="{$placeholder}"
                            value="{$value}"
                        >
                    <br>
                HTML;
        }

        $currentYear = gmdate('Y');

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
                <head>
                <link media="all" rel="stylesheet" href="./css/main.css" />
                <meta name="description" content="The world's very first instant forum." />
                <title>Jaxboards - The free AJAX powered forum host</title>
                </head>
                <body onload="if(top.location!=self.location) top.location=self.location">
                    <div id="container">
                        <div id="logo"></div>
                        <div id="content">
                            <div class="box">
                                <div class="content">
                                    <form id="signup" method="post">
                                        {$errorsHTML}
                                        <label for="service">Service Install</label>
                                        <input type="checkbox" name="service" value="1" id="service">
                                        <br>
                                        {$formFields}
                                        <div class="center">
                                            <input type="submit" name="submit" value="Start your service!" />
                                        </div>
                                    </form>
                                    <strong>Bring a Jaxboards service of your own to life.</strong>
                                    <br><br>
                                    <p>
                                        This installer sets up everything you need to get your very own JaxBoards
                                        service up and running! Make sure you have your database credentials ready
                                        and your webserver setup to send the root domain to the /Service directory
                                        and a windcard subdomain to the Jaxboards root directory.
                                    </p>
                                    <br>
                                </div>
                            </div>
                            <div class="flex">
                                <div class="box mini box1">
                                    <div class="title">Customizable</div>
                                    <div class="content">
                                        Jaxboards offers entirely new ways to make your forum look exactly the way you want:
                                        <ul>
                                            <li>Easy CSS</li>
                                            <li>Template access</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="box mini box2">
                                    <div class="title">Stable &amp; Secure</div>
                                    <div class="content">
                                        Jaxboards maintains the highest standards of efficient, optimized software
                                        that can handle anything you throw at it, and a support forum that will back
                                        you up 100%.
                                    </div>
                                </div>
                                <div class="box mini box3">
                                    <div class="title">Real Time!</div>
                                    <div class="content">
                                        In an age where communication is becoming ever more terse, we know how
                                        valuable you and your members' time is. Everything that is posted, messaged,
                                        or shared shows up instantly on the screen.
                                        <br><br>
                                        Save your refresh button.
                                    </div>
                                </div>
                            </div>
                            <div id="copyright">
                                JaxBoards &copy; 2007-{$currentYear}, All Rights Reserved
                            </div>
                        </div>
                    </div>
                </body>
            </html>
            HTML;
    }

    /**
     * Installs the software.
     *
     * @return array<string> any errors that happened during installation
     */
    private function install(): array
    {
        $errors = [];

        // Make sure each field is set.
        foreach (self::FIELDS as $field => $attributes) {
            if ($this->request->post($field)) {
                continue;
            }

            $errors[] = $attributes['name'] . ' must be filled in.';
        }

        $adminEmail = $this->request->asString->post('admin_email');
        $adminUsername = $this->request->asString->post('admin_username');
        $adminPassword = $this->request->asString->post('admin_password');
        $adminPassword2 = $this->request->asString->post('admin_password_2');
        $domain = $this->request->asString->post('domain');
        // Are we installing this the service way.
        $serviceMode = (bool) $this->request->post('service');
        $sqlHost = $this->request->asString->post('sql_host');
        $sqlUsername = $this->request->asString->post('sql_username');
        $sqlPassword = $this->request->asString->post('sql_password');
        $sqlDB = $this->request->asString->post('sql_db');

        // This only exists so the test can set it, although
        // it could be a form field one day (so the user can choose the driver)
        $sqlDriver = $this->request->asString->post('sql_driver');

        if (
            $domain !== null
            && !parse_url($domain, PHP_URL_HOST)
        ) {
            if (preg_match('@[^\w\-.]@', $domain)) {
                $errors[] = 'Invalid domain';
            } else {
                // Looks like we have a proper hostname,
                // just remove the leading www. if it exists.
                $domain = (string) preg_replace(
                    '/^www./',
                    '',
                    $domain,
                );
            }
        } else {
            // Remove www if it exists, also only grab host if url is entered.
            $domain = (string) preg_replace(
                '/^www./',
                '',
                (string) parse_url((string) $domain, PHP_URL_HOST),
            );
        }

        if ($adminPassword !== $adminPassword2) {
            $errors[] = 'Admin passwords do not match';
        }

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'invalid email';
        }

        if (mb_strlen((string) $adminUsername) > 50) {
            $errors[] = 'Admin username is too long';
        } elseif (preg_match('@\W@', (string) $adminUsername)) {
            $errors[] = 'Admin username needs to consist of letters,'
                . 'numbers, and underscore only';
        }

        if (!$sqlHost || !$sqlUsername || !$sqlPassword || !$sqlDB) {
            $errors[] = 'SQL host, username, password, database fields required';
        } else {
            $this->database->connect(
                host: $sqlHost,
                user: $sqlUsername,
                password: $sqlPassword,
                database: $sqlDB,
                driver: $sqlDriver,
            );
        }

        if ($errors !== []) {
            return $errors;
        }

        // Update with our settings.
        $this->serviceConfig->writeServiceConfig(
            [
                'boardname' => 'Jaxboards',
                'domain' => $domain,
                'mail_from' => "{$adminUsername} <{$adminEmail}>",
                'sql_db' => $sqlDB,
                'sql_host' => $sqlHost,
                'sql_username' => $sqlUsername,
                'sql_password' => $sqlPassword,
                'service' => $serviceMode,
                'prefix' => $serviceMode ? '' : 'jaxboards',
                'sql_prefix' => $serviceMode ? '' : 'jaxboards_',
            ],
        );

        if ($serviceMode) {
            // Create directory table.
            $queries = [
                'DROP TABLE IF EXISTS `directory`;',
                $this->databaseUtils->createTableQueryFromModel(new Directory()),
                'DROP TABLE IF EXISTS `banlist`;',
                $this->databaseUtils->createTableQueryFromModel(new Banlist()),
            ];

            foreach ($queries as $query) {
                $result = $this->database->query($query);
                $this->database->disposeresult($result);
            }

            // Create the text and support boards.
            $default_boards = [
                'support' => 'Support forums',
                'test' => 'Test forums',
            ];
        } else {
            // Create the board!
            $default_boards = [
                'jaxboards' => 'Jaxboards',
            ];
        }

        foreach (array_keys($default_boards) as $board) {
            $boardPrefix = $board . '_';
            $this->database->setPrefix($boardPrefix);

            if ($serviceMode) {
                $this->database->setPrefix('');
                // Add board to directory.
                $this->database->insert(
                    'directory',
                    [
                        'boardname' => $board,
                        'date' => $this->database->datetime(),
                        'referral' => $this->request->asString->both('r') ?? '',
                        'registrar_email' => $adminEmail,
                        'registrar_ip' => $this->ipAddress->asBinary(),
                    ],
                );
                $this->database->setPrefix($boardPrefix);
            }

            $this->databaseUtils->install();

            // Don't forget to create the admin.
            $member = new Member();
            $member->id = 1;
            $member->displayName = $adminUsername ?? '';
            $member->email = $adminEmail ?? '';
            $member->groupID = 2;
            $member->joinDate = $this->database->datetime();
            $member->lastVisit = $this->database->datetime();
            $member->name = $adminUsername ?? '';
            $member->pass = password_hash(
                (string) $adminPassword,
                PASSWORD_DEFAULT,
            );
            $member->insert();

            $this->fileSystem->copyDirectory('Service/blueprint', 'boards/' . $board);
        }

        if ($serviceMode) {
            header('Location: ./');
            return [];
        }

        header('Location: ../');

        return [];
    }
}
