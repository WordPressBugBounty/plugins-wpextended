<?php

namespace Wpextended\Includes\Services\Export;

use Wpextended\Includes\Services\Export\Interfaces\FormatterInterface;

class ExportService
{
    /**
     * Valid export types
     */
    const VALID_EXPORT_TYPES = array('csv', 'json', 'txt');

    /**
     * @var array
     */
    private $formatters = array();

    /**
     * @var string|null
     */
    private $moduleName;

    public function __construct($moduleName = null)
    {
        $this->moduleName = $moduleName;
        $this->registerDefaultFormatters();
    }

    /**
     * Register default formatters
     */
    private function registerDefaultFormatters()
    {
        $this->registerFormatter('csv', new Formatters\CSVFormatter());
        $this->registerFormatter('json', new Formatters\JSONFormatter());
        $this->registerFormatter('txt', new Formatters\TXTFormatter());
    }

    /**
     * Register a new formatter
     *
     * @param string $type
     * @param FormatterInterface $formatter
     */
    public function registerFormatter($type, FormatterInterface $formatter)
    {
        $this->formatters[$type] = $formatter;
    }

    /**
     * Apply a filter with both generic and module-specific hooks
     *
     * @param string $hook The filter hook name
     * @param mixed $value The value to filter
     * @param mixed ...$args Additional arguments to pass to the filter
     * @return mixed The filtered value
     */
    protected function applyFilter($hook, $value, ...$args)
    {
        // Apply generic filter first
        $value = apply_filters("wpextended/export/{$hook}", $value, ...$args);

        // Apply module-specific filter if module name is set
        if ($this->moduleName) {
            $value = apply_filters("wpextended/{$this->moduleName}/export/{$hook}", $value, ...$args);
        }

        return $value;
    }

    /**
     * Export data
     *
     * @param array $data Data to export
     * @param string $type Export type (csv, json, txt)
     * @param array $options Export options
     * @throws \InvalidArgumentException
     */
    public function export($data, $type, array $options = array())
    {
        if (!$this->isValidExportType($type)) {
            throw new \InvalidArgumentException("Invalid export type: {$type}");
        }

        // Use the new wrapper function for filters
        $data = $this->applyFilter('data', $data, $type, $options);

        $formatter = $this->formatters[$type];

        // Generate filename if not provided
        if (!isset($options['filename'])) {
            $options['filename'] = $this->generateFilename($type);
        }

        // Use the new wrapper function for filename filter
        $options['filename'] = $this->applyFilter('filename', $options['filename'], $type, $options);

        $this->sendHeaders($formatter->getContentType(), $options['filename']);
        echo $formatter->format($data, $options);
        exit;
    }

    /**
     * Check if export type is valid
     *
     * @param string $type
     * @return bool
     */
    private function isValidExportType($type)
    {
        return in_array($type, self::VALID_EXPORT_TYPES);
    }

    /**
     * Generate default filename
     *
     * @param string $type
     * @return string
     */
    private function generateFilename($type)
    {
        $prefix = $this->moduleName ? "{$this->moduleName}-" : '';
        return sprintf(
            '%s%s-export-%s.%s',
            $prefix,
            sanitize_file_name(get_bloginfo('name')),
            date('Y-m-d'),
            $type
        );
    }

    /**
     * Send appropriate headers for export
     *
     * @param string $contentType
     * @param string $filename
     */
    private function sendHeaders($contentType, $filename)
    {
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . esc_attr($filename) . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}
