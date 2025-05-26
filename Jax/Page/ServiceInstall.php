<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\FileUtils;
use Jax\IPAddress;
use Jax\Request;
use Jax\ServiceConfig;
use Service\Blueprint;

use function array_keys;
use function array_map;
use function dirname;
use function filter_var;
use function gmdate;
use function header;
use function implode;
use function mb_strlen;
use function mb_substr;
use function mkdir;
use function parse_url;
use function password_hash;
use function preg_match;
use function preg_replace;
use function str_replace;
use function trim;

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
        private readonly Blueprint $blueprint,
        private readonly Database $database,
        private readonly FileUtils $fileUtils,
        private readonly IPAddress $ipAddress,
        private readonly Request $request,
        private readonly ServiceConfig $serviceConfig,
    ) {}

    public function render(): void
    {
        $errors = [];

        if ($this->request->post('submit') !== null) {
            $errors = $this->install();
        }

        $errorsHTML = implode('', array_map(static fn($error): string => "<div class='error'>{$error}</div>", $errors));
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

        echo <<<HTML
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
                                        <input type="checkbox" name="service" value="1" id="service" checked>
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
        $domain = $this->request->asString->post('domain');
        // Are we installing this the service way.
        $service = (bool) $this->request->post('service');
        $sqlHost = $this->request->asString->post('sql_host');
        $sqlUsername = $this->request->asString->post('sql_username');
        $sqlPassword = $this->request->asString->post('sql_password');
        $sqlDB = $this->request->asString->post('sql_db');

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

        if ($adminPassword !== $this->request->asString->post('admin_password_2')) {
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
            $this->database->connect($sqlHost, $sqlUsername, $sqlPassword, $sqlDB);
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
                'service' => $service,
                'prefix' => $service ? '' : 'jaxboards',
                'sql_prefix' => $service ? '' : 'jaxboards_',
            ],
        );

        if ($service) {
            // Create directory table.
            $queries = [
                'DROP TABLE IF EXISTS `directory`;',
                <<<'SQL'
                    CREATE TABLE `directory` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `registrar_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `registrar_ip` varbinary(16) NOT NULL DEFAULT '',
                    `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                    `boardname` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `referral` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                    PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    SQL,
                'TRUNCATE `directory`;',
                'DROP TABLE IF EXISTS `banlist`;',
                <<<'SQL'
                    CREATE TABLE `banlist` (
                    `ip` varbinary(16) NOT NULL,
                    UNIQUE KEY `ip` (`ip`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    SQL,
                'TRUNCATE `banlist`;',
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

            if ($service) {
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

            // Create the directory and blueprint tables
            // Import sql file and run it with php from this:
            // https://stackoverflow.com/a/19752106
            // It's not pretty or perfect but it'll work for our use case...
            $query = '';
            $lines = $this->blueprint->getSchema();
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
                $result = $this->database->query($query);
                $this->database->disposeresult($result);
                // Reset temp variable to empty.
                $query = '';
            }

            // Don't forget to create the admin.
            $this->database->insert(
                'members',
                [
                    'display_name' => $adminUsername,
                    'email' => $adminEmail,
                    'group_id' => 2,
                    'join_date' => $this->database->datetime(),
                    'last_visit' => $this->database->datetime(),
                    'name' => $adminUsername,
                    'pass' => password_hash(
                        (string) $adminPassword,
                        PASSWORD_DEFAULT,
                    ),
                    'posts' => 0,
                    'sig' => '',
                ],
            );

            mkdir(dirname(__DIR__) . '/boards');
            $this->fileUtils->copyDirectory($this->blueprint->getDirectory(), dirname(__DIR__) . '/boards/' . $board);
        }

        // Send us to the service page.
        header('Location: ./');

        return [];
    }
}
