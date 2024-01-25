<?php

namespace Ivo\LoadScripts\EventListener;

use Contao\System;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\Validator;
use Ivo\LoadScripts\Classes\Combiner;
use Ivo\LoadScripts\Classes\CSS;
use MatthiasMullie\Minify;

use function Safe\file_get_contents;

#[AsHook('replaceDynamicScriptTags', priority: -999)]
class ReplaceDynamicScriptTagsListener
{
    /**
     * The .css file extension
     * @var string
     */
    const CSS = '.css';
    const CSS_PATH = 'css';

    /**
     * The .js file extension
     * @var string
     */
    const JS = '.js';
    const JS_PATH = 'js';

    /**
     * Unique file key
     * @var string
     */
    protected $strKey = '';

    /**
     * Operation mode
     * @var string
     */
    protected $strMode;

    protected $rootDir;

    protected $assetsUrl;

    protected $layout;

    public function __invoke(string $buffer): string
    {
        global $objPage;
        $container = System::getContainer();
        $this->rootDir = $container->getParameter('kernel.project_dir');
        $this->assetsUrl = $container->get('contao.assets.assets_context')->getStaticUrl();
        $this->layout = $objPage->getRelated('layout');
        $request = $container->get('request_stack')->getCurrentRequest();
        if (!$objPage->scriptsGenerated && $request && System::getContainer()->get('contao.routing.scope_matcher')->isFrontendRequest($request)) {
            $this->strMode = 'JS';
            $this->replaceDynamicScriptTags($objPage);
            $this->strMode = 'CSS';
            $this->replaceDynamicScriptTags($objPage);
            $objPage->scriptsGenerated = true;
        }
        return $buffer;
    }

    protected function replaceDynamicScriptTags($objPage)
    {
        // Get the files
        if ($this->strMode == 'JS') {
            [$arrHeadFiles, $arrHeadKeys] = $this->getHeadJS();
            [$arrBodyFiles, $arrBodyKeys] = $this->getBodyJS();
            $this->generateJsQueue();
        } else {
            [$arrHeadFiles, $arrHeadKeys] = $this->getHeadCSS();
            [$arrBodyFiles, $arrBodyKeys] = $this->getBodyCSS();
        }
        // Minimize the files
        $strHeadFile = $this->getMinimizedFile($arrHeadFiles, $arrHeadKeys);
        $strBodyFile = $this->getMinimizedFile($arrBodyFiles, $arrBodyKeys);
        // Get the file contents of the Head file
        $strHeadFileContent = file_get_contents($this->rootDir . $strHeadFile);
        if ($this->strMode == 'JS') {
            if ($strHeadFile) {
                // Check if the file is too big for the <head> tag
                if (strlen($strHeadFileContent) > 20000) {
                    // Link the file in the <head> tag
                    $GLOBALS['TL_HEAD']['js_head'] = '<script src="' . $this->assetsUrl . $strHeadFile . '"></script>';
                } else {
                    // Embed the file in the <head> tag
                    $GLOBALS['TL_HEAD']['js_head'] = '<script>' . $strHeadFileContent . '</script>';
                }
            }
            if ($strBodyFile) {
                // Preload the Body JS file
                $GLOBALS['TL_HEAD']['js_body'] = '<link rel="preload" href="' . $this->assetsUrl . $strBodyFile . '" as="script">';
                // Initialize the body Array if it is not set
                if (!\array_key_exists('TL_BODY', $GLOBALS)) {
                    $GLOBALS['TL_BODY'] = [];
                }
                // Add the Body JS file to the body Array
                array_unshift($GLOBALS['TL_BODY'], '<script defer src="' . $this->assetsUrl . $strBodyFile . '" onload="ready()"></script>');
            }
        } else {
            if ($strHeadFile) {
                // Embed the file in the <head> tag
                $GLOBALS['TL_HEAD']['css_head'] = '<style>@charset "UTF-8";' . str_replace('@charset "UTF-8";', '', $strHeadFileContent) . '</style>';
            }
            if ($strBodyFile) {
                // Preload the Body CSS file
                $GLOBALS['TL_HEAD']['css_body'] = '<link rel="preload" href="' . $this->assetsUrl . $strBodyFile . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
                // Initialize the body Array if it is not set
                if (!\array_key_exists('TL_BODY', $GLOBALS)) {
                    $GLOBALS['TL_BODY'] = [];
                }
                // Add the Body CSS file to the body Array
                array_unshift($GLOBALS['TL_BODY'], '<noscript><link rel="stylesheet" href="' . $this->assetsUrl . $strBodyFile . '" media="all"></noscript>');
            }
        }
    }

