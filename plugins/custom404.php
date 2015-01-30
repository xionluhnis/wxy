<?php

include_once '../files.php';

/**
 * Custom 404 Page Not Found using a 404.md file
 *
 * @author Alexandre Kaspar
 */
class Custom404 {
    public function after_404_load_content(&$file, &$content){
        $custom_file = Files::resolve('404.md');
        if(!empty($custom_file) && is_file($custom_file)){
            $content = file_get_contents($custom_file);
        }
    }
}

?>
