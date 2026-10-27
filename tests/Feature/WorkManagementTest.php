<?php

use App\Models\Company;
use App\Models\EodAttachment;
use App\Models\EodReport;
use App\Models\TaskLabel;
use App\Models\User;
use App\Models\WorkBoard;
use App\Models\WorkBoardColumn;
use App\Models\WorkTask;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

test('authorized users can create a board with members and starter columns', function () {
    $user = userWithPermissions(['work-boards.create', 'work-boards.view']);
    $company = Company::factory()->create();
    $member = User::factory()->create();
    $user->companies()->attach($company);
    $member->companies()->attach($company);

    $this->actingAs($user)->post(route('work-boards.store'), [
        'company_id' => $company->id,
        'name' => 'Payroll System',
        'description' => 'Daily development board',
        'color' => 'blue',
        'member_ids' => [$member->id],
    ])->assertRedirect();

    $board = WorkBoard::query()->sole();

    expect($board->name)->toBe('Payroll System')
        ->and($board->members()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$user->id, $member->id])->sort()->values()->all())
        ->and($board->columns()->pluck('name')->all())
        ->toBe(['To Do', 'In Progress', 'Blocked', 'Done']);
});

test('board members can view their board but unrelated users cannot', function () {
    $member = userWithPermissions('work-boards.view');
    $outsider = userWithPermissions('work-boards.view');
    $company = Company::factory()->create();
    $member->companies()->attach($company);
    $outsider->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->create();
    $board->members()->attach($member);

    $this->actingAs($member)->get(route('work-boards.show', $board))->assertOk();
    $this->actingAs($outsider)->get(route('work-boards.show', $board))->assertForbidden();
});

test('authorized users can assign urgent labeled tasks to active company users', function () {
    $user = userWithPermissions(['work-boards.view', 'work-tasks.create', 'work-tasks.assign']);
    $user->assignRole(Role::findOrCreate('Super Admin', 'web'));
    $company = Company::factory()->create();
    $assignee = User::factory()->create();
    $user->companies()->attach($company);
    $assignee->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach($user);
    $column = WorkBoardColumn::factory()->for($board, 'board')->create(['name' => 'To Do']);
    $label = TaskLabel::factory()->for($board, 'board')->create(['name' => 'Business']);

    $this->actingAs($user)->post(route('work-tasks.store', $board), [
        'work_board_column_id' => $column->id,
        'assigned_to' => $assignee->id,
        'title' => 'Fix client payroll issue',
        'description' => 'Needs attention today.',
        'priority' => WorkTask::PRIORITY_URGENT,
        'due_date' => '2026-10-27',
        'label_ids' => [$label->id],
    ])->assertRedirect();

    $task = WorkTask::query()->sole();

    expect($task->assigned_to)->toBe($assignee->id)
        ->and($task->priority)->toBe(WorkTask::PRIORITY_URGENT)
        ->and($task->labels()->sole()->id)->toBe($label->id)
        ->and($board->members()->whereKey($assignee->id)->exists())->toBeTrue();
});

test('regular users are automatically assigned to tasks they create', function () {
    $user = userWithPermissions(['work-boards.view', 'work-tasks.create', 'work-tasks.assign']);
    $otherUser = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $otherUser->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach($user);
    $column = WorkBoardColumn::factory()->for($board, 'board')->create();

    $this->actingAs($user)->post(route('work-tasks.store', $board), [
        'work_board_column_id' => $column->id,
        'assigned_to' => $otherUser->id,
        'collaborator_ids' => [$user->id, $otherUser->id],
        'title' => 'My own task',
        'priority' => WorkTask::PRIORITY_NORMAL,
    ])->assertRedirect();

    $task = WorkTask::query()->sole();

    expect($task->created_by)->toBe($user->id)
        ->and($task->assigned_to)->toBe($user->id)
        ->and($task->collaborators()->pluck('users.id')->all())->toBe([$otherUser->id])
        ->and($board->members()->whereKey($otherUser->id)->exists())->toBeTrue();
});

test('regular users see created assigned and collaborating tasks with permitted EOD updates', function () {
    $creator = userWithPermissions('work-boards.view');
    $assignee = User::factory()->create();
    $otherCreator = User::factory()->create();
    $company = Company::factory()->create();
    $creator->companies()->attach($company);
    $assignee->companies()->attach($company);
    $otherCreator->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($creator, 'creator')->create();
    $board->members()->attach([$creator->id, $assignee->id, $otherCreator->id]);
    $column = WorkBoardColumn::factory()->for($board, 'board')->create();
    $ownTask = WorkTask::factory()->for($board, 'board')->for($column, 'column')->for($creator, 'creator')->create([
        'assigned_to' => $assignee->id,
    ]);
    $otherTask = WorkTask::factory()->for($board, 'board')->for($column, 'column')->for($otherCreator, 'creator')->create([
        'assigned_to' => $otherCreator->id,
    ]);
    $collaboratingTask = WorkTask::factory()->for($board, 'board')->for($column, 'column')->for($otherCreator, 'creator')->create([
        'assigned_to' => $assignee->id,
    ]);
    $collaboratingTask->collaborators()->attach($creator);
    $report = EodReport::factory()->for($company)->for($assignee)->create([
        'work_date' => now()->toDateString(),
    ]);
    $eodItem = $report->items()->create([
        'work_task_id' => $ownTask->id,
        'accomplishment' => 'Completed the assigned work.',
    ]);

    $this->actingAs($creator)
        ->get(route('work-boards.show', $board))
        ->assertInertia(fn ($page) => $page
            ->where('isSuperAdmin', false)
            ->where('can.assignTask', false)
            ->where('board.columns.0.tasks', fn ($tasks) => collect($tasks)->contains('id', $ownTask->id)
                && collect($tasks)->contains('id', $collaboratingTask->id)
                && ! collect($tasks)->contains('id', $otherTask->id)
                && collect($tasks)->firstWhere('id', $ownTask->id)['eod_items'][0]['id'] === $eodItem->id)
        );
});

test('company users are included in the board assignee options before becoming board members', function () {
    $user = userWithPermissions('work-boards.view');
    $companyUser = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $companyUser->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach($user);

    $this->actingAs($user)
        ->get(route('work-boards.show', $board))
        ->assertInertia(fn ($page) => $page
            ->where('availableUsers', fn ($users) => collect($users)->contains('id', $companyUser->id))
        );
});

test('tasks reject assignees without active access to the board company', function () {
    $user = userWithPermissions(['work-boards.view', 'work-tasks.create', 'work-tasks.assign']);
    $user->assignRole(Role::findOrCreate('Super Admin', 'web'));
    $outsider = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach($user);
    $column = WorkBoardColumn::factory()->for($board, 'board')->create();

    $this->actingAs($user)->post(route('work-tasks.store', $board), [
        'work_board_column_id' => $column->id,
        'assigned_to' => $outsider->id,
        'title' => 'Invalid assignee',
        'priority' => WorkTask::PRIORITY_NORMAL,
    ])->assertSessionHasErrors('assigned_to');

    expect(WorkTask::query()->doesntExist())->toBeTrue();
});

test('tasks reject collaborators without active access to the board company', function () {
    $user = userWithPermissions(['work-boards.view', 'work-tasks.create']);
    $outsider = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach($user);
    $column = WorkBoardColumn::factory()->for($board, 'board')->create();

    $this->actingAs($user)->post(route('work-tasks.store', $board), [
        'work_board_column_id' => $column->id,
        'collaborator_ids' => [$outsider->id],
        'title' => 'Invalid collaborator',
        'priority' => WorkTask::PRIORITY_NORMAL,
    ])->assertSessionHasErrors('collaborator_ids');

    expect(WorkTask::query()->doesntExist())->toBeTrue();
});

test('authorized users can update task details assignment column and labels', function () {
    $user = userWithPermissions(['work-boards.view', 'work-tasks.edit', 'work-tasks.assign']);
    $user->assignRole(Role::findOrCreate('Super Admin', 'web'));
    $assignee = User::factory()->create();
    $collaborator = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $assignee->companies()->attach($company);
    $collaborator->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach($user);
    $todo = WorkBoardColumn::factory()->for($board, 'board')->create(['name' => 'To Do']);
    $done = WorkBoardColumn::factory()->for($board, 'board')->create(['name' => 'Done', 'is_done' => true]);
    $oldLabel = TaskLabel::factory()->for($board, 'board')->create(['name' => 'Old']);
    $newLabel = TaskLabel::factory()->for($board, 'board')->create(['name' => 'Urgent']);
    $task = WorkTask::factory()->for($board, 'board')->for($todo, 'column')->for($user, 'creator')->create();
    $task->labels()->attach($oldLabel);

    $this->actingAs($user)->patch(route('work-tasks.update', [$board, $task]), [
        'work_board_column_id' => $done->id,
        'assigned_to' => $assignee->id,
        'title' => 'Updated task title',
        'description' => 'Updated task description',
        'priority' => WorkTask::PRIORITY_HIGH,
        'due_date' => '2026-10-30',
        'label_ids' => [$newLabel->id],
        'collaborator_ids' => [$assignee->id, $collaborator->id],
    ])->assertRedirect();

    $task->refresh();

    expect($task->title)->toBe('Updated task title')
        ->and($task->work_board_column_id)->toBe($done->id)
        ->and($task->assigned_to)->toBe($assignee->id)
        ->and($task->priority)->toBe(WorkTask::PRIORITY_HIGH)
        ->and($task->completed_at)->not->toBeNull()
        ->and($task->labels()->sole()->id)->toBe($newLabel->id)
        ->and($task->collaborators()->pluck('users.id')->all())->toBe([$collaborator->id])
        ->and($board->members()->whereKey($assignee->id)->exists())->toBeTrue();
});

