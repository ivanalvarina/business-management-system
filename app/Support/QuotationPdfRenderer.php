<?php

namespace App\Support;

use App\Models\Quotation;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class QuotationPdfRenderer
{
    /** Style => TTF file inside storage/app/pdf-fonts/unifont/ */
    private const FONT_FILES = [
        '' => 'ARIALN.TTF',
        'B' => 'ARIALNB.TTF',
        'I' => 'ARIALNI.TTF',
        'BI' => 'ARIALNBI.TTF',
    ];

    private bool $debug = false;

    /** Font family used for all text ('Narrow' if TTFs found, else 'helvetica'). */
    private string $family = 'helvetica';

    /** Enable the grid + field-box overlay (use ?debug=1 in local only). */
    public function debug(bool $on = true): static
    {
        $this->debug = $on;

        return $this;
    }

    public function render(Quotation $quotation): string
    {
        $snapshot = $quotation->quotation_template_snapshot;

        if (! is_array($snapshot)) {
            throw new RuntimeException(__('This quotation has no template snapshot.'));
        }

        $storedPath = (string) ($snapshot['stored_path'] ?? '');

        if (! $this->templatePathIsSafe($storedPath) || ! Storage::disk('public')->exists($storedPath)) {
            throw new RuntimeException(__('The quotation template file is missing.'));
        }

        $config = is_array($snapshot['config'] ?? null) ? $snapshot['config'] : [];
        $templatePath = Storage::disk('public')->path($storedPath);

        // tFPDF looks for TTFs in FPDF_FONTPATH.'unifont/'. Must end with a slash.
        if (! defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', storage_path('app/pdf-fonts').DIRECTORY_SEPARATOR);
        }

        $pdf = new PdfCanvas('P', 'mm');
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetMargins(0, 0, 0);
        // MultiCell adds a 1mm inner padding by default, which shifts every
        // text box right by 1mm (and shrinks right-aligned boxes).
        $pdf->setCellMargin(0);

        $this->family = $this->registerFonts($pdf);

        $pageCount = $pdf->setSourceFile($templatePath);

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            // If your template was drawn against the MediaBox but has a CropBox,
            // everything shifts. Try: importPage($pageNumber, '/MediaBox')
            $template = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($template);

            if (! is_array($size)) {
                throw new RuntimeException(__('Unable to read quotation template page size.'));
            }

            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($template, 0, 0, $size['width'], $size['height'], true);

            $this->writeConfiguredFields($pdf, $quotation, $config, $pageNumber);
            $this->writeConfiguredItems($pdf, $quotation, $config, $pageNumber);
            $this->drawGrid($pdf, $size['width'], $size['height']);
        }

        return $pdf->Output($this->filename($quotation), 'S');
    }

    /**
     * Registers Arial Narrow (or a metric-compatible file with the same names).
     * Falls back to Helvetica instead of crashing when the TTFs are missing.
     */
    private function registerFonts(PdfCanvas $pdf): string
    {
        $dir = storage_path('app/pdf-fonts/unifont').DIRECTORY_SEPARATOR;

        foreach (self::FONT_FILES as $file) {
            if (! is_file($dir.$file)) {
                return 'helvetica';
            }
        }

        foreach (self::FONT_FILES as $style => $file) {
            $pdf->AddFont('Narrow', $style, $file, true);
        }

        return 'Narrow';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function writeConfiguredFields(PdfCanvas $pdf, Quotation $quotation, array $config, int $pageNumber): void
    {
        $fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];
        $values = $this->fieldValues($quotation);

        foreach ($fields as $fieldName => $fieldConfig) {
            if (! is_array($fieldConfig) || (int) ($fieldConfig['page'] ?? 1) !== $pageNumber) {
                continue;
            }

            // "field" lets one value appear in several places, e.g. the header
            // repeated on page 2: {"quotation_no_p2": {"field": "quotation_no", "page": 2, ...}}
            $source = (string) ($fieldConfig['field'] ?? $fieldName);
            $value = (string) ($values[$source] ?? '');

            // In debug mode still outline empty fields so they can be positioned.
            if ($value === '' && ! $this->debug) {
                continue;
            }

            $this->writeText($pdf, $value, $fieldConfig, (string) $fieldName);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function writeConfiguredItems(PdfCanvas $pdf, Quotation $quotation, array $config, int $pageNumber): void
    {
        $itemsConfig = is_array($config['items'] ?? null) ? $config['items'] : [];

        if ($itemsConfig === [] || (int) ($itemsConfig['page'] ?? 1) !== $pageNumber) {
            return;
        }

        $columns = is_array($itemsConfig['columns'] ?? null) ? $itemsConfig['columns'] : [];
        $startY = (float) ($itemsConfig['start_y'] ?? 0);
        $rowHeight = (float) ($itemsConfig['row_height'] ?? 6);
        $fontSize = (float) ($itemsConfig['font_size'] ?? 9);
        $maxRows = (int) ($itemsConfig['max_rows'] ?? $quotation->items->count());

        foreach ($quotation->items->take($maxRows)->values() as $index => $item) {
            $rowY = $startY + ($index * $rowHeight);

            // Column headers on the template have no currency, so items show plain numbers.
            $values = [
                'item_no' => (string) ($index + 1),
                'quantity' => $this->quantity($item->quantity),
                'description' => (string) $item->description,
                'unit' => (string) $item->unit,
                'unit_price' => number_format((float) $item->unit_price, 2),
                'discount' => number_format((float) $item->discount, 2),
                'tax' => number_format((float) $item->tax, 2),
                'amount' => number_format((float) $item->line_total, 2),
            ];

            foreach ($columns as $columnName => $columnConfig) {
                if (! is_array($columnConfig)) {
                    continue;
                }

                $this->writeText($pdf, $values[(string) $columnName] ?? '', [
                    ...$columnConfig,
                    'y' => $rowY,
                    // Default each cell's height to the row height so the
                    // debug box matches the row on the template.
                    'height' => $columnConfig['height'] ?? $rowHeight,
                    'font_size' => $columnConfig['font_size'] ?? $fontSize,
                ], "items.{$columnName}#".($index + 1));
            }
        }
    }

    /**
     * x/y = top-left of the box in mm. width = box width. Right/center
     * alignment happens INSIDE that box, so for right-aligned numbers set
     * x to the box's left edge and width so that x + width = the right edge.
     *
     * Optional config keys: font_family, font_style ('italic'), font_weight ('bold').
     *
     * @param  array<string, mixed>  $config
     */
    private function writeText(PdfCanvas $pdf, string $value, array $config, string $label = ''): void
    {
        $x = (float) ($config['x'] ?? 0);
        $y = (float) ($config['y'] ?? 0);
        $width = (float) ($config['width'] ?? $config['max_width'] ?? 60);
        $height = (float) ($config['height'] ?? 5);
        $fontSize = (float) ($config['font_size'] ?? 10);
        $fontWeight = (string) ($config['font_weight'] ?? '');
        $align = strtoupper((string) ($config['align'] ?? 'L'));

        $bold = in_array($fontWeight, ['bold', '700', '800', '900'], true);
        $italic = in_array((string) ($config['font_style'] ?? ''), ['italic', 'I'], true);
        $style = ($bold ? 'B' : '').($italic ? 'I' : '');
        $family = (string) ($config['font_family'] ?? $this->family);

        $this->drawBox($pdf, $label, $x, $y, $width, $height);

        $pdf->SetFont($family, $style, $fontSize);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($width, $height, $this->text($value), 0, $align, false);
    }

    /** TTF fonts take UTF-8 as-is; the Helvetica fallback needs cp1252. */
    private function text(string $value): string
    {
        if ($this->family !== 'helvetica') {
            return $value;
        }

        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value);

        return $converted === false ? $value : $converted;
    }

    private function drawBox(PdfCanvas $pdf, string $label, float $x, float $y, float $w, float $h): void
    {
        if (! $this->debug) {
            return;
        }

        $pdf->SetLineWidth(0.15);
        $pdf->SetDrawColor(0, 110, 255);
        $pdf->Rect($x, $y, $w, $h);

        $pdf->SetFont($this->family, '', 4);
        $pdf->SetTextColor(0, 110, 255);
        $pdf->Text($x, max($y - 0.5, 1.5), "{$label} x={$x} y={$y} w={$w}");
    }

    /** Millimetre ruler grid: light every 5mm, strong + numbered every 10mm. */
    private function drawGrid(PdfCanvas $pdf, float $pageW, float $pageH): void
    {
        if (! $this->debug) {
            return;
        }

        $pdf->SetLineWidth(0.05);
        $pdf->SetFont($this->family, '', 4);

        for ($x = 0; $x <= (int) $pageW; $x += 5) {
            $major = $x % 10 === 0;
            $pdf->SetDrawColor(...($major ? [255, 130, 130] : [255, 215, 215]));
            $pdf->Line($x, 0, $x, $pageH);

            if ($major) {
                $pdf->SetTextColor(220, 0, 0);
                $pdf->Text($x + 0.3, 2.5, (string) $x);
            }
        }

        for ($y = 0; $y <= (int) $pageH; $y += 5) {
            $major = $y % 10 === 0;
            $pdf->SetDrawColor(...($major ? [255, 130, 130] : [255, 215, 215]));
            $pdf->Line(0, $y, $pageW, $y);

            if ($major) {
                $pdf->SetTextColor(220, 0, 0);
                $pdf->Text(0.5, $y - 0.3, (string) $y);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function fieldValues(Quotation $quotation): array
    {
        $client = $quotation->client;

        return [
            'quotation_no' => $quotation->quotation_no,
            'quotation_date' => $quotation->quotation_date->toDateString(),
            'valid_until' => $quotation->valid_until?->toDateString() ?? '',
            'customer_name' => $client->client_name,
            'customer_address' => $client->billing_address ?? $client->shipping_address ?? '',
            'customer_phone' => $client->phone ?? '',
            'customer_email' => $client->email ?? '',
            'notes' => $quotation->notes ?? '',
            'terms_conditions' => $quotation->terms_conditions ?? '',
            'subtotal' => $this->money($quotation, $quotation->subtotal),
            'discount' => $this->money($quotation, $quotation->discount),
            'tax_amount' => $this->money($quotation, $quotation->tax_amount),
            'total_amount' => $this->money($quotation, $quotation->total_amount),
        ];
    }

    private function money(Quotation $quotation, string|float|int|null $value): string
    {
        return $quotation->currency.' '.number_format((float) $value, 2);
    }

    /** 1.0000 => "1", 2.5000 => "2.5" */
    private function quantity(string|float|int|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }

    private function templatePathIsSafe(string $path): bool
    {
        return ! str_contains($path, '..') && str_starts_with($path, 'quotation-templates/');
    }

    private function filename(Quotation $quotation): string
    {
        return preg_replace('/[^\w\-]+/', '-', $quotation->quotation_no).'.pdf';
    }
}
