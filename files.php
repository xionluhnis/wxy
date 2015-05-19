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
     * @param boolean $list_dirs whether to include directories (except for . and ..)
     * @return array the matched files
     */
    public static function find($directory, $ext = '', $recursive = TRUE, $list_dirs = FALSE) {
        $array_items = array();
        if (is_dir($directory) && $handle = opendir($directory)) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match("/^(^\.)/", $file) === 0) {
                    $full_file = $directory . '/' . $file;
                    if (is_dir($full_file)) {
                        if ($list_dirs){
                            if (!$ext || strstr($full_file, $ext))
                                $array_items[] = preg_replace("/\/\//si", '/', $full_file);
                        }
                        if ($recursive) {
                            $array_items = array_merge($array_items, 
                                    Files::find($full_file, $ext));
                        }
                    } else {
                        if (!$ext || strstr($full_file, $ext))
                            $array_items[] = preg_replace("/\/\//si", '/', $full_file);
                    }
                }
            }
            closedir($handle);
        }
        return $array_items;
    }

    /**
     * Search for files up to $levels level
     *
     * @param string $directory the base directory to search in
     * @param number $levels the depth of search
     * @return array the list of pages found
     */
    public static function find_pages($directory, $levels = 0) {
        $array_items = array();
        if (is_dir($directory) && $handle = opendir($directory)) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match("/^(^\.)/", $file) === 0) {
                    $full_file = $directory . '/' . $file;
                    if (is_dir($full_file)) {
                        if ($levels > 0) {
                            $array_items = array_merge($array_items, 
                                    Files::find_pages($full_file, $levels - 1));
                        } else if(is_file($full_file . '/index' . CONTENT_EXT)){
                            $array_items[] = preg_replace("/\/\//si", '/', $full_file . '/index' . CONTENT_EXT);
                        }
                    } else if(Text::ends_with($full_file, CONTENT_EXT)){
                        $array_items[] = preg_replace("/\/\//si", '/', $full_file);
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
        $uri  = '/' . trim(Files::current_uri(), '/'); // we don't need the left or right slashes
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

    /**
     * Resolve a filename and return all potential values
     * 
     * @param string $filename the file or directory name
     * @return array the list of resolved files
     */
    public static function resolve_all($filename) {
        $files = array();
        $root = $_SERVER['DOCUMENT_ROOT'];
        $uri  = '/' . trim(Files::current_uri() , '/');
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
        return array_unique($files);
    }

    /**
     * Resolve the page for a given route
     *
     * @param string $route
     * @param string $file_ext the file extension for content (CONTENT_EXT by default)
     * @return string the page url
     */
    public static function resolve_page($route, $file_ext = CONTENT_EXT) {
        $base_dir  = rtrim(Files::base_dir(), '/');
        // Get the file path
        $base_file = rtrim($base_dir . $route, '/');
        $file = $base_file . $file_ext;
        if(is_dir($base_file)){
            $index_file = $base_file . '/index' . $file_ext;
            if(is_file($index_file)){
                $file = $index_file;
            }
        }
        return $file;
    }
    
    /**
     * Retrieve the requested uri without the query
     */
    public static function current_uri() {
        $uri = (isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : '';
        return preg_replace('/\?.*/', '', $uri); // Strip query string
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

    public static function base_dir() {
        $root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        $base_url = Request::base_url();
        return $root . $base_url;
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
        $config_file = Files::resolve('config.php');
        if(file_exists($config_file)){
            @include_once($config_file);
        } else {
            die('No config.php found. Cannot resolve $base_url.');
        }

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
 * Request utility functions
 */
class Request {

    /**
     * Helper function to work out the base URL
     *
     * @return string the base url
     */
    public static function default_base_url() {
        $url = '';
        $request_url = (isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : '';
        $script_url = (isset($_SERVER['PHP_SELF'])) ? $_SERVER['PHP_SELF'] : '';
        if ($request_url != $script_url)
            $url = trim(preg_replace('/' . str_replace('/', '\/', str_replace('index.php', '', $script_url)) . '/', '', $request_url, 1), '/');

        $protocol = Request::get_protocol();
        return rtrim(str_replace($url, '', $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']), '/');
    }
    
    /**
     * Retrieve the base url from the configuration
     * 
     * @return string the base url (without ending /)
     */
    public static function base_url() {
        global $config;
        if(!is_array($config) || !array_key_exists('base_url', $config)){
            die('Requesting url path without base_url in configuration!');
        }
        return rtrim($config['base_url'], ' /');
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
    
    /**
     * Returns the url path above the url base
     * 
     * @return string the route parameter
     */
    public static function route() {
        $url = str_replace(Request::base_url(), '', Files::current_uri()); // Remove base url
        $url = trim($url); // Trim white spaces
        $url = '/' . ltrim($url, '/'); // Trim potential left slashes to normalize
        return $url;
    }

    /**
     * Return the filename corresponding to a url
     *
     * @param string $file_url
     * @param string $base_url
     * @return string the filename (without extension)
     */
    public static function get_real_filename($file_url, $base_url = FALSE) {
        $file_components = parse_url($file_url);
        $base_components = parse_url($base_url ? $base_url : self::base_url());
        $file_path = rtrim($file_components['path'], '/');
        $base_path = rtrim($base_components['path'], '/');
        var_dump($file_path);
        var_dump($base_path);
        if(empty($file_path) || $file_path === $base_path){
            return '/index';
        } else {
            $file_path = strip_tags(substr($file_path, strlen($base_path)));
            if(is_dir($file_path)){
                $file_path .= "/index";
            }
            return $file_path;
        }
    }

    /**
     * Retrieve a parameter from a request, while removing tags and providing a string default
     *
     * @param string $name the parameter name
     * @param string $default_value
     * @param string $from
     * @return string the parameter value
     */
    public static function get_parameter($name, $default_value = '', $from = FALSE) {
        if($from === FALSE)
            $from = $_POST;
        return isset($from[$name]) && $from[$name] ? strip_tags($from[$name]) : $default_value;
    }

}


/**
 * Utility methods for text processing
 */
class Text {
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
     * Check whether a string starts with another string
     *
     * @param string $haystack the string to look at the beginning of
     * @param string $needle the string to look for
     * @return boolean whether $haystack starts with $needle
     */
    public static function starts_with($haystack, $needle){
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }

    /**
     * Check whether a string ends with another string
     *
     * @param string $haystack the string to look at the end of
     * @param string $needle the string to look for
     * @return boolean whether $haystack ends with $needle
     */
    public static function ends_with($haystack, $needle){
        return substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * Helper function to clean extra slashes in urls and file paths
     *
     * @param string $str the string to clean
     * @return string the string without duplicate slashes
     */
    public static function clean_slashes($str){
        $off = 0;
        while(FALSE !== ($pos = strpos($str, '//', $off))){
            $str = substr($str, 0, $pos) . substr($str, $pos + 1);
            $off = $pos;
        }
        return $str;
    }

}

?>