    protected function getHeadJS()
    {
        $arrFiles = [];
        $arrKeys = [];
        $externalJsHead = StringUtil::deserialize($this->layout->externalJsHead, true);
        if ($this->layout->orderExtJsHead) {
            $externalJsHead = StringUtil::deserialize($this->layout->orderExtJsHead, true);
        }
        if (\array_key_exists('TL_JAVASCRIPT_HEAD', $GLOBALS) && !empty($GLOBALS['TL_JAVASCRIPT_HEAD'])) {
            $externalJsHead = \array_merge($externalJsHead, $GLOBALS['TL_JAVASCRIPT_HEAD']);
        }
        if ($externalJsHead && !empty($externalJsHead)) {
            [$arrFiles, $arrKeys] = $this->getFilesFromArray($externalJsHead);
        }
        return [$arrFiles, $arrKeys];
    }

    protected function getHeadCSS()
    {
        $arrFiles = [];
        $arrKeys = [];
        $externalCssHead = StringUtil::deserialize($this->layout->externalCssHead, true);
        if (\array_key_exists('TL_CSS_HEAD', $GLOBALS) && !empty($GLOBALS['TL_CSS_HEAD'])) {
            $externalCssHead = array_merge($externalCssHead, $GLOBALS['TL_CSS_HEAD']);
        }
        if (\array_key_exists('TL_FRAMEWORK_CSS', $GLOBALS) && !empty($GLOBALS['TL_FRAMEWORK_CSS'])) {
            $externalCssHead = array_merge($externalCssHead, $GLOBALS['TL_FRAMEWORK_CSS']);
        }
        $GLOBALS['TL_FRAMEWORK_CSS'] = null;
        if ($externalCssHead && !empty($externalCssHead)) {
            [$arrFiles, $arrKeys] = $this->getFilesFromArray($externalCssHead);
        }
        return [$arrFiles, $arrKeys];
    }

    protected function getBodyJS()
    {
        $arrFiles = [];
        $arrKeys = [];
        $externalJs = StringUtil::deserialize($this->layout->externalJsBody, true);
        if (\array_key_exists('TL_JAVASCRIPT_BODY', $GLOBALS) && !empty($GLOBALS['TL_JAVASCRIPT_BODY'])) {
            $externalJs = array_merge($externalJs, $GLOBALS['TL_JAVASCRIPT_BODY']);
        }
        if (\array_key_exists('TL_JAVASCRIPT', $GLOBALS) && !empty($GLOBALS['TL_JAVASCRIPT'])) {
            $externalJs = array_merge($externalJs, $GLOBALS['TL_JAVASCRIPT']);
        }
        $GLOBALS['TL_JAVASCRIPT'] = null;
        if ($externalJs && !empty($externalJs)) {
            [$arrFiles, $arrKeys] = $this->getFilesFromArray($externalJs);
        }
        return [$arrFiles, $arrKeys];
    }

    protected function getBodyCSS()
    {
        $arrFiles = [];
        $arrKeys = [];
        $externalCss = StringUtil::deserialize($this->layout->external, true);
        if (\array_key_exists('TL_CSS_BODY', $GLOBALS) && !empty($GLOBALS['TL_CSS_BODY'])) {
            $externalCss = array_merge($externalCss, $GLOBALS['TL_CSS_BODY']);
        }
        if (\array_key_exists('TL_USER_CSS', $GLOBALS) && !empty($GLOBALS['TL_USER_CSS'])) {
            $externalCss = array_merge($externalCss, $GLOBALS['TL_USER_CSS']);
        }
        if (\array_key_exists('TL_CSS', $GLOBALS) && !empty($GLOBALS['TL_CSS'])) {
            $externalCss = array_merge($externalCss, $GLOBALS['TL_CSS']);
        }
        $GLOBALS['TL_CSS'] = null;
        $GLOBALS['TL_USER_CSS'] = null;
        if ($externalCss && !empty($externalCss)) {
            [$arrFiles, $arrKeys] = $this->getFilesFromArray($externalCss);
        }
        return [$arrFiles, $arrKeys];
    }

    protected function generateJsQueue()
    {
        $script = "
            <script>
            var loaded = false;
                function ready(){
                    loaded = true;
                }
            </script>
        ";
        $GLOBALS['TL_HEAD']['js_queue'] = $script;
        if (\array_key_exists('TL_JAVASCRIPT_QUEUE', $GLOBALS) && !empty($GLOBALS['TL_JAVASCRIPT_QUEUE'])) {
            $script = "
                <script>
                    (function(){
                        function onReady(){";
            foreach ($GLOBALS['TL_JAVASCRIPT_QUEUE'] as $js) {
                $script .= $js;
            }
            $script .= "}
                        function waitForScript(){
                            if (loaded) {
                                onReady();
                            } else {
                                setTimeout(waitForScript, 200);
                            }
                        }
                        waitForScript();
                    })();
                </script>
            ";
            $minifier = new Minify\JS($script);
            $script = $minifier->minify();
            $GLOBALS['TL_BODY']['js_queue'] = $script;
        }
    }

    protected function getFilesFromArray(array $files)
    {
        $arrFiles = [];
        $arrKeys = [];
        foreach ($files as $file) {
            if ("" == $file) {
                continue;
            }
            if (Validator::isUuid($file)) {
                $objFile = FilesModel::findByUuid($file);
                $arrFiles[] = $objFile->path;
                $arrKeys[] = hash('md5', file_get_contents($this->rootDir . '/public/' . $objFile->path));
            } else {
                $file = explode('|', $file)[0];
                $arrFiles[] = $file;
                $arrKeys[] = hash('md5', file_get_contents($this->rootDir . '/public/' . $file));
            }
        }
        return [$arrFiles, $arrKeys];
    }

    protected function getMinimizedFile($arrFiles, $arrKeys)
    {
        if (!$arrFiles || empty($arrFiles)) {
            return;
        }
        $strKey = md5(implode(',', $arrKeys));
        if ($this->strMode == 'JS') {
            $strExt = static::JS;
            $strPath = static::JS_PATH;
            $minifier = new Minify\JS();
        } else {
            $strExt = static::CSS;
            $strPath = static::CSS_PATH;
            $minifier = new CSS();
            $minifier->setMaxImportSize(10);
            $combiner = new Combiner();
        }
        if (!file_exists($this->rootDir . '/public/assets/' . $strPath . '/minify_' . $strKey . $strExt)) {
            if ($this->strMode == 'CSS') {
                foreach ($arrFiles as $file) {
                    $combiner->add($file);
                }
                $strFile = $combiner->getCombinedFile();
                $arrFiles = [$strFile];
            }
            foreach ($arrFiles as $file) {
                $minifier->add($this->rootDir . '/public/' . $file);
            }
            $minifier->minify($this->rootDir . '/public/assets/' . $strPath . '/minify_' . $strKey . $strExt);
        }
        return '/assets/' . $strPath . '/minify_' . $strKey . $strExt;
    }
}