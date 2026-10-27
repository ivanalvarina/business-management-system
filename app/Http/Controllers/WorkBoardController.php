<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkBoardRequest;
use App\Http\Requests\UpdateWorkBoardRequest;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkBoard;
use App\Models\WorkTask;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkBoardController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', WorkBoard::class);
        $company = $this->selectedCompany($request, $request->user());
        $user = $request->user();

        $boards = WorkBoard::query()
            ->with('company:id,company_name')
            ->withCount(['members', 'tasks'])
            ->when($company !== null, fn (Builder $query) => $query->whereBelongsTo($company))
            ->when($company === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(! $user->can('work-boards.view-all'), fn (Builder $query) => $query->where(function (Builder $query) use ($user): void {
                $query->where('created_by', $user->id)
                    ->orWhereHas('members', fn (Builder $query) => $query->whereKey($user->id));
            }))
            ->orderByRaw("status = 'archived'")
            ->orderBy('name')
            ->get();

        return Inertia::render('work-boards/Index', [
            'company' => $company?->only(['id', 'company_code', 'company_name']),
            'boards' => $boards,
            'users' => $company === null ? [] : $company->users()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['users.id', 'name', 'email']),
            'can' => ['create' => $user->can('create', WorkBoard::class)],
        ]);
    }

    public function store(StoreWorkBoardRequest $request, AuditLogger $audit): RedirectResponse
    {
        $board = DB::transaction(function () use ($request): WorkBoard {
            $board = WorkBoard::create([
                ...$request->safe()->only(['company_id', 'name', 'description', 'color']),
                'status' => WorkBoard::STATUS_ACTIVE,
                'created_by' => $request->user()->id,
            ]);
            $memberIds = $request->safe()->array('member_ids');
            $memberIds[] = $request->user()->id;
            $board->members()->sync(array_values(array_unique($memberIds)));

            collect([
                ['name' => 'To Do', 'color' => 'slate', 'is_done' => false],
                ['name' => 'In Progress', 'color' => 'blue', 'is_done' => false],
                ['name' => 'Blocked', 'color' => 'rose', 'is_done' => false],
                ['name' => 'Done', 'color' => 'emerald', 'is_done' => true],
            ])->each(fn (array $column, int $position) => $board->columns()->create([...$column, 'position' => $position]));

            return $board;
        });

        $audit->record('work-boards', 'created', $board, newValues: $board->only(['company_id', 'name', 'status']), request: $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Board created.')]);

        return to_route('work-boards.show', $board);
    }

    public function show(Request $request, WorkBoard $workBoard): Response
    {
        Gate::authorize('view', $workBoard);
        $canViewAllEod = $request->user()->can('eod-reports.view-all');

        $workBoard->load([
            'company:id,company_code,company_name',
            'members:id,name,email',
            'labels:id,work_board_id,name,color',
            'columns.tasks' => fn ($query) => $query
                ->with([
                    'assignee:id,name,email',
                    'labels:id,name,color',
                    'eodItems' => fn ($query) => $query
                        ->whereHas('report', fn ($query) => $query
                            ->when(! $canViewAllEod, fn ($query) => $query->where('user_id', $request->user()->id))
                            ->whereDate('work_date', '>=', now()->subDays(30)))
                        ->with(['report.user:id,name', 'attachments'])
                        ->latest(),
                ]),
        ]);

        return Inertia::render('work-boards/Show', [
            'board' => $workBoard,
            'priorities' => WorkTask::priorities(),
            'availableUsers' => $workBoard->company->users()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['users.id', 'name', 'email']),
            'today' => now()->toDateString(),
            'can' => [
                'editBoard' => $request->user()->can('update', $workBoard),
                'deleteBoard' => $request->user()->can('delete', $workBoard),
                'createTask' => $request->user()->can('work-tasks.create'),
                'editTask' => $request->user()->can('work-tasks.edit'),
                'deleteTask' => $request->user()->can('work-tasks.delete'),
                'moveTask' => $request->user()->can('work-tasks.move'),
                'assignTask' => $request->user()->can('work-tasks.assign'),
                'submitEod' => $request->user()->can('eod-reports.submit-own'),
            ],
            'currentUserId' => $request->user()->id,
        ]);
    }

    public function update(UpdateWorkBoardRequest $request, WorkBoard $workBoard, AuditLogger $audit): RedirectResponse
    {
        $oldValues = $workBoard->only(['name', 'description', 'color', 'status']);
        DB::transaction(function () use ($request, $workBoard): void {
            $workBoard->update($request->safe()->only(['name', 'description', 'color', 'status']));
            $memberIds = $request->safe()->array('member_ids');
            $memberIds[] = $workBoard->created_by;
            $workBoard->members()->sync(array_values(array_unique($memberIds)));
        });
        $audit->recordChanges('work-boards', 'updated', $workBoard, $oldValues, $workBoard->only(['name', 'description', 'color', 'status']), request: $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Board settings saved.')]);

        return back();
    }

    public function destroy(Request $request, WorkBoard $workBoard, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $workBoard);
        abort_if(
            $workBoard->tasks()->whereHas('eodItems')->exists(),
            409,
            __('Boards with EOD history cannot be deleted.'),
        );
        $companyId = $workBoard->company_id;
        $oldValues = $workBoard->only(['company_id', 'name', 'status']);
        $workBoard->delete();
        $audit->record('work-boards', 'deleted', $workBoard, oldValues: $oldValues, request: $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Board deleted.')]);

        return to_route('work-boards.index', ['company_id' => $companyId]);
    }

    private function selectedCompany(Request $request, User $user): ?Company
    {
        return Company::query()
            ->active()
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('users', fn (Builder $query) => $query->whereKey($user->id)))
            ->find((int) $request->session()->get('current_company_id'));
    }
}
