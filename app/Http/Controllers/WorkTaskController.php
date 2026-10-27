<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkTaskRequest;
use App\Http\Requests\UpdateWorkTaskRequest;
use App\Models\EodAttachment;
use App\Models\EodReport;
use App\Models\WorkBoard;
use App\Models\WorkBoardColumn;
use App\Models\WorkTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class WorkTaskController extends Controller
{
    public function store(StoreWorkTaskRequest $request, WorkBoard $workBoard): RedirectResponse
    {
        $column = $workBoard->columns()->findOrFail($request->integer('work_board_column_id'));
        DB::transaction(function () use ($column, $request, $workBoard): void {
            $requestedAssignee = $request->filled('assigned_to') ? $request->integer('assigned_to') : null;
            $assignedTo = $request->user()->hasRole('Super Admin')
                ? $requestedAssignee
                : $request->user()->id;
            $collaboratorIds = $this->collaboratorIds($request, $assignedTo);

            if ($assignedTo !== null) {
                $workBoard->members()->syncWithoutDetaching([$assignedTo]);
            }

            $workBoard->members()->syncWithoutDetaching($collaboratorIds);

            $task = $workBoard->tasks()->create([
                ...$request->safe()->only(['work_board_column_id', 'assigned_to', 'title', 'description', 'priority', 'due_date']),
                'assigned_to' => $assignedTo,
                'position' => ((int) $column->tasks()->max('position')) + 1,
                'completed_at' => $column->is_done ? now() : null,
                'created_by' => $request->user()->id,
            ]);
            $task->labels()->sync($request->validated('label_ids', []));
            $task->collaborators()->sync($collaboratorIds);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task created.')]);

        return back();
    }

    public function update(UpdateWorkTaskRequest $request, WorkBoard $workBoard, WorkTask $workTask): RedirectResponse
    {
        abort_unless($workTask->work_board_id === $workBoard->id, 404);

        DB::transaction(function () use ($request, $workBoard, $workTask): void {
            $column = WorkBoardColumn::query()
                ->whereBelongsTo($workBoard, 'board')
                ->lockForUpdate()
                ->findOrFail($request->integer('work_board_column_id'));
            $requestedAssignee = $request->filled('assigned_to') ? $request->integer('assigned_to') : null;
            $assignedTo = $request->user()->hasRole('Super Admin')
                ? $requestedAssignee
                : $workTask->assigned_to;
            $collaboratorIds = $this->collaboratorIds($request, $assignedTo);
            $isMoving = $workTask->work_board_column_id !== $column->id;

            if ($assignedTo !== null) {
                $workBoard->members()->syncWithoutDetaching([$assignedTo]);
            }

            $workBoard->members()->syncWithoutDetaching($collaboratorIds);

            $workTask->update([
                ...$request->safe()->only(['work_board_column_id', 'assigned_to', 'title', 'description', 'priority', 'due_date']),
                'assigned_to' => $assignedTo,
                'position' => $isMoving
                    ? ((int) $column->tasks()->max('position')) + 1
                    : $workTask->position,
                'completed_at' => $column->is_done ? ($workTask->completed_at ?? now()) : null,
            ]);
            $workTask->labels()->sync($request->validated('label_ids', []));
            $workTask->collaborators()->sync($collaboratorIds);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task updated.')]);

        return back();
    }

    public function destroy(Request $request, WorkBoard $workBoard, WorkTask $workTask): RedirectResponse
    {
        Gate::authorize('delete', $workTask);
        abort_unless($workTask->work_board_id === $workBoard->id, 404);

        $eodItems = $workTask->eodItems()->with('attachments')->get();
        $reportIds = $eodItems->pluck('eod_report_id')->unique()->values()->all();
        $attachments = $eodItems->pluck('attachments')->flatten();

        DB::transaction(function () use ($reportIds, $workTask): void {
            $workTask->eodItems()->delete();

            EodReport::query()
                ->whereKey($reportIds)
                ->whereDoesntHave('items')
                ->delete();

            $workTask->delete();
        });

        $attachments->each(function (EodAttachment $attachment): void {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task deleted.')]);

        return back();
    }

    /** @return array<int, int> */
    private function collaboratorIds(StoreWorkTaskRequest $request, ?int $assignedTo): array
    {
        $collaboratorIds = [];

        foreach ($request->safe()->array('collaborator_ids') as $userId) {
            if (! is_numeric($userId)) {
                continue;
            }

            $normalizedUserId = (int) $userId;

            if ($normalizedUserId !== $assignedTo) {
                $collaboratorIds[$normalizedUserId] = $normalizedUserId;
            }
        }

        return array_values($collaboratorIds);
    }
}
