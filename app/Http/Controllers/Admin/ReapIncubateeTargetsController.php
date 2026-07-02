<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\ReapIncubateeTargetProgressService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReapIncubateeTargetsController extends Controller
{
    public function __construct(
        private readonly ReapIncubateeTargetProgressService $progress,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        [$fiscalYearId, $fiscalYears] = FiscalYear::resolveIdForUi(
            $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null
        );

        $fiscalYear = $fiscalYears->firstWhere('id', $fiscalYearId);

        return view('admin.targets.reap-incubatee', [
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'fiscalYear' => $fiscalYear,
            'progress' => $this->progress->statewideProgress($fiscalYear instanceof FiscalYear ? $fiscalYear : null),
        ]);
    }
}
