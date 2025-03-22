# Jaxboards

Jaxboards is PHP/MySQL forum software built sometime between 2007 and October 6, 2010. It's pretty full-featured and offers a unique experience over other forum software even today. It delivers outstanding performance, and creating themes is relatively easy. That being said, it's showing its age, and relies on some older technologies to get by. It is not recommended to run this in production these days.

## Getting Started

These instructions will get you a copy of the project up and running on your
local machine for development purposes.

## Deployment

Tested on Apache and Linux only.

It is not recommended to run Jaxboards in production.

### Prerequisites

- PHP 8.3+
- MySQL>=5.5.3
- TLS certificate (due to hardcoded URLs in codebase)

You'll want a dedicated database just for Jaxboards just to avoid any kind of
conflicts with anything else.

For the service setup you'll want your webserver configured like this:

- The apex domain and www subdomain should point to the `Service/` directory.
- Wildcard subdomain of that apex domain should point to the root directory.

Jaxboards must be installed in the root directory of a domain or subdomain.
You're not going to have a good time trying to get this working in a subdirectory.

### Installation

Once you've met all the requirements, head to: `https://$DOMAIN/Service/install.php`,
replacing `$DOMAIN` with your domain.
This page gives you the option of installing the service or a standalone
Jaxboards - pick whatever suits your needs.

The install script at `Service/install.php` handles configuration and setting up a new install. It does the following:

- Saves configuration settings from the installer. Mainly this is database information, but it also saves the domain you're running the board on. Basically copies `config.default.php` to `config.php` and updates the values. Here's the direct PHP code what values are being set specifically:

```php
        // Update with our settings.
        $CFG['boardname'] = 'Jaxboards';
        $CFG['domain'] = $JAX->p['domain'];
        $CFG['mail_from'] = $JAX->p['admin_username'] . ' <' .
            $JAX->p['admin_email'] . '>';
        $CFG['sql_db'] = $JAX->p['sql_db'];
        $CFG['sql_host'] = $JAX->p['sql_host'];
        $CFG['sql_username'] = $JAX->p['sql_username'];
        $CFG['sql_password'] = $JAX->p['sql_password'];
        $CFG['installed'] = true;
        $CFG['service'] = $service; // boolean if it's a service or not
        $CFG['prefix'] = $service ? '' : 'jaxboards';
        $CFG['sql_prefix'] = $CFG['prefix'] ? $CFG['prefix'] . '_' : '';
```

- Figures out if you're installing a service (multiple boards like jaxboards.com) or a single-board install.
- If it's a service install, install those special service tables.
- Copy over the MySQL tables here. Service installs have an additional step of adding each board installed to the directory table. Once the database is imported, the admin user is created as well.

After install the MySQL credentials are saved in `config.php`. This is copied over from `config.default.php` so you can see what the format looks like there.

`Service/blueprint.sql` contains the base tables and base data for the database for a single-board install. Every table is prefixed with `blueprint_` (and should be updated to match what the `sql_prefix` setting is in the `config.php` file before importing). In addition, a service install (multiple boards) has two additional tables, `directory` (containing a list of all the registered boards) and `banlist` (containing a list of IP addresses to ban). These are both described in the `Service/install.php` file.

### Troubleshooting

Permissions issues are a major source of bugs during installation. If you manually created any directories, you may have to assign ownership to your PHP user using a command similar to the following:

```bash
PUBLIC_PATH="/var/www/html/<your_domain_name>/public_html" && \
  sudo chown www-data:www-data "$PUBLIC_PATH" && \
  chmod og+rwX -R "$PUBLIC_PATH" && \
  chmod u+rX -R "$PUBLIC_PATH"
```

If you plan to reuse any old themes, be prepared to update hardcoded images, especially those that refer to the now-defunct jaxboards.com domain.

### Updating

In the unlikely event that you're restoring from an old (pre-2020) Jaxboards database, you can update it to the latest with the update script. It's only meant to run via the CLI, so run it with this:

```bash
php ./Service/update.php
```

If you're just starting to use this repo, you don't need to run this script.

## Contributing

If you want to contribute, great! We make use of some command line utilities,
though there may be was to integrate these tools in your development environment
in other ways. It is recommened you fix any errors given off by these tools
before you commit anything, so we can keep our code clean and easy to manage.
Here's how to get the developer tools working and running:

### node.js Tools

We use a few tools made with node.js.

Install the node.js tools with this command in the Jaxboards directory:

```bash
npm install
```

The tools have `npm` scripts for them, so you can easily get the tools working.

#### JS Compilation

_EXPERIMENTAL / IN PROGRESS_: All Javascript sourcecode is modularized into ES6 classes, and is bundled together using [rollup](https://www.npmjs.com/package/rollup). To build a bundled source ("jsnew.js") from the modules, run:

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
avoid every pitfall, but it should take care of some of the things Prettier
does not. Run this fixer on all the files with this command in the Jaxboards
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

[PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) is basically
the equivalent of Stylelint for PHP. It also includes a tool to automatically
fix fixable issues, which helps keep code looking great.

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

See also the list of [contributors](https://github.com/Jaxboards/Jaxboards/graphs/contributors) who participated in this project.

## License

This project is licensed under the MIT License - see the
[LICENSE.md](LICENSE.md) file for details

## Acknowledgments

- Contributors to the original [Jaxboards Service](https://jaxboards.com);
  Jaxboards wouldn't exist without feedback from everyone who's supported it
  over the years.
