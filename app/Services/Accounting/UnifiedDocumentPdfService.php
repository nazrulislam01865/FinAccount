<?php

namespace App\Services\Accounting;

use App\Support\PrintedDocumentBrand;

class UnifiedDocumentPdfService
{
    private const PAGE_WIDTH = 595.28;
    private const PAGE_HEIGHT = 841.89;

    /** @var array<int, string> */
    private array $objects = [];

    /**
     * @param array{
     *   title:string,
     *   company:array<string,mixed>,
     *   meta:array<int,array{label:string,value:mixed}>,
     *   party_title:string,
     *   party:array<string,mixed>,
     *   purpose:string,
     *   lines:array<int,array{description:string,remarks:string,amount:float|int|string}>,
     *   currency_label:string,
     *   summary:array<int,array{label:string,amount?:float|int|string,display?:string,total?:bool}>,
     *   amount_words:string,
     *   notes:string,
     *   prepared_name:string,
     *   prepared_position?:string,
     *   prepared_date:string,
     *   footer?:string
     * } $document
     */
    public function render(array $document): string
    {
        $company = PrintedDocumentBrand::company((array) ($document['company'] ?? []));
        $logo = $this->logoData($company['logo_path'] ?? null);

        $this->objects = [];
        $fontRegular = $this->addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        $fontBold = $this->addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');
        $fontItalic = $this->addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique >>');
        $logoObject = $logo ? $this->addStreamObject($logo['stream'], sprintf(
            '<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode',
            $logo['width'],
            $logo['height']
        )) : null;

        $lines = array_values((array) ($document['lines'] ?? []));
        if ($lines === []) {
            $lines = [['description' => '-', 'remarks' => '-', 'amount' => 0]];
        }

        $tableWidth = 491.0;
        $columnWidths = [205.0, 190.0, $tableWidth - 395.0];
        $preparedRows = 2 + (trim((string) ($document['prepared_position'] ?? '')) !== '' ? 1 : 0);
        $summary = array_values((array) ($document['summary'] ?? []));
        $summaryRowHeight = count($summary) > 5 ? 15.0 : 18.0;
        $summaryHeight = 0.0;
        foreach ($summary as $row) {
            $summaryHeight += !empty($row['total']) ? 22.0 : $summaryRowHeight;
        }

        $measuredLines = array_map(function (array $line) use ($columnWidths): array {
            $fontSize = 8;
            $leading = 10;
            $descriptionLines = $this->wrapTextByWidth(
                (string) ($line['description'] ?? '-'),
                $columnWidths[0] - 20,
                $fontSize,
                0.5,
                50
            );
            $remarkLines = [];
            foreach ($this->normaliseTextLines($line['remarks_lines'] ?? ($line['remarks'] ?? '-')) as $logicalLine) {
                foreach ($this->wrapTextByWidth($logicalLine, $columnWidths[1] - 20, $fontSize, 0.5, 50) as $wrappedLine) {
                    $remarkLines[] = $wrappedLine;
                }
            }
            $remarkLines = $remarkLines ?: ['-'];
            $textLineCount = max(count($descriptionLines), count($remarkLines), 1);

            return [
                'description_lines' => $descriptionLines,
                'remarks_lines' => $remarkLines,
                'amount' => (float) ($line['amount'] ?? 0),
                'height' => max(28.0, 10.0 + ($textLineCount * $leading)),
                'font_size' => $fontSize,
                'leading' => $leading,
            ];
        }, $lines);

        // Reserve a stable lower block on the final page. Earlier pages may use
        // the full table area. This prevents totals and PREPARED BY from ever
        // sharing the same coordinates in downloaded PDFs.
        $minimumLowerTop = $preparedRows > 2 ? 190.0 : 178.0;
        $lastPageMinimumTableBottom = $minimumLowerTop + 14.0 + 16.0 + $summaryHeight;
        $firstTableTop = 505.0;
        $continuedTableTop = 720.0;
        $tableHeaderHeight = 24.0;
        $nonFinalBottom = 82.0;
        $firstFinalBudget = max(40.0, $firstTableTop - $tableHeaderHeight - $lastPageMinimumTableBottom);
        $continuedFinalBudget = max(40.0, $continuedTableTop - $tableHeaderHeight - $lastPageMinimumTableBottom);
        $firstNonFinalBudget = $firstTableTop - $tableHeaderHeight - $nonFinalBottom;
        $continuedNonFinalBudget = $continuedTableTop - $tableHeaderHeight - $nonFinalBottom;

        $pages = [];
        $remaining = $measuredLines;
        if ($this->rowsHeight($remaining) <= $firstFinalBudget) {
            $pages[] = ['rows' => $remaining, 'first' => true, 'final' => true];
            $remaining = [];
        } else {
            $take = $this->rowsThatFit($remaining, $firstNonFinalBudget, true);
            $pages[] = ['rows' => array_splice($remaining, 0, $take), 'first' => true, 'final' => false];

            while ($remaining !== []) {
                if ($this->rowsHeight($remaining) <= $continuedFinalBudget) {
                    $pages[] = ['rows' => $remaining, 'first' => false, 'final' => true];
                    $remaining = [];
                    break;
                }

                $take = $this->rowsThatFit($remaining, $continuedNonFinalBudget, true);
                $pages[] = ['rows' => array_splice($remaining, 0, $take), 'first' => false, 'final' => false];
            }
        }

        $pageContents = [];
        $pageCount = count($pages);
        foreach ($pages as $index => $page) {
            $pageContents[] = $this->renderPage(
                $document,
                $company,
                $logo,
                $logoObject,
                $page['rows'],
                (bool) $page['first'],
                (bool) $page['final'],
                $index + 1,
                $pageCount,
                $summary,
                $summaryRowHeight
            );
        }

        return $this->buildPdf($pageContents, $fontRegular, $fontBold, $fontItalic, $logoObject);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function rowsHeight(array $rows): float
    {
        return array_sum(array_map(static fn (array $row): float => (float) ($row['height'] ?? 28.0), $rows));
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function rowsThatFit(array $rows, float $budget, bool $leaveAtLeastOne): int
    {
        $height = 0.0;
        $count = 0;
        $maximum = $leaveAtLeastOne && count($rows) > 1 ? count($rows) - 1 : count($rows);
        foreach ($rows as $row) {
            $rowHeight = (float) ($row['height'] ?? 28.0);
            if ($count > 0 && $height + $rowHeight > $budget) {
                break;
            }
            if ($count >= $maximum && $maximum > 0) {
                break;
            }
            $height += $rowHeight;
            $count++;
        }

        return max(1, $count);
    }

    /**
     * @param array<string,mixed> $document
     * @param array<string,mixed> $company
     * @param array{stream:string,width:int,height:int}|null $logo
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,array<string,mixed>> $summary
     */
    private function renderPage(
        array $document,
        array $company,
        ?array $logo,
        ?int $logoObject,
        array $rows,
        bool $firstPage,
        bool $finalPage,
        int $pageNumber,
        int $pageCount,
        array $summary,
        float $summaryRowHeight
    ): string {
        $content = [];
        $content[] = '1 1 1 rg 0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.' re f';

        $pageX = 28.0;
        $pageY = 28.0;
        $pageW = self::PAGE_WIDTH - 56.0;
        $pageH = self::PAGE_HEIGHT - 56.0;
        $left = 52.0;
        $right = 543.0;
        $content[] = $this->rect($pageX, $pageY, $pageW, $pageH, '1 1 1', '0.07 0.07 0.07', false);

        if ($firstPage) {
            $logoBoxX = 52.0;
            $logoBoxY = 692.0;
            $logoBoxW = 70.0;
            $logoBoxH = 76.0;
            if ($logoObject && $logo) {
                [$imageW, $imageH, $imageX, $imageY] = $this->fitImage(
                    (float) $logo['width'],
                    (float) $logo['height'],
                    $logoBoxW,
                    $logoBoxH,
                    $logoBoxX,
                    $logoBoxY
                );
                $content[] = 'q '.$this->num($imageW).' 0 0 '.$this->num($imageH).' '.$this->num($imageX).' '.$this->num($imageY).' cm /Logo Do Q';
            } else {
                $content[] = $this->rect($logoBoxX + 6, $logoBoxY + 8, 58, 58, '1 1 1', '0.07 0.07 0.07', false);
                $content[] = $this->text($logoBoxX + 20, $logoBoxY + 31, $this->initials((string) ($company['short_name'] ?? $company['name'] ?? 'BA')), 19, 'F2');
            }

            $content[] = '0.50 0.53 0.58 RG 137 686 m 137 776 l S';
            $companyX = 154.0;
            $companyRight = 317.0;
            $companyName = strtoupper(trim((string) ($company['name'] ?? 'Bashir Agro')) ?: 'BASHIR AGRO');
            $companyNameSize = $this->fitTextSize($companyName, $companyRight - $companyX, 18, 11, 0.54);
            $content[] = $this->text($companyX, 758, $this->truncateByWidth($companyName, $companyRight - $companyX, $companyNameSize, 0.54), $companyNameSize, 'F2');

            $companyLines = array_values(array_filter([
                trim((string) ($company['address'] ?? '')),
                trim((string) ($company['phone'] ?? '')) !== '' ? 'Phone: '.trim((string) $company['phone']) : '',
                trim((string) ($company['website'] ?? '')),
            ], static fn (string $value): bool => $value !== ''));
            $companyY = 738.0;
            foreach (array_slice($companyLines, 0, 4) as $line) {
                $content[] = $this->text($companyX, $companyY, $this->truncateByWidth($line, $companyRight - $companyX, 8, 0.5), 8, 'F1');
                $companyY -= 13.0;
            }

            $metaX = 332.0;
            $title = strtoupper(trim((string) ($document['title'] ?? 'INVOICE')));
            $titleRight = 535.0;
            $titleSize = $this->fitTextSize($title, $titleRight - $metaX, 25, 14, 0.58);
            $content[] = $this->textRight($titleRight, 758, $title, $titleSize, 'F2', '0 0 0', 0.58);

            $metaY = 720.0;
            foreach (array_slice((array) ($document['meta'] ?? []), 0, 4) as $row) {
                $label = (string) ($row['label'] ?? '');
                $value = (string) ($row['value'] ?? '-');
                $content[] = $this->text($metaX, $metaY, $this->truncate($label, 22), 9, 'F2');
                $content[] = $this->text($metaX + 89, $metaY, ':', 9, 'F2');
                $content[] = $this->text($metaX + 104, $metaY, $this->truncateByWidth($value, $right - ($metaX + 104), 9, 0.5), 9, 'F1');
                $content[] = '0.62 0.65 0.70 RG '.($metaX + 104).' '.($metaY - 4).' m '.$right.' '.($metaY - 4).' l S';
                $metaY -= 20.0;
            }

            $headerRuleY = 642.0;
            $content[] = '0.62 0.65 0.70 RG '.$left.' '.$headerRuleY.' m '.$right.' '.$headerRuleY.' l S';

            $party = (array) ($document['party'] ?? []);
            $content[] = $this->personIcon(60, 617);
            $content[] = $this->text(82, 613, strtoupper((string) ($document['party_title'] ?? 'BILL TO')), 12, 'F2');
            $phoneEmail = trim((string) ($party['phone_email'] ?? ''));
            if ($phoneEmail === '') {
                $phone = trim((string) ($party['phone'] ?? ''));
                $email = trim((string) ($party['email'] ?? ''));
                $phoneEmail = trim($phone.($phone !== '' && $email !== '' ? ' / ' : '').$email);
            }
            $partyRows = [
                'Name' => trim((string) ($party['name'] ?? '-')) ?: '-',
                'Address' => trim((string) ($party['address'] ?? '-')) ?: '-',
                'Phone / Email' => $phoneEmail !== '' ? $phoneEmail : '-',
                'Purpose / Against' => trim((string) ($document['purpose'] ?? '-')) ?: '-',
            ];
            $partyY = 587.0;
            foreach ($partyRows as $label => $value) {
                $content[] = $this->text(82, $partyY, $label, 9, 'F2');
                $content[] = $this->text(174, $partyY, ':', 9, 'F2');
                $content[] = $this->text(190, $partyY, $this->truncateByWidth($value, $right - 190, 9, 0.5), 9, 'F1');
                $partyY -= 18.0;
            }
            $tableTop = 505.0;
        } else {
            $title = strtoupper(trim((string) ($document['title'] ?? 'INVOICE')));
            $companyName = strtoupper(trim((string) ($company['name'] ?? 'Bashir Agro')) ?: 'BASHIR AGRO');
            $content[] = $this->text($left, 774, $this->truncateByWidth($companyName, 220, 13, 0.54), 13, 'F2');
            $content[] = $this->textRight($right, 774, $title.' - CONTINUED', 13, 'F2', '0 0 0', 0.54);
            $content[] = $this->text($left, 754, 'Page '.$pageNumber.' of '.$pageCount, 8, 'F1');
            $content[] = '0.62 0.65 0.70 RG '.$left.' 742 m '.$right.' 742 l S';
            $tableTop = 720.0;
        }

        $tableHeaderHeight = 24.0;
        $tableX = $left;
        $tableW = $right - $left;
        $col1 = 205.0;
        $col2 = 190.0;
        $currency = strtoupper((string) ($document['currency_label'] ?? 'TK'));
        $content[] = $this->rect($tableX, $tableTop - $tableHeaderHeight, $tableW, $tableHeaderHeight, '0.95 0.95 0.95', '0.62 0.65 0.70', true);
        $content[] = $this->text($tableX + 70, $tableTop - 16, 'DESCRIPTION', 9, 'F2');
        $content[] = $this->text($tableX + $col1 + 72, $tableTop - 16, 'REMARKS', 9, 'F2');
        $content[] = $this->text($tableX + $col1 + $col2 + 14, $tableTop - 16, 'AMOUNT ('.$currency.')', 9, 'F2');

        $rowTop = $tableTop - $tableHeaderHeight;
        foreach ($rows as $row) {
            $rowHeight = (float) ($row['height'] ?? 28.0);
            $rowBottom = $rowTop - $rowHeight;
            $content[] = '0.62 0.65 0.70 RG '.$tableX.' '.$rowBottom.' m '.$right.' '.$rowBottom.' l S';
            foreach ([$tableX, $tableX + $col1, $tableX + $col1 + $col2, $right] as $x) {
                $content[] = '0.62 0.65 0.70 RG '.$x.' '.$rowBottom.' m '.$x.' '.$rowTop.' l S';
            }

            $fontSize = (int) ($row['font_size'] ?? 8);
            $leading = (int) ($row['leading'] ?? 10);
            $baseline = $rowTop - $leading - 3;
            foreach ((array) ($row['description_lines'] ?? ['-']) as $lineIndex => $textLine) {
                $content[] = $this->text($tableX + 10, $baseline - ($lineIndex * $leading), (string) $textLine, $fontSize, 'F1');
            }
            foreach ((array) ($row['remarks_lines'] ?? ['-']) as $lineIndex => $textLine) {
                $content[] = $this->text($tableX + $col1 + 10, $baseline - ($lineIndex * $leading), (string) $textLine, $fontSize, 'F1');
            }
            $content[] = $this->textRight($right - 10, $baseline, number_format((float) ($row['amount'] ?? 0), 2, '.', ','), $fontSize, 'F1');
            $rowTop = $rowBottom;
        }
        $tableBottom = $rowTop;
        $content[] = '0.62 0.65 0.70 RG '.$tableX.' '.$tableTop.' m '.$right.' '.$tableTop.' l S';
        $content[] = '0.62 0.65 0.70 RG '.$tableX.' '.($tableTop - $tableHeaderHeight).' m '.$right.' '.($tableTop - $tableHeaderHeight).' l S';

        if ($finalPage) {
            $summaryX = 345.0;
            $summaryY = $tableBottom - 14.0;
            foreach ($summary as $row) {
                $isTotal = !empty($row['total']);
                $height = $isTotal ? 22.0 : $summaryRowHeight;
                if ($isTotal) {
                    $content[] = $this->rect($summaryX, $summaryY - $height + 3, $right - $summaryX, $height, '0.94 0.94 0.94', '0.62 0.65 0.70', true);
                }
                $font = $isTotal ? 'F2' : 'F1';
                $size = $isTotal ? 9 : 8;
                $label = (string) ($row['label'] ?? '');
                $display = array_key_exists('display', $row)
                    ? (string) $row['display']
                    : number_format((float) ($row['amount'] ?? 0), 2, '.', ',');
                $baseline = $summaryY - $height + 10;
                $content[] = $this->text($summaryX + 7, $baseline, $this->truncateByWidth($label, 112, $size, 0.52), $size, $font);
                $content[] = $this->textRight($right - 7, $baseline, $display, $size, $font);
                if (! $isTotal) {
                    $content[] = '0.62 0.65 0.70 RG '.$summaryX.' '.($summaryY - $height + 2).' m '.$right.' '.($summaryY - $height + 2).' l S';
                }
                $summaryY -= $height;
            }

            $lowerTop = $summaryY - 14.0;
            $content[] = $this->text($left, $lowerTop, 'AMOUNT IN WORDS', 10, 'F2');
            $wordsTop = $lowerTop - 12.0;
            $wordsHeight = 36.0;
            $content[] = $this->rect($left, $wordsTop - $wordsHeight, 260, $wordsHeight, '1 1 1', '0.72 0.74 0.78', false);
            $wordLines = $this->wrapTextByWidth((string) ($document['amount_words'] ?? '-'), 240, 8, 0.5, 2);
            $wordY = $wordsTop - 14.0;
            foreach ($wordLines as $wordLine) {
                $content[] = $this->text($left + 10, $wordY, $wordLine, 8, 'F1');
                $wordY -= 11.0;
            }
            $content[] = $this->text($left, $lowerTop - 68, 'NOTES', 10, 'F2');
            $notesTop = $lowerTop - 79;
            $noteLines = $this->wrapTextByWidth(
                (string) ($document['notes'] ?? 'Thank you for your business.'),
                240,
                8,
                0.5,
                4
            );
            $noteLines = $noteLines ?: ['-'];
            // Keep the notes box content-sized. Previously it stretched down to
            // the footer using all remaining page height, which produced a huge
            // empty box in downloaded PDFs.
            $notesHeight = min(58.0, max(30.0, 14.0 + (count($noteLines) * 11.0)));
            $content[] = $this->rect($left, $notesTop - $notesHeight, 260, $notesHeight, '1 1 1', '0.72 0.74 0.78', false);
            $noteY = $notesTop - 16;
            foreach ($noteLines as $noteLine) {
                $content[] = $this->text($left + 10, $noteY, $noteLine, 8, 'F1');
                $noteY -= 11;
            }

            $dividerX = 330.0;
            $content[] = '[2 2] 0 d 0.72 0.74 0.78 RG '.$dividerX.' '.($lowerTop + 4).' m '.$dividerX.' 78 l S [] 0 d';
            $preparedX = 350.0;
            $preparedName = trim((string) ($document['prepared_name'] ?? 'System User')) ?: 'System User';
            $preparedPosition = trim((string) ($document['prepared_position'] ?? ''));
            $content[] = $this->text($preparedX, $lowerTop, 'PREPARED BY', 10, 'F2');
            $preparedY = $lowerTop - 25.0;
            $preparedRows = ['Name' => $preparedName];
            if ($preparedPosition !== '') {
                $preparedRows['Position'] = $preparedPosition;
            }
            $preparedRows['Date'] = (string) ($document['prepared_date'] ?? '-');
            foreach ($preparedRows as $label => $value) {
                $content[] = $this->text($preparedX, $preparedY, $label, 9, 'F1');
                $content[] = $this->text($preparedX + 55, $preparedY, ':', 9, 'F1');
                $content[] = $this->text($preparedX + 69, $preparedY, $this->truncateByWidth($value, $right - ($preparedX + 69), 9, 0.5), 9, 'F1');
                $preparedY -= 20.0;
            }

            $signatureLineY = 80.0;
            $signatureCenterX = $preparedX + (($right - $preparedX) / 2);
            $content[] = $this->textCenter($signatureCenterX, $signatureLineY + 10, $this->truncateByWidth($preparedName, $right - $preparedX - 16, 8, 0.5), 8, 'F3');
            $content[] = '0.07 0.07 0.07 RG '.$preparedX.' '.$signatureLineY.' m '.$right.' '.$signatureLineY.' l S';
            $content[] = $this->textCenter($signatureCenterX, $signatureLineY - 14, 'DIGITAL SIGNATURE', 9, 'F2');
        }

        $footerRuleY = 58.0;
        $content[] = '0.07 0.07 0.07 RG '.$left.' '.$footerRuleY.' m '.$right.' '.$footerRuleY.' l S';
        $content[] = $this->checkMark(111, 39);
        $footer = $finalPage
            ? (string) ($document['footer'] ?? 'This document is electronically generated and may not require a physical signature.')
            : 'Continued on the next page.';
        $content[] = $this->text(137, 39, $this->truncateByWidth($footer, 395, 8, 0.48), 8, 'F3');

        return implode("\n", $content);
    }


    /** @return array{stream:string,width:int,height:int}|null */
    private function logoData(mixed $path): ?array
    {
        $fullPath = PrintedDocumentBrand::logoFilePath($path);
        if (! $fullPath) {
            return null;
        }

        $info = @getimagesize($fullPath);
        if (! is_array($info)) {
            return null;
        }

        $mime = (string) ($info['mime'] ?? '');
        if (in_array($mime, ['image/jpeg', 'image/jpg'], true)) {
            return ['stream' => (string) file_get_contents($fullPath), 'width' => (int) $info[0], 'height' => (int) $info[1]];
        }

        if ($mime === 'image/png' && extension_loaded('gd')) {
            $source = @imagecreatefrompng($fullPath);
            if (! $source) {
                return null;
            }
            $width = imagesx($source);
            $height = imagesy($source);
            $canvas = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
            imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
            ob_start();
            imagejpeg($canvas, null, 90);
            $stream = (string) ob_get_clean();
            imagedestroy($source);
            imagedestroy($canvas);

            return ['stream' => $stream, 'width' => $width, 'height' => $height];
        }

        return null;
    }

    /** @return array{0:float,1:float,2:float,3:float} */
    private function fitImage(float $sourceW, float $sourceH, float $boxW, float $boxH, float $boxX, float $boxY): array
    {
        if ($sourceW <= 0 || $sourceH <= 0) {
            return [$boxW, $boxH, $boxX, $boxY];
        }
        $scale = min($boxW / $sourceW, $boxH / $sourceH);
        $width = $sourceW * $scale;
        $height = $sourceH * $scale;

        return [$width, $height, $boxX + (($boxW - $width) / 2), $boxY + (($boxH - $height) / 2)];
    }

    /** @param array<int,string> $pageContents */
    private function buildPdf(array $pageContents, int $fontRegular, int $fontBold, int $fontItalic, ?int $logoObject): string
    {
        $xObject = $logoObject ? ' /XObject << /Logo '.$logoObject.' 0 R >>' : '';
        $pageObjects = [];
        foreach ($pageContents as $pageContent) {
            $contentObject = $this->addStreamObject($pageContent, '<<');
            $pageObjects[] = $this->addObject('<< /Type /Page /Parent __PAGES__ 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Resources << /Font << /F1 '.$fontRegular.' 0 R /F2 '.$fontBold.' 0 R /F3 '.$fontItalic.' 0 R >>'.$xObject.' >> /Contents '.$contentObject.' 0 R >>');
        }
        $kids = implode(' ', array_map(static fn (int $id): string => $id.' 0 R', $pageObjects));
        $pagesObject = $this->addObject('<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pageObjects).' >>');
        foreach ($pageObjects as $pageObject) {
            $this->objects[$pageObject] = str_replace('__PAGES__', (string) $pagesObject, $this->objects[$pageObject]);
        }
        $catalogObject = $this->addObject('<< /Type /Catalog /Pages '.$pagesObject.' 0 R >>');

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($this->objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($this->objects) + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($this->objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size ".(count($this->objects) + 1).' /Root '.$catalogObject." 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function addObject(string $content): int
    {
        $id = count($this->objects) + 1;
        $this->objects[$id] = $content;

        return $id;
    }

    private function addStreamObject(string $stream, string $prefix): int
    {
        return $this->addObject($prefix.' /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream");
    }

    private function rect(float $x, float $y, float $w, float $h, string $fill, string $stroke, bool $filled = true): string
    {
        return $fill.' rg '.$stroke.' RG '.$this->num($x).' '.$this->num($y).' '.$this->num($w).' '.$this->num($h).' re '.($filled ? 'B' : 'S');
    }

    private function circle(float $x, float $y, float $r, string $stroke = '0.07 0.07 0.07'): string
    {
        $c = $r * 0.5522847498;

        return $stroke.' RG '.$this->num($x).' '.$this->num($y + $r).' m '
            .$this->num($x + $c).' '.$this->num($y + $r).' '.$this->num($x + $r).' '.$this->num($y + $c).' '.$this->num($x + $r).' '.$this->num($y).' c '
            .$this->num($x + $r).' '.$this->num($y - $c).' '.$this->num($x + $c).' '.$this->num($y - $r).' '.$this->num($x).' '.$this->num($y - $r).' c '
            .$this->num($x - $c).' '.$this->num($y - $r).' '.$this->num($x - $r).' '.$this->num($y - $c).' '.$this->num($x - $r).' '.$this->num($y).' c '
            .$this->num($x - $r).' '.$this->num($y + $c).' '.$this->num($x - $c).' '.$this->num($y + $r).' '.$this->num($x).' '.$this->num($y + $r).' c S';
    }

    private function personIcon(float $x, float $y): string
    {
        return $this->circle($x, $y, 10)."\n".$this->circle($x, $y + 3.5, 2.5)."\n".
            '0.07 0.07 0.07 RG '.$this->num($x - 5).' '.$this->num($y - 5).' m '.$this->num($x - 3).' '.$this->num($y - 1).' '.$this->num($x + 3).' '.$this->num($y - 1).' '.$this->num($x + 5).' '.$this->num($y - 5).' c S';
    }

    private function checkMark(float $x, float $y): string
    {
        return '0.07 0.07 0.07 RG 2.2 w '
            .$this->num($x).' '.$this->num($y).' m '
            .$this->num($x + 5).' '.$this->num($y - 5).' l '
            .$this->num($x + 14).' '.$this->num($y + 8).' l S';
    }

    private function text(float $x, float $y, string $text, int $size = 10, string $font = 'F1', string $color = '0 0 0'): string
    {
        return 'BT '.$color.' rg /'.$font.' '.$size.' Tf '.$this->num($x).' '.$this->num($y).' Td ('.$this->escape($text).') Tj ET';
    }

    private function textCenter(float $centerX, float $y, string $text, int $size = 10, string $font = 'F1', string $color = '0 0 0', float $factor = 0.48): string
    {
        $width = strlen($this->pdfText($text)) * $size * $factor;

        return $this->text($centerX - ($width / 2), $y, $text, $size, $font, $color);
    }

    private function textRight(float $rightX, float $y, string $text, int $size = 10, string $font = 'F1', string $color = '0 0 0', float $factor = 0.48): string
    {
        $width = strlen($this->pdfText($text)) * $size * $factor;

        return $this->text($rightX - $width, $y, $text, $size, $font, $color);
    }

    private function fitTextSize(string $text, float $maxWidth, int $maxSize, int $minSize, float $factor): int
    {
        for ($size = $maxSize; $size >= $minSize; $size--) {
            if (strlen($this->pdfText($text)) * $size * $factor <= $maxWidth) {
                return $size;
            }
        }

        return $minSize;
    }

    private function truncateByWidth(string $text, float $maxWidth, int $size, float $factor = 0.5): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        $maxChars = max(3, (int) floor($maxWidth / max(1, $size * $factor)));

        return $this->truncate($text, $maxChars);
    }

    /** @return array<int,string> */
    private function normaliseTextLines(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $lines = [];

        foreach ($values as $item) {
            if (is_array($item)) {
                foreach ($this->normaliseTextLines($item) as $nestedLine) {
                    $lines[] = $nestedLine;
                }
                continue;
            }

            $text = str_replace(["\r\n", "\r"], "\n", trim((string) $item));
            foreach (preg_split('/\n+|\s*\|\s*/', $text) ?: [] as $line) {
                $line = trim(preg_replace('/[ \t]+/', ' ', $line) ?? $line);
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        return $lines ?: ['-'];
    }

    /** @return array<int,string> */
    private function wrapTextByWidth(string $text, float $maxWidth, int $size, float $factor, int $maxLines): array
    {
        $maxChars = max(4, (int) floor($maxWidth / max(1, $size * $factor)));
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $paragraphs = preg_split('/\n+/', $text) ?: [];
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim(preg_replace('/[ \t]+/', ' ', $paragraph) ?? $paragraph);
            if ($paragraph === '') {
                continue;
            }

            $wrapped = wordwrap($paragraph, $maxChars, "\n", true);
            foreach (explode("\n", $wrapped) as $wrappedLine) {
                $wrappedLine = trim($wrappedLine);
                if ($wrappedLine !== '') {
                    $lines[] = $wrappedLine;
                }
            }
        }

        if ($lines === []) {
            return ['-'];
        }

        // Receipt remarks are deliberately allowed to use the second, third and
        // fourth lines. Never add an ellipsis when the complete text fits there.
        if (count($lines) <= $maxLines) {
            return $lines;
        }

        // As a last resort, re-wrap more tightly before clipping. This keeps the
        // full due information visible for normal receipt remarks.
        $compactChars = max($maxChars, (int) ceil(array_sum(array_map('mb_strlen', $lines)) / max(1, $maxLines)));
        $compact = wordwrap(implode(' ', $lines), $compactChars, "\n", true);
        $compactLines = array_values(array_filter(array_map('trim', explode("\n", $compact))));

        return array_slice($compactLines, 0, $maxLines);
    }

    /** @return array<int,string> */
    private function wrapText(string $text, int $maxChars, int $maxLines): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;
                continue;
            }
            if ($current !== '') {
                $lines[] = $current;
            }
            $current = $word;
            if (count($lines) >= $maxLines - 1) {
                break;
            }
        }
        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }
        if (count($lines) === $maxLines && count($words) > 0) {
            $lines[$maxLines - 1] = $this->truncate($lines[$maxLines - 1], $maxChars);
        }

        return $lines ?: ['-'];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name ?: 'BA')) ?: [];
        $letters = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $letters .= mb_substr($part, 0, 1);
            }
            if (mb_strlen($letters) >= 2) {
                break;
            }
        }

        return strtoupper($letters ?: 'BA');
    }

    private function truncate(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, max(1, $max - 3)).'...' : $text;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->pdfText($text));
    }

    private function pdfText(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        return $converted === false ? preg_replace('/[^\x20-\x7E]/', '', $text) ?: '' : $converted;
    }

    private function num(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
