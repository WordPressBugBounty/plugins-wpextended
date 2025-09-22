<?php

/**
 * Custom Autoloader for WP Extended Pro
 * Maps PascalCase namespaces to lowercase-with-dashes folder names.
 * PHP files remain in PascalCase.
 */

spl_autoload_register(function ($class) {
    // Only handle Wpextended namespaces
    if (strpos($class, 'Wpextended\\') !== 0) {
        return;
    }

    // Remove root namespace
    $relativeClass = substr($class, strlen('Wpextended\\'));

    // Split by namespace separator
    $parts = explode('\\', $relativeClass);
    if (empty($parts)) {
        return;
    }

    // Map top-level namespace to folder
    $baseDir = '';
    $first = array_shift($parts);
    switch (strtolower($first)) {
        case 'includes':
            $baseDir = __DIR__ . '/includes/';
            break;
        case 'admin':
            $baseDir = __DIR__ . '/admin/';
            break;
        case 'modules':
            $baseDir = __DIR__ . '/modules/';
            break;
        default:
            return;
    }

    // Convert each namespace part to lowercase-with-dashes (except the last part which is the class name)
    $fileParts = array_map(function ($part) {
        // If all lowercase, keep as is (for e.g. 'pro')
        if (strtolower($part) === $part) {
            return $part;
        }
        // Convert PascalCase to lowercase-with-dashes
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $part));
    }, array_slice($parts, 0, -1));

    // The last part is the class name (file name), keep PascalCase
    $classFile = end($parts);
    $path = $baseDir . ($fileParts ? implode('/', $fileParts) . '/' : '') . $classFile . '.php';

    if (file_exists($path)) {
        require_once $path;
    } else {
        error_log("Autoloader: File not found: " . $path);
    }
});
