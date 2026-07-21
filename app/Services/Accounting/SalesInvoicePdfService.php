<?php

namespace App\Services\Accounting;

use App\Models\SalesInvoice;
use Illuminate\Support\Facades\Storage;

class SalesInvoicePdfService
{
    private const PAGE_WIDTH = 595.28;
    private const PAGE_HEIGHT = 841.89;
    private const MARGIN = 36.0;

    /** @var array<int, string> */
    private array $objects = [];

    public function render(SalesInvoice $invoice): string
    {
        $invoice->loadMissing([
            'transaction.transactionHead',
            'transaction.moneyAccount',
            'transaction.party',
            'transaction.saleLines',
            'company',
            'party',
        ]);

        $company = $this->companyData($invoice);
        $customer = $this->customerData($invoice);
        $transaction = $invoice->transaction;
        $currency = $company['currency_code'] ?: ($invoice->company?->currency_code ?: 'BDT');
        $lines = $this->invoiceLines($invoice);
        $logo = $this->logoData($company['logo_path'] ?? null);

        $this->objects = [];
        $fontRegular = $this->addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        $fontBold = $this->addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');
        $logoObject = $logo ? $this->addStreamObject($logo['stream'], sprintf(
            '<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode',
            $logo['width'],
            $logo['height']
        )) : null;

        $content = [];
        $content[] = '0.97 0.98 1 rg 0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.' re f';
        $paperX = self::MARGIN;
        $paperY = 54.0;
        $paperW = self::PAGE_WIDTH - (self::MARGIN * 2);
        $paperH = self::PAGE_HEIGHT - 108;
        $paperRight = $paperX + $paperW;
        $content[] = $this->rect($paperX, $paperY, $paperW, $paperH, '1 1 1', '0.82 0.86 0.91', true);

        $headerTop = 778.0;
        $headerBottom = 710.0;
        $logoX = $paperX + 18;
        $logoY = $headerTop - 50;
        $logoSize = 50.0;
        if ($logoObject) {
            $content[] = 'q '.$this->num($logoSize).' 0 0 '.$this->num($logoSize).' '.$this->num($logoX).' '.$this->num($logoY).' cm /Logo Do Q';
        } else {
            $content[] = $this->rect($logoX, $logoY, $logoSize, $logoSize, '0.90 0.94 1', '0.37 0.52 0.82', true, 8);
            $content[] = $this->text($logoX + 13, $logoY + 18, $this->initials($company['short_name'] ?: $company['name']), 16, 'F2', '0.10 0.28 0.55');
        }

        $companyX = $logoX + $logoSize + 14;
        $invoiceRight = $paperRight - 18;
        $companyMaxChars = 34;
        $content[] = $this->text($companyX, $headerTop - 4, $this->truncate($company['name'], $companyMaxChars), 16, 'F2', '0.08 0.13 0.22');
        if ($company['address'] !== '') {
            $content[] = $this->text($companyX, $headerTop - 22, $this->truncate($company['address'], 42), 8, 'F1', '0.37 0.44 0.55');
        }
        $contact = trim(implode('  |  ', array_filter([
            $company['phone'] !== '' ? 'Phone: '.$company['phone'] : '',
            $company['email'] !== '' ? 'Email: '.$company['email'] : '',
            $company['website'] !== '' ? $company['website'] : '',
        ])));
        if ($contact !== '') {
            $content[] = $this->text($companyX, $headerTop - 36, $this->truncate($contact, 48), 8, 'F1', '0.37 0.44 0.55');
        }
        $registration = trim(implode('  |  ', array_filter([
            $company['tin'] !== '' ? 'TIN: '.$company['tin'] : '',
            $company['bin_vat_registration_no'] !== '' ? 'BIN/VAT: '.$company['bin_vat_registration_no'] : '',
        ])));
        if ($registration !== '') {
            $content[] = $this->text($companyX, $headerTop - 49, $this->truncate($registration, 48), 8, 'F1', '0.37 0.44 0.55');
        }

        $content[] = $this->textRight($invoiceRight, $headerTop - 2, 'BILL / INVOICE', 20, 'F2', '0.10 0.28 0.55');
        $content[] = $this->textRight($invoiceRight, $headerTop - 26, 'Invoice No: '.$invoice->invoice_no, 10, 'F2', '0.08 0.13 0.22');
        $content[] = $this->textRight($invoiceRight, $headerTop - 42, 'Date: '.$invoice->invoice_date?->format('d/m/Y'), 10, 'F1', '0.08 0.13 0.22');
        if ($invoice->due_date) {
            $content[] = $this->textRight($invoiceRight, $headerTop - 57, 'Due Date: '.$invoice->due_date->format('d/m/Y'), 9, 'F1', '0.37 0.44 0.55');
        }

        $content[] = '0.86 0.90 0.96 RG '.$paperX.' '.$headerBottom.' m '.$paperRight.' '.$headerBottom.' l S';

        $content[] = $this->sectionTitle(self::MARGIN + 18, 680, 'Bill / Invoice To');
        $content[] = $this->text(self::MARGIN + 18, 662, $customer['name'], 13, 'F2', '0.08 0.13 0.22');
        if ($customer['code'] !== '') {
            $content[] = $this->text(self::MARGIN + 18, 646, 'Customer Code: '.$customer['code'], 9, 'F1', '0.37 0.44 0.55');
        }
        if ($customer['type'] !== '') {
            $content[] = $this->text(self::MARGIN + 18, 632, 'Type: '.$customer['type'], 9, 'F1', '0.37 0.44 0.55');
        }

        $content[] = $this->sectionTitle(360, 680, 'Transaction');
        $content[] = $this->text(360, 662, 'Voucher: '.($transaction?->voucher_no ?: '-'), 9, 'F1', '0.37 0.44 0.55');
        $content[] = $this->text(360, 648, 'Head: '.($transaction?->displayHeadName('-') ?: '-'), 9, 'F1', '0.37 0.44 0.55');
        $content[] = $this->text(360, 634, 'Status: '.ucfirst((string) $invoice->status), 9, 'F2', '0.05 0.45 0.28');

        $tableTop = 600.0;
        $content[] = $this->rect(self::MARGIN + 18, $tableTop - 26, self::PAGE_WIDTH - 108, 26, '0.10 0.32 0.50', '0.10 0.32 0.50', true);
        $content[] = $this->text(60, $tableTop - 17, 'SL.', 10, 'F2', '1 1 1');
        $content[] = $this->text(95, $tableTop - 17, 'Particulars', 10, 'F2', '1 1 1');
        $content[] = $this->text(330, $tableTop - 17, 'Qty', 10, 'F2', '1 1 1');
        $content[] = $this->text(392, $tableTop - 17, 'Rate', 10, 'F2', '1 1 1');
        $content[] = $this->text(470, $tableTop - 17, 'Amount', 10, 'F2', '1 1 1');

        $rowY = $tableTop - 52;
        foreach (array_slice($lines, 0, 15) as $index => $line) {
            $fill = $index % 2 === 0 ? '0.98 0.99 1' : '1 1 1';
            $content[] = $this->rect(self::MARGIN + 18, $rowY - 8, self::PAGE_WIDTH - 108, 26, $fill, '0.89 0.92 0.96', true);
            $content[] = $this->text(60, $rowY + 2, (string) ($index + 1), 10, 'F1', '0.08 0.13 0.22');
            $content[] = $this->text(95, $rowY + 2, $this->truncate($line['description'], 42), 10, 'F1', '0.08 0.13 0.22');
            $content[] = $this->textRight(356, $rowY + 2, $line['quantity'], 10, 'F1', '0.08 0.13 0.22');
            $content[] = $this->textRight(430, $rowY + 2, $line['rate'], 10, 'F1', '0.08 0.13 0.22');
            $content[] = $this->textRight(535, $rowY + 2, $this->money($line['amount'], $currency), 10, 'F2', '0.08 0.13 0.22');
            $rowY -= 28;
        }

        if (count($lines) > 15) {
            $content[] = $this->text(95, $rowY + 8, '+ '.(count($lines) - 15).' more line(s) included in transaction description', 9, 'F1', '0.37 0.44 0.55');
            $rowY -= 20;
        }

        $summaryX = 345.0;
        $summaryY = max(188.0, $rowY - 16);
        $content[] = $this->summaryRow($summaryX, $summaryY, 'Amount', $this->money((float) $invoice->subtotal, $currency));
        $summaryY -= 24;
        $content[] = $this->summaryRow($summaryX, $summaryY, '(-) Discount', $this->money((float) $invoice->discount_amount, $currency));
        $summaryY -= 24;
        $content[] = $this->summaryRow($summaryX, $summaryY, '(+) Tax', $this->money((float) $invoice->tax_amount, $currency));
        $summaryY -= 28;
        $content[] = $this->rect($summaryX - 8, $summaryY - 8, 210, 26, '0.90 0.94 1', '0.56 0.68 0.86', true);
        $content[] = $this->summaryRow($summaryX, $summaryY, 'Total', $this->money((float) $invoice->total_amount, $currency), true);
        $summaryY -= 26;
        $content[] = $this->summaryRow($summaryX, $summaryY, 'Paid', $this->money((float) $invoice->paid_amount, $currency));
        $summaryY -= 26;
        $content[] = $this->summaryRow($summaryX, $summaryY, 'Due', $this->money((float) $invoice->due_amount, $currency), true, '0.75 0.12 0.12');

        $noteY = 128.0;
        $content[] = '0.86 0.90 0.96 RG '.(self::MARGIN + 18).' '.$noteY.' m '.(self::PAGE_WIDTH - self::MARGIN - 18).' '.$noteY.' l S';
        $content[] = $this->text(self::MARGIN + 18, $noteY - 20, 'Note: This invoice was generated from posted voucher '.($transaction?->voucher_no ?: '-').'.', 9, 'F1', '0.37 0.44 0.55');
        $content[] = $this->text(self::MARGIN + 18, 76, 'Authorized Signature', 9, 'F2', '0.08 0.13 0.22');
        $content[] = '0.40 0.48 0.60 RG '.(self::MARGIN + 18).' 94 m 170 94 l S';

        return $this->buildPdf(implode("\n", $content), $fontRegular, $fontBold, $logoObject);
    }

