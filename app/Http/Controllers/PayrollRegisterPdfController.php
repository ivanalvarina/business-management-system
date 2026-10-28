<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Support\PayrollRegisterPdfRenderer;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PayrollRegisterPdfController extends Controller
{
    public function __invoke(Payroll $payroll, PayrollRegisterPdfRenderer $renderer): Response
    {
        $payroll->load([
            'company',
            'items' => fn (HasMany $query) => $query->orderBy('employee_name')->orderBy('id'),
        ]);
        $filename = Str::slug(
            $payroll->company->company_code.'-'.$payroll->period_start->format('Y-m-d').'-'.$payroll->period_end->format('Y-m-d'),
        ).'-payroll-register.pdf';

        return response($renderer->render($payroll), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
