<?php

namespace App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use App\Models\OnboardingBatch;
use App\Services\HubBatchImportParser;
use App\Services\HubBatchImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HubBatchImportController extends Controller
{
    public function preview(Request $request, HubBatchImportParser $parser, HubBatchImportService $importer): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'exists:onboarding_batches,id'],
            'file' => ['required', 'file', 'max:5120'],
            'fiscal_year_id' => ['nullable', 'integer'],
            'all_pool_years' => ['nullable', 'boolean'],
            'stop_when_full' => ['nullable', 'boolean'],
            'application_header' => ['nullable', 'string', 'max:100'],
            'mobile_header' => ['nullable', 'string', 'max:100'],
            'name_header' => ['nullable', 'string', 'max:100'],
        ]);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            return response()->json(['message' => 'Only .xlsx and .csv files are supported.'], 422);
        }
        try {
            $parsed = $parser->parse($file, [
                'application_no' => $validated['application_header'] ?? '',
                'mobile' => $validated['mobile_header'] ?? '',
                'name' => $validated['name_header'] ?? '',
            ]);
            $result = $importer->preview(
                $request->user(),
                OnboardingBatch::query()->findOrFail((int) $validated['batch_id']),
                $parsed['rows'],
                [
                    'fiscal_year_id' => (int) ($validated['fiscal_year_id'] ?? 0),
                    'all_pool_years' => $request->boolean('all_pool_years', true),
                    'stop_when_full' => $request->boolean('stop_when_full', true),
                ],
                ['name' => $file->getClientOriginalName(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'sheet' => $parsed['sheet'], 'header_row' => $parsed['header_row']]
            );

            return response()->json(['ok' => true] + $result);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function commit(Request $request, HubBatchImportService $importer): JsonResponse
    {
        $request->validate(['token' => ['required', 'uuid']]);
        try {
            return response()->json(['ok' => true] + $importer->commit($request->user(), (string) $request->input('token'), $request));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['SN', 'Name', 'Mobile', 'Application No', 'Block', 'Stage']);
            fputcsv($out, [1, 'Sample Applicant', '9876543210', 'RBI000000001', 'Sample Block', 'Early']);
            fclose($out);
        }, 'hub-batch-import-template.csv', ['Content-Type' => 'text/csv']);
    }
}