    /** @return array{name:string,short_name:string,address:string,phone:string,email:string,website:string,tin:string,bin_vat_registration_no:string,logo_path:?string,currency_code:string} */
    private function companyData(SalesInvoice $invoice): array
    {
        $snapshot = is_array($invoice->company_snapshot) ? $invoice->company_snapshot : [];
        $company = $invoice->company;

        return [
            'name' => (string) ($company?->name ?: ($snapshot['name'] ?? 'Company')),
            'short_name' => (string) ($company?->short_name ?: ($snapshot['short_name'] ?? '')),
            'address' => (string) ($company?->address ?: ($snapshot['address'] ?? '')),
            'phone' => (string) ($company?->contact_phone ?: ($snapshot['phone'] ?? '')),
            'email' => (string) ($company?->contact_email ?: ($snapshot['email'] ?? '')),
            'website' => (string) ($company?->website ?: ($snapshot['website'] ?? '')),
            'tin' => (string) ($company?->tin ?: ($snapshot['tin'] ?? '')),
            'bin_vat_registration_no' => (string) ($company?->bin_vat_registration_no ?: ($snapshot['bin_vat_registration_no'] ?? '')),
            'logo_path' => $company?->logo_path ?: ($snapshot['logo_path'] ?? null),
            'currency_code' => (string) ($company?->currency_code ?: ($snapshot['currency_code'] ?? 'BDT')),
        ];
    }

