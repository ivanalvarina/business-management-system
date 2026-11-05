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
            $sourceColumnId = $workTask->work_board_column_id;
            $column = WorkBoardColumn::query()
                ->whereBelongsTo($workBoard, 'board')
                ->lockForUpdate()
                ->findOrFail($request->integer('work_board_column_id'));
            $tasks = WorkTask::query()
                ->whereBelongsTo($workBoard, 'board')
                ->whereIn('work_board_column_id', [$sourceColumnId, $column->id])
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $movingTask = $tasks->firstWhere('id', $workTask->id) ?? $workTask;
            $destinationTasks = $tasks
                ->where('work_board_column_id', $column->id)
                ->reject(fn (WorkTask $task): bool => $task->is($movingTask))
                ->values();
            $beforeTaskId = $request->filled('before_task_id')
                ? $request->integer('before_task_id')
                : null;
            $insertionIndex = $beforeTaskId === null
                ? $destinationTasks->count()
                : $destinationTasks->search(
                    fn (WorkTask $task): bool => $task->id === $beforeTaskId,
                );

            $destinationTasks->splice(
                $insertionIndex === false ? $destinationTasks->count() : $insertionIndex,
                0,
                [$movingTask],
            );

            $destinationTasks->each(function (WorkTask $task, int $position) use ($column, $movingTask): void {
                $attributes = ['position' => $position];

                if ($task->is($movingTask)) {
                    $attributes['work_board_column_id'] = $column->id;
                    $attributes['completed_at'] = $column->is_done
                        ? ($movingTask->completed_at ?? now())
                        : null;
                }

                $task->update($attributes);
            });

            if ($sourceColumnId !== $column->id) {
                $tasks
                    ->where('work_board_column_id', $sourceColumnId)
                    ->reject(fn (WorkTask $task): bool => $task->is($movingTask))
                    ->values()
                    ->each(fn (WorkTask $task, int $position) => $task->update(['position' => $position]));
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task moved.')]);

        return back();
    }
}
