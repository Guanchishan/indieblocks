<?php

// scoper-autoload.php @generated by PhpScoper

$loader = require_once __DIR__.'/autoload.php';

// Aliases for the whitelisted classes. For more information see:
// https://github.com/humbug/php-scoper/blob/master/README.md#class-whitelisting
if (!class_exists('ComposerAutoloaderInitc21e1e459e9b2b685b6767293adfc2b1', false) && !interface_exists('ComposerAutoloaderInitc21e1e459e9b2b685b6767293adfc2b1', false) && !trait_exists('ComposerAutoloaderInitc21e1e459e9b2b685b6767293adfc2b1', false)) {
    spl_autoload_call('IndieBlocks\ComposerAutoloaderInitc21e1e459e9b2b685b6767293adfc2b1');
}

// Functions whitelisting. For more information see:
// https://github.com/humbug/php-scoper/blob/master/README.md#functions-whitelisting
if (!function_exists('composerRequirec21e1e459e9b2b685b6767293adfc2b1')) {
    function composerRequirec21e1e459e9b2b685b6767293adfc2b1() {
        return \IndieBlocks\composerRequirec21e1e459e9b2b685b6767293adfc2b1(...func_get_args());
    }
}

return $loader;
