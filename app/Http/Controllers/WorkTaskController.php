<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkTaskRequest;
use App\Models\WorkBoard;
use App\Models\WorkTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class WorkTaskController extends Controller
{
    public function store(StoreWorkTaskRequest $request, WorkBoard $workBoard): RedirectResponse
    {
        $column = $workBoard->columns()->findOrFail($request->integer('work_board_column_id'));
        DB::transaction(function () use ($column, $request, $workBoard): void {
            $assignedTo = $request->integer('assigned_to');

            if ($assignedTo > 0) {
                $workBoard->members()->syncWithoutDetaching([$assignedTo]);
            }

            $task = $workBoard->tasks()->create([
                ...$request->safe()->only(['work_board_column_id', 'assigned_to', 'title', 'description', 'priority', 'due_date']),
                'position' => ((int) $column->tasks()->max('position')) + 1,
                'completed_at' => $column->is_done ? now() : null,
                'created_by' => $request->user()->id,
            ]);
            $task->labels()->sync($request->validated('label_ids', []));
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task created.')]);

        return back();
    }

    public function destroy(Request $request, WorkBoard $workBoard, WorkTask $workTask): RedirectResponse
    {
        Gate::authorize('delete', $workTask);
        abort_unless($workTask->work_board_id === $workBoard->id, 404);
        abort_if($workTask->eodItems()->exists(), 409, __('Tasks with EOD history cannot be deleted.'));
        $workTask->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task deleted.')]);

        return back();
    }
}
