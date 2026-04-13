<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicHoliday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicHolidayController extends Controller
{
    public function index(): View
    {
        $holidays = PublicHoliday::query()->orderByDesc('holiday_date')->paginate(30);

        return view('admin.public-holidays.index', compact('holidays'));
    }

    public function create(): View
    {
        return view('admin.public-holidays.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'holiday_date' => ['required', 'date', 'unique:public_holidays,holiday_date'],
            'name' => ['required', 'string', 'max:160'],
        ]);

        PublicHoliday::query()->create($data);

        return redirect()->route('admin.holidays.index')->with('status', 'Holiday added.');
    }

    public function edit(PublicHoliday $holiday): View
    {
        return view('admin.public-holidays.edit', ['holiday' => $holiday]);
    }

    public function update(Request $request, PublicHoliday $holiday): RedirectResponse
    {
        $data = $request->validate([
            'holiday_date' => ['required', 'date', 'unique:public_holidays,holiday_date,'.$holiday->id],
            'name' => ['required', 'string', 'max:160'],
        ]);

        $holiday->update($data);

        return redirect()->route('admin.holidays.index')->with('status', 'Holiday updated.');
    }

    public function destroy(PublicHoliday $holiday): RedirectResponse
    {
        $holiday->delete();

        return redirect()->route('admin.holidays.index')->with('status', 'Holiday removed.');
    }
}
