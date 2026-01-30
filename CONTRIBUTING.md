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
bun install
```

The tools have `bun` scripts for them, so you can easily get the tools working.

#### Build

```bash
bun run build
```

#### Prettier

[Prettier](https://github.com/prettier/prettier) automatically formats code to
its standards. This keeps code easier to manage and makes sure anyone
contributing keeps to a consistent style. The only limitation is that it's
limited to the file types it supports, so we aren't using this for PHP or HTML.
However, it does support CSS, JavaScript, YAML, and JSON, which we make use of.

Run with this command in the Jaxboards directory to run it on all the files:

```bash
bun run prettier
```

#### Linters

#### Stylelint

[Stylelint](https://stylelint.io/) adds some additional rules to keep CSS files
clean and help us avoid trouble in the future with over-complicated CSS rules.

Run with this command in the Jaxboards directory to run it on all the files:

```bash
bun run stylelint
```

In additon, Stylelint supports automatic code fixing for some rules. This won't
avoid every pitfall, but it should take care of some of the things Prettier does
not. Run this fixer on all the files with this command in the Jaxboards
directory:

```bash
bun run stylelint-fix
```

#### ESLint

[ESLint](https://eslint.org/) keeps the javascript clean. Run with:

```bash
bun run eslint
```

### Composer tools

[Composer](https://getcomposer.org/) is a package manager for PHP and tools made
in PHP. As Jaxboards is a PHP project, it shouldn't be too surprising we make
use of some PHP tools. We're using
[node-composer-runner](https://github.com/garthenweb/node-composer-runner) to
run composer commands with `bun`, so composer packages should be installed after
you run `bun install`.

#### PHP_CodeSniffer

[PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) is basically the
equivalent of Stylelint for PHP. It also includes a tool to automatically fix
fixable issues, which helps keep code looking great.

Run on all files with the following command in the Jaxboards directory:

```bash
bun run phpcs
```

And run this in the Jaxboards directory to run the fixer tool on all files:

```bash
bun run phpcbf
```

### Tests

All changes are run against our test suite through github actions, but if you want to run them yourself locally:

```bash
bun run test
```
