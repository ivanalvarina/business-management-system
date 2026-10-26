<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->string('employment_type')->default('regular')->after('job_title');
            $table->string('voucher_no', 20)->nullable()->unique()->after('employment_type');
        });

        DB::table('payroll_items')
            ->whereIn(
                'employee_id',
                DB::table('employees')
                    ->select('id')
                    ->where('employment_type', 'contractual'),
            )
            ->update(['employment_type' => 'contractual']);

        $lastNumbers = [];
        $items = DB::table('payroll_items')
            ->join('payrolls', 'payrolls.id', '=', 'payroll_items.payroll_id')
            ->where('payroll_items.employment_type', 'contractual')
            ->where('payrolls.status', 'finalized')
            ->whereNull('payroll_items.voucher_no')
            ->orderBy('payrolls.pay_date')
            ->orderBy('payroll_items.id')
            ->get(['payroll_items.id', 'payrolls.pay_date']);

        foreach ($items as $item) {
            $year = (int) substr((string) $item->pay_date, 0, 4);

            if (! array_key_exists($year, $lastNumbers)) {
                $lastNumbers[$year] = (int) (DB::table('document_sequences')
                    ->where('series', 'PV')
                    ->where('year', $year)
                    ->value('last_number') ?? 0);
            }

            $lastNumbers[$year]++;

            DB::table('payroll_items')
                ->where('id', $item->id)
                ->update(['voucher_no' => sprintf('%02d%03d', $year % 100, $lastNumbers[$year])]);
        }

        foreach ($lastNumbers as $year => $lastNumber) {
            $sequence = DB::table('document_sequences')
                ->where('series', 'PV')
                ->where('year', $year);

            if ($sequence->exists()) {
                $sequence->update(['last_number' => $lastNumber, 'updated_at' => now()]);
            } else {
                DB::table('document_sequences')->insert([
                    'series' => 'PV',
                    'year' => $year,
                    'last_number' => $lastNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropUnique(['voucher_no']);
            $table->dropColumn(['employment_type', 'voucher_no']);
        });
    }
};
