![Jaxboards](https://github.com/Jaxboards/Jaxboards/blob/main/ACP/img/loginlogo.png?raw=true)

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Bugs](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=bugs)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Code Smells](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=code_smells)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=coverage)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Duplicated Lines (%)](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=duplicated_lines_density)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Lines of Code](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=ncloc)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=reliability_rating)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=security_rating)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Technical Debt](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=sqale_index)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=sqale_rating)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=vulnerabilities)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)

Jaxboards is a _realtime_ forum software built on PHP/MySQL (originally in ~2010 on PHP4, and rebuilt in 2025 on PHP8).

All of the standard forum features (messaging, forums, topics, replies, online status) are all instant and highly responsive.

As users interact with the forum, those changes are reflected to all other users without them refreshing.

It delivers **outstanding** performance and creating themes is relatively easy.

## Getting Started

These instructions will get you a copy of the project up and running on your
local machine for development purposes.

### Prerequisites

- PHP 8.2.12 or higher
- MySQL >= 8.0.41 < 8.1
- TLS certificate (for security and protection of user data)

You'll want a dedicated database just for Jaxboards just to avoid any kind of
conflicts with anything else.

For the service setup you'll want your webserver configured like this:

- The apex domain and www subdomain should point to the `Service/` directory.
- Wildcard subdomain of that apex domain should point to the root directory.

Jaxboards must be installed in the root directory of a domain or subdomain.
You're not going to have a good time trying to get this working in a
subdirectory.

### Installation

Once you've met all the requirements, head to:
`https://$DOMAIN/Service/install.php`, replacing `$DOMAIN` with your domain.
This page gives you the option of installing the service or a standalone
Jaxboards - pick whatever suits your needs.

The install script at `Service/install.php` handles configuration and setting up
a new install. It does the following:

- Saves configuration settings from the installer. Mainly this is database
  information, but it also saves the domain you're running the board on.
  Basically copies `config.default.php` to `config.php` and updates the values.
  Here's the direct PHP code what values are being set specifically:
    ```php
    // Update with our settings.
    $CFG['boardname'] = 'Jaxboards';
    $CFG['domain'] = $request->post('domain');
    $CFG['mail_from'] = $request->post('admin_username') . ' <' .
        $request->post('admin_email') . '>';
    $CFG['sql_db'] = $request->post('sql_db');
    $CFG['sql_host'] = $request->post('sql_host');
    $CFG['sql_username'] = $request->post('sql_username');
    $CFG['sql_password'] = $request->post('sql_password');
    $CFG['service'] = $service; // boolean if it's a service or not
    $CFG['prefix'] = $service ? '' : 'jaxboards';
    $CFG['sql_prefix'] = $CFG['prefix'] ? $CFG['prefix'] . '_' : '';
    ```
- Figures out if you're installing a service (multiple boards) or a single-board
  install.
- If it's a service install, install those special service tables.
- Copy over the MySQL tables here. Service installs have an additional step of
  adding each board installed to the directory table. Once the database is
  imported, the admin user is created as well.

After install the MySQL credentials are saved in `config.php`. This is copied
over from `config.default.php` so you can see what the format looks like there.

`Service/schema.sql` contains the base tables and base data for the database
for a single-board install. Every table is prefixed with `blueprint_` (and
should be updated to match what the `sql_prefix` setting is in the `config.php`
file before importing). In addition, a service install (multiple boards) has two
additional tables, `directory` (containing a list of all the registered boards)
and `banlist` (containing a list of IP addresses to ban). These are both
described in the `Service/install.php` file.

### Troubleshooting

Permissions issues are a major source of bugs during installation. If you
manually created any directories, you may have to assign ownership to your PHP
user using a command similar to the following:

```bash
PUBLIC_PATH="/var/www/html/<your_domain_name>/public_html" && \
  sudo chown www-data:www-data "$PUBLIC_PATH" && \
  chmod og+rwX -R "$PUBLIC_PATH" && \
  chmod u+rX -R "$PUBLIC_PATH"
```

