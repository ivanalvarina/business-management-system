<?php

namespace App\Http\Controllers;

use App\Models\EodAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EodAttachmentController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, EodAttachment $eodAttachment): StreamedResponse
    {
        $eodAttachment->loadMissing('item.report', 'item.task.board.company');
        $report = $eodAttachment->item->report;
        $task = $eodAttachment->item->task;
        $board = $eodAttachment->item->task->board;
        $canView = $request->user()->can('view', $board)
            && ($request->user()->hasRole('Super Admin')
                || $task->created_by === $request->user()->id
                || ($report->user_id === $request->user()->id
                    && $request->user()->can('eod-reports.view-own')));
        abort_unless($canView, 403);

        return Storage::disk($eodAttachment->disk)->response(
            $eodAttachment->path,
            $eodAttachment->original_name,
            ['Content-Type' => $eodAttachment->mime_type, 'Content-Disposition' => 'inline'],
        );
    }
}
