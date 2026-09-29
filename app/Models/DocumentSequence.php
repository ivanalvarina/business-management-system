<?php

namespace App\Models;

use Database\Factories\DocumentSequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentSequence extends Model
{
    /** @use HasFactory<DocumentSequenceFactory> */
    use HasFactory;

    protected $fillable = [
        'series',
        'year',
        'last_number',
    ];

    public static function nextQuotationNumber(Carbon|string $quotationDate): string
    {
        $year = Carbon::parse($quotationDate)->year;
        $number = self::nextNumber('QT', $year);

        return sprintf('QT-%d-%06d', $year, $number);
    }

    public static function nextPurchaseOrderNumber(Carbon|string $purchaseOrderDate): string
    {
        $year = Carbon::parse($purchaseOrderDate)->year;
        $number = self::nextNumber('PO', $year);

        return sprintf('PO-%d-%06d', $year, $number);
    }

    public static function nextPurchaseRequestNumber(Carbon|string $requestDate): string
    {
        $year = Carbon::parse($requestDate)->year;
        $number = self::nextNumber('PR', $year);

        return sprintf('PR-%d-%06d', $year, $number);
    }

    public static function nextReceivingReceiptNumber(Carbon|string $receivedDate): string
    {
        $year = Carbon::parse($receivedDate)->year;
        $number = self::nextNumber('RR', $year);

        return sprintf('RR-%d-%06d', $year, $number);
    }

    private static function nextNumber(string $series, int $year): int
    {
        return DB::transaction(function () use ($series, $year): int {
            $sequence = self::query()
                ->where('series', $series)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                try {
                    $sequence = self::create([
                        'series' => $series,
                        'year' => $year,
                        'last_number' => 0,
                    ]);
                } catch (QueryException) {
                    $sequence = self::query()
                        ->where('series', $series)
                        ->where('year', $year)
                        ->lockForUpdate()
                        ->firstOrFail();
                }
            }

            $sequence->increment('last_number');

            return (int) $sequence->refresh()->last_number;
        }, attempts: 5);
    }
}
