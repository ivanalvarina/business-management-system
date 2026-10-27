<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEodReportRequest;
use App\Models\EodReport;
use App\Models\WorkBoard;
use App\Models\WorkTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EodReportController extends Controller
{
    public function store(StoreEodReportRequest $request, WorkBoard $workBoard, WorkTask $workTask): RedirectResponse
    {
        abort_unless($workTask->work_board_id === $workBoard->id, 404);
        $workDate = $request->date('work_date');
        abort_if($workDate === null, 422, __('A valid work date is required.'));

        DB::transaction(function () use ($request, $workBoard, $workTask, $workDate): void {
            $report = EodReport::updateOrCreate(
                [
                    'company_id' => $workBoard->company_id,
                    'user_id' => $request->user()->id,
                    'work_date' => $workDate->toDateString(),
                ],
                [
                    ...$request->safe()->only(['summary', 'blockers', 'next_plan']),
                    'status' => EodReport::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ],
            );

            $item = $report->items()->updateOrCreate(
                ['work_task_id' => $workTask->id],
                $request->safe()->only(['accomplishment', 'minutes_spent']),
            );

            collect($request->file('screenshots', []))->each(function (UploadedFile $screenshot) use ($item, $workBoard, $workDate): void {
                $path = $screenshot->store("eod-screenshots/{$workBoard->company_id}/{$workDate->format('Y/m')}", 'local');

                $item->attachments()->create([
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $screenshot->getClientOriginalName(),
                    'mime_type' => $screenshot->getMimeType() ?? 'application/octet-stream',
                    'size' => $screenshot->getSize(),
                ]);
            });
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('EOD update submitted.')]);

        return back();
    }
}
