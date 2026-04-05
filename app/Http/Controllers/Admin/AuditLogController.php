<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('id')
            ->paginate(40);

        return view('admin.audit.index', ['logs' => $logs]);
    }
}
