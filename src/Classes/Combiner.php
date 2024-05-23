<?php

namespace Ivo\LoadScripts\Classes;

use Contao\Combiner as ContaoCombiner;

class Combiner extends ContaoCombiner
{
    public function getCombinedFile($strUrl = null)
    {
        return $this->getCombinedFileUrl($strUrl);
    }
}