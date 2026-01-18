<?php

/*
 * Configuration for `php-cs-fixer`.
 *
 * This document has been generated with
 * https://mlocati.github.io/php-cs-fixer-configurator/#version:3.70.0|configurator
 * you can change this configuration by importing this file.
 */

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        // # Rulesets
        // Default (risky) rule set. Applies newest PER-CS and optimizations for
        // PHP and PHPUnit, based on project’s composer.json file.
        //
        // @see https://cs.symfony.com/doc/ruleSets/AutoRisky.html
        '@auto:risky' => true,
        // Rules that follow the official
        // [Symfony Coding Standards](https://symfony.com/doc/current/contributing/code/standards.html).
        //
        // @see https://cs.symfony.com/doc/ruleSets/Symfony.html
        '@Symfony' => true,
        // Rule set as used by the PHP-CS-Fixer development team, highly
        // opinionated.
        //
        // @see https://cs.symfony.com/doc/ruleSets/PhpCsFixer.html
        '@PhpCsFixer' => true,
        // Default rule set. Applies newest PER-CS and optimizations for PHP,
        // based on project’s composer.json file.
        //
        // Run this after the PhpCsFixer and Symfony rules to make sure these
        // take priority
        //
        // @see https://cs.symfony.com/doc/ruleSets/Auto.html
        '@auto' => true,
        // # Individual Rules
        //
        // Each line of multi-line DocComments must have an asterisk [PSR-5] and
        // must be aligned with the first one.
        //
        // @see https://cs.symfony.com/doc/rules/phpdoc/align_multiline_comment.html
        'align_multiline_comment' => [
            'comment_type' => 'all_multiline',
        ],
        // Replaces short-echo `<?=` with long format `<?php echo`/`<?php print`
        // syntax, or vice-versa.
        //
        // This is included as part of the symfony rule set already, but
        // explicitly including this rule because rector has false positivies
        // with these tags so we swap them out for the longer version
        // automatically.
        //
        // @see https://cs.symfony.com/doc/rules/php_tag/echo_tag_syntax.html
        'echo_tag_syntax' => true,
        // Removes the leading part of fully qualified symbol references if a
        // given symbol is imported or belongs to the current namespace.
        //
        // @see https://cs.symfony.com/doc/rules/import/fully_qualified_strict_types.html
        'fully_qualified_strict_types' => [
            // Whether FQCNs should be automatically imported.
            'import_symbols' => true,
        ],
        // Imports or fully qualifies global classes/functions/constants.
        //
        // @see https://cs.symfony.com/doc/rules/import/global_namespace_import.html
        'global_namespace_import' => [
            // Whether to import, not import or ignore global classes.
            'import_classes' => true,
            // Whether to import, not import or ignore global constants.
            'import_constants' => true,
            // Whether to import, not import or ignore global functions.
            'import_functions' => true,
        ],
        // Simplify `if` control structures that return the boolean result of
        // their condition.
        //
        // @see https://cs.symfony.com/doc/rules/control_structure/simplified_if_return.html
        'simplified_if_return' => true,
        // Write conditions in Yoda style (`true`), non-Yoda style
        // (`['equal' => false, 'identical' => false, 'less_and_greater' => false]`)
        // or ignore those conditions (`null`) based on configuration.
        //
        // @see https://cs.symfony.com/doc/rules/control_structure/yoda_style.html
        'yoda_style' => [
            // Style for equal (`==`, `!=`) statements.
            'equal' => false,
            // Style for identical (`===`, `!==`) statements.
            'identical' => false,
            // Style for less and greater than (`<`, `<=`, `>`, `>=`)
            // statements.
            'less_and_greater' => false,
        ],
        // # Individual Risky Rules
        // Rules marked as risky, to be cautious about
        //
        // Comments with annotation should be docblock when used on structural
        // elements.
        //
        // Risky as new docblocks might mean more, e.g. a Doctrine entity might
        // have a new column in database.
        //
        // @see https://cs.symfony.com/doc/rules/comment/comment_to_phpdoc.html
        'comment_to_phpdoc' => true,
        // Replaces `dirname(__FILE__)` expression with equivalent `__DIR__`
        // constant.
        //
        // Risky when the function dirname is overridden.
        //
        // @see https://cs.symfony.com/doc/rules/language_construct/dir_constant.html
        'dir_constant' => true,
        // Order the flags in fopen calls, `b` and `t` must be last.
        //
        // Risky when the function fopen is overridden.
        //
        // @see https://cs.symfony.com/doc/rules/function_notation/fopen_flag_order.html
        'fopen_flag_order' => true,
        // The flags in fopen calls must omit `t`, and `b` must be omitted or
        // included consistently.
        //
        // Risky when the function fopen is overridden.
        //
        // @see https://cs.symfony.com/doc/rules/function_notation/fopen_flags.html
        'fopen_flags' => ['b_mode' => false],
        // Replace core functions calls returning constants with the constants.
        //
        // Risky when any of the configured functions to replace are overridden.
        //
        // @see https://cs.symfony.com/doc/rules/language_construct/function_to_constant.html
        'function_to_constant' => ['functions' => [
            'php_sapi_name',
            'phpversion',
            'pi',
        ]],
        // Replaces `is_null($var)` expression with `null === $var`.
        //
        // Risky when the function is_null is overridden.
        //
        // @see https://cs.symfony.com/doc/rules/language_construct/is_null.html
        'is_null' => true,
        // Use `&&` and `||` logical operators instead of `and` and `or`.
        //
        // Risky, because you must double-check if using and/or with lower
        // precedence was intentional.
        //
        // @see https://cs.symfony.com/doc/rules/operator/logical_operators.html
        'logical_operators' => true,
        // Shorthand notation for operators should be used if possible.
        //
        // Risky when applying for string offsets
        // (e.g. `<?php $text = "foo"; $text[0] = $text[0] & "\x7F";`).
        //
        // @see https://cs.symfony.com/doc/rules/operator/long_to_shorthand_operator.html
        'long_to_shorthand_operator' => true,
        // Replaces `intval`, `floatval`, `doubleval`, `strval` and `boolval`
        // function calls with according type casting operator.
        //
        // Risky if any of the functions intval, floatval, doubleval, strval or
        // boolval are overridden.
        //
        // @see https://cs.symfony.com/doc/rules/cast_notation/modernize_types_casting.html
        'modernize_types_casting' => true,
        // Replace accidental usage of homoglyphs (non ascii characters) in
        // names.
        //
        // Renames classes and cannot rename the files. You might have string
        // references to renamed code (`$$name`).
        //
        // @see https://cs.symfony.com/doc/rules/naming/no_homoglyph_names.html
        'no_homoglyph_names' => true,
        // Convert PHP4-style constructors to `__construct`.
        //
        // Risky when old style constructor being fixed is overridden or
        // overrides parent one.
        //
        // @see https://cs.symfony.com/doc/rules/class_notation/no_php4_constructor.html
        'no_php4_constructor' => true,
        // Removes `final` from methods where possible.
        //
        // Risky when child class overrides a private method.
        //
        // @see https://cs.symfony.com/doc/rules/class_notation/no_unneeded_final_method.html
        'no_unneeded_final_method' => ['private_methods' => false],
        // There must be no `sprintf` calls with only the first argument.
        //
        // Risky when if the `sprintf` function is overridden.
        //
        // @see https://cs.symfony.com/doc/rules/function_notation/no_useless_sprintf.html
        'no_useless_sprintf' => true,
        // Remove Zero-width space (ZWSP), Non-breaking space (NBSP) and other
        // invisible unicode symbols.
        //
        // Risky when strings contain intended invisible characters.
        //
        // @see https://cs.symfony.com/doc/rules/basic/non_printable_character.html
        'non_printable_character' => true,
        // Inside class or interface element self should be preferred to the
        // class name itself.
        //
        // Risky when using dynamic calls like get_called_class() or late static
        // binding.
        //
        // @see https://cs.symfony.com/doc/rules/class_notation/self_accessor.html
        'self_accessor' => true,
        // Cast shall be used, not `settype`.
        //
        // Risky when the settype function is overridden or when used as the 2nd
        // or 3rd expression in a for loop .
        //
        // @see https://cs.symfony.com/doc/rules/alias/set_type_to_cast.html
        'set_type_to_cast' => true,
        // Lambdas not (indirectly) referencing $this must be declared static.
        //
        // Risky when using `->bindTo` on lambdas without referencing to
        // `$this`.
        //
        // @see https://cs.symfony.com/doc/rules/function_notation/static_lambda.html
        'static_lambda' => true,
        // String tests for empty must be done against `''`, not with `strlen`.
        //
        // Risky when `strlen` is overridden, when called using a `stringable`
        // object, also no longer triggers warning when called using
        // non-string(able).
        //
        // @see https://cs.symfony.com/doc/rules/string_notation/string_length_to_empty.html
        'string_length_to_empty' => true,
        // Use the Elvis operator ?: where possible.
        //
        // Risky when relying on functions called on both sides of the `?`
        // operator.
        //
        // @see https://cs.symfony.com/doc/rules/operator/ternary_to_elvis_operator.html
        'ternary_to_elvis_operator' => true,
    ])
    ->setFinder(
        Finder::create()
            ->in(__DIR__)
            ->exclude([
                '.git',
                'vendor',
                'node_modules',
            ]),
    )
;
