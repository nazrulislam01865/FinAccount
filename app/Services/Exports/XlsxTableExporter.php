<?php

namespace App\Services\Exports;

use App\Support\PortableZipWriter;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

final class XlsxTableExporter
{
    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $rows
     * @param array<int, int|float> $columnWidths
     */
    public function download(
        string $filename,
        string $sheetName,
        string $title,
        string $companyName,
        array $headers,
        array $rows,
        array $columnWidths = [],
    ): BinaryFileResponse {
        $path = $this->createTemporaryWorkbook(
            $sheetName,
            $title,
            $companyName,
            $headers,
            $rows,
            $columnWidths,
        );

        return response()
            ->download($path, $this->safeFilename($filename), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $rows
     * @param array<int, int|float> $columnWidths
     */
    public function createTemporaryWorkbook(
        string $sheetName,
        string $title,
        string $companyName,
        array $headers,
        array $rows,
        array $columnWidths = [],
    ): string {
        if ($headers === []) {
            throw new RuntimeException('An Excel export requires at least one column.');
        }

        if (count($headers) > 16384) {
            throw new RuntimeException('The Excel export exceeds the maximum column count.');
        }

        if (count($rows) + 5 > 1048576) {
            throw new RuntimeException('The Excel export exceeds the maximum row count.');
        }

        $normalizedRows = $this->normalizeRows($rows, count($headers));
        $widths = $this->resolveColumnWidths($headers, $normalizedRows, $columnWidths);
        $safeSheetName = $this->safeSheetName($sheetName);
        $path = tempnam(sys_get_temp_dir(), 'hisebghor-xlsx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary Excel export file.');
        }

        $xlsxPath = $path.'.xlsx';
        @unlink($path);

        try {
            $zip = new PortableZipWriter($xlsxPath);
            $zip->add('[Content_Types].xml', $this->contentTypesXml());
            $zip->add('_rels/.rels', $this->rootRelationshipsXml());
            $zip->add('docProps/app.xml', $this->appPropertiesXml());
            $zip->add('docProps/core.xml', $this->corePropertiesXml($title));
            $zip->add('xl/workbook.xml', $this->workbookXml($safeSheetName));
            $zip->add('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
            $zip->add('xl/styles.xml', $this->stylesXml());
            $zip->add('xl/worksheets/sheet1.xml', $this->worksheetXml(
                $title,
                $companyName,
                $headers,
                $normalizedRows,
                $widths,
            ));
            $zip->finish();
        } catch (Throwable $exception) {
            @unlink($xlsxPath);
            throw $exception;
        }

        return $xlsxPath;
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function normalizeRows(array $rows, int $columnCount): array
    {
        return array_map(function (array $row) use ($columnCount): array {
            $values = array_values($row);
            $values = array_slice($values, 0, $columnCount);

            while (count($values) < $columnCount) {
                $values[] = '';
            }

            return array_map(fn (mixed $value): string => $this->stringValue($value), $values);
        }, $rows);
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string>> $rows
     * @param array<int, int|float> $requestedWidths
     * @return array<int, float>
     */
    private function resolveColumnWidths(array $headers, array $rows, array $requestedWidths): array
    {
        $widths = [];

        foreach (array_values($headers) as $index => $header) {
            if (isset($requestedWidths[$index])) {
                $widths[$index] = max(8, min(60, (float) $requestedWidths[$index]));
                continue;
            }

            $maxLength = $this->displayLength((string) $header);
            foreach ($rows as $row) {
                foreach (preg_split('/\R/u', $row[$index] ?? '') ?: [''] as $line) {
                    $maxLength = max($maxLength, $this->displayLength($line));
                }
            }

            $widths[$index] = max(10, min(45, $maxLength + 2.5));
        }

        return $widths;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string>> $rows
     * @param array<int, float> $columnWidths
     */
    private function worksheetXml(
        string $title,
        string $companyName,
        array $headers,
        array $rows,
        array $columnWidths,
    ): string {
        $columnCount = count($headers);
        $lastColumn = $this->columnName($columnCount);
        $lastRow = count($rows) + 5;
        $exportedAt = date('Y-m-d H:i:s T');
        $recordText = number_format(count($rows)).' '.Str::plural('record', count($rows));

        $columnsXml = '';
        foreach ($columnWidths as $index => $width) {
            $columnNumber = $index + 1;
            $columnsXml .= '<col min="'.$columnNumber.'" max="'.$columnNumber.'" width="'.number_format($width, 2, '.', '').'" customWidth="1"/>';
        }

        $sheetData = '';
        $sheetData .= $this->rowXml(1, [$title], 1, 28);
        $sheetData .= $this->rowXml(2, ['Company: '.($companyName !== '' ? $companyName : 'Not specified')], 2, 21);
        $sheetData .= $this->rowXml(3, ['Exported: '.$exportedAt.'  |  Total: '.$recordText], 2, 21);
        $sheetData .= '<row r="4" ht="9" customHeight="1"/>';
        $sheetData .= $this->rowXml(5, array_values($headers), 3, 34);

        foreach ($rows as $index => $row) {
            $sheetData .= $this->rowXml($index + 6, $row, $index % 2 === 0 ? 4 : 5, 31);
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<dimension ref="A1:'.$lastColumn.$lastRow.'"/>'
            .'<sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A6" sqref="A6"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/>'
            .'<cols>'.$columnsXml.'</cols>'
            .'<sheetData>'.$sheetData.'</sheetData>'
            .'<autoFilter ref="A5:'.$lastColumn.$lastRow.'"/>'
            .'<mergeCells count="3"><mergeCell ref="A1:'.$lastColumn.'1"/><mergeCell ref="A2:'.$lastColumn.'2"/><mergeCell ref="A3:'.$lastColumn.'3"/></mergeCells>'
            .'<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            .'<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }

    /** @param array<int, string> $values */
    private function rowXml(int $rowNumber, array $values, int $style, int $height): string
    {
        $cells = '';
        foreach ($values as $index => $value) {
            $reference = $this->columnName($index + 1).$rowNumber;
            $cells .= '<c r="'.$reference.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'.$this->xml($value).'</t></is></c>';
        }

        return '<row r="'.$rowNumber.'" ht="'.$height.'" customHeight="1">'.$cells.'</row>';
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="12000"/></bookViews>'
            .'<sheets><sheet name="'.$this->xml($sheetName).'" sheetId="1" r:id="rId1"/></sheets>'
            .'<calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3">'
            .'<font><sz val="11"/><color rgb="FF1F2937"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            .'</fonts>'
            .'<fills count="5">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF205781"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFDCEAF5"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF4F8FB"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFD7E0EA"/></left><right style="thin"><color rgb="FFD7E0EA"/></right><top style="thin"><color rgb="FFD7E0EA"/></top><bottom style="thin"><color rgb="FFD7E0EA"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="6">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top" wrapText="1"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'<dxfs count="0"/>'
            .'<tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
            .'</styleSheet>';
    }

    private function appPropertiesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>HisebGhor</Application><AppVersion>1.0</AppVersion>'
            .'</Properties>';
    }

    private function corePropertiesXml(string $title): string
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.$this->xml($title).'</dc:title><dc:creator>HisebGhor</dc:creator><cp:lastModifiedBy>HisebGhor</cp:lastModifiedBy>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    private function safeSheetName(string $name): string
    {
        $name = preg_replace('/[\\\\\/?*\[\]:]/u', ' ', trim($name)) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name, " '\t\n\r\0\x0B");
        if ($name === '') {
            $name = 'Export';
        }

        return function_exists('mb_substr') ? mb_substr($name, 0, 31) : substr($name, 0, 31);
    }

    private function safeFilename(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = Str::slug($base, '_');
        if ($base === '') {
            $base = 'hisebghor_export';
        }

        return $base.'.xlsx';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function displayLength(string $value): int
    {
        return function_exists('mb_strwidth') ? mb_strwidth($value, 'UTF-8') : strlen($value);
    }

    private function xml(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $clean = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);
        if ($clean === null) {
            $clean = function_exists('iconv') ? (iconv('UTF-8', 'UTF-8//IGNORE', $value) ?: '') : '';
        }

        return htmlspecialchars($clean, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
