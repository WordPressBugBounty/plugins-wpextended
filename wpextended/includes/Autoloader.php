<?php

namespace Wpextended;

class Autoloader
{
    /**
     * Register the autoloader
     */
    public static function register()
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    /**
     * Autoload classes
     *
     * @param string $class The fully-qualified class name
     */
    public static function autoload($class)
    {
        // Only handle our namespace
        if (strpos($class, 'Wpextended\\') !== 0) {
            return;
        }

        // Remove the namespace prefix
        $relative_class = substr($class, strlen('Wpextended\\'));

        // Convert namespace to file path
        $file = self::get_file_path_from_class($relative_class);

        // If the file exists, require it
        if (file_exists($file)) {
            require_once $file;
        }
    }

    /**
     * Convert a class name to a file path
     *
     * @param string $class The class name
     * @return string The file path
     */
    private static function get_file_path_from_class($class)
    {
        // Split the class into parts
        $parts = explode('\\', $class);

        // Convert each part to the appropriate format
        $path_parts = array_map(function ($part) {
            // Convert PascalCase to lowercase-dashed for folders
            return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $part));
        }, $parts);

        // Get the last part (the file name)
        $file_name = array_pop($path_parts);

        // Convert the file name to PascalCase
        $file_name = str_replace('-', '', ucwords($file_name, '-'));

        // Build the path
        $path = WP_EXTENDED_PATH . implode('/', $path_parts);

        // Add the file name with .php extension
        return $path . '/' . $file_name . '.php';
    }
}
