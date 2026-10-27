<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkBoardColumnRequest;
use App\Models\WorkBoard;
use App\Models\WorkBoardColumn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class WorkBoardColumnController extends Controller
{
    public function store(StoreWorkBoardColumnRequest $request, WorkBoard $workBoard): RedirectResponse
    {
        $workBoard->columns()->create([
            ...$request->validated(),
            'position' => ((int) $workBoard->columns()->max('position')) + 1,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Column added.')]);

        return back();
    }

    public function destroy(WorkBoard $workBoard, WorkBoardColumn $column): RedirectResponse
    {
        Gate::authorize('update', $workBoard);
        abort_if($column->tasks()->exists(), 409, __('Move or delete the tasks in this column before deleting it.'));
        abort_if($workBoard->columns()->count() <= 1, 409, __('A board must have at least one column.'));

        $column->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Column deleted.')]);

        return back();
    }
}
