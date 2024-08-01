<?php

namespace Ivo\LoadScripts\Classes;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\LayoutModel;

#[AsCallback(table: 'tl_layout', target: 'config.onload')]
class DcaCallback
{
    public function onLoadCallback($dc)
    {
        $objLayout = LayoutModel::findByPk($dc->id);
        if (!$objLayout) {
            return;
        }
        if ($objLayout->externalJs && !$objLayout->externalJsBody) {
            $objLayout->externalJsBody = $objLayout->externalJs;
            $objLayout->externalJs = null;
        }
        if ($objLayout->external && !$objLayout->externalCssBody) {
            $objLayout->externalCssBody = $objLayout->external;
            $objLayout->external = null;
        }
        $objLayout->save();
    }
}
