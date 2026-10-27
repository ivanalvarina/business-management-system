<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveWorkTaskRequest;
use App\Models\WorkBoard;
use App\Models\WorkBoardColumn;
use App\Models\WorkTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WorkTaskMoveController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(MoveWorkTaskRequest $request, WorkBoard $workBoard, WorkTask $workTask): RedirectResponse
    {
        abort_unless($workTask->work_board_id === $workBoard->id, 404);

        DB::transaction(function () use ($request, $workTask, $workBoard): void {
            $column = WorkBoardColumn::query()
                ->whereBelongsTo($workBoard, 'board')
                ->lockForUpdate()
                ->findOrFail($request->integer('work_board_column_id'));

            $workTask->update([
                'work_board_column_id' => $column->id,
                'position' => ((int) $column->tasks()->max('position')) + 1,
                'completed_at' => $column->is_done ? ($workTask->completed_at ?? now()) : null,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task moved.')]);

        return back();
    }
}
