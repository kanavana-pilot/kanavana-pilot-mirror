<?php
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors','1');
echo "OK PHP: ".PHP_VERSION."\n";
echo "disable_functions: ".ini_get('disable_functions')."\n";
echo "open_basedir: ".ini_get('open_basedir')."\n";