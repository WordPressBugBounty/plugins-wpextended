<?php

namespace Wpextended\Includes\Services\Export\Formatters;

use Wpextended\Includes\Services\Export\Interfaces\FormatterInterface;

class JSONFormatter implements FormatterInterface
{
    public function format($data, array $options = [])
    {
        // Convert data to associative array for JSON using field keys
        $json_data = array();

        if (isset($options['keys'])) {
            foreach ($data['body'] as $row) {
                $json_data[] = array_combine($options['keys'], $row);
            }
        } else {
            $json_data = array(
                'headers' => $data['headers'],
                'body' => $data['body']
            );
        }

        return wp_json_encode($json_data, JSON_PRETTY_PRINT);
    }

    public function getContentType()
    {
        return 'application/json';
    }
}
