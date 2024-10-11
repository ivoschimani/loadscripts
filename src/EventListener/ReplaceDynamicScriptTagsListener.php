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
        if (!$objPage) {
            return $buffer;
        }
        if ($objPage->scriptsGenerated === null) {
            $objPage->scriptsGenerated = false;
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
            // $objPage->scriptsGenerated = true;
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
        $arrFiles = [];
        $arrKeys = [];
        $externalJsHead = StringUtil::deserialize($this->layout->externalJsHead, true);
        if ($this->layout->orderExtJsHead) {
            $externalJsHead = StringUtil::deserialize($this->layout->orderExtJsHead, true);
        }
        if ($GLOBALS['TL_JAVASCRIPT_HEAD'] ?? false || $_SESSION['TL_JAVASCRIPT_HEAD'] ?? false) {
            $_SESSION['TL_JAVASCRIPT_HEAD'] = \array_merge($_SESSION['TL_JAVASCRIPT_HEAD'] ?? [], $GLOBALS['TL_JAVASCRIPT_HEAD'] ?? []);
            $GLOBALS['TL_JAVASCRIPT_HEAD'] = null;
            $externalJsHead = \array_merge($externalJsHead, $_SESSION['TL_JAVASCRIPT_HEAD'] ?? []);
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
        if ($GLOBALS['TL_CSS_HEAD'] ?? false || $_SESSION['TL_CSS_HEAD'] ?? false) {
            $_SESSION['TL_CSS_HEAD'] = \array_merge($_SESSION['TL_CSS_HEAD'] ?? [], $GLOBALS['TL_CSS_HEAD'] ?? []);
            $GLOBALS['TL_CSS_HEAD'] = null;
            $externalCssHead = array_merge($externalCssHead, $_SESSION['TL_CSS_HEAD'] ?? []);
        }
        if ($GLOBALS['TL_FRAMEWORK_CSS'] ?? false || $_SESSION['TL_FRAMEWORK_CSS'] ?? false) {
            $_SESSION['TL_FRAMEWORK_CSS'] = \array_merge($_SESSION['TL_FRAMEWORK_CSS'] ?? [], $GLOBALS['TL_FRAMEWORK_CSS'] ?? []);
            $GLOBALS['TL_FRAMEWORK_CSS'] = null;
            $externalCssHead = array_merge($_SESSION['TL_FRAMEWORK_CSS'] ?? false, $externalCssHead);
        }
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
        if ($GLOBALS['TL_JAVASCRIPT_BODY'] ?? false || $_SESSION['TL_JAVASCRIPT_BODY'] ?? false) {
            $_SESSION['TL_JAVASCRIPT_BODY'] = \array_merge($_SESSION['TL_JAVASCRIPT_BODY'] ?? [], $GLOBALS['TL_JAVASCRIPT_BODY'] ?? []);
            $GLOBALS['TL_JAVASCRIPT_BODY'] = null;
            $externalJs = array_merge($externalJs, $_SESSION['TL_JAVASCRIPT_BODY'] ?? []);
        }
        if ($GLOBALS['TL_JAVASCRIPT'] ?? false || $_SESSION['TL_JAVASCRIPT'] ?? false) {
            $_SESSION['TL_JAVASCRIPT'] = \array_merge($_SESSION['TL_JAVASCRIPT'] ?? [], $GLOBALS['TL_JAVASCRIPT'] ?? []);
            $GLOBALS['TL_JAVASCRIPT'] = null;
            $externalJs = array_merge($externalJs, $_SESSION['TL_JAVASCRIPT'] ?? []);
        }
        if ($externalJs && !empty($externalJs)) {
            [$arrFiles, $arrKeys] = $this->getFilesFromArray($externalJs);
        }
        return [$arrFiles, $arrKeys];
    }

    protected function getBodyCSS()
    {
        $arrFiles = [];
        $arrKeys = [];
        $externalCss = StringUtil::deserialize($this->layout->externalCssBody, true);
        if ($GLOBALS['TL_CSS_BODY'] ?? false || $_SESSION['TL_CSS_BODY'] ?? false) {
            $_SESSION['TL_CSS_BODY'] = \array_merge($_SESSION['TL_CSS_BODY'] ?? [], $GLOBALS['TL_CSS_BODY'] ?? []);
            $GLOBALS['TL_CSS_BODY'] = null;
            $externalCss = array_merge($externalCss, $_SESSION['TL_CSS_BODY'] ?? []);
        }
        if ($GLOBALS['TL_USER_CSS'] ?? false || $_SESSION['TL_USER_CSS'] ?? false) {
            $_SESSION['TL_USER_CSS'] = \array_merge($_SESSION['TL_USER_CSS'] ?? [], $GLOBALS['TL_USER_CSS'] ?? []);
            $GLOBALS['TL_USER_CSS'] = null;
            $externalCss = array_merge($_SESSION['TL_USER_CSS'] ?? false, $externalCss);
        }
        if ($GLOBALS['TL_CSS'] ?? false || $_SESSION['TL_CSS'] ?? false) {
            $_SESSION['TL_CSS'] = \array_merge($_SESSION['TL_CSS'] ?? [], $GLOBALS['TL_CSS'] ?? []);
            $GLOBALS['TL_CSS'] = null;
            $externalCss = array_merge($externalCss, $_SESSION['TL_CSS'] ?? []);
        }
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

            $_SESSION['TL_JAVASCRIPT_QUEUE'] = \array_merge($_SESSION['TL_JAVASCRIPT_QUEUE'] ?? [], $GLOBALS['TL_JAVASCRIPT_QUEUE'] ?? []);
            $GLOBALS['TL_JAVASCRIPT_QUEUE'] = null;
            $script = "
                <script>
                    (function(){
                        function onReady(){";
            foreach ($_SESSION['TL_JAVASCRIPT_QUEUE'] as $js) {
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
        return 'assets/' . $strPath . '/minify_' . $strKey . $strExt;
    }
}