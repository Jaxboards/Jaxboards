<?php
$finder = PhpCsFixer\Finder::create()
    ->exclude(array('.git', 'node_modules', 'vendor'))
    ->in(__DIR__);

return PhpCsFixer\Config::create()
    ->setRules([
        '@PSR1' => true,
        '@PSR2' => true,
        '@Symfony' => true,
        'align_multiline_comment' => [
            'comment_type' => 'all_multiline'
        ],
        'array_indentation' => true,
        'array_syntax' => [
            'syntax' => 'long',
        ],
        'backtick_to_shell_exec' => true,
        'class_keyword_remove' => true,
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'compact_nullable_typehint' => true,
        'escape_implicit_backslashes' => [
            'double_quoted' => true,
            'heredoc_syntax' => true,
            'single_quoted' => true,
        ],
        'explicit_string_variable' => true,
        'fully_qualified_strict_types' => true,
        'heredoc_to_nowdoc' => true,
        'linebreak_after_opening_tag' => true,
        'list_syntax' => [
            'syntax' => 'long',
        ],
        'method_chaining_indentation' => true,
        'multiline_comment_opening_closing' => true,
        'multiline_whitespace_before_semicolons' => true,
        'no_alternative_syntax' => true,
        'no_binary_string' => true,
        'no_extra_consecutive_blank_lines' => true,
        'no_null_property_initialization' => true,
        'no_short_echo_tag' => true,
        'no_superfluous_elseif' => true,
        'phpdoc_no_empty_return' => false,
        'no_superfluous_phpdoc_tags' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'object_operator_without_whitespace' => true,
        'phpdoc_order' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_types_order' => true,
        'return_assignment' => true,
        'simplified_null_return' => true,
        'phpdoc_no_alias_tag' => false,
        'phpdoc_no_package' => false,
    ])
    ->setFinder($finder);
