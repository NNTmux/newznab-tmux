<?php

/**
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 *
 * Copyright (c) 2014-2017 Yuuki Takezawa
 */

/*
 * Smarty configure.
 * @author  yuuki.takezawa<yuuki.takezawa@comnect.jp.net>
 * @license http://opensource.org/licenses/MIT MIT
 */
return [

    // smarty file extension
    'extension' => 'tpl',

    //
    'debugging' => env('SMARTY_DEBUG', false),

    // use cache
    'caching' => env('SMARTY_CACHING', false),

    //
    'cache_lifetime' => env('SMARTY_CACHE_LIFE', 120),

    //
    'compile_check' => env('SMARTY_COMPILE_CHECK', false),

    // delimiters
    // default "{$smarty}"
    'left_delimiter' => '{',
    'right_delimiter' => '}',

    // path info
    'template_path' => base_path().'/resources/views/themes',

    // smarty cache directory
    'cache_path' => storage_path().'/framework/smarty/cache',

    // smarty template compiler
    'compile_path' => storage_path().'/framework/smarty/templates_c',

    // smarty plugins
    'plugins_paths' => [
        base_path().'/vendor/smarty/libs/plugins/',
        base_path().'/resources/smarty/plugins/',

    ],

    // smarty configure
    'config_paths' => [
        base_path().'/resources/smarty/config',
    ],

    /*
     * for develop true
     * for production false
     */
    'force_compile' => env('SMARTY_FORCE_COMPILE', false),

    // smarty cache driver "file", "memcached", "redis"
    'cache_driver' => env('SMARTY_CACHE_DRIVER', 'file'),

    // memcached servers
    'memcached' => [
        [
            'host' => '127.0.0.1',
            'port' => 11211,
            'weight' => 100,
        ],
    ],

    // redis configure
    'redis' => [
        [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => 0,
        ],
    ],

    /*
     * All smarty config properties are available below
     * Leave settings commented to use smarty's default values
     * See smarty docs for usage
     */

    //    'auto_literal'                        => null,
    //    'error_unassigned'                    => null,
    //    'use_include_path'                    => null,
    //    'joined_template_dir'                 => null,
    //    'joined_config_dir'                   => null,
    //    'default_template_handler_func'       => null,
    //    'default_config_handler_func'         => null,
    //    'default_plugin_handler_func'         => null,
    //    'use_sub_dirs'                        => null,
    //    'allow_ambiguous_resources'           => null,
    //    'merge_compiled_includes'             => null,
    //    'inheritance_merge_compiled_includes' => null,
    //    'force_cache'                         => null,
    //    'security_class'                      => null,
    //    'php_handling'                        => null,
    //    'allow_php_templates'                 => null,
    //    'direct_access_security'              => null,
    //    'debugging_ctrl'                      => null,
    //    'smarty_debug_id'                     => null,
    //    'debug_tpl'                           => null,
    //    'error_reporting'                     => null,
    //    'get_used_tags'                       => null,
    //    'config_overwrite'                    => null,
    //    'config_booleanize'                   => null,
    //    'config_read_hidden'                  => null,
    //    'compile_locking'                     => null,
    //    'cache_locking'                       => null,
    //    'locking_timeout'                     => null,
    //    'default_resource_type'               => null,
    //    'caching_type'                        => null,
    //    'properties'                          => null,
    //    'default_config_type'                 => null,
    //    'source_objects'                      => null,
    //    'template_objects'                    => null,
    //    'resource_caching'                    => null,
    //    'template_resource_caching'           => null,
    //    'cache_modified_check'                => null,
    //    'registered_plugins'                  => null,
    //    'plugin_search_order'                 => null,
    //    'registered_objects'                  => null,
    //    'registered_classes'                  => null,
    //    'registered_filters'                  => null,
    //    'registered_resources'                => null,
    //    '_resource_handlers'                  => null,
    //    'registered_cache_resources'          => null,
    //    '_cacheresource_handlers'             => null,
    //    'autoload_filters'                    => null,
    //    'default_modifiers'                   => null,
    //    'escape_html'                         => null,
    //    'start_time'                          => null,
    //    '_file_perms'                         => null,
    //    '_dir_perms'                          => null,
    //    '_tag_stack'                          => null,
    //    '_current_file'                       => null,
    //    '_parserdebug'                        => null,
    //    '_is_file_cache'                      => null,
    //    'cache_id'                            => null,
    //    'compile_id'                          => null,
    //    'template_class'                      => null,
    //    'tpl_vars'                            => null,
    //    'parent'                              => null,
    //    'config_vars'                         => null,

    /*
     *  If true smarty will enable security `$smarty->enableSecurity()`
     */
    //    'enable_security'                     => false,

    /*
     * The following settings will be applied to the smarty security policy object `$smarty->security_policy`
     * Ignored if enable_security == false
     */

    //     'security_policy' => [
    //         'php_handling' => null,
    //         'secure_dir' => null,
    //         'trusted_dir' => null,
    //         'trusted_uri' => null,
    //         'trusted_constants' => null,
    //         'static_classes' => null,
    //         'trusted_static_methods' => null,
    //         'trusted_static_properties' => null,
    //         'php_functions' => null,
    //         'php_modifiers' => null,
    //         'allowed_tags' => null,
    //         'disabled_tags' => null,
    //         'allowed_modifiers' => null,
    //         'disabled_modifiers' => null,
    //         'disabled_special_smarty_vars' => null,
    //         'streams' => null,
    //         'allow_constants' => null,
    //         'allow_super_globals' => null,
    //         'max_template_nesting' => null,
    //     ]

];
