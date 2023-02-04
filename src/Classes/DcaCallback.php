<?php

namespace Ivo\LoadScripts\Classes;

use Contao\LayoutModel;

class DcaCallback
{
    public function onLoadCallback($dc)
    {
        $objLayout = LayoutModel::findByPk($dc->id);
        $objLayout->externalJsBody = $objLayout->externalJs;
        $objLayout->externalJs = null;
        $objLayout->save();
    }
}