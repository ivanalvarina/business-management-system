<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskLabelRequest;
use App\Models\WorkBoard;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TaskLabelController extends Controller
{
    public function store(StoreTaskLabelRequest $request, WorkBoard $workBoard): RedirectResponse
    {
        $workBoard->labels()->create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Label added.')]);

        return back();
    }
}