test('users without task edit permission cannot update tasks', function () {
    $user = userWithPermissions('work-boards.view');
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach($user);
    $column = WorkBoardColumn::factory()->for($board, 'board')->create();
    $task = WorkTask::factory()->for($board, 'board')->for($column, 'column')->for($user, 'creator')->create();

    $this->actingAs($user)->patch(route('work-tasks.update', [$board, $task]), [
        'work_board_column_id' => $column->id,
        'title' => 'Unauthorized change',
        'priority' => WorkTask::PRIORITY_NORMAL,
    ])->assertForbidden();

    expect($task->fresh()->title)->not->toBe('Unauthorized change');
});

test('tasks reject columns and labels from another board', function () {
    $user = userWithPermissions(['work-boards.view', 'work-tasks.create']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $otherBoard = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach($user);
    $otherColumn = WorkBoardColumn::factory()->for($otherBoard, 'board')->create();
    $otherLabel = TaskLabel::factory()->for($otherBoard, 'board')->create();

    $this->actingAs($user)->post(route('work-tasks.store', $board), [
        'work_board_column_id' => $otherColumn->id,
        'title' => 'Invalid task',
        'priority' => WorkTask::PRIORITY_NORMAL,
        'label_ids' => [$otherLabel->id],
    ])->assertSessionHasErrors(['work_board_column_id', 'label_ids']);

    expect(WorkTask::query()->doesntExist())->toBeTrue();
});

test('authorized users can delete an empty board column', function () {
    $user = userWithPermissions(['work-boards.view', 'work-boards.edit']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach($user);
    WorkBoardColumn::factory()->for($board, 'board')->create(['name' => 'To Do']);
    $emptyColumn = WorkBoardColumn::factory()->for($board, 'board')->create(['name' => 'Blocked']);

    $this->actingAs($user)
        ->delete(route('work-board-columns.destroy', [$board, $emptyColumn]))
        ->assertRedirect();

    expect($emptyColumn->fresh())->toBeNull();
});

test('columns containing tasks cannot be deleted', function () {
    $user = userWithPermissions(['work-boards.view', 'work-boards.edit']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach($user);
    $column = WorkBoardColumn::factory()->for($board, 'board')->create();
    WorkBoardColumn::factory()->for($board, 'board')->create(['name' => 'Done']);
    WorkTask::factory()->for($board, 'board')->for($column, 'column')->for($user, 'creator')->create();

    $this->actingAs($user)
        ->delete(route('work-board-columns.destroy', [$board, $column]))
        ->assertConflict();

    expect($column->fresh())->not->toBeNull();
});

test('the last board column cannot be deleted', function () {
    $user = userWithPermissions(['work-boards.view', 'work-boards.edit']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach($user);
    $column = WorkBoardColumn::factory()->for($board, 'board')->create();

    $this->actingAs($user)
        ->delete(route('work-board-columns.destroy', [$board, $column]))
        ->assertConflict();

    expect($column->fresh())->not->toBeNull();
});

test('moving a task into and out of a done column updates completion time', function () {
    $user = userWithPermissions(['work-boards.view', 'work-tasks.move']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach($user);
    $todo = WorkBoardColumn::factory()->for($board, 'board')->create(['name' => 'To Do']);
    $done = WorkBoardColumn::factory()->for($board, 'board')->create(['name' => 'Done', 'is_done' => true]);
    $task = WorkTask::factory()->for($board, 'board')->for($todo, 'column')->for($user, 'creator')->create();

    $this->actingAs($user)->patch(route('work-tasks.move', [$board, $task]), [
        'work_board_column_id' => $done->id,
    ])->assertRedirect();

    expect($task->fresh()->completed_at)->not->toBeNull();

    $this->actingAs($user)->patch(route('work-tasks.move', [$board, $task]), [
        'work_board_column_id' => $todo->id,
    ])->assertRedirect();

    expect($task->fresh()->completed_at)->toBeNull();
});

test('collaborators with move permission can move their task', function () {
    $owner = userWithPermissions('work-boards.view');
    $collaborator = userWithPermissions(['work-boards.view', 'work-tasks.move']);
    $company = Company::factory()->create();
    $owner->companies()->attach($company);
    $collaborator->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($owner, 'creator')->create();
    $board->members()->attach([$owner->id, $collaborator->id]);
    $todo = WorkBoardColumn::factory()->for($board, 'board')->create(['name' => 'To Do']);
    $inProgress = WorkBoardColumn::factory()->for($board, 'board')->create(['name' => 'In Progress']);
    $task = WorkTask::factory()->for($board, 'board')->for($todo, 'column')->for($owner, 'creator')->create([
        'assigned_to' => $owner->id,
    ]);
    $task->collaborators()->attach($collaborator);

    $this->actingAs($collaborator)->patch(route('work-tasks.move', [$board, $task]), [
        'work_board_column_id' => $inProgress->id,
    ])->assertRedirect();

    expect($task->fresh()->work_board_column_id)->toBe($inProgress->id);
});

test('collaborators cannot edit task details without being the owner or creator', function () {
    $owner = userWithPermissions('work-boards.view');
    $collaborator = userWithPermissions(['work-boards.view', 'work-tasks.edit']);
    $company = Company::factory()->create();
    $owner->companies()->attach($company);
    $collaborator->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($owner, 'creator')->create();
    $board->members()->attach([$owner->id, $collaborator->id]);
    $column = WorkBoardColumn::factory()->for($board, 'board')->create();
    $task = WorkTask::factory()->for($board, 'board')->for($column, 'column')->for($owner, 'creator')->create([
        'assigned_to' => $owner->id,
        'title' => 'Owner controlled task',
    ]);
    $task->collaborators()->attach($collaborator);

    $this->actingAs($collaborator)->patch(route('work-tasks.update', [$board, $task]), [
        'work_board_column_id' => $column->id,
        'title' => 'Unauthorized edit',
        'priority' => WorkTask::PRIORITY_NORMAL,
    ])->assertForbidden();

    expect($task->fresh()->title)->toBe('Owner controlled task');
});

test('an assignee can submit one daily EOD item with private screenshots', function () {
    Storage::fake('local');
    $user = userWithPermissions(['work-boards.view', 'eod-reports.submit-own', 'eod-reports.view-own']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach($user);
    $column = WorkBoardColumn::factory()->for($board, 'board')->create();
    $task = WorkTask::factory()->for($board, 'board')->for($column, 'column')->for($user, 'creator')->create([
        'assigned_to' => $user->id,
    ]);

    $this->actingAs($user)->post(route('eod-reports.store', [$board, $task]), [
        'work_date' => '2026-10-27',
        'accomplishment' => 'Completed payroll PDF layout.',
        'minutes_spent' => 180,
        'summary' => 'Payroll work',
        'blockers' => 'Waiting for sample data.',
        'next_plan' => 'Add bulk printing.',
        'screenshots' => [UploadedFile::fake()->image('payroll.png')],
    ])->assertRedirect();

    $report = EodReport::query()->sole();
    $item = $report->items()->sole();
    $attachment = $item->attachments()->sole();

    expect($report->user_id)->toBe($user->id)
        ->and($item->accomplishment)->toBe('Completed payroll PDF layout.')
        ->and($attachment->disk)->toBe('local');
    Storage::disk('local')->assertExists($attachment->path);
});

test('a collaborator can submit their own EOD update', function () {
    $owner = userWithPermissions('work-boards.view');
    $collaborator = userWithPermissions(['work-boards.view', 'eod-reports.submit-own', 'eod-reports.view-own']);
    $company = Company::factory()->create();
    $owner->companies()->attach($company);
    $collaborator->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($owner, 'creator')->create();
    $board->members()->attach([$owner->id, $collaborator->id]);
    $column = WorkBoardColumn::factory()->for($board, 'board')->create();
    $task = WorkTask::factory()->for($board, 'board')->for($column, 'column')->for($owner, 'creator')->create([
        'assigned_to' => $owner->id,
    ]);
    $task->collaborators()->attach($collaborator);

    $this->actingAs($collaborator)->post(route('eod-reports.store', [$board, $task]), [
        'work_date' => '2026-10-27',
        'accomplishment' => 'Completed the API integration.',
    ])->assertRedirect();

    $report = EodReport::query()->sole();

    expect($report->user_id)->toBe($collaborator->id)
        ->and($report->items()->sole()->work_task_id)->toBe($task->id);
});

test('users cannot submit EOD updates for tasks assigned to someone else', function () {
    $user = userWithPermissions(['work-boards.view', 'eod-reports.submit-own']);
    $assignee = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $assignee->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach([$user->id, $assignee->id]);
    $column = WorkBoardColumn::factory()->for($board, 'board')->create();
    $task = WorkTask::factory()->for($board, 'board')->for($column, 'column')->for($user, 'creator')->create([
        'assigned_to' => $assignee->id,
    ]);

    $this->actingAs($user)->post(route('eod-reports.store', [$board, $task]), [
        'work_date' => '2026-10-27',
        'accomplishment' => 'Not my work.',
    ])->assertForbidden();

    expect(EodReport::query()->doesntExist())->toBeTrue();
});

test('private EOD screenshots are visible to their owner and task creator', function () {
    Storage::fake('local');
    $owner = userWithPermissions(['work-boards.view', 'eod-reports.view-own']);
    $creator = userWithPermissions('work-boards.view');
    $outsider = userWithPermissions(['work-boards.view', 'eod-reports.view-own']);
    $company = Company::factory()->create();
    $owner->companies()->attach($company);
    $creator->companies()->attach($company);
    $outsider->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($creator, 'creator')->create();
    $board->members()->attach([$owner->id, $creator->id, $outsider->id]);
    $column = WorkBoardColumn::factory()->for($board, 'board')->create();
    $task = WorkTask::factory()->for($board, 'board')->for($column, 'column')->for($creator, 'creator')->create(['assigned_to' => $owner->id]);
    $report = EodReport::factory()->for($company)->for($owner)->create(['work_date' => '2026-10-27']);
    $item = $report->items()->create(['work_task_id' => $task->id, 'accomplishment' => 'Done']);
    Storage::disk('local')->put('eod-screenshots/private.png', 'image');
    $attachment = EodAttachment::factory()->for($item, 'item')->create(['path' => 'eod-screenshots/private.png']);

    $this->actingAs($owner)->get(route('eod-attachments.show', $attachment))->assertOk();
    $this->actingAs($creator)->get(route('eod-attachments.show', $attachment))->assertOk();
    $this->actingAs($outsider)->get(route('eod-attachments.show', $attachment))->assertForbidden();
});

test('tasks with EOD history delete their reports and screenshot files', function () {
    Storage::fake('local');
    $user = userWithPermissions(['work-boards.view', 'work-boards.delete', 'work-tasks.delete']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach($user);
    $column = WorkBoardColumn::factory()->for($board, 'board')->create();
    $task = WorkTask::factory()->for($board, 'board')->for($column, 'column')->for($user, 'creator')->create([
        'assigned_to' => $user->id,
    ]);
    $report = EodReport::factory()->for($company)->for($user)->create();
    $item = $report->items()->create(['work_task_id' => $task->id, 'accomplishment' => 'Completed work.']);
    Storage::disk('local')->put('eod-screenshots/delete-me.png', 'image');
    $attachment = EodAttachment::factory()->for($item, 'item')->create([
        'disk' => 'local',
        'path' => 'eod-screenshots/delete-me.png',
    ]);

    $this->actingAs($user)
        ->delete(route('work-tasks.destroy', [$board, $task]))
        ->assertRedirect()
        ->assertSessionHas(
            'inertia.flash_data.toast.message',
            'Task deleted.',
        );

    expect($task->fresh())->toBeNull()
        ->and($item->fresh())->toBeNull()
        ->and($attachment->fresh())->toBeNull()
        ->and($report->fresh())->toBeNull();
    Storage::disk('local')->assertMissing('eod-screenshots/delete-me.png');
});

test('boards with EOD history still return with an error message', function () {
    $user = userWithPermissions(['work-boards.view', 'work-boards.delete']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $board = WorkBoard::factory()->for($company)->for($user, 'creator')->create();
    $board->members()->attach($user);
    $column = WorkBoardColumn::factory()->for($board, 'board')->create();
    $task = WorkTask::factory()->for($board, 'board')->for($column, 'column')->for($user, 'creator')->create([
        'assigned_to' => $user->id,
    ]);
    $report = EodReport::factory()->for($company)->for($user)->create();
    $report->items()->create(['work_task_id' => $task->id, 'accomplishment' => 'Completed work.']);

    $this->actingAs($user)
        ->delete(route('work-boards.destroy', $board))
        ->assertRedirect()
        ->assertSessionHas(
            'inertia.flash_data.toast.message',
            'This board cannot be deleted because one or more tasks have EOD updates.',
        );

    expect($board->fresh())->not->toBeNull();
});
