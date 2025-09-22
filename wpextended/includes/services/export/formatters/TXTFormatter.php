<?php

namespace Wpextended\Includes\Services\Export\Formatters;

use Wpextended\Includes\Services\Export\Interfaces\FormatterInterface;

class TXTFormatter implements FormatterInterface
{
    public function format($data, array $options = [])
    {
        if (!is_array($data) || empty($data['body'])) {
            return '';
        }

        $output = '';
        $headers = $data['headers'];

        foreach ($data['body'] as $row) {
            foreach ($headers as $index => $label) {
                $value = isset($row[$index]) ? esc_html($row[$index]) : '';
                $output .= sprintf("%s: %s\n", esc_html($label), $value);
            }
            $output .= "\n";
        }

        return $output;
    }

    public function getContentType()
    {
        return 'text/plain';
    }
}
