<?php

include_once "files.php";

/**
 * Single hook
 */
class Hook {

	private $name;
	private $hooks;

	public function __construct($hook_name) {
		$this->name = $hook_name;
		$this->hooks = array();
	}

	public function connect($plugin) {
		if (is_callable(array($plugin, $this->name))) {
			$this->hooks[] = $plugin;
			return true;
		}
		return false;
	}

	public function run(array $args = array()) {
		if (empty($this->hooks)) {
			return;
		}
		foreach ($this->hooks as $hook) {
			// if(is_callable(array($hook, $this->name))){
			call_user_func_array(array($hook, $this->name), $args);
			// }
		}
	}

}

/**
 * Hook manager that keeps all the hooks and corresponding plugins
 */
class HookEnvironment {

	private $hooks;
	private $plugins;

	/**
	 * Construct a plugin manager with a set of default hooks
	 * 
	 * @param array $default_hooks an array of hook names
	 */
	public function __construct(array $default_hooks = array()) {
		$this->hooks = array();
		foreach ($default_hooks as $hook_name) {
			$this->add_hook($hook_name, FALSE);
		}
		$this->plugins = array();
	}

	/**
	 * Register a new hook and connect it to the past plugins
	 * 
	 * @param string $hook_name
	 * @return boolean whether a new hook got created
	 */
	public function add_hook($hook_name, $connect = TRUE) {
		if (array_key_exists($hook_name, $this->hooks)) {
			return FALSE;
		}
		$hook = new Hook($hook_name);
		$this->hooks[$hook_name] = $hook;
		if (!$connect)
			return TRUE;
		// attempts to connect the new hook with past plugins
		foreach ($this->plugins as $plugin) {
			$hook->connect($plugin);
		}
		return TRUE;
	}

	/**
	 * Register a new plugin
	 * 
	 * @param mixed $plugin the plugin to register
	 */
	public function register_plugin($plugin) {
		$this->plugins[] = $plugin;
		if (empty($this->hooks)) {
			return;
		}
		foreach ($this->hooks as $hook) {
			$hook->connect($plugin);
		}
	}

	/**
	 * Load a plugin class
	 * 
	 * @param string $plugin_file the plugin file name
	 */
	public function load_plugin($plugin_file) {
		include_once($plugin_file);
		$plugin_name = preg_replace("/\\.[^.\\s]{3}$/", '', basename($plugin_file));
		if (class_exists($plugin_name)) {
			$obj = new $plugin_name;
			$this->register_plugin($obj);
		}
	}

	/**
	 * Load all the plugins that can be resolved
	 * 
	 * @param string $plugin_dir the directory to load plugins from
	 */
	public function load_dir($plugin_dir = 'plugins') {
		$dirs = Files::resolve_all($plugin_dir);
		foreach($dirs as $dir){
			$plugins = Files::find($dir, '.php', FALSE);
			if (empty($plugins)) {
				return;
			}
			foreach ($plugins as $plugin) {
				$this->load_plugin($plugin);
			}
		}
	}
	
	/**
	 * Trigger hooks
	 * 
	 * @param string $hook_name
	 * @param type $args
	 */
	public function run_hooks($hook_name, $args = array()){
		$hook = $this->hooks[$hook_name];
		if(!empty($hook)){
			$hook->run($args);
		}
	}

}

?>
