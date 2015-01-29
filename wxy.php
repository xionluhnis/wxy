<?php

date_default_timezone_set('America/New_York');
define('CONTENT_EXT', '.md');

// Our helper functions --------------------------------------------------------
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
$env = new HookEnvironment(array(
	'plugins_loaded',	// when plugins are loaded
	'config_loaded',	// when the configuration is loaded
	'request_url',		// for reacting to the url request
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
	'get_index',		// for loading the index
	'get_page_data',	// for loading index page content
	'after_index',		// when the index is loaded
	// twig
	'before_twig_register',
	'before_render',
	'after_render',
));


// 1 = Load plugins ------------------------------------------------------------
$env->load_dir('plugins');
$env->run_hooks('plugins_loaded', array(&$env));


// 2 = Load the settings -------------------------------------------------------
$defaults = array(
	'site_title'      => 'wxy',
	'base_dir'        => rtrim(dirname($_SERVER['SCRIPT_FILENAME']), '/'),
	'base_url'        => Request::default_base_url(),
	'theme_dir'       => 'themes',
	'theme'           => 'default',
	'date_format'     => 'jS M Y',
	'twig_config'     => array('cache' => false, 'autoescape' => false, 'debug' => false),
	'pages_order_by'  => 'alpha',
	'pages_order'     => 'asc',
	'excerpt_length'  => 50
);
$settings = Files::get_config($defaults);
$env->run_hooks('config_loaded', array(&$settings));


// 3 = Request routing ---------------------------------------------------------
$route = Request::route();
$env->run_hooks('request_url', array(&$route));


// 4 = Load content ------------------------------------------------------------
$cur_dir  = Files::current_dir();
// Get the file path
if($route)
	$file = $cur_dir . '/' . $route;
else
	$file = $cur_dir . '/index';
// Append extension
if(is_dir($file))
	$file = $cur_dir . '/index' . CONTENT_EXT;
else
	$file .= CONTENT_EXT;

$env->run_hooks('before_load_content', array(&$file));
if(file_exists($file)){
	$content = file_get_contents($file);
} else {
	$env->run_hooks('before_404_load_content', array(&$file));
	// $content = file_get_contents(CONTENT_DIR .'404'. CONTENT_EXT);"
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


// 6 = Parse content -----------------------------------------------------------
$env->run_hooks('before_parse_content', array(&$content));
$new_content = FALSE;
$env->run_hooks('parse_content', array($content, &$new_content));
if($new_content === FALSE){
	$new_content = Markdown::parse_content($content);
}
$env->run_hooks('after_parse_content', array(&$new_content));
$content = $new_content;


// 7 = Create index ------------------------------------------------------------
// Get all the pages
$pages = array();
$env->run_hooks('get_index', array(
	$file, $env,
	$settings['pages_order_by'], 
	$settings['pages_order'], 
	$settings['excerpt_length'], 
	&$pages
));
if(empty($pages)){
	$pages = Markdown::get_pages(
		$file, $env,
		$settings['pages_order_by'],
		$settings['pages_order'],
		$settings['excerpt_length'],
		$headers
	);
}
$prev_page = array();
$current_page = array();
$next_page = array();
while($current_page = current($pages)){
	if((isset($meta['title'])) && ($meta['title'] == $current_page['title'])){
		break;
	}
	next($pages);
}
$prev_page = next($pages);
prev($pages);
$next_page = prev($pages);
$env->run_hooks('after_index', array(&$pages, &$current_page, &$prev_page, &$next_page));


// 8 = Template rendering ------------------------------------------------------
// Load the theme
$env->run_hooks('before_twig_register');
Twig_Autoloader::register();
$theme_base_dir = $settings['theme_dir'] . '/' . $settings['theme'];
$loader = new Twig_Loader_Filesystem($theme_base_dir);
$twig = new Twig_Environment($loader, $settings['twig_config']);
if(array_key_exists('debug', $settings) && $settings['debug']){
	$twig->addExtension(new Twig_Extension_Debug());
}
$twig_vars = array(
	'config' => $settings,
	'base_dir' => $settings['base_dir'],
	'base_url' => $settings['base_url'],
	'theme_dir' => $settings['theme'],
	'theme_url' => $settings['base_url'] . '/' . $theme_base_dir,
	'site_title' => $settings['site_title'],
	'meta' => $meta,
	'content' => $content,
	'pages' => $pages,
	'prev_page' => $prev_page,
	'current_page' => $current_page,
	'next_page' => $next_page,
	'is_front_page' => $url ? false : true,
);
if(isset($meta['template']) && $meta['template'])
	$template = $meta['template'];
else
	$template = 'index';
$env->run_hooks('before_render', array(&$twig_vars, &$twig, &$template));
$output = $twig->render($template . '.html', $twig_vars);
$env->run_hooks('after_render', array(&$output));
echo $output;

?>
