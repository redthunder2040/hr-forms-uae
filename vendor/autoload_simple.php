<?php
/**
 * Minimal PSR-4 autoloader for the bundled libraries (Dompdf + its dependencies).
 * The web host does not need Composer - upload the vendor folder exactly as it is.
 * Dompdf keeps a few classes in lib/ (Cpdf) and the rest in src/, so both are mapped.
 */
spl_autoload_register(function (string $class): void {
    $base = __DIR__;
    $map = [
        'Dompdf\\'         => [$base . '/dompdf/src/', $base . '/dompdf/lib/'],
        'FontLib\\'        => [$base . '/php-font-lib/src/'],
        'Svg\\'            => [$base . '/php-svg-lib/src/'],
        'Masterminds\\'    => [$base . '/html5/src/'],
        'Sabberworm\\CSS\\' => [$base . '/php-css-parser/src/'],
    ];
    foreach ($map as $prefix => $dirs) {
        if (strpos($class, $prefix) !== 0) continue;
        $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
        foreach ((array) $dirs as $dir) {
            $file = $dir . $rel . '.php';
            if (is_file($file)) { require_once $file; return; }
        }
    }
});
if (!defined('DOMPDF_VERSION')) { define('DOMPDF_VERSION', '3.1.0'); }
