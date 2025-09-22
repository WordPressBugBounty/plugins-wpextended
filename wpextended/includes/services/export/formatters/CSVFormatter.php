<?php

namespace Wpextended\Includes\Services\Export\Formatters;

use Wpextended\Includes\Services\Export\Interfaces\FormatterInterface;

class CSVFormatter implements FormatterInterface
{
    public function format($data, array $options = [])
    {
        ob_start();
        $output = fopen('php://output', 'w');

        // Write headers
        fputcsv($output, $data['headers']);

        // Write body
        foreach ($data['body'] as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        return ob_get_clean();
    }

    public function getContentType()
    {
        return 'text/csv';
    }
}
