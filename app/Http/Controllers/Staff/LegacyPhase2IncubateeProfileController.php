<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\LegacyApplicationServiceCaseSupport;
use App\Services\LegacyPhase2IncubateeProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegacyPhase2IncubateeProfileController extends Controller
{
    public function __construct(
        private LegacyApplicationServiceCaseSupport $legacySupport,
        private LegacyPhase2IncubateeProfileService $profileService,
    ) {}

    /**
     * Same-origin PNG for html2canvas / PDF (avoids tainted canvas from hotlinked logo).
     */
    public function headerLogo(Request $request): BinaryFileResponse|Response
    {
        $request->user();

        $path = storage_path('app/cache/muy-header-logo.png');
        $ttlSeconds = 86400 * 7;

        if (is_file($path) && (time() - filemtime($path)) < $ttlSeconds) {
            return response()->file($path, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'private, max-age=86400',
            ]);
        }

        try {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            $remote = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'MUY-MIS/1.0 (logo cache)'])
                ->get('https://ukrbi.in/new/admin/muy.png');

            if ($remote->successful() && strlen($remote->body()) > 64) {
                file_put_contents($path, $remote->body());

                return response()->file($path, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'private, max-age=86400',
                ]);
            }
        } catch (\Throwable) {
            // fall through to placeholder
        }

        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==');

        return response($pixel, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    public function show(Request $request, int $legacy_application): View
    {
        $staff = $request->user();
        $this->legacySupport->assertLegacyApplicationInStaffDistrict($staff, $legacy_application);

        $data = $this->profileService->loadProfile($legacy_application);
        abort_if($data === null, 404);

        $profilePhotoUrl = $this->resolveProfilePhotoPublicUrl($legacy_application, $data);
        $autopdf = $request->boolean('autopdf');

        return view('staff.phase2-incubatee-profile', [
            'profile' => $data,
            'profilePhotoUrl' => $profilePhotoUrl,
            'autopdf' => $autopdf,
        ]);
    }

    public function photo(Request $request, int $legacy_application): StreamedResponse
    {
        $staff = $request->user();
        $this->legacySupport->assertLegacyApplicationInStaffDistrict($staff, $legacy_application);

        $data = $this->profileService->loadProfile($legacy_application);
        abort_if($data === null, 404);

        $rel = $data['profile_pic_storage_relative'] ?? null;
        if (! is_string($rel) || $rel === '' || ! Storage::disk('local')->exists($rel)) {
            abort(404);
        }

        return Storage::disk('local')->response($rel, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function uploadPhoto(Request $request, int $legacy_application): RedirectResponse
    {
        $staff = $request->user();
        $this->legacySupport->assertLegacyApplicationInStaffDistrict($staff, $legacy_application);

        if (! $this->profileService->legacyAvailable()) {
            return redirect()->back()->withErrors(['profile_pic' => 'Legacy database is not available.']);
        }

        $request->validate([
            'profile_pic' => ['required', 'file', 'image', 'max:5120'],
        ], [
            'profile_pic.required' => 'Please choose an image file.',
        ]);

        $file = $request->file('profile_pic');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        $filename = 'pic_'.$legacy_application.'_'.time().'.'.$ext;
        $relativeDir = 'legacy-incubatee-photos/'.$legacy_application;
        Storage::disk('local')->makeDirectory($relativeDir);
        $storedPath = $file->storeAs($relativeDir, $filename, 'local');

        if ($storedPath === false) {
            return redirect()->back()->withErrors(['profile_pic' => 'Could not store the uploaded file.']);
        }

        try {
            if (Schema::connection('legacy')->hasTable('rbi_onboarded_applicants')) {
                $updated = DB::connection('legacy')
                    ->table('rbi_onboarded_applicants')
                    ->where('application_id', $legacy_application)
                    ->update(['pic' => $filename]);
                if ($updated === 0) {
                    Storage::disk('local')->delete($storedPath);

                    return redirect()->back()->withErrors([
                        'profile_pic' => 'No legacy onboarding row exists for this application; upload is only allowed after onboarding in the legacy MIS.',
                    ]);
                }
            } else {
                Storage::disk('local')->delete($storedPath);

                return redirect()->back()->withErrors(['profile_pic' => 'Legacy onboarding table is missing.']);
            }
        } catch (\Throwable) {
            Storage::disk('local')->delete($storedPath);

            return redirect()->back()->withErrors(['profile_pic' => 'Could not update legacy onboarded record.']);
        }

        return redirect()
            ->route('staff.phase2-profile.show', ['legacy_application' => $legacy_application])
            ->with('status', 'Profile picture updated.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveProfilePhotoPublicUrl(int $legacyApplicationId, array $data): string
    {
        if (! empty($data['profile_pic_storage_relative']) && Storage::disk('local')->exists((string) $data['profile_pic_storage_relative'])) {
            return route('staff.phase2-profile.photo', ['legacy_application' => $legacyApplicationId]);
        }

        $fn = $data['profile_pic_filename'] ?? null;
        $base = (string) config('legacy_phase2.legacy_public_assets_base_url', '');
        if (is_string($fn) && $fn !== '' && $base !== '') {
            return $base.'/incubatees/'.rawurlencode($fn);
        }

        return 'https://ui-avatars.com/api/?name='.rawurlencode((string) ($data['applicant']['applicant_name'] ?? 'MUY')).'&size=240&background=e5e7eb&color=374151';
    }
}
