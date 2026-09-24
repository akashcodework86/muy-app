<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Services\OnboardedProductAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductAnalyticsController extends Controller
{
    public function index(Request $request, OnboardedProductAnalyticsService $analytics): View
    {
        $filters = [
            'q' => trim((string) $request->string('q')),
            'district' => $request->integer('district') ?: null,
            'sector' => trim((string) $request->string('sector')),
            'product' => trim((string) $request->string('product')),
        ];

        return view('admin.product-analytics.index', array_merge(
            $analytics->analyze($filters),
            [
                'filters' => $filters,
                'districtOptions' => District::query()->orderBy('name')->get(['id', 'name']),
            ],
        ));
    }
}
