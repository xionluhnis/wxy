<?php

define('CONTENT_EXT', '.md');
define('ROOT_DIR', dirname(__FILE__));

// Our helper functions --------------------------------------------------------
include_once 'chrono.php';
include_once 'files.php';
include_once 'hooks.php';
include_once 'markdown.php';

// Registering a markdown parser -----------------------------------------------
include_once 'libs/parsedown/Parsedown.php';
include_once 'libs/parsedown-extra/ParsedownExtra.php';
$mkdown = new ParsedownExtra();
Markdown::register(function($text) use (&$mkdown) {
    return $mkdown->text($text);
});

// Loading our twig version ----------------------------------------------------
include_once 'libs/twig/lib/Twig/Autoloader.php';

/**
 * wxy - three-letter-cms
 *
 * @author Alexandre Kaspar
 * @link http://github.com/xionluhnis/wxy
 * @license http://opensource.org/licenses/MIT
 * @version 0.1
 * @see PicoCMS https://github.com/picocms/Pico/blob/master/lib/pico.php
 */


// 0 = Create hooks ------------------------------------------------------------
tic();
$env = new HookEnvironment(array(
    'plugins_loaded',   // when plugins are loaded
    'config_loaded',    // when the configuration is loaded
    'request_url',      // for reacting to the url request
    // load content
    'before_load_content',
    'after_load_content',
    'before_404_load_content',
    'after_404_load_content',
    // get file meta
    'before_file_meta',
    'get_file_meta',
    'after_file_meta',
    // parse content
    'before_parse_content',
    'parse_content',
    'after_parse_content',
    // get index
    'get_index',        // for loading the index
    'after_index',      // when the index is loaded
    // indexing hooks
    'indexing_content',
    'after_indexing_content',
    // twig
    'before_twig_register',
    'before_render',
    'after_render',
));
toc('env');


// 1 = Load plugins ------------------------------------------------------------
$env->load_dir('plugins');
$env->run_hooks('plugins_loaded', array(&$env));
toc('plugins');


// 2 = Load the settings -------------------------------------------------------
$defaults = array(
    'site_title'      => 'wxy',
    'base_dir'        => rtrim(dirname($_SERVER['SCRIPT_FILENAME']), '/'),
    'base_url'        => Request::default_base_url(),
    'theme_dir'       => 'themes',
    'theme'           => 'index',
    'date_format'     => 'jS M Y',
    'twig_config'     => array('cache' => false, 'autoescape' => false, 'debug' => false),
    'order_by'        => 'alpha',
    'order'           => 'asc',
    'excerpt_length'  => 50,
    'timezone'        => 'America/New_York',
    'debug'           => FALSE,
);
$settings = Files::get_config($defaults);
$env->run_hooks('config_loaded', array(&$settings));
if($settings['debug']){
  ini_set('display_errors', '1');
}
date_default_timezone_set($settings['timezone']);
toc('config');


// 3 = Request routing ---------------------------------------------------------
$route = Request::route();
$env->run_hooks('request_url', array(&$route));
toc('route');


// 4 = Load content ------------------------------------------------------------
$file = Files::resolve_page($route);
$env->run_hooks('before_load_content', array(&$file));
if(file_exists($file)){
    $content = file_get_contents($file);
} else {
    $env->run_hooks('before_404_load_content', array(&$file));
    $content = '
/*
Title: Error 404
Robots: noindex,nofollow
*/

Error 404
=========

Woops. Looks like this page doesn\'t exist.';
    header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
    $env->run_hooks('after_404_load_content', array(&$file, &$content));
}
$env->run_hooks('after_load_content', array(&$file, &$content));
toc('content');


// 5 = Load meta ---------------------------------------------------------------
$headers = array(
    'title' => 'Title',
    'description' => 'Description',
    'author' => 'Author',
    'date' => 'Date',
    'robots' => 'Robots',
    'template' => 'Template'
);
$env->run_hooks('before_file_meta', array($content, &$headers));
$meta = array();
$env->run_hooks('get_file_meta', array($content, &$headers, &$meta));
if(empty($meta)){
    $meta = Markdown::read_file_meta($content, $headers);
}
$env->run_hooks('after_file_meta', array($content, &$meta));
toc('meta');


// 6 = Parse content -----------------------------------------------------------
$env->run_hooks('before_parse_content', array(&$content));
$new_content = FALSE;
$env->run_hooks('parse_content', array($content, &$new_content));
if($new_content === FALSE){
    $new_content = Markdown::parse_content($content);
}
$env->run_hooks('after_parse_content', array(&$new_content));
$content = $new_content;
toc('parse');


// 7 = Create index ------------------------------------------------------------
// Get all the pages
$index = FALSE;
$env->run_hooks('get_index', array($file, $env, $headers, &$index));
if(!is_array($index)){
    $index = Markdown::get_pages($file, $env, $headers);
}
$prev_page = array();
$current_page = array();
$next_page = array();
while($current_page = current($index)){
    if($file == $current_page['file']){
        break;
    }
    next($index);
}
$prev_page = next($index);
prev($index);
$next_page = prev($index);
$env->run_hooks('after_index', array(&$index, &$current_page, &$prev_page, &$next_page));
toc('index');


// 8 = Template rendering ------------------------------------------------------
// Load the theme
$env->run_hooks('before_twig_register');
Twig_Autoloader::register();
$theme_base_dir = Files::resolve($settings['theme_dir']) . '/' . $settings['theme'];
$theme_base_route = $settings['theme_dir'] . '/' . $settings['theme'];
$loader = new Twig_Loader_Filesystem($theme_base_dir);
$twig = new Twig_Environment($loader, $settings['twig_config']);
if(array_key_exists('debug', $settings) && $settings['debug']){
    $twig->addExtension(new Twig_Extension_Debug());
}
$base_url = $settings['base_url'];
$parent_route = dirname(str_replace($base_url, '', $route));
$twig_vars = array(
    'config'        => $settings,
    'base_dir'      => $settings['base_dir'],
    'base_url'      => $base_url,
    'theme_dir'     => $settings['theme'],
    'theme_url'     => $base_url . '/' . $theme_base_route,
    'site_title'    => $settings['site_title'],
    'meta'          => $meta,
    'content'       => $content,
    'index'         => $index,
    'prev_page'     => $prev_page,
    'current_page'  => $current_page,
    'next_page'     => $next_page,
    'current_route' => $route,
    'parent_route'  => $parent_route,
    'current_url'   => rtrim($base_url . $route, '/'),
    'parent_url'    => rtrim($base_url . $parent_route, '/'),
    'is_front_page' => trim($route, ' /') ? false : true,
);
if(isset($meta['template']) && $meta['template'])
    $template = $meta['template'];
else
    $template = 'index';
$env->run_hooks('before_render', array(&$twig_vars, &$twig, &$template));
$output = $twig->render($template . '.html', $twig_vars);
$env->run_hooks('after_render', array(&$output));
echo $output;
toc('template');

if($config['debug']){
    echo "<!--\n";
    time_profile();
    echo "-->\n";
}
?>
