<?php

namespace Wpextended\Includes\Services\Export\Interfaces;

interface FormatterInterface
{
    /**
     * Format data for export
     *
     * @param array $data Data to format
     * @param array $options Formatting options
     * @return string Formatted data
     */
    public function format($data, array $options = []);

    /**
     * Get content type for the format
     *
     * @return string
     */
    public function getContentType();
}
