<?php
declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/** CSV (streamed) and PDF (Dompdf) downloads shared by the audit log and reports. */
final class Export
{
    /**
     * Stream rows as CSV without holding them all in memory.
     * @param iterable<array> $rows each row is a list of cell values in header order
     */
    public static function csv(string $filename, array $headers, iterable $rows): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . self::safeName($filename) . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens UTF-8 correctly
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'cell'], $row));
        }
        fclose($out);
        exit;
    }

    /**
     * Branded PDF of one table (audit log exports).
     * @param array<string, string> $meta  label => value lines under the title (filters, period)
     * @param array<int, array>     $rows  each row is a list of cell values in header order
     */
    public static function pdf(string $filename, string $title, array $meta, array $headers, array $rows, string $orientation = 'landscape'): never
    {
        $head = implode('', array_map(static fn ($h) => '<th>' . e($h) . '</th>', $headers));
        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>' . implode('', array_map(static fn ($c) => '<td>' . nl2br(e((string) $c)) . '</td>', $row)) . '</tr>';
        }
        if ($body === '') {
            $body = '<tr><td colspan="' . count($headers) . '">No records match these filters.</td></tr>';
        }
        self::document($filename, $title, $meta, "<table class=\"data\"><thead><tr>{$head}</tr></thead><tbody>{$body}</tbody></table>", $orientation);
    }

    /**
     * Branded PDF around any body HTML (the caller escapes its own content).
     * Header: logo, hospital name, generation time and person. Footer: page numbers.
     */
    public static function document(string $filename, string $title, array $meta, string $bodyHtml, string $orientation = 'portrait'): never
    {
        $html = self::wrap($title, $meta, $bodyHtml);

        $options = new Options();
        $options->set('isRemoteEnabled', false);   // never fetch URLs while rendering
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('chroot', BASE_PATH . '/public/assets');
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', BASE_PATH . '/storage/cache');
        $options->set('fontCache', BASE_PATH . '/storage/cache');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 28, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 7, [0.4, 0.4, 0.4]);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . self::safeName($filename) . '"');
        header('Cache-Control: private, no-store');
        echo $dompdf->output();
        exit;
    }

    /** Full HTML document for Dompdf. Public so the layout can be previewed in a browser. */
    public static function wrap(string $title, array $meta, string $bodyHtml): string
    {
        $safeTitle = e($title);
        $org = e(setting('org.name', 'RUMA Hospital'));
        $logo = BASE_PATH . '/public/assets/img/logo-mark.png';
        $generated = e(date('j F Y, H:i') . ' (' . date_default_timezone_get() . ')');
        $by = e(user() ? user()['full_name'] . ' · ' . user()['email'] : 'System');

        $metaHtml = '';
        foreach ($meta as $label => $value) {
            $metaHtml .= '<tr><th>' . e($label) . '</th><td>' . e($value) . '</td></tr>';
        }
        return <<<HTML
<!doctype html><html><head><meta charset="utf-8"><style>
  @page { margin: 28px 28px 40px 28px; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 8px; color: #2b2b2b; }
  .brand { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  .brand td { vertical-align: middle; }
  .bar { height: 4px; background: #0d8257; margin-bottom: 12px; }
  h1 { font-size: 15px; margin: 0; color: #2b2b2b; }
  .org { font-size: 10px; font-weight: bold; color: #08573a; }
  .sub { color: #545454; font-size: 8px; }
  .meta { border-collapse: collapse; margin: 0 0 12px 0; }
  .meta th { text-align: left; color: #545454; font-weight: normal; padding: 1px 12px 1px 0; }
  .meta td { padding: 1px 0; }
  table.data { width: 100%; border-collapse: collapse; }
  table.data th { background: #e7f4ee; color: #08573a; text-align: left; padding: 4px 5px; font-size: 7.5px; border-bottom: 1px solid #0d8257; }
  table.data td { padding: 3px 5px; border-bottom: 0.5px solid #e2e2e2; vertical-align: top; word-wrap: break-word; }
  table.data tr:nth-child(even) td { background: #f7f7f7; }
  table.data tr { page-break-inside: avoid; }
  h2 { font-size: 11px; margin: 16px 0 6px; color: #08573a; page-break-after: avoid; }
  table.data .num, .num { text-align: right; }
  .foot { margin-top: 10px; color: #666666; font-size: 7px; }
</style></head><body>
<table class="brand"><tr>
  <td style="width:150px"><img src="{$logo}" height="32" style="height:32px;max-width:145px;width:auto;" alt=""></td>
  <td><div class="org" style="font-size:12px;font-weight:bold;color:#08573a">IT Support &amp; Maintenance</div></td>
  <td style="text-align:right" class="sub">Generated {$generated}<br>by {$by}</td>
</tr></table>
<div class="bar"></div>
<h1>{$safeTitle}</h1>
<table class="meta">{$metaHtml}</table>
{$bodyHtml}
<p class="foot">Confidential: internal use only. This document must not contain patient information.</p>
</body></html>
HTML;
    }

    /** Neutralise spreadsheet formula injection (=, +, -, @, tab, CR at cell start). */
    private static function cell(mixed $value): string
    {
        $v = (string) ($value ?? '');
        return $v !== '' && str_contains("=+-@\t\r", $v[0]) ? "'" . $v : $v;
    }

    private static function safeName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? 'export';
    }
}
