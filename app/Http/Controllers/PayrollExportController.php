<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Support\PayrollCsvExporter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollExportController extends Controller
{
    public function __invoke(Payroll $payroll, PayrollCsvExporter $exporter): StreamedResponse
    {
        $payroll->load('company');
        $filename = Str::slug(
            $payroll->company->company_code.'-'.$payroll->period_start->format('Y-m-d').'-'.$payroll->period_end->format('Y-m-d'),
        ).'-payroll-report.csv';

        return response()->streamDownload(function () use ($exporter, $payroll): void {
            $stream = fopen('php://output', 'w');

            if ($stream === false) {
                throw new \RuntimeException('Unable to open the payroll CSV output stream.');
            }

            $exporter->write($stream, $payroll);
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
