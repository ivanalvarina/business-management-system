<?php

namespace App\Support;

use setasign\Fpdi\Tfpdf\Fpdi;

class PdfCanvas extends Fpdi
{
    /** cMargin is protected in FPDF/tFPDF; expose it so text boxes align exactly. */
    public function setCellMargin(float $margin): void
    {
        $this->cMargin = $margin;
    }
}
