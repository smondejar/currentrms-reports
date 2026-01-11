<?php
/**
 * Report Exporter - CSV, PDF, Excel exports
 */

class Exporter
{
    /**
     * Export data to CSV
     */
    public static function toCSV(array $data, array $columns, array $columnConfig): string
    {
        $output = fopen('php://temp', 'r+');

        // Write BOM for Excel UTF-8 compatibility
        fwrite($output, "\xEF\xBB\xBF");

        // Write header row
        $headers = [];
        foreach ($columns as $col) {
            $headers[] = $columnConfig[$col]['label'] ?? $col;
        }
        fputcsv($output, $headers);

        // Write data rows
        foreach ($data as $row) {
            $values = [];
            foreach ($columns as $col) {
                $value = $row[$col] ?? '';
                $type = $columnConfig[$col]['type'] ?? 'string';
                $values[] = self::formatValue($value, $type);
            }
            fputcsv($output, $values);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Export to HTML (for print and PDF)
     */
    public static function toHTML(array $data, array $columns, array $columnConfig, string $title = 'Report'): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        .header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }
        .header h1 { margin: 0 0 5px 0; font-size: 18px; }
        .header .meta { color: #666; font-size: 11px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #f5f5f5;
            font-weight: 600;
            white-space: nowrap;
        }
        tr:nth-child(even) { background: #fafafa; }
        tr:hover { background: #f0f0f0; }
        .number, .currency { text-align: right; }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . htmlspecialchars($title) . '</h1>
        <div class="meta">Generated: ' . date('F j, Y \a\t g:i A') . ' | Records: ' . count($data) . '</div>
    </div>
    <table>
        <thead>
            <tr>';

        foreach ($columns as $col) {
            $label = htmlspecialchars($columnConfig[$col]['label'] ?? $col);
            $type = $columnConfig[$col]['type'] ?? 'string';
            $class = in_array($type, ['number', 'currency']) ? ' class="' . $type . '"' : '';
            $html .= "<th{$class}>{$label}</th>";
        }

        $html .= '</tr>
        </thead>
        <tbody>';

        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $value = $row[$col] ?? '';
                $type = $columnConfig[$col]['type'] ?? 'string';
                $formatted = self::formatValueHTML($value, $type);
                $class = in_array($type, ['number', 'currency']) ? ' class="' . $type . '"' : '';
                $html .= "<td{$class}>{$formatted}</td>";
            }
            $html .= '</tr>';
        }

        $html .= '</tbody>
    </table>
    <div class="footer">
        CurrentRMS Report Builder | ' . htmlspecialchars($title) . '
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Generate PDF from HTML (requires external tool or service)
     */
    public static function toPDF(string $html): string
    {
        // Check if wkhtmltopdf is available
        $wkhtmltopdf = '/usr/bin/wkhtmltopdf';
        if (!file_exists($wkhtmltopdf)) {
            $wkhtmltopdf = '/usr/local/bin/wkhtmltopdf';
        }

        if (file_exists($wkhtmltopdf)) {
            // Use wkhtmltopdf
            $tempHtml = tempnam(sys_get_temp_dir(), 'report_') . '.html';
            $tempPdf = tempnam(sys_get_temp_dir(), 'report_') . '.pdf';

            file_put_contents($tempHtml, $html);

            $command = escapeshellcmd($wkhtmltopdf) .
                ' --page-size A4 --orientation Landscape --margin-top 10 --margin-bottom 10 ' .
                escapeshellarg($tempHtml) . ' ' . escapeshellarg($tempPdf);

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($tempPdf)) {
                $pdf = file_get_contents($tempPdf);
                unlink($tempHtml);
                unlink($tempPdf);
                return $pdf;
            }

            unlink($tempHtml);
        }

        // Fallback: Return HTML with PDF headers suggestion
        throw new Exception('PDF generation not available. Please install wkhtmltopdf or use the print function.');
    }

    /**
     * Export to JSON
     */
    public static function toJSON(array $data, array $columns, bool $pretty = true): string
    {
        $filtered = array_map(function ($row) use ($columns) {
            $filtered = [];
            foreach ($columns as $col) {
                $filtered[$col] = $row[$col] ?? null;
            }
            return $filtered;
        }, $data);

        $flags = JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode([
            'generated_at' => date('c'),
            'total_records' => count($data),
            'columns' => $columns,
            'data' => $filtered,
        ], $flags);
    }

    /**
     * Export to Excel XML (compatible with Excel without external libraries)
     */
    public static function toExcelXML(array $data, array $columns, array $columnConfig, string $title = 'Report'): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
    <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
        <Title>' . htmlspecialchars($title) . '</Title>
        <Created>' . date('c') . '</Created>
    </DocumentProperties>
    <Styles>
        <Style ss:ID="Header">
            <Font ss:Bold="1"/>
            <Interior ss:Color="#F0F0F0" ss:Pattern="Solid"/>
        </Style>
        <Style ss:ID="Currency">
            <NumberFormat ss:Format="Currency"/>
        </Style>
        <Style ss:ID="Date">
            <NumberFormat ss:Format="Short Date"/>
        </Style>
    </Styles>
    <Worksheet ss:Name="' . htmlspecialchars(substr($title, 0, 31)) . '">
        <Table>';

        // Header row
        $xml .= '<Row ss:StyleID="Header">';
        foreach ($columns as $col) {
            $label = htmlspecialchars($columnConfig[$col]['label'] ?? $col);
            $xml .= '<Cell><Data ss:Type="String">' . $label . '</Data></Cell>';
        }
        $xml .= '</Row>';

        // Data rows
        foreach ($data as $row) {
            $xml .= '<Row>';
            foreach ($columns as $col) {
                $value = $row[$col] ?? '';
                $type = $columnConfig[$col]['type'] ?? 'string';
                $cellType = 'String';
                $style = '';

                if (in_array($type, ['number', 'currency'])) {
                    $cellType = 'Number';
                    if ($type === 'currency') {
                        $style = ' ss:StyleID="Currency"';
                    }
                    $value = is_numeric($value) ? $value : 0;
                } elseif (in_array($type, ['date', 'datetime'])) {
                    $style = ' ss:StyleID="Date"';
                    if ($value) {
                        $value = date('Y-m-d\TH:i:s', strtotime($value));
                        $cellType = 'DateTime';
                    }
                } else {
                    $value = htmlspecialchars((string) $value);
                }

                $xml .= '<Cell' . $style . '><Data ss:Type="' . $cellType . '">' . $value . '</Data></Cell>';
            }
            $xml .= '</Row>';
        }

        $xml .= '</Table>
    </Worksheet>
</Workbook>';

        return $xml;
    }

    /**
     * Format value for display
     */
    private static function formatValue($value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        switch ($type) {
            case 'currency':
                return number_format((float) $value, 2);
            case 'number':
                return is_numeric($value) ? number_format((float) $value) : $value;
            case 'date':
                return $value ? date('Y-m-d', strtotime($value)) : '';
            case 'datetime':
                return $value ? date('Y-m-d H:i', strtotime($value)) : '';
            case 'boolean':
                return $value ? 'Yes' : 'No';
            default:
                return (string) $value;
        }
    }

    /**
     * Format value for HTML display
     */
    private static function formatValueHTML($value, string $type): string
    {
        $formatted = self::formatValue($value, $type);
        return htmlspecialchars($formatted);
    }

    /**
     * Send file download headers
     */
    public static function downloadHeaders(string $filename, string $contentType, int $size = 0): void
    {
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        if ($size > 0) {
            header('Content-Length: ' . $size);
        }
    }

    /**
     * Get MIME type for format
     */
    public static function getMimeType(string $format): string
    {
        $types = [
            'csv' => 'text/csv; charset=UTF-8',
            'json' => 'application/json',
            'html' => 'text/html; charset=UTF-8',
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
        ];

        return $types[$format] ?? 'application/octet-stream';
    }

    /**
     * Get file extension for format
     */
    public static function getExtension(string $format): string
    {
        $extensions = [
            'csv' => 'csv',
            'json' => 'json',
            'html' => 'html',
            'pdf' => 'pdf',
            'xlsx' => 'xml',
            'xls' => 'xml',
        ];

        return $extensions[$format] ?? 'txt';
    }
}
