<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Support\PayrollPayslipPdfRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PayrollPayslipController extends Controller
{
    public function __invoke(Payroll $payroll, PayrollItem $payrollItem, PayrollPayslipPdfRenderer $renderer): Response
    {
        abort_unless(
            Gate::allows('viewPayslip', $payroll) || Gate::allows('printPayslip', $payroll),
            403,
        );
        abort_unless($payrollItem->payroll_id === $payroll->id, 404);

        $documentType = $payrollItem->employment_type === Employee::EMPLOYMENT_CONTRACTUAL
            ? 'voucher'
            : 'payslip';
        $filename = preg_replace('/[^A-Za-z0-9_-]+/', '-', $payrollItem->employee_number).'-'.$documentType.'.pdf';

        return response($renderer->render($payrollItem), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
