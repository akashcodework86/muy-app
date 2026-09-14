<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\IncubateeBill;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchCfa;
use App\Models\OnboardingBatchDraftCfa;
use App\Models\User;
use App\Support\TodayOnlyDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BatchMemberBillController extends Controller
{
    public function index(Request $request, OnboardingBatch $batch, CfaSubmission $cfa_submission): View
    {
        $this->authorizeMember($request, $batch, $cfa_submission);

        $bills = IncubateeBill::query()
            ->where('onboarding_batch_id', $batch->id)
            ->where('cfa_submission_id', $cfa_submission->id)
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->get();

        return view('staff.batch-member-bills.index', [
            'batch' => $batch->load(['hub:id,name', 'district:id,name']),
            'cfa' => $cfa_submission,
            'bills' => $bills,
        ]);
    }

    public function store(Request $request, OnboardingBatch $batch, CfaSubmission $cfa_submission): RedirectResponse
    {
        $staff = $this->authorizeMember($request, $batch, $cfa_submission);

        $validated = $request->validate([
            'bills' => ['required', 'array', 'min:1', 'max:20'],
            'bills.*.bill_date' => TodayOnlyDate::rules(),
            'bills.*.amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'bills.*.bill_number' => ['required', 'string', 'max:100'],
            'bills.*.document' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ], [
            'bills.required' => 'Add at least one bill.',
            'bills.*.bill_date.required' => 'Bill date is required.',
            'bills.*.amount.required' => 'Amount is required.',
            'bills.*.bill_number.required' => 'Bill number is required.',
            'bills.*.document.required' => 'Document is required.',
            'bills.*.document.mimes' => 'Document must be a PDF or image (jpg, png, webp).',
            'bills.*.document.max' => 'Document must be 5 MB or smaller.',
        ]);

        $this->assertBillNumbersUnique($validated['bills']);

        $count = 0;
        DB::transaction(function () use ($request, $validated, $batch, $cfa_submission, $staff, &$count): void {
            foreach ($validated['bills'] as $key => $row) {
                $file = $request->file('bills.'.$key.'.document');
                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages([
                        'bills.'.$key.'.document' => 'Document is required.',
                    ]);
                }

                $bill = IncubateeBill::query()->create([
                    'onboarding_batch_id' => (int) $batch->id,
                    'cfa_submission_id' => (int) $cfa_submission->id,
                    'district_id' => (int) $batch->district_id,
                    'bill_date' => (string) $row['bill_date'],
                    'amount' => $row['amount'],
                    'bill_number' => trim((string) $row['bill_number']),
                    'document_disk' => 'local',
                    'document_path' => '',
                    'created_by' => (int) $staff->id,
                ]);

                $dir = 'incubatee-bills/'.$cfa_submission->id.'/'.$bill->id;
                $path = $file->store($dir, 'local');

                $bill->update([
                    'document_path' => $path,
                    'document_original_name' => $file->getClientOriginalName(),
                    'document_mime' => $file->getClientMimeType(),
                    'document_size' => $file->getSize(),
                ]);

                $count++;
            }
        });

        return redirect()
            ->route('staff.batches.members.bills', [$batch, $cfa_submission])
            ->with('status', $count === 1 ? 'Bill added.' : $count.' bills added.');
    }

    public function document(
        Request $request,
        OnboardingBatch $batch,
        CfaSubmission $cfa_submission,
        IncubateeBill $bill,
    ): StreamedResponse {
        $this->authorizeMember($request, $batch, $cfa_submission);
        abort_unless((int) $bill->onboarding_batch_id === (int) $batch->id, 404);
        abort_unless((int) $bill->cfa_submission_id === (int) $cfa_submission->id, 404);
        abort_unless($bill->hasDocument(), 404);

        $disk = Storage::disk((string) ($bill->document_disk ?: 'local'));
        $path = (string) $bill->document_path;
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, (string) ($bill->document_original_name ?: 'bill-document'));
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $rows
     */
    private function assertBillNumbersUnique(array $rows): void
    {
        $seen = [];
        $errors = [];

        foreach ($rows as $key => $row) {
            $number = trim((string) ($row['bill_number'] ?? ''));
            $lower = mb_strtolower($number);
            if ($lower === '') {
                continue;
            }

            if (isset($seen[$lower])) {
                $errors['bills.'.$key.'.bill_number'] = 'Bill number must be unique.';

                continue;
            }

            $seen[$lower] = true;

            $exists = IncubateeBill::query()
                ->whereRaw('LOWER(bill_number) = ?', [$lower])
                ->exists();

            if ($exists) {
                $errors['bills.'.$key.'.bill_number'] = 'This bill number is already used.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function authorizeMember(Request $request, OnboardingBatch $batch, CfaSubmission $cfa): User
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'district_staff', 403);
        abort_unless((int) $user->district_id === (int) $batch->district_id, 403);

        $isMember = OnboardingBatchCfa::query()
            ->where('onboarding_batch_id', $batch->id)
            ->where('cfa_submission_id', $cfa->id)
            ->exists()
            || OnboardingBatchDraftCfa::query()
                ->where('onboarding_batch_id', $batch->id)
                ->where('cfa_submission_id', $cfa->id)
                ->exists();

        abort_unless($isMember, 404);

        return $user;
    }
}
