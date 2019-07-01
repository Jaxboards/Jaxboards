<?php

class TEMPLATE
{
    public $filename = "";
    private $templateData = "";
    private $renderData = "";

    public function __construct($_filename)
    {

        $file_parts = pathinfo($_filename);
        if (!$file_parts['extension']) {
            $_filename = $_filename.".hbs";
        }

        $this->filename = $_filename;

        $this->loadTemplate();
    }

    public function loadTemplate()
    {
        $this->templateData = file_get_contents($this->filename);
    }

    // Using the loaded Template String replace with the provided properties
    // set $this->render with the finished rendering of the template
    public function render($_props)
    {
        if (trim($this->templateData) !== '') {
            // Set render for template starting point
            $renderData = $this->templateData;
            foreach ($_props as $key => $value) {
                $pattern = '/{{'.$key.'}}/';
                $renderData = preg_replace($pattern, $value, $renderData);
            }

            return $renderData;
        }
    }
}
