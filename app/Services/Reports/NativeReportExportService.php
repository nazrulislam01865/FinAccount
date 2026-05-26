<?php

namespace App\Services\Reports;

use Illuminate\Http\Response;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class NativeReportExportService
{
    /**
     * @param array<int, array<int, mixed>> $rows
     * @param array<int, string> $headers
     * @param array<string, mixed> $metadata
     */
    public function download(
        string $reportTitle,
        array $headers,
        array $rows,
        array $metadata = [],
        string $format = 'xlsx',
        ?string $baseFileName = null
    ): Response {
        $format = strtolower($format ?: 'xlsx');
        $baseFileName = $baseFileName ?: Str::slug($reportTitle) . '-' . now()->format('Ymd-His');

        if ($format === 'pdf') {
            $content = $this->buildPdf($reportTitle, $headers, $rows, $metadata);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $baseFileName . '.pdf"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        }

        $content = $this->buildXlsx($reportTitle, $headers, $rows, $metadata);

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $baseFileName . '.xlsx"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     * @param array<int, string> $headers
     * @param array<string, mixed> $metadata
     */
    public function buildXlsx(string $reportTitle, array $headers, array $rows, array $metadata = []): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for XLSX export. Install php-zip on the server.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'hisebghor-xlsx-');
        if ($tmp === false) {
            throw new RuntimeException('Unable to create temporary XLSX file.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new RuntimeException('Unable to open temporary XLSX archive.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($reportTitle, $headers, $rows, $metadata));
        $zip->close();

        $content = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $content;
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     * @param array<int, string> $headers
     * @param array<string, mixed> $metadata
     */
    public function buildPdf(string $reportTitle, array $headers, array $rows, array $metadata = []): string
    {
        $lines = [];
        $lines[] = $reportTitle;
        foreach ($metadata as $label => $value) {
            $lines[] = $label . ': ' . $this->stringValue($value);
        }
        $lines[] = 'Prepared At: ' . now()->format('Y-m-d H:i:s');
        $lines[] = '';
        $lines[] = implode(' | ', $headers);
        $lines[] = str_repeat('-', min(140, max(20, strlen(end($lines)))));

        foreach ($rows as $row) {
            $line = implode(' | ', array_map(fn ($value) => $this->stringValue($value), $row));
            foreach ($this->wrapPdfLine($line, 115) as $wrapped) {
                $lines[] = $wrapped;
            }
        }

        if (empty($rows)) {
            $lines[] = 'No data found.';
        }

        $objects = [];
        $pages = [];
        $lineHeight = 14;
        $linesPerPage = 52;
        $chunks = array_chunk($lines, $linesPerPage);
        $fontObjectId = 3;

        foreach ($chunks as $pageIndex => $chunk) {
            $pageObjectId = 4 + ($pageIndex * 2);
            $contentObjectId = $pageObjectId + 1;
            $pages[] = $pageObjectId;

            $y = 790;
            $stream = "BT\n/F1 9 Tf\n";
            foreach ($chunk as $line) {
                $stream .= '1 0 0 1 36 ' . $y . ' Tm (' . $this->pdfEscape($line) . ") Tj\n";
                $y -= $lineHeight;
            }
            $stream .= "ET\n";

            $objects[$contentObjectId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . $fontObjectId . ' 0 R >> >> /Contents ' . $contentObjectId . ' 0 R >>';
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(fn ($id) => $id . ' 0 R', $pages)) . '] /Count ' . count($pages) . ' >>';
        $objects[$fontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObjectId = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxObjectId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObjectId; $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i] ?? 0) . "\n";
        }
        $pdf .= "trailer\n<< /Size " . ($maxObjectId + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     * @param array<int, string> $headers
     * @param array<string, mixed> $metadata
     */
    private function worksheetXml(string $reportTitle, array $headers, array $rows, array $metadata): string
    {
        $sheetRows = [];
        $rowNumber = 1;

        $sheetRows[] = $this->sheetRow($rowNumber++, [$reportTitle], 1);
        foreach ($metadata as $label => $value) {
            $sheetRows[] = $this->sheetRow($rowNumber++, [$label, $this->stringValue($value)], 0);
        }
        $sheetRows[] = $this->sheetRow($rowNumber++, ['Prepared At', now()->format('Y-m-d H:i:s')], 0);
        $sheetRows[] = $this->sheetRow($rowNumber++, [], 0);
        $sheetRows[] = $this->sheetRow($rowNumber++, $headers, 1);

        foreach ($rows as $row) {
            $sheetRows[] = $this->sheetRow($rowNumber++, $row, 0);
        }

        $columnCount = max(1, count($headers));
        $cols = '';
        for ($i = 1; $i <= $columnCount; $i++) {
            $cols .= '<col min="' . $i . '" max="' . $i . '" width="22" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<cols>' . $cols . '</cols>'
            . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
            . '</worksheet>';
    }

    /**
     * @param array<int, mixed> $values
     */
    private function sheetRow(int $rowNumber, array $values, int $style): string
    {
        if ($values === []) {
            return '<row r="' . $rowNumber . '"></row>';
        }

        $cells = '';
        foreach (array_values($values) as $index => $value) {
            $cell = $this->columnName($index + 1) . $rowNumber;
            if (is_numeric($value) && $value !== '' && ! preg_match('/^0\d+/', (string) $value)) {
                $cells .= '<c r="' . $cell . '" s="' . $style . '"><v>' . $this->xml((string) $value) . '</v></c>';
            } else {
                $cells .= '<c r="' . $cell . '" t="inlineStr" s="' . $style . '"><is><t>' . $this->xml($this->stringValue($value)) . '</t></is></c>';
            }
        }

        return '<row r="' . $rowNumber . '">' . $cells . '</row>';
    }

    private function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            . '</styleSheet>';
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function pdfEscape(string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    /**
     * @return array<int, string>
     */
    private function wrapPdfLine(string $line, int $length): array
    {
        $line = trim($line);
        if ($line === '') {
            return [''];
        }

        return explode("\n", wordwrap($line, $length, "\n", true));
    }

    private function stringValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
