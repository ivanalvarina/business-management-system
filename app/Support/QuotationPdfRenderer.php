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

    private bool $customFontsAvailable = false;

    /** @var array<string, array{label: string, value: string}> */
    private array $overflowSections = [];

    /** Enable the grid + field-box overlay (use ?debug=1 in local only). */
    public function debug(bool $on = true): static
    {
        $this->debug = $on;

        return $this;
    }

    public function render(Quotation $quotation): string
    {
        $this->overflowSections = [];
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

        $this->customFontsAvailable = $this->configureCustomFonts();

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

            $this->writeConfiguredFields($pdf, $quotation, $config, $pageNumber, $size['height']);
            $this->writeConfiguredItems($pdf, $quotation, $config, $pageNumber);
            $this->drawGrid($pdf, $size['width'], $size['height']);
        }

        $this->writeOverflowPages($pdf, $quotation);

        return $pdf->Output($this->filename($quotation), 'S');
    }

    /**
     * Registers Arial Narrow (or a metric-compatible file with the same names).
     * Falls back to Helvetica instead of crashing when the TTFs are missing.
     */
    private function registerFonts(PdfCanvas $pdf): string
    {
        if (! $this->customFontsAvailable) {
            return 'helvetica';
        }

        foreach (self::FONT_FILES as $file) {
            if (! $this->forgetStaleFontMetrics($file)) {
                return 'helvetica';
            }
        }

        foreach (self::FONT_FILES as $style => $file) {
            $pdf->AddFont('Narrow', $style, $file, true);
        }

        return 'Narrow';
    }

    private function configureCustomFonts(): bool
    {
        $fontDirectory = storage_path('app/pdf-fonts/unifont').DIRECTORY_SEPARATOR;

        foreach (self::FONT_FILES as $file) {
            if (! is_readable($fontDirectory.$file)) {
                return false;
            }
        }

        if (! defined('_SYSTEM_TTFONTS')) {
            define('_SYSTEM_TTFONTS', $fontDirectory);
        }

        return realpath((string) constant('_SYSTEM_TTFONTS')) === realpath($fontDirectory);
    }

    /**
     * tFPDF stores the absolute TTF path in its generated metrics file. Remove
     * metrics created on a different host, such as Windows host files mounted
     * into a Linux container, so tFPDF can rebuild them for this runtime.
     */
    private function forgetStaleFontMetrics(string $fontFile): bool
    {
        $metricsName = strtolower(pathinfo($fontFile, PATHINFO_FILENAME));
        $metricsDirectory = base_path('vendor/setasign/tfpdf/font/unifont');
        $metricsPath = $metricsDirectory.DIRECTORY_SEPARATOR.$metricsName.'.mtx.php';

        if (! is_file($metricsPath)) {
            return true;
        }

        $metrics = file_get_contents($metricsPath);
        $cachedFontPath = null;

        if (is_string($metrics) && preg_match("/\\\$ttffile='([^']*)';/", $metrics, $matches) === 1) {
            $cachedFontPath = $matches[1];
        }
        $fontPath = storage_path('app/pdf-fonts/unifont'.DIRECTORY_SEPARATOR.$fontFile);

        if ($cachedFontPath !== null && realpath($cachedFontPath) === realpath($fontPath)) {
            return true;
        }

        @unlink($metricsPath);
        @unlink($metricsDirectory.DIRECTORY_SEPARATOR.$metricsName.'.cw.dat');

        return ! is_file($metricsPath);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function writeConfiguredFields(
        PdfCanvas $pdf,
        Quotation $quotation,
        array $config,
        int $pageNumber,
        float $pageHeight,
    ): void {
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

            $this->writeText($pdf, $value, [
                ...$fieldConfig,
                'max_height' => $this->fieldMaxHeight($fieldConfig, $fields, $pageNumber, $pageHeight),
            ], (string) $fieldName);
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
        $items = $quotation->items->take($maxRows)->values();
        $bottomY = $this->itemsBottomY($itemsConfig, $config, $pageNumber, $startY, $rowHeight, $items->count());
        $rowY = $startY;

        foreach ($items as $index => $item) {
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

            $requiredHeight = $rowHeight;

            foreach ($columns as $columnName => $columnConfig) {
                if (! is_array($columnConfig)) {
                    continue;
                }

                $requiredHeight = max(
                    $requiredHeight,
                    $this->textHeight(
                        $pdf,
                        $values[(string) $columnName] ?? '',
                        $columnConfig,
                        $fontSize,
                    ),
                );
            }

            $remainingRows = $items->count() - $index - 1;
            $availableHeight = $bottomY - $rowY - ($remainingRows * $rowHeight);

            if ($availableHeight < $rowHeight) {
                $this->rememberOverflow('items.description#'.($index + 1), $values['description']);

                continue;
            }

            $actualHeight = min($requiredHeight, max($rowHeight, $availableHeight));

            foreach ($columns as $columnName => $columnConfig) {
                if (! is_array($columnConfig)) {
                    continue;
                }

                $this->writeText($pdf, $values[(string) $columnName] ?? '', [
                    ...$columnConfig,
                    'y' => $rowY,
                    'height' => $actualHeight,
                    'max_height' => $actualHeight,
                    'font_size' => $columnConfig['font_size'] ?? $fontSize,
                ], "items.{$columnName}#".($index + 1));
            }

            $rowY += $actualHeight;
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
        $maxHeight = max((float) ($config['max_height'] ?? $height), 1);
        $fontSize = (float) ($config['font_size'] ?? 10);
        $fontWeight = (string) ($config['font_weight'] ?? '');
        $align = strtoupper((string) ($config['align'] ?? 'L'));

        $bold = in_array($fontWeight, ['bold', '700', '800', '900'], true);
        $italic = in_array((string) ($config['font_style'] ?? ''), ['italic', 'I'], true);
        $style = ($bold ? 'B' : '').($italic ? 'I' : '');
        $family = (string) ($config['font_family'] ?? $this->family);

        $this->drawBox($pdf, $label, $x, $y, $width, $height);

        $pdf->SetFont($family, $style, $fontSize);
        $lineHeight = $this->lineHeight($config, $fontSize);
        $printableValue = $this->text($value);
        $lines = $this->wrappedLines($pdf, $printableValue, $width);
        $maximumLines = max(1, (int) floor($maxHeight / $lineHeight));

        if (count($lines) > $maximumLines) {
            $this->rememberOverflow($label, $value);
            $lines = array_slice($lines, 0, $maximumLines);
            $lines[$maximumLines - 1] = $this->continuedLine($pdf, $lines[$maximumLines - 1], $width);
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($width, $lineHeight, implode("\n", $lines), 0, $align, false);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $fields
     */
    private function fieldMaxHeight(array $config, array $fields, int $pageNumber, float $pageHeight): float
    {
        if (isset($config['max_height'])) {
            return max((float) $config['max_height'], 1);
        }

        if (isset($config['height'])) {
            return max((float) $config['height'], 1);
        }

        $x = (float) ($config['x'] ?? 0);
        $y = (float) ($config['y'] ?? 0);
        $width = (float) ($config['width'] ?? $config['max_width'] ?? 60);
        $nextY = $pageHeight - 10;

        foreach ($fields as $otherConfig) {
            if (! is_array($otherConfig) || (int) ($otherConfig['page'] ?? 1) !== $pageNumber) {
                continue;
            }

            $otherY = (float) ($otherConfig['y'] ?? 0);
            $otherX = (float) ($otherConfig['x'] ?? 0);
            $otherWidth = (float) ($otherConfig['width'] ?? $otherConfig['max_width'] ?? 60);
            $horizontallyOverlaps = $x < $otherX + $otherWidth && $otherX < $x + $width;

            if ($horizontallyOverlaps && $otherY > $y && $otherY < $nextY) {
                $nextY = $otherY;
            }
        }

        return max($nextY - $y - 1, 1);
    }

    /**
     * @param  array<string, mixed>  $itemsConfig
     * @param  array<string, mixed>  $config
     */
    private function itemsBottomY(
        array $itemsConfig,
        array $config,
        int $pageNumber,
        float $startY,
        float $rowHeight,
        int $itemCount,
    ): float {
        if (isset($itemsConfig['bottom_y'])) {
            return (float) $itemsConfig['bottom_y'];
        }

        $bottomY = $startY + (max($itemCount, 1) * $rowHeight);
        $fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];
        $protectedFields = ['subtotal', 'discount', 'tax_amount', 'total_amount', 'notes', 'terms_conditions'];
        $protectedBottomY = null;

        foreach ($fields as $fieldName => $fieldConfig) {
            if (! is_array($fieldConfig) || (int) ($fieldConfig['page'] ?? 1) !== $pageNumber) {
                continue;
            }

            $source = (string) ($fieldConfig['field'] ?? $fieldName);
            $fieldY = (float) ($fieldConfig['y'] ?? 0);

            if (in_array($source, $protectedFields, true) && $fieldY > $startY) {
                $protectedBottomY = $protectedBottomY === null
                    ? $fieldY - 1
                    : min($protectedBottomY, $fieldY - 1);
            }
        }

        return $protectedBottomY ?? $bottomY;
    }

    /** @param array<string, mixed> $config */
    private function textHeight(PdfCanvas $pdf, string $value, array $config, float $defaultFontSize): float
    {
        $fontSize = (float) ($config['font_size'] ?? $defaultFontSize);
        $fontWeight = (string) ($config['font_weight'] ?? '');
        $bold = in_array($fontWeight, ['bold', '700', '800', '900'], true);
        $italic = in_array((string) ($config['font_style'] ?? ''), ['italic', 'I'], true);
        $style = ($bold ? 'B' : '').($italic ? 'I' : '');
        $family = (string) ($config['font_family'] ?? $this->family);
        $width = (float) ($config['width'] ?? $config['max_width'] ?? 60);

        $pdf->SetFont($family, $style, $fontSize);

        return max(count($this->wrappedLines($pdf, $this->text($value), $width)), 1)
            * $this->lineHeight($config, $fontSize);
    }

    /** @param array<string, mixed> $config */
    private function lineHeight(array $config, float $fontSize): float
    {
        return max((float) ($config['line_height'] ?? ($fontSize * 0.4233)), 1);
    }

    /** @return list<string> */
    private function wrappedLines(PdfCanvas $pdf, string $value, float $width): array
    {
        $lines = [];

        foreach (preg_split('/\R/u', $value) ?: [''] as $paragraph) {
            $words = preg_split('/\s+/u', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($words === []) {
                $lines[] = '';

                continue;
            }

            $line = '';

            foreach ($words as $word) {
                $candidate = $line === '' ? $word : $line.' '.$word;

                if ($line === '' || $pdf->GetStringWidth($candidate) <= $width) {
                    $line = $candidate;

                    continue;
                }

                $lines[] = $line;
                $line = $word;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function continuedLine(PdfCanvas $pdf, string $line, float $width): string
    {
        $marker = ' ... (continued)';
        $line = rtrim($line);

        while ($line !== '' && $pdf->GetStringWidth($line.$marker) > $width) {
            $line = mb_substr($line, 0, -1);
        }

        return rtrim($line).$marker;
    }

    private function rememberOverflow(string $label, string $value): void
    {
        if ($label === '' || $value === '') {
            return;
        }

        $key = $label.':'.hash('sha256', $value);
        $this->overflowSections[$key] = [
            'label' => $this->overflowLabel($label),
            'value' => $value,
        ];
    }

    private function overflowLabel(string $label): string
    {
        if (preg_match('/^items\.([^#]+)#(\d+)$/', $label, $matches) === 1) {
            return 'Item '.$matches[2].' — '.str($matches[1])->replace('_', ' ')->title();
        }

        return str($label)->replace('_', ' ')->title()->toString();
    }

    private function writeOverflowPages(PdfCanvas $pdf, Quotation $quotation): void
    {
        if ($this->overflowSections === []) {
            return;
        }

        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage('P', 'A4');
        $pdf->SetFont($this->family, 'B', 14);
        $pdf->MultiCell(180, 7, $this->text($quotation->quotation_no.' — Continued details'));
        $pdf->Ln(3);

        foreach ($this->overflowSections as $section) {
            if ($pdf->GetY() > 260) {
                $pdf->AddPage('P', 'A4');
            }

            $pdf->SetFont($this->family, 'B', 10);
            $pdf->MultiCell(180, 5, $this->text($section['label']));
            $pdf->SetFont($this->family, '', 9);
            $pdf->MultiCell(180, 4.5, $this->text($section['value']));
            $pdf->Ln(3);
        }
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
