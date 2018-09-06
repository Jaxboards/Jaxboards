# Jaxboards

Jaxboards is a PHP/MySQL forum software built sometime between 2007 and Oct
6th, 2010. It's pretty full featured and offers a unique experience over other
forum software even today. That being said, it's showing its age, and relies
on some flash and older technologies to get by. It is not recommended to run
this in production these days.

## Getting Started

These instructions will get you a copy of the project up and running on your
local machine for development purposes.

### Prerequisites

Jaxboards should run with PHP 5.3.7 or above and MySQL 5.5.3 or above.
You'll want a dedicated database just for Jaxboards just to avoid any kind of
conflicts with anything else. It's only been tested on Apache and Linux as
well, but it may work with something else.

This release of Jaxboards requires HTTPS. There's a lot of hardcoded URLs, and
defaulting that to HTTPS was the easiest solution.

You'll also want this installed in the root directory of a domain or subdomain.
You're not going to have a good time trying to get this working in a subdirectory.

For the service setup you'll want your webserver configured like this:

- The apex domain and www subdomain should point to the `Service` directory.
- Wildcard subdomain of that apex domain should point to the root directory.

### Update Script

If you're running an old Jaxboards database, you can update it to the latest
with the update script. It's only meant to run via the CLI, so run it with this:

```bash
/usr/bin/env php ./Service/update.php
```

If you're just starting to use this repo, it's not needed.

### Installing

Once you've met all the requirements, head to: `https://example.com/Service/install.php`,
replacing `example.com` with your domain.
This page gives you the option of installing the service or a standalone
jaxboards- pick whatever suits your needs.

## Deployment

It is not recommended to run this in production.

## Contributing

If you want to contribute, great! We make use of some command line utilities,
though there may be was to integrate these tools in your development environment
in other ways. It is recommened you fix any errors given off by these tools
before you commit anything, so we can keep our code clean and easy to manage.
Here's how to get the developer tools working and running:

### node.js Tools

We use a few tools made with node.js. I've been using
[pnpm](https://pnpm.js.org/) to manage node.js dependencies, but feel free to
use `npm` or `yarn` if you're more comfortable with that, just keep in mind the
`shrinkwrap.yaml` file is incompatable with those.

Install the node.js tools with this command in the Jaxboards directory:

```bash
pnpm install
```

The tools have `npm` scripts for them, so you can easily get the tools working.

#### JS Compilation

_EXPERIMENTAL / IN PROGRESS_: All Javascript sourcecode is modularized into ES6 classes, and is bundled together using [rollup](https://www.npmjs.com/package/rollup). To build a bundled source ("jsnew.js") from the modules, run:

```bash
pnpm run build
```

#### Prettier

[Prettier](https://github.com/prettier/prettier) automatically formats code to
its standards. This keeps code easier to manage and makes sure anyone
contributing keeps to a consistent style. The only limitation is that it's
limited to the file types it supports, so we aren't using this for PHP or HTML.
However, it does support CSS, JavaScript, YAML, and JSON, which we make use of.

Run with this command in the Jaxboards directory to run it on all the files:

```bash
pnpm run prettier
```

#### Linters

#### Stylelint

[Stylelint](https://stylelint.io/) adds some additional rules to keep CSS files
clean and help us avoid trouble in the future with over-complicated CSS rules.

Run with this command in the Jaxboards directory to run it on all the files:

```bash
pnpm run stylelint
```

In additon, Stylelint supports automatic code fixing for some rules. This won't
avoid every pitfall, but it should take care of some of the things Prettier
does not. Run this fixer on all the files with this command in the Jaxboards
directory:

```bash
pnpm run stylelint-fix
```

#### ESLint

[ESLint](https://eslint.org/) keeps the javascript clean. Run with:

```bash
pnpm run eslint
```

### Composer tools

[Composer](https://getcomposer.org/) is a package manager for PHP and tools
made in PHP. As Jaxboards is a PHP project, it shouldn't be too surprising we
make use of some PHP tools. Run this command in the Jaxboards directory to
install the composer tools:

```bash
composer install
```

#### PHP_CodeSniffer

[PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) is basically
the equivalent of Stylelint for PHP. It also includes a tool to automatically
fix fixable issues, which helps keep code looking great.

Run on all files with the following command in the Jaxboards directory:

```bash
composer run-script phpcs
```

And run this in the Jaxboards directory to run the fixer tool on all files:

```bash
composer run-script phpcbf
```

## Authors

- [seanjohnson08](https://github.com/seanjohnson08) - Original developer
- [dgatwood](https://github.com/dgatwood) - Updated the codebase to support
  PHP's MySQLi interface and work with PHP7
- [wtl420](https://github.com/wtl420) - Maintainer of this fork.

See also the list of [contributors](https://github.com/Jaxboards/Jaxboards/graphs/contributors) who participated in this project.

## License

This project is licensed under the MIT License - see the
[LICENSE.md](LICENSE.md) file for details

## Third-Party Libraries

- [password_compat](https://github.com/ircmaxell/password_compat) library so
  we can make use of PHP's built-in password hashing functions

## Acknowledgments

- Contributors to the original [Jaxboards Service](http://jaxboards.com);
  Jaxboards wouldn't exist without feedback from everyone who's supported it
  over the years.
