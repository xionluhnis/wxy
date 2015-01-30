# wxy
Personal Content Management System using raw file storage, templating and markdown content in a modular way

## About wxy
wxy is built upon [PicoCMS](http://picocms.org) modular file-based content management system:

* **Modular**: plugins can hook to each of the main parts of the page generation
* **Simple**: make it as simple as possible, but not simpler; less is more
* **File-based**: no database is required, files store what they should, such as images, text (markdown) or parameters (json)
* **Templated**: use of [Twig](http://twig.sensiolabs.org/) templates

## Page cycle

1. **Plugin loading**: the many different plugins are loaded
2. **Configuration loading**: the configuration is loaded (resolution from the url)
3. **Url request treatment**: the routing is made (plugins can do big work here)

Unless any plugin decides to take over the content processing, the page is then
generated by 4. parsing the necessary content, 5. creating an index and 6. generating the page from the template.

## Page hooks
* `plugins_loaded(HookEnvironment &$env)`
* `config_loaded(array &$config)`
* `request_url(string &$route)`
* content loading
  * `before_load_content(string &$file)`
  * `after_load_content(string &$file, string &$content)`
  * `before_404_load_content(string &$file)`
  * `after_404_load_content(string &$file, string &$content)`
* metadata-related
  * `before_file_meta(string $content, array &$headers)`
  * `get_file_meta(string $content, array &$headers, array &$meta)`
  * `after_file_meta(string $content, array &$meta)`
* parsing-related
  * `before_parse_content(string &$content)`
  * `parse_content(string $content, string &$new_content)`
  * `after_parse_content(string &$new_content)`
* indexing-related
  * `get_index(string $file, HookEnvironment $env, array &$index)`
  * `after_index(array &$index, array &$current, array &$prev, array &$next)`
  * `indexing_content(string $file, array $headers, array &$data)`
  * `after_indexing_content(array &$data, array $meta)`
* twig-related
  * `before_twig_register()`
  * `before_render(array &$twig_vars, TwigEnvironment &$twig, string &$template)`
  * `after_render(string &$output)`

Plugins can register new hooks (which they should do early such as in `plugins_loaded(&$env)`).

## TODO

* Create plugin and associated theme to have a full index of files (.md => pages, .png/.jpg/.gif => image display, other => downloadable file)
* Add plugin for content management (probably want to pull data from yoctomanager)
* Have a way to disable plugins or tweak the resolution process for plugin loading, so that one can use wxy
  at the base of a filesystem, and just add `config.php`, plugins for the child applications
* Integrate *json* for parameters
* Create themes and deploy
