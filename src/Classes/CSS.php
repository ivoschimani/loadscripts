<?php

namespace Ivo\LoadScripts\Classes;

class CSS extends \MatthiasMullie\Minify\CSS
{

    protected function importFiles($source, $content)
    {
        $extensions = array_keys($this->importExtensions);
        $regex      = '/url\((["\']?)((?!["\']?data:).*?\.(' . implode('|', $extensions) . '))\\1\)/i';
        if ($extensions && preg_match_all($regex, $content, $matches, PREG_SET_ORDER)) {
            $search  = array();
            $replace = array();
            // loop the matches
            foreach ($matches as $match) {
                // get the path for the file that will be imported
                $path      = $match[2];
                $path      = dirname($source) . '/' . $path;
                $extension = $match[3];

                // only replace the import with the content if we're able to get
                // the content of the file, and it's relatively small
                $path = str_replace(TL_ASSETS_URL, "../../", $path);
                if ($this->canImportFile($path) && $this->canImportBySize($path)) {
                    // grab content && base64-ize
                    $importContent = $this->load($path);
                    $importContent = base64_encode($importContent);

                    // build replacement
                    $search[]  = $match[0];
                    $replace[] = 'url(' . $this->importExtensions[$extension] . ';base64,' . $importContent . ')';
                }
            }

            // replace the import statements
            $content = str_replace($search, $replace, $content);
        }

        return $content;
    }
}