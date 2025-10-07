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
        if (!$objPage) {
            return $buffer;
        }
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
        $strHeadFileContent = file_get_contents($this->rootDir . '/' . $strHeadFile);
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
                // Add the Body JS file to the body Array
                $GLOBALS['TL_BODY']['js_body'] = '<script defer src="' . $this->assetsUrl . $strBodyFile . '" onload="ready()"></script>';
            }
        } else {
            if ($strHeadFile) {
                // Embed the file in the <head> tag
                $GLOBALS['TL_HEAD']['css_head'] = '<style>@charset "UTF-8";' . str_replace('@charset "UTF-8";', '', $strHeadFileContent) . '</style>';
            }
            if ($strBodyFile) {
                // Preload the Body CSS file
                $GLOBALS['TL_HEAD']['css_body'] = '<link rel="preload" href="' . $this->assetsUrl . $strBodyFile . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
                // Add the Body CSS file to the body Array
                $GLOBALS['TL_BODY']['css_body'] = '<noscript><link rel="stylesheet" href="' . $this->assetsUrl . $strBodyFile . '" media="all"></noscript>';
            }
        }
    }

    protected function getHeadJS()
    {
        global $objPage;
        $arrFiles = [];
        $arrKeys = [];
        $externalJsHead = StringUtil::deserialize($this->layout->externalJsHead, true);
        if ($this->layout->orderExtJsHead) {
            $externalJsHead = StringUtil::deserialize($this->layout->orderExtJsHead, true);
        }
        if ($GLOBALS['TL_JAVASCRIPT_HEAD'] ?? false) {
            $externalJsHead = \array_merge($externalJsHead, $GLOBALS['TL_JAVASCRIPT_HEAD'] ?? []);
            $GLOBALS['TL_JAVASCRIPT_HEAD'] = null;
        }
        if ($objPage->externalJsHead) {
            $externalJsHead = \array_merge($externalJsHead ?? [], $objPage->externalJsHead);
        }
        if ($externalJsHead && !empty($externalJsHead)) {
            [$arrFiles, $arrKeys] = $this->getFilesFromArray($externalJsHead);
            $objPage->externalJsHead = $externalJsHead;
        }
        return [$arrFiles, $arrKeys];
    }

    protected function getHeadCSS()
    {
        global $objPage;
        $arrFiles = [];
        $arrKeys = [];
        $externalCssHead = StringUtil::deserialize($this->layout->externalCssHead, true);
        if ($GLOBALS['TL_CSS_HEAD'] ?? false) {
            $externalCssHead = array_merge($externalCssHead, $GLOBALS['TL_CSS_HEAD'] ?? []);
            $GLOBALS['TL_CSS_HEAD'] = null;
        }
        if ($GLOBALS['TL_FRAMEWORK_CSS'] ?? false) {
            $externalCssHead = array_merge($GLOBALS['TL_FRAMEWORK_CSS'] ?? [], $externalCssHead);
            $GLOBALS['TL_FRAMEWORK_CSS'] = null;
        }
        if ($objPage->externalCssHead) {
            $externalCssHead = array_merge($externalCssHead ?? [], $objPage->externalCssHead);
        }
        if ($externalCssHead && !empty($externalCssHead)) {
            [$arrFiles, $arrKeys] = $this->getFilesFromArray($externalCssHead);
            $objPage->externalCssHead = $externalCssHead;
        }
        return [$arrFiles, $arrKeys];
    }

    protected function getBodyJS()
    {
        global $objPage;
        $arrFiles = [];
        $arrKeys = [];
        $externalJs = StringUtil::deserialize($this->layout->externalJsBody, true);
        if ($GLOBALS['TL_JAVASCRIPT_BODY'] ?? false) {
            $externalJs = array_merge($externalJs, $GLOBALS['TL_JAVASCRIPT_BODY'] ?? []);
            $GLOBALS['TL_JAVASCRIPT_BODY'] = null;
        }
        if ($GLOBALS['TL_JAVASCRIPT'] ?? false) {
            $externalJs = array_merge($externalJs, $GLOBALS['TL_JAVASCRIPT'] ?? []);
            $GLOBALS['TL_JAVASCRIPT'] = null;
        }
        if ($objPage->externalJs) {
            $externalJs = array_merge($objPage->externalJs ?? [], $externalJs);
        }
        if ($externalJs && !empty($externalJs)) {
            [$arrFiles, $arrKeys] = $this->getFilesFromArray($externalJs);
            $objPage->externalJs = $externalJs;
        }
        return [$arrFiles, $arrKeys];
    }

    protected function getBodyCSS()
    {
        global $objPage;
        $arrFiles = [];
        $arrKeys = [];
        $externalCss = StringUtil::deserialize($this->layout->externalCssBody, true);
        if ($GLOBALS['TL_CSS_BODY'] ?? false) {
            $externalCss = array_merge($externalCss, $GLOBALS['TL_CSS_BODY'] ?? []);
            $GLOBALS['TL_CSS_BODY'] = null;
        }
        if ($GLOBALS['TL_USER_CSS'] ?? false) {
            $externalCss = array_merge($GLOBALS['TL_USER_CSS'] ?? [], $externalCss);
            $GLOBALS['TL_USER_CSS'] = null;
        }
        if ($GLOBALS['TL_CSS'] ?? false) {
            $externalCss = array_merge($externalCss, $GLOBALS['TL_CSS'] ?? []);
            $GLOBALS['TL_CSS'] = null;
        }
        if ($objPage->externalCss) {
            $externalCss = array_merge($externalCss ?? [], $objPage->externalCss);
        }
        if ($externalCss && !empty($externalCss)) {
            [$arrFiles, $arrKeys] = $this->getFilesFromArray($externalCss);
            $objPage->externalCss = $externalCss;
        }
        return [$arrFiles, $arrKeys];
    }

    protected function generateJsQueue()
    {
        global $objPage;
        $script = "
            <script>
            var loaded = false;
                function ready(){
                    loaded = true;
                }
            </script>
        ";
        $GLOBALS['TL_HEAD']['js_queue'] = $script;
        if ($objPage->scriptQueue) {
            $GLOBALS['TL_JAVASCRIPT_QUEUE'] = array_merge($GLOBALS['TL_JAVASCRIPT_QUEUE'] ?? [], $objPage->scriptQueue);
        }
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
            $objPage->scriptQueue = $GLOBALS['TL_JAVASCRIPT_QUEUE'];
            $GLOBALS['TL_JAVASCRIPT_QUEUE'] = null;
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
        return 'assets/' . $strPath . '/minify_' . $strKey . $strExt;
    }
}