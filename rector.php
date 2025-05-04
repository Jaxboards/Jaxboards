<?php

/**
 * Rector configuration.
 *
 * @see https://getrector.com/documentation
 */

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\CodingStyle\Rector\If_\NullableCompareToNullRector;
use Rector\Config\RectorConfig;
use Rector\Php70\Rector\FunctionLike\ExceptionHandlerTypehintRector;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;
use Rector\Renaming\Rector\FuncCall\RenameFunctionRector;
use Rector\Strict\Rector\BooleanNot\BooleanInBooleanNotRuleFixerRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;
use Rector\Strict\Rector\If_\BooleanInIfConditionRuleFixerRector;
use Rector\Strict\Rector\Ternary\BooleanInTernaryOperatorRuleFixerRector;
use Rector\Strict\Rector\Ternary\DisallowedShortTernaryRuleFixerRector;
use Rector\TypeDeclaration\Rector\BooleanAnd\BinaryOpNullableToInstanceofRector;
use Rector\TypeDeclaration\Rector\Class_\PropertyTypeFromStrictSetterGetterRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddParamTypeFromPropertyTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ParamTypeByMethodCallTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\StrictArrayParamDimFetchRector;
use Rector\TypeDeclaration\Rector\ClassMethod\StrictStringParamConcatRector;
use Rector\TypeDeclaration\Rector\Empty_\EmptyOnNullableObjectToInstanceOfRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictSetUpRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;
use Rector\TypeDeclaration\Rector\While_\WhileNullableToInstanceofRector;

return RectorConfig::configure()
    ->withAttributesSets()
    ->withBootstrapFiles([
        __DIR__ . '/tools/phpstan-bootstrap.php',
    ])
    ->withCache(
        // ensure file system caching is used instead of in-memory
        cacheClass: FileCacheStorage::class,
        cacheDirectory: '/tmp/rector',
    )
    ->withComposerBased(
        twig: false,
        doctrine: false,
        phpunit: false,
        symfony: false,
    )
    ->withConfiguredRule(RenameFunctionRector::class, [
        'chop' => 'rtrim',
        'chr' => 'mb_chr',
        'close' => 'closedir',
        'com_get' => 'com_propget',
        'com_propset' => 'com_propput',
        'com_set' => 'com_propput',
        'delete' => 'unset',
        'die' => 'exit',
        'diskfreespace' => 'disk_free_space',
        'doubleval' => 'floatval',
        'fputs' => 'fwrite',
        'getrandmax' => 'mt_getrandmax',
        'gzputs' => 'gzwrite',
        'i18n_convert' => 'mb_convert_encoding',
        'i18n_discover_encoding' => 'mb_detect_encoding',
        'i18n_http_input' => 'mb_http_input',
        'i18n_http_output' => 'mb_http_output',
        'i18n_internal_encoding' => 'mb_internal_encoding',
        'i18n_ja_jp_hantozen' => 'mb_convert_kana',
        'i18n_mime_header_decode' => 'mb_decode_mimeheader',
        'i18n_mime_header_encode' => 'mb_encode_mimeheader',
        'imap_create' => 'imap_createmailbox',
        'imap_fetchtext' => 'imap_body',
        'imap_getmailboxes' => 'imap_list_full',
        'imap_getsubscribed' => 'imap_lsub_full',
        'imap_header' => 'imap_headerinfo',
        'imap_listmailbox' => 'imap_list',
        'imap_listsubscribed' => 'imap_lsub',
        'imap_rename' => 'imap_renamemailbox',
        'imap_scan' => 'imap_listscan',
        'imap_scanmailbox' => 'imap_listscan',
        'ini_alter' => 'ini_set',
        'is_double' => 'is_float',
        'is_integer' => 'is_int',
        'is_long' => 'is_int',
        'is_real' => 'is_float',
        'is_writeable' => 'is_writable',
        'join' => 'implode',
        'key_exists' => 'array_key_exists',
        'ldap_close' => 'ldap_unbind',
        'mbstrcut' => 'mb_strcut',
        'mbstrlen' => 'mb_strlen',
        'mbstrpos' => 'mb_strpos',
        'mbstrrpos' => 'mb_strrpos',
        'mbsubstr' => 'mb_substr',
        'mysql' => 'mysql_db_query',
        'mysql_affected_rows' => 'mysqli_affected_rows',
        'mysql_close' => 'mysqli_close',
        'mysql_connect' => 'mysqli_connect',
        'mysql_createdb' => 'mysql_create_db',
        'mysql_data_seek' => 'mysqli_data_seek',
        'mysql_dbname' => 'mysql_result',
        'mysql_db_name' => 'mysql_result',
        'mysql_dropdb' => 'mysql_drop_db',
        'mysql_errno' => 'mysqli_errno',
        'mysql_error' => 'mysqli_error',
        'mysql_escape_string' => 'mysqli_escape_string',
        'mysql_fetch_array' => 'mysqli_fetch_array',
        'mysql_fetch_assoc' => 'mysqli_fetch_assoc',
        'mysql_fetch_field' => 'mysqli_fetch_field',
        'mysql_fetch_lengths' => 'mysqli_fetch_lengths',
        'mysql_fetch_object' => 'mysqli_fetch_object',
        'mysql_fetch_row' => 'mysqli_fetch_row',
        'mysql_fieldflags' => 'mysql_field_flags',
        'mysql_fieldlen' => 'mysql_field_len',
        'mysql_fieldname' => 'mysql_field_name',
        'mysql_fieldtable' => 'mysql_field_table',
        'mysql_fieldtype' => 'mysql_field_type',
        'mysql_field_seek' => 'mysqli_field_seek',
        'mysql_freeresult' => 'mysql_free_result',
        'mysql_free_result' => 'mysqli_free_result',
        'mysql_get_client_info' => 'mysqli_get_client_info',
        'mysql_get_host_info' => 'mysqli_get_host_info',
        'mysql_get_proto_info' => 'mysqli_get_proto_info',
        'mysql_get_server_info' => 'mysqli_get_server_info',
        'mysql_info' => 'mysqli_info',
        'mysql_insert_id' => 'mysqli_insert_id',
        'mysql_listdbs' => 'mysql_list_dbs',
        'mysql_listfields' => 'mysql_list_fields',
        'mysql_listtables' => 'mysql_list_tables',
        'mysql_numfields' => 'mysql_num_fields',
        'mysql_numrows' => 'mysql_num_rows',
        'mysql_num_fields' => 'mysqli_num_fields',
        'mysql_num_rows' => 'mysqli_num_rows',
        'mysql_ping' => 'mysqli_ping',
        'mysql_query' => 'mysqli_query',
        'mysql_real_escape_string' => 'mysqli_real_escape_string',
        'mysql_selectdb' => 'mysql_select_db',
        'mysql_select_db' => 'mysqli_select_db',
        'mysql_set_charset' => 'mysqli_set_charset',
        'mysql_stat' => 'mysqli_stat',
        'mysql_tablename' => 'mysql_result',
        'mysql_thread_id' => 'mysqli_thread_id',
        'ocibindbyname' => 'oci_bind_by_name',
        'ocicancel' => 'oci_cancel',
        'ocicolumnisnull' => 'oci_field_is_null',
        'ocicolumnname' => 'oci_field_name',
        'ocicolumnprecision' => 'oci_field_precision',
        'ocicolumnscale' => 'oci_field_scale',
        'ocicolumnsize' => 'oci_field_size',
        'ocicolumntype' => 'oci_field_type',
        'ocicolumntyperaw' => 'oci_field_type_raw',
        'ocicommit' => 'oci_commit',
        'ocidefinebyname' => 'oci_define_by_name',
        'ocierror' => 'oci_error',
        'ociexecute' => 'oci_execute',
        'ocifetch' => 'oci_fetch',
        'ocifetchstatement' => 'oci_fetch_all',
        'ocifreecursor' => 'oci_free_statement',
        'ocifreedesc' => 'oci_free_descriptor',
        'ocifreestatement' => 'oci_free_statement',
        'ociinternaldebug' => 'oci_internal_debug',
        'ocilogon' => 'oci_connect',
        'ocinewcollection' => 'oci_new_collection',
        'ocinewcursor' => 'oci_new_cursor',
        'ocinewdescriptor' => 'oci_new_descriptor',
        'ocinlogon' => 'oci_new_connect',
        'ocinumcols' => 'oci_num_fields',
        'ociparse' => 'oci_parse',
        'ocipasswordchange' => 'oci_password_change',
        'ociplogon' => 'oci_pconnect',
        'ociresult' => 'oci_result',
        'ocirollback' => 'oci_rollback',
        'ociserverversion' => 'oci_server_version',
        'ocisetprefetch' => 'oci_set_prefetch',
        'ocistatementtype' => 'oci_statement_type',
        'odbc_do' => 'odbc_exec',
        'odbc_field_precision' => 'odbc_field_len',
        'ord' => 'mb_ord',
        'pg_clientencoding' => 'pg_client_encoding',
        'pg_setclientencoding' => 'pg_set_client_encoding',
        'pos' => 'current',
        'print' => 'echo',
        'recode' => 'recode_string',
        'show_source' => 'highlight_file',
        'sizeof' => 'count',
        'snmpwalkoid' => 'snmprealwalk',
        'strchr' => 'mb_strstr',
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
        'substr' => 'mb_substr',
        'substr_count' => 'mb_substr_count',
        '_' => 'gettext',
    ])
    ->withImportNames(
        importNames: true,
        importDocBlockNames: true,
        importShortClasses: true,
        removeUnusedImports: true,
    )
    ->withPaths([
        __DIR__,
    ])
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        naming: true,
        instanceOf: true,
        earlyReturn: true,
        strictBooleans: true,
        carbon: true,
        rectorPreset: true,
        phpunitCodeQuality: false,
        doctrineCodeQuality: false,
        symfonyCodeQuality: false,
        symfonyConfigs: false,
    )
    ->withRootFiles()
    ->withRules([
        // PHP 8.4 rule that's safe to enable whenever
        ExplicitNullableParamTypeRector::class,
    ])
    ->withSkip([
        // disable ! and empty rules which make the code noisy due to the
        // automated handling of it
        BooleanInBooleanNotRuleFixerRector::class,
        BooleanInIfConditionRuleFixerRector::class,
        BooleanInTernaryOperatorRuleFixerRector::class,
        DisallowedEmptyRuleFixerRector::class,
        DisallowedShortTernaryRuleFixerRector::class,
        // disable transforming Exception catching to Throwable catching which
        // is way too vague of a scope
        ExceptionHandlerTypehintRector::class,
        // disable using exclusive type instead of null checks
        FlipTypeControlToUseExclusiveTypeRector::class,
        WhileNullableToInstanceofRector::class,
        BinaryOpNullableToInstanceofRector::class,
        // disable renaming exception variables
        CatchExceptionNameMatchingTypeRector::class,
        // disable converting enscaped strings, which makes things less readable
        EncapsedStringsToSprintfRector::class,
        // disable converting ! on nullable to !== null
        NullableCompareToNullRector::class,
        // no need to check .git directory
        __DIR__ . '/.git',
        // no need to touch vendor files
        __DIR__ . '/vendor',
        __DIR__ . '/node_modules',
    ])
;
