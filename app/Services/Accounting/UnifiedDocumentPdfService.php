<?php

namespace App\Services\Accounting;

use Illuminate\Support\Facades\Storage;

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
     *   prepared_position:string,
     *   prepared_date:string,
     *   prepared_email?:string,
     *   footer?:string
     * } $document
     */
    public function render(array $document): string
    {
        $company = (array) ($document['company'] ?? []);

        // Use one fixed company header for every generated PDF. The logo path
        // remains supplied by the current company, while all printed letters
        // are controlled from config/document_company.php.
        $printedCompany = (array) config('document_company', []);
        $company = array_merge($company, array_filter([
            'name' => $printedCompany['name'] ?? 'BASHIR AGRO',
            'short_name' => $printedCompany['short_name'] ?? 'BA',
            'address' => $printedCompany['address'] ?? 'Mymensingh, Bangladesh',
            'phone' => $printedCompany['phone'] ?? '+8801700000000',
            'email' => $printedCompany['email'] ?? 'info@bashiragro.com',
            'website' => $printedCompany['website'] ?? 'www.Bashiragro.com',
        ], static fn ($value): bool => $value !== null && $value !== ''));

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

        $content = [];
        $content[] = '1 1 1 rg 0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.' re f';

        $pageX = 28.0;
        $pageY = 28.0;
        $pageW = self::PAGE_WIDTH - 56.0;
        $pageH = self::PAGE_HEIGHT - 56.0;
        $left = 52.0;
        $right = 543.0;
        $content[] = $this->rect($pageX, $pageY, $pageW, $pageH, '1 1 1', '0.07 0.07 0.07', false);

        // Header: fixed independent columns. Company content can never enter the title column.
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
            trim((string) ($company['email'] ?? '')) !== '' ? 'Email: '.trim((string) $company['email']) : '',
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
        $metaRows = array_slice((array) ($document['meta'] ?? []), 0, 4);
        foreach ($metaRows as $row) {
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

        // Party block.
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

        // Item table. Its height is bounded so the lower section always has dedicated space.
        $lines = array_values((array) ($document['lines'] ?? []));
        if ($lines === []) {
            $lines = [['description' => '-', 'remarks' => '-', 'amount' => 0]];
        }
        if (count($lines) > 12) {
            $visible = array_slice($lines, 0, 11);
            $remaining = array_slice($lines, 11);
            $visible[] = [
                'description' => 'Additional '.count($remaining).' item(s)',
                'remarks' => 'Included in this transaction',
                'amount' => array_sum(array_map(static fn ($line): float => (float) ($line['amount'] ?? 0), $remaining)),
            ];
            $lines = $visible;
        }

        $rowCount = max(4, count($lines));
        $tableTop = 505.0;
        $headerH = 24.0;
        $rowBudget = 164.0;
        $rowH = max(13.0, min(28.0, floor($rowBudget / $rowCount)));
        $tableBottom = $tableTop - $headerH - ($rowCount * $rowH);
        $tableX = $left;
        $tableW = $right - $left;
        $col1 = 205.0;
        $col2 = 190.0;
        $col3 = $tableW - $col1 - $col2;

        $content[] = $this->rect($tableX, $tableTop - $headerH, $tableW, $headerH, '0.95 0.95 0.95', '0.62 0.65 0.70', true);
        $content[] = $this->text($tableX + 70, $tableTop - 16, 'DESCRIPTION', 9, 'F2');
        $content[] = $this->text($tableX + $col1 + 72, $tableTop - 16, 'REMARKS', 9, 'F2');
        $currency = strtoupper((string) ($document['currency_label'] ?? 'TK'));
        $content[] = $this->text($tableX + $col1 + $col2 + 14, $tableTop - 16, 'AMOUNT ('.$currency.')', 9, 'F2');

        $content[] = '0.62 0.65 0.70 RG '.$tableX.' '.$tableTop.' m '.$right.' '.$tableTop.' l S';
        $content[] = '0.62 0.65 0.70 RG '.$tableX.' '.($tableTop - $headerH).' m '.$right.' '.($tableTop - $headerH).' l S';
        for ($i = 1; $i <= $rowCount; $i++) {
            $y = $tableTop - $headerH - ($i * $rowH);
            $dash = $i > count($lines) ? '[2 2] 0 d ' : '[] 0 d ';
            $content[] = $dash.'0.62 0.65 0.70 RG '.$tableX.' '.$y.' m '.$right.' '.$y.' l S [] 0 d';
        }
        foreach ([$tableX, $tableX + $col1, $tableX + $col1 + $col2, $right] as $x) {
            $content[] = '0.62 0.65 0.70 RG '.$x.' '.$tableBottom.' m '.$x.' '.$tableTop.' l S';
        }

        $lineFont = $rowH <= 15 ? 6 : ($rowH <= 19 ? 7 : 8);
        foreach ($lines as $index => $line) {
            $baseline = $tableTop - $headerH - ($index * $rowH) - min($rowH - 4, 16);
            $description = (string) ($line['description'] ?? '-');
            $remarks = (string) ($line['remarks'] ?? '-');
            $amount = (float) ($line['amount'] ?? 0);
            $content[] = $this->text($tableX + 10, $baseline, $this->truncateByWidth($description, $col1 - 20, $lineFont, 0.5), $lineFont, 'F1');
            $content[] = $this->text($tableX + $col1 + 10, $baseline, $this->truncateByWidth($remarks, $col2 - 20, $lineFont, 0.5), $lineFont, 'F1');
            $content[] = $this->textRight($right - 10, $baseline, number_format($amount, 2, '.', ','), $lineFont, 'F1');
        }

        // Summary is completed first. Prepared-by starts only after the final summary row.
        $summary = array_values((array) ($document['summary'] ?? []));
        $summaryX = 345.0;
        $summaryTop = $tableBottom - 16.0;
        $summaryRowH = count($summary) > 5 ? 15.0 : 18.0;
        $summaryY = $summaryTop;
        foreach ($summary as $row) {
            $isTotal = !empty($row['total']);
            $height = $isTotal ? 22.0 : $summaryRowH;
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
        $lowerTop = max(170.0, $lowerTop);

        // Left lower section.
        $content[] = $this->text($left, $lowerTop, 'AMOUNT IN WORDS', 10, 'F2');
        $content[] = $this->rect($left, $lowerTop - 40, 260, 28, '1 1 1', '0.72 0.74 0.78', false);
        $content[] = $this->text($left + 10, $lowerTop - 29, $this->truncateByWidth((string) ($document['amount_words'] ?? '-'), 240, 9, 0.5), 9, 'F1');
        $content[] = $this->text($left, $lowerTop - 69, 'NOTES', 10, 'F2');
        $notesTop = $lowerTop - 80;
        $notesBottom = 82.0;
        $notesHeight = max(28.0, $notesTop - $notesBottom);
        $content[] = $this->rect($left, $notesTop - $notesHeight, 260, $notesHeight, '1 1 1', '0.72 0.74 0.78', false);
        $maxNoteLines = $notesHeight < 36 ? 1 : ($notesHeight < 52 ? 2 : 3);
        $noteFont = $notesHeight < 36 ? 7 : 8;
        $noteLines = $this->wrapText((string) ($document['notes'] ?? 'Thank you for your business.'), $notesHeight < 36 ? 62 : 53, $maxNoteLines);
        $noteY = $notesTop - 16;
        foreach ($noteLines as $noteLine) {
            $content[] = $this->text($left + 10, $noteY, $noteLine, $noteFont, 'F1');
            $noteY -= 11;
        }

        // Right lower section: independently positioned below summary, never over it.
        $dividerX = 330.0;
        $content[] = '[2 2] 0 d 0.72 0.74 0.78 RG '.$dividerX.' '.($lowerTop + 4).' m '.$dividerX.' 78 l S [] 0 d';
        $preparedX = 350.0;
        $content[] = $this->text($preparedX, $lowerTop, 'PREPARED BY', 10, 'F2');
        $preparedY = $lowerTop - 25.0;
        foreach ([
            'Name' => (string) ($document['prepared_name'] ?? 'System User'),
            'Position' => (string) ($document['prepared_position'] ?? 'Accounts Executive'),
            'Date' => (string) ($document['prepared_date'] ?? '-'),
        ] as $label => $value) {
            $content[] = $this->text($preparedX, $preparedY, $label, 9, 'F1');
            $content[] = $this->text($preparedX + 55, $preparedY, ':', 9, 'F1');
            $content[] = $this->text($preparedX + 69, $preparedY, $this->truncateByWidth($value, $right - ($preparedX + 69), 9, 0.5), 9, 'F1');
            $preparedY -= 20.0;
        }

        $signatureLineY = max(86.0, min(112.0, $preparedY - 22.0));
        $content[] = '0.07 0.07 0.07 RG '.$preparedX.' '.$signatureLineY.' m '.$right.' '.$signatureLineY.' l S';
        $content[] = $this->text($preparedX + 38, $signatureLineY - 14, 'DIGITAL SIGNATURE', 9, 'F2');
        $preparedEmail = trim((string) ($document['prepared_email'] ?? ''));
        if ($preparedEmail !== '' && $signatureLineY >= 100) {
            $content[] = $this->text($preparedX + 30, $signatureLineY - 27, $this->truncateByWidth($preparedEmail, $right - ($preparedX + 30), 7, 0.5), 7, 'F3');
        }

        // Footer has its own reserved strip.
        $footerRuleY = 58.0;
        $content[] = '0.07 0.07 0.07 RG '.$left.' '.$footerRuleY.' m '.$right.' '.$footerRuleY.' l S';
        $content[] = $this->shield(110, 35);
        $footer = (string) ($document['footer'] ?? 'This document is electronically generated and may not require a physical signature.');
        $content[] = $this->text(150, 39, $this->truncateByWidth($footer, 380, 8, 0.48), 8, 'F3');

        return $this->buildPdf(implode("\n", $content), $fontRegular, $fontBold, $fontItalic, $logoObject);
    }

    /** @return array{stream:string,width:int,height:int}|null */
    private function logoData(mixed $path): ?array
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        try {
            $fullPath = Storage::disk('public')->path($path);
        } catch (\Throwable) {
            $fullPath = storage_path('app/public/'.ltrim($path, '/'));
        }

        if (! is_file($fullPath)) {
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

    private function buildPdf(string $pageContent, int $fontRegular, int $fontBold, int $fontItalic, ?int $logoObject): string
    {
        $contentObject = $this->addStreamObject($pageContent, '<<');
        $xObject = $logoObject ? ' /XObject << /Logo '.$logoObject.' 0 R >>' : '';
        $pageObject = $this->addObject('<< /Type /Page /Parent __PAGES__ 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Resources << /Font << /F1 '.$fontRegular.' 0 R /F2 '.$fontBold.' 0 R /F3 '.$fontItalic.' 0 R >>'.$xObject.' >> /Contents '.$contentObject.' 0 R >>');
        $pagesObject = $this->addObject('<< /Type /Pages /Kids ['.$pageObject.' 0 R] /Count 1 >>');
        $this->objects[$pageObject] = str_replace('__PAGES__', (string) $pagesObject, $this->objects[$pageObject]);
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

    private function shield(float $x, float $y): string
    {
        return '0.07 0.07 0.07 RG '.$this->num($x).' '.$this->num($y + 18).' m '.$this->num($x + 16).' '.$this->num($y + 12).' l '.$this->num($x + 14).' '.$this->num($y).' l '.$this->num($x + 8).' '.$this->num($y - 7).' l '.$this->num($x + 2).' '.$this->num($y).' l h S'."\n".
            '0.07 0.07 0.07 RG '.$this->num($x + 4.5).' '.$this->num($y + 6).' m '.$this->num($x + 7.5).' '.$this->num($y + 3).' l '.$this->num($x + 12).' '.$this->num($y + 10).' l S';
    }

    private function text(float $x, float $y, string $text, int $size = 10, string $font = 'F1', string $color = '0 0 0'): string
    {
        return 'BT '.$color.' rg /'.$font.' '.$size.' Tf '.$this->num($x).' '.$this->num($y).' Td ('.$this->escape($text).') Tj ET';
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
