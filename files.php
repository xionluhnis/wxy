<?php

/**
 * File utility functions
 *
 * @author Alexandre Kaspar
 */
class Files {

	/**
	 * Helper function to recusively get all files in a directory
	 *
	 * @param string $directory start directory
	 * @param string $ext optional limit to file extensions
	 * @param boolean $recursive whether to search recursively
	 * @return array the matched files
	 */
	public static function find($directory, $ext = '', $recursive = TRUE) {
		$array_items = array();
		if ($handle = opendir($directory)) {
			while (false !== ($file = readdir($handle))) {
				if (preg_match("/^(^\.)/", $file) === 0) {
					if (is_dir($directory . "/" . $file)) {
						if ($recursive) {
							$array_items = array_merge($array_items, 
									Files::find($directory . "/" . $file, $ext));
						}
					} else {
						$file = $directory . "/" . $file;
						if (!$ext || strstr($file, $ext))
							$array_items[] = preg_replace("/\/\//si", "/", $file);
					}
				}
			}
			closedir($handle);
		}
		return $array_items;
	}

	/**
	 * Try to resolve a filename
	 */
	public static function resolve($filename) {
		$root = $_SERVER['DOCUMENT_ROOT'];
		$uri = $_SERVER['REQUEST_URI'];
		$uri = preg_replace('/\?.*/', '', $uri); // Strip query string
		$path = explode('/', $uri);
		do {
			$file = $root . implode('/', $path) . '/' . $filename;
			if (file_exists($file)) {
				return $file;
			} else {
				array_pop($path);
			}
		} while (!empty($path));
		// try in the directory of script_name
		$file = dirname($_SERVER['SCRIPT_FILENAME']) . '/' . $filename;
		if (file_exists($file)) {
			return $file;
		} else {
			return NULL;
		}
	}

	public static function resolve_all($filename) {
		$files = array();
		$root = $_SERVER['DOCUMENT_ROOT'];
		$uri = $_SERVER['REQUEST_URI'];
		$uri = preg_replace('/\?.*/', '', $uri); // Strip query string
		$path = explode('/', $uri);
		do {
			$file = $root . implode('/', $path) . '/' . $filename;
			if (file_exists($file)) {
				$files[] = $file;
			}
			array_pop($path);
		} while (!empty($path));
		// try in the directory of script_name
		$file = dirname($_SERVER['SCRIPT_FILENAME']) . '/' . $filename;
		if (file_exists($file)) {
			$files[] = $file;
		}
		return $files;
	}

	/**
	 * Tries to resolve the current directory
	 */
	public static function current_dir() {
		$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
		$current = $root . Files::current_uri();
		$len = strlen($current);
		if (substr($current, $len - 1) === '/')
			return substr($current, 0, $len - 1);
		return dirname($current);
	}

	public static function current_uri() {
		$uri = $_SERVER['REQUEST_URI'];
		return preg_replace('/\?.*/', '', $uri); // Strip query string
	}

	public static function current_file() {
		return basename(Files::current_uri());
	}

	/**
	 * Helper function to limit the words in a string
	 *
	 * @param string $string the given string
	 * @param int $word_limit the number of words to limit to
	 * @return string the limited string
	 */
	public static function limit_words($string, $word_limit) {
		$words = explode(' ', $string);
		$excerpt = trim(implode(' ', array_splice($words, 0, $word_limit)));
		if (count($words) > $word_limit)
			$excerpt .= '&hellip;';
		return $excerpt;
	}

	/**
	 * Loads the config
	 *
	 * @return array $config an array of config values
	 */
	public static function get_config($defaults = array()) {
		global $config;
		if (is_array($config)) {
			$old_config = $config;
		} else {
			$old_config = $defaults;
		}
		@include_once(self::resolve('config.php'));

		if ($config == $old_config)
			return $config; // no need to merge
		if (is_array($config))
			$config = array_merge($old_config, $config);
		else
			$config = $old_config;

		return $config;
	}

}

/**
 * HTTP utility functions
 */
class HTTP {

	/**
	 * Helper function to work out the base URL
	 *
	 * @return string the base url
	 */
	public static function base_url() {
		$url = '';
		$request_url = (isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : '';
		$script_url = (isset($_SERVER['PHP_SELF'])) ? $_SERVER['PHP_SELF'] : '';
		if ($request_url != $script_url)
			$url = trim(preg_replace('/' . str_replace('/', '\/', str_replace('index.php', '', $script_url)) . '/', '', $request_url, 1), '/');

		$protocol = HTTP::get_protocol();
		return rtrim(str_replace($url, '', $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']), '/');
	}

	/**
	 * Tries to guess the server protocol. Used in base_url()
	 *
	 * @return string the current protocol
	 */
	public static function get_protocol() {
		$protocol = 'http';
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
			$protocol = 'https';
		}
		return $protocol;
	}

}

?>
