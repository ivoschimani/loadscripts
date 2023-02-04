<?php

namespace Ivo\LoadScripts\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Ivo\LoadScripts\LoadScripts;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;

class Plugin implements BundlePluginInterface
{

    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(LoadScripts::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}
