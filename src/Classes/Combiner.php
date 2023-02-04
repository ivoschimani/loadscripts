<?php

namespace Ivo\LoadScripts\Classes;

class Combiner extends \Contao\Combiner
{
    public function getCombinedFile($strUrl = null)
    {
        return $this->getCombinedFileUrl($strUrl);
    }
}