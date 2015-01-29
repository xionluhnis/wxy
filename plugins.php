<?php

/**
 * Single plugin hook
 */
class PluginHook {
  private $name;
  private $hooks;
  public function __construct($hook_name){
    $this->name = $hook_name;
    $this->hooks = array();
  }

  public function get_name(){
    return $this->name;
  }

  public function connect($plugin){
    if(is_callable(array($plugin, $this->name))){
      $this->hooks[] = $plugin;
      return true;
    }
    return false;
  }

  public function trigger($args = array()){
    if(!empty($this->hooks)){
      foreach($this->hooks as $hook){
        // if(is_callable(array($hook, $this->name))){
        call_user_func_array(array($hook, $this->name), $args);
        // }
      }
    }
  }
}

/**
 * Plugin manager that keeps all the hooks
 */
class PluginManager {
  private $hooks;
  private $plugins;

  public function __construct($default_hooks = array()){
    $this->hooks = array();
    foreach($default_hooks as $hook_name){
      $this->hooks[$hook_name] = new PluginHook($hook_name);
    }
    $this->plugins = array();
  }

  public function register_hook($hook_name){
    if(array_key_exists($hook_name, $this->hooks)){
      return false;
    }
    $hook = new PluginHook($hook_name);
    $this->hooks[$hook_name] = $hook;
    // attempts to connect the new hook with past plugins
    foreach($this->plugins as $plugin){
      $hook->connect($plugin);
    }
    return true;
  }

  public function register_plugin($plugin){
    $this->plugins[] = $plugin;
    if(empty($this->hooks)){
      return;
    }
    foreach($this->hooks as $hook){
      $hook->connect($plugin);
    }
  }

  public function load_plugin($plugin){
		include_once($plugin);
		$plugin_name = preg_replace("/\\.[^.\\s]{3}$/", '', basename($plugin));
		if(class_exists($plugin_name)){
			$obj = new $plugin_name;
      $this->register_plugin($obj);
		}
  }

  public function load_dir($plugin_dir){
		$plugins = get_files($plugin_dir, '.php');
    if(empty($plugins)){
      return;
    }
    foreach($plugins as $plugin){
      $this->load_plugin($plugin);
		}
  }
}

?>