    /** @return array{name:string,code:string,type:string} */
    private function customerData(SalesInvoice $invoice): array
    {
        $snapshot = is_array($invoice->customer_snapshot) ? $invoice->customer_snapshot : [];

        return [
            'name' => (string) ($invoice->party?->name ?: ($snapshot['name'] ?? 'Cash Customer')),
            'code' => (string) ($invoice->party?->code ?: ($snapshot['code'] ?? '')),
            'type' => (string) ($invoice->party?->type ?: ($snapshot['type'] ?? '')),
        ];
    }

    /** @return array<int, array{description:string,quantity:string,rate:string,amount:float}> */
    private function invoiceLines(SalesInvoice $invoice): array
    {
        $transaction = $invoice->transaction;
        $saleLines = $transaction?->saleLines ?? collect();

        if ($saleLines->isNotEmpty()) {
            return $saleLines->map(fn ($line): array => [
                'description' => (string) $line->item_name,
                'quantity' => rtrim(rtrim(number_format((float) $line->quantity, 4, '.', ''), '0'), '.') . ($line->unit ? ' '.$line->unit : ''),
                'rate' => number_format((float) $line->rate, 2, '.', ''),
                'amount' => (float) $line->line_total,
            ])->values()->all();
        }

        return [[
            'description' => (string) ($transaction?->displayHeadName('Sales') ?: 'Sales'),
            'quantity' => '1',
            'rate' => number_format((float) $invoice->subtotal, 2, '.', ''),
            'amount' => (float) $invoice->subtotal,
        ]];
    }

