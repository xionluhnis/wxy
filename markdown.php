<?php

include_once 'files.php';
include_once 'hooks.php';

/**
 * Markdown utility
 *
 * @author Alexandre Kaspar
 */
class Markdown {

	private static $parser;

	/**
	 * Register a markdown parser
	 */
	public static function register($parser) {
		self::$parser = $parser;
	}

	/**
	 * Parses the content using Markdown
	 *
	 * @param string $content the raw txt content
	 * @return string $content the Markdown formatted content
	 */
	public static function parse_content($content) {
		$content = preg_replace('#/\*.+?\*/#s', '', $content); // Remove comments and meta
		$content = str_replace('%base_url%', Request::base_url(), $content);
		$parser = self::$parser;
		return $parser($content);
	}

	/**
	 * Parses the file meta from the txt file header
	 *
	 * @param string $content the raw txt content
	 * @return array $headers an array of meta values
	 */
	public static function read_file_meta($content, $headers) {
		global $config;

		foreach ($headers as $field => $regex) {
			if (preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi', $content, $match) && $match[1]) {
				$headers[$field] = trim(preg_replace("/\s*(?:\*\/|\?>).*/", '', $match[1]));
			} else {
				$headers[$field] = '';
			}
		}

		if (isset($headers['date']) && isset($config['date_format']))
			$headers['date_formatted'] = date($config['date_format'], strtotime($headers['date']));
		return $headers;
	}

	/**
	 * Get a list of pages
	 *
	 * @param string file the file we get the content of
	 * @param HookEnvironment $env
	 * @return array $sorted_pages an array of pages
	 */
	public static function get_pages($file, $env, $headers = array()) {
		global $config;
        
        $cur_dir = dirname($file);
		$base_url = Request::base_url();
		$dir_url = Request::route();
		if(substr($dir_url, -1) != '/'){
			$dir_url = $base_url . dirname(str_replace($base_url, '', $dir_url));
		}
		$pages = Files::find($cur_dir, CONTENT_EXT);
		$sorted_pages = array();
		$date_id = 0;
		foreach ($pages as $key => $page) {
			// Skip 404
			if (basename($page) == '404' . CONTENT_EXT) {
				unset($pages[$key]);
				continue;
			}

			// Ignore Emacs (and Nano) temp files
			if (in_array(substr($page, -1), array('~', '#'))) {
				unset($pages[$key]);
				continue;
			}
			// Get title and format $page
			$page_content = file_get_contents($page);
			$page_meta = Markdown::read_file_meta($page_content, $headers);
			$page_content = Markdown::parse_content($page_content);
			$url = str_replace($cur_dir, $dir_url . '/', $page);
			$url = str_replace('index' . CONTENT_EXT, '', $url);
			$url = str_replace(CONTENT_EXT, '', $url);
			$data = array(
				'title' => isset($page_meta['title']) ? $page_meta['title'] : '',
                'url' => $url,
                'file' => $page,
				'author' => isset($page_meta['author']) ? $page_meta['author'] : '',
				'date' => isset($page_meta['date']) ? $page_meta['date'] : '',
				'date_formatted' => isset($page_meta['date']) ? date($config['date_format'], strtotime($page_meta['date'])) : '',
				'content' => $page_content,
				'excerpt' => Text::limit_words(strip_tags($page_content), $config['excerpt_length'])
			);

			// Extend the data provided with each page by hooking into the data array
			$env->run_hooks('after_indexing_content', array(&$data, $page_meta));

			if ($config['order_by'] == 'date' && isset($page_meta['date'])) {
				$sorted_pages[$page_meta['date'] . $date_id] = $data;
				$date_id++;
			}
			else
				$sorted_pages[] = $data;
		}

		if ($config['order'] == 'desc')
			krsort($sorted_pages);
		else
			ksort($sorted_pages);

		return $sorted_pages;
	}

}

?>
