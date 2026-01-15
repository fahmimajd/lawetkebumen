<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\DailyAgentReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DailyAgentReportController extends Controller
{
    public function index(Request $request, DailyAgentReportService $service)
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'queue_id' => ['nullable', 'integer'],
            'team_id' => ['nullable', 'integer'],
        ]);

        $date = $validated['date'] ?? null;
        $queueId = $validated['queue_id'] ?? null;
        $teamId = $validated['team_id'] ?? null;

        $rows = $service->build($date, $queueId, $teamId);

        if ($request->wantsJson()) {
            return response()->json($rows);
        }

        $defaultDate = $date
            ?: CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d');

        return view('reports.daily-agents', [
            'defaultDate' => $defaultDate,
            'dataUrl' => route('reports.daily-agents'),
        ]);
    }
}
