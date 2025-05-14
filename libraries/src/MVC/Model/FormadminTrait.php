<?php

namespace Joomla\CMS\MVC\Model;

trait FormadminTrait {

    protected $hasFormDefinition = false;

    public function hasFormDefinition($file)
    {
        return $this->hasFormDefinition;
    }

    public function getLayoutDefinion($file)
    {
        return '';
    }

    public function getFormDefinion($file)
    {
        return '';
    }
}
