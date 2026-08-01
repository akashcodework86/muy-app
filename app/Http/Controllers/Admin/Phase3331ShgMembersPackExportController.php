<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Exports\Phase3331ShgMembersPackDataService;
use App\Services\Exports\Phase3331ShgMembersPackExcelExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Phase3331ShgMembersPackExportController extends Controller
{
    public function __construct(
        private readonly Phase3331ShgMembersPackDataService $data,
        private readonly Phase3331ShgMembersPackExcelExport $excel,
    ) {}

    public function download(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $pack = $this->data->build();
        $fileName = 'phase3-3_3_1-shg-members-'.now()->format('Ymd_His').'.xlsx';

        return $this->excel->download($pack, $fileName);
    }
}
