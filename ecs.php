<?php

use PHP_CodeSniffer\Standards\Generic\Sniffs\PHP\ForbiddenFunctionsSniff;
use PHP_CodeSniffer\Standards\PSR1\Sniffs\Files\SideEffectsSniff;
use PHP_CodeSniffer\Standards\PSR1\Sniffs\Methods\CamelCapsMethodNameSniff;
use PHP_CodeSniffer\Standards\PSR2\Sniffs\Classes\ClassDeclarationSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\Classes\ValidClassNameSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\Commenting\BlockCommentSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\Commenting\InlineCommentSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\Commenting\PostStatementCommentSniff;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withConfiguredRule(ForbiddenFunctionsSniff::class, [
        'forbiddenFunctions' => [
            'sizeof' => 'count',
            'delete' => 'unset',
            'print' => 'echo',
            'is_null' => null,
            'create_function' => null,
            'stripos' => 'mb_stripos',
            'stristr' => 'mb_stristr',
            'strlen' => 'mb_strlen',
            'strpos' => 'mb_strpos',
            'strrchr' => 'mb_strrchr',
            'strrichr' => 'mb_strrichr',
            'strripos' => 'mb_strripos',
            'strrpos' => 'mb_strrpos',
            'strstr' => 'mb_strstr',
            'strtolower' => 'mb_strtolower',
            'strtoupper' => 'mb_strtoupper',
            'substr_count' => 'mb_substr_count',
            'substr' => 'mb_substr',
            'md5' => 'hash',
            'md5_file' => 'hash_file',
            'mysql_affected_rows' => 'mysqli_affected_rows',
            'mysql_client_encoding' => null,
            'mysql_close' => 'mysqli_close',
            'mysql_connect' => 'mysqli_connect',
            'mysql_create_db' => null,
            'mysql_data_seek' => 'mysqli_data_seek',
            'mysql_db_name' => null,
            'mysql_db_query' => null,
            'mysql_drop_db' => null,
            'mysql_errno' => 'mysqli_errno',
            'mysql_error' => 'mysqli_error',
            'mysql_escape_string' => 'mysqli_escape_string',
            'mysql_fetch_array' => 'mysqli_fetch_array',
            'mysql_fetch_assoc' => 'mysqli_fetch_assoc',
            'mysql_fetch_field' => 'mysqli_fetch_field',
            'mysql_fetch_lengths' => 'mysqli_fetch_lengths',
            'mysql_fetch_object' => 'mysqli_fetch_object',
            'mysql_fetch_row' => 'mysqli_fetch_row',
            'mysql_field_flags' => null,
            'mysql_field_len' => null,
            'mysql_field_name' => null,
            'mysql_field_seek' => 'mysqli_field_seek',
            'mysql_field_table' => null,
            'mysql_field_type' => null,
            'mysql_free_result' => 'mysqli_free_result',
            'mysql_get_client_info' => 'mysqli_get_client_info',
            'mysql_get_host_info' => 'mysqli_get_host_info',
            'mysql_get_proto_info' => 'mysqli_get_proto_info',
            'mysql_get_server_info' => 'mysqli_get_server_info',
            'mysql_info' => 'mysqli_info',
            'mysql_insert_id' => 'mysqli_insert_id',
            'mysql_list_dbs' => null,
            'mysql_list_fields' => null,
            'mysql_list_processes' => null,
            'mysql_list_tables' => null,
            'mysql_num_fields' => 'mysqli_num_fields',
            'mysql_num_rows' => 'mysqli_num_rows',
            'mysql_pconnect' => null,
            'mysql_ping' => 'mysqli_ping',
            'mysql_query' => 'mysqli_query',
            'mysql_real_escape_string' => 'mysqli_real_escape_string',
            'mysql_result' => null,
            'mysql_select_db' => 'mysqli_select_db',
            'mysql_set_charset' => 'mysqli_set_charset',
            'mysql_stat' => 'mysqli_stat',
            'mysql_tablename' => null,
            'mysql_thread_id' => 'mysqli_thread_id',
            'mysql_unbuffered_query' => null,
        ],
    ])
    ->withPaths([
        __DIR__.'/Script',
        __DIR__.'/Service',
        __DIR__.'/acp',
        __DIR__.'/config.default.php',
        __DIR__.'/domaindefinitions.php',
        __DIR__.'/ecs.php',
        __DIR__.'/inc',
        __DIR__.'/index.php',
        __DIR__.'/misc',
        __DIR__.'/ecs_to_sonarqube.php',
    ])
    ->withPreparedSets(psr12: true, common: true, symplify: true, laravel: true)
    ->withRules([BlockCommentSniff::class, InlineCommentSniff::class, PostStatementCommentSniff::class])
    ->withSkip([
        BlockCommentSniff::class.'.InvalidEndChar',
        BlockCommentSniff::class.'.NotCapital',
        CamelCapsMethodNameSniff::class,
        ClassDeclarationSniff::class,
        InlineCommentSniff::class.'.InvalidEndChar',
        InlineCommentSniff::class.'.NotCapital',
        SideEffectsSniff::class,
        ValidClassNameSniff::class,
    ]);