If you plan to reuse any old themes, be prepared to update hardcoded images,
especially those that refer to the now-defunct jaxboards service domain.

## Contributing

If you want to contribute, great! We make use of some command line utilities,
though there may be was to integrate these tools in your development environment
in other ways. It is recommened you fix any errors given off by these tools
before you commit anything, so we can keep our code clean and easy to manage.

These tools are designed for POSIX compatible shells, so running on bare Windows
probably won't work. Thankfully Windows has a great solution for this via
[Windows Subsystem for Linux (wsl)](https://learn.microsoft.com/en-us/windows/wsl/install). Version 1 or 2 should both work.

Here's how to get the developer tools working and running:

### `direnv`

Direnv is a tool that lets us set specific environment settings in this
directory.

See [installation
instructions](https://github.com/direnv/direnv/blob/master/docs/installation.md)
here.

### node.js Tools

We use a few tools made with node.js.

Install the node.js tools with this command in the Jaxboards directory:

```bash
npm install
```

The tools have `npm` scripts for them, so you can easily get the tools working.

#### JS Compilation

All Javascript sourcecode is modularized into ES6
classes, and is bundled together using
[rollup](https://www.npmjs.com/package/rollup). To build a bundled source
("jsnew.js") from the modules, run:

```bash
npm run-script build
```

#### Prettier

[Prettier](https://github.com/prettier/prettier) automatically formats code to
its standards. This keeps code easier to manage and makes sure anyone
contributing keeps to a consistent style. The only limitation is that it's
limited to the file types it supports, so we aren't using this for PHP or HTML.
However, it does support CSS, JavaScript, YAML, and JSON, which we make use of.

Run with this command in the Jaxboards directory to run it on all the files:

```bash
npm run-script prettier
```

#### Linters

#### Stylelint

[Stylelint](https://stylelint.io/) adds some additional rules to keep CSS files
clean and help us avoid trouble in the future with over-complicated CSS rules.

Run with this command in the Jaxboards directory to run it on all the files:

```bash
npm run-script stylelint
```

In additon, Stylelint supports automatic code fixing for some rules. This won't
avoid every pitfall, but it should take care of some of the things Prettier does
not. Run this fixer on all the files with this command in the Jaxboards
directory:

```bash
npm run-script stylelint-fix
```

#### ESLint

[ESLint](https://eslint.org/) keeps the javascript clean. Run with:

```bash
npm run-script eslint
```

### Composer tools

[Composer](https://getcomposer.org/) is a package manager for PHP and tools made
in PHP. As Jaxboards is a PHP project, it shouldn't be too surprising we make
use of some PHP tools. We're using
[node-composer-runner](https://github.com/garthenweb/node-composer-runner) to
run composer commands with `npm`, so composer packages should be installed after
you run `npm install`.

#### PHP_CodeSniffer

[PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) is basically the
equivalent of Stylelint for PHP. It also includes a tool to automatically fix
fixable issues, which helps keep code looking great.

Run on all files with the following command in the Jaxboards directory:

```bash
npm run-script phpcs
```

And run this in the Jaxboards directory to run the fixer tool on all files:

```bash
npm run-script phpcbf
```

## Authors

- [seanjohnson08](https://github.com/seanjohnson08) - Original developer
- [dgatwood](https://github.com/dgatwood) - Updated the codebase to support
  PHP's MySQLi interface and work with PHP7
- [wtl420](https://github.com/wtl420) - Maintainer of this fork.
- [VinnyVideo](https://github.com/VinnyVideo) - Updated Jaxboards to run in PHP8

See also the list of
[contributors](https://github.com/Jaxboards/Jaxboards/graphs/contributors) who
participated in this project.

## License

This project is licensed under the MIT License - see the
[LICENSE](LICENSE) file for details

## Acknowledgments

- Contributors to the original Jaxboards Service;
  Jaxboards wouldn't exist without feedback from everyone who's supported it
  over the years.

[![SonarQube Cloud](https://sonarcloud.io/images/project_badges/sonarcloud-dark.svg)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
