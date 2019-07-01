<?php

// TEMPLATE Class is a singleton that can be statically invoked and will
// store a chache of loaded template files for rendering
class TEMPLATE
{
    private static $TEMPLATE_ARR = [];

    private $filename="";
    private $templateData="";

    private function __construct($_filename){
        $this->filename = $_filename;
        $this->templateData = file_get_contents($this->filename);
    }

    // Using the loaded Template String replace with the provided properties
    // set $this->render with the finished rendering of the template
    public static function render($_filename, $_props){
        $template = null;

        // normalize the filename
        $file_parts = pathinfo($_filename);
        switch($file_parts['extension']){
            case "":
                $_filename = $_filename.".hbs";
            break;
        }

        // load from cache if already loaded otherwise, create template object
        if(array_key_exists($_filename, self::$TEMPLATE_ARR)){
            $template = self::$TEMPLATE_ARR[$_filename];
        } else {
            $template = new TEMPLATE($_filename);
            array_push(self::$TEMPLATE_ARR, [$_filename => $template]);
        }

        // return if template retrieval fails
        if($template == null){
            return null;
        }

        // If the template is loaded and valid atempt to insert values
        if(trim($template->templateData) !== ''){

            // Set render for template starting point
            $renderData = $template->templateData;
            foreach($_props as $key => $value){
                $pattern = '/{{'.$key.'}}/';
                $renderData = preg_replace($pattern, $value, $renderData);
            }

            return $renderData;
        }
    }
}

?>