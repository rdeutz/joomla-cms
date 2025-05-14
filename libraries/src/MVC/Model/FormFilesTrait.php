<?php

namespace Joomla\CMS\MVC\Model;

use Joomla\CMS\Form\Form;
use Joomla\Filesystem\Path;

trait FormFilesTrait {

    public function findFormFile($file)
    {
        // Check to see if the path is an absolute path.
        if (!is_file($file)) {
            // Not an absolute path so let's attempt to find one using JPath.
            $file = Path::find(Form::addFormPath(), strtolower($file) . '.xml');

            // If unable to find the file return false.
            if (!$file) {
                return false;
            }
        }

        return $file;
    }

    public function findLayoutFile($file)
    {
        $path = dirname($file);
        $filename = basename($file, '.xml');

        $layoutFile = $path . '/' . $filename . '.layout.xml';

        if (file_exists($layoutFile)) {
            return  $layoutFile;
        }

        return false;
    }
}