    /** @return array{stream:string,width:int,height:int}|null */
    private function logoData(?string $path): ?array
    {
        if (! $path) {
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
            return [
                'stream' => (string) file_get_contents($fullPath),
                'width' => (int) $info[0],
                'height' => (int) $info[1],
            ];
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
            imagejpeg($canvas, null, 88);
            $stream = (string) ob_get_clean();
            imagedestroy($source);
            imagedestroy($canvas);

            return ['stream' => $stream, 'width' => $width, 'height' => $height];
        }

        return null;
    }

    private function buildPdf(string $pageContent, int $fontRegular, int $fontBold, ?int $logoObject): string
    {
        $contentObject = $this->addStreamObject($pageContent, '<<');
        $xObject = $logoObject ? ' /XObject << /Logo '.$logoObject.' 0 R >>' : '';
        $pageObject = $this->addObject('<< /Type /Page /Parent __PAGES__ 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Resources << /Font << /F1 '.$fontRegular.' 0 R /F2 '.$fontBold.' 0 R >>'.$xObject.' >> /Contents '.$contentObject.' 0 R >>');
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

    private function sectionTitle(float $x, float $y, string $text): string
    {
        return $this->text($x, $y, strtoupper($text), 9, 'F2', '0.10 0.28 0.55');
    }

    private function summaryRow(float $x, float $y, string $label, string $value, bool $bold = false, string $color = '0.08 0.13 0.22'): string
    {
        return $this->text($x, $y, $label, 10, $bold ? 'F2' : 'F1', $color)."\n".
            $this->textRight($x + 186, $y, $value, 10, $bold ? 'F2' : 'F1', $color);
    }

    private function rect(float $x, float $y, float $w, float $h, string $fill, string $stroke, bool $filled = true, float $radius = 0): string
    {
        unset($radius);
        $operator = $filled ? 'B' : 'S';

        return $fill.' rg '.$stroke.' RG '.$this->num($x).' '.$this->num($y).' '.$this->num($w).' '.$this->num($h).' re '.$operator;
    }

    private function text(float $x, float $y, string $text, int $size = 10, string $font = 'F1', string $color = '0 0 0'): string
    {
        return 'BT '.$color.' rg /'.$font.' '.$size.' Tf '.$this->num($x).' '.$this->num($y).' Td ('.$this->escape($text).') Tj ET';
    }

    private function textRight(float $rightX, float $y, string $text, int $size = 10, string $font = 'F1', string $color = '0 0 0'): string
    {
        $width = strlen($this->pdfText($text)) * $size * 0.48;

        return $this->text($rightX - $width, $y, $text, $size, $font, $color);
    }

    private function money(float $amount, string $currency): string
    {
        return strtoupper($currency).' '.number_format($amount, 2, '.', ',');
    }

    private function initials(string $name): string
    {
        $name = trim($name ?: 'HG');
        $parts = preg_split('/\s+/', $name) ?: [];

        if (count($parts) <= 1) {
            return strtoupper(mb_substr($name, 0, 2) ?: 'HG');
        }

        $letters = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $letters .= mb_substr($part, 0, 1);
            }
            if (mb_strlen($letters) >= 2) {
                break;
            }
        }

        return strtoupper($letters ?: 'HG');
    }

    private function truncate(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'...' : $text;
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
