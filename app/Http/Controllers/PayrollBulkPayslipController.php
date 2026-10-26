<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Support\PayrollPayslipPdfRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PayrollBulkPayslipController extends Controller
{
    public function __invoke(Payroll $payroll, PayrollPayslipPdfRenderer $renderer): Response
    {
        $payroll->load(['company', 'items']);
        $filename = Str::slug(
            $payroll->company->company_code.'-'.$payroll->period_start->format('Y-m-d').'-'.$payroll->period_end->format('Y-m-d'),
        ).'-payslips.pdf';

        return response($renderer->renderPayroll($payroll), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
