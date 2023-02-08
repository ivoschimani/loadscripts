# Contao LoadScripts

## Introduction

The Module allows to specify which CSS / JS should be loaded in the **\<head>**-Tag and which at the end of the **\<body>**-Tag.
The CSS and JS Files will be combined and Minimized. The CSS Files in the **\<head>**-Tag  will be embeded as inline Style. JS Files in the **\<head>**-Tag smaller than 20kb will also be embeded as inline Javascript.

## Usage

The Module adds new Fields to the Layout table where you can select your files which should be loaded in the **\<head>**- and **\<body>**-Tag

You could also use the **$GLOBALS** Array in your Template to load your Files.

Add your Files to the **\$GLOBALS['TL_CSS_HEAD'] / \$GLOBALS['TL_JAVASCRIPT_HEAD']** Arrays to load your files in the \<head>-Tag and the **\$GLOBALS['TL_JAVASCRIPT'] / \$GLOBALS['TL_CSS']** Arrays to load your Files at the end of the \<body>-Tag

If you use jQuery you could add some js code to the **\$GLOBALS['TL_JAVASCRIPT_QUEUE']** Array. The Code will be executed after jQuery is Loaded. 

The Module will work with the most custom extensions if you change the Templates. In many cases you have to add the inline JS Functions without the \<script>-Tag to the **\$GLOBALS['TL_JAVASCRIPT_QUEUE']** Array. 