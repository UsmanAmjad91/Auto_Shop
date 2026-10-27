<?php

namespace App\Support;

use App\Exports\ArrayExport;
use App\Services\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exports any tabular data to CSV, Excel (xlsx), PDF or a printable HTML page.
 */
class Exporter
{
    /**
     * @param  array<string,string>  $columns  key => heading
     * @param  iterable<array>  $rows  associative rows keyed like $columns
     */
    public static function download(string $format, string $title, array $columns, iterable $rows, array $summary = []): Response
    {
        $headings = array_values($columns);
        $data = [];
        foreach ($rows as $row) {
            $line = [];
            foreach (array_keys($columns) as $key) {
                $v = $row[$key] ?? '';
                $line[] = is_bool($v) ? ($v ? 'Yes' : 'No') : (is_scalar($v) || $v === null ? $v : json_encode($v));
            }
            $data[] = $line;
        }
        $file = Str::slug($title).'-'.now()->format('Ymd-His');

        AuditLogger::log('exported', null, "Exported \"{$title}\" as {$format} (".count($data).' rows)');

        return match ($format) {
            'xlsx', 'excel' => Excel::download(new ArrayExport($headings, $data), "$file.xlsx"),
            'pdf' => Pdf::loadView('exports.table', compact('title', 'headings', 'data', 'summary'))
                ->setPaper('a4', count($headings) > 7 ? 'landscape' : 'portrait')->download("$file.pdf"),
            'print' => response()->view('exports.table', compact('title', 'headings', 'data', 'summary') + ['print' => true]),
            default => self::csv("$file.csv", $headings, $data),
        };
    }

    protected static function csv(string $file, array $headings, array $data): Response
    {
        return response()->streamDownload(function () use ($headings, $data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, $headings, ',', '"', '');
            foreach ($data as $row) {
                // Neutralise spreadsheet formula injection.
                $row = array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) && ! is_numeric($v) ? "'".$v : $v, $row);
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
