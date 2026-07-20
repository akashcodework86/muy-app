<?php

namespace App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\ServiceCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FieldActivityHighlightController extends Controller
{
    public function image(
        Request $request,
        string $module,
        int $record,
        string $collection,
        int $index,
    ): BinaryFileResponse {
        $user = $request->user();
        abort_unless($user?->role === 'hub_admin' && (int) ($user->hub_id ?? 0) > 0, 403);

        $sources = $this->sources();
        abort_unless(isset($sources[$module]), 404);
        $source = $sources[$module];
        abort_unless(in_array($collection, $source['collections'], true), 404);

        $modelClass = $source['model'];
        $row = $modelClass::query()->findOrFail($record);
        abort_unless((string) ($row->status ?? '') === ServiceCase::STATUS_APPROVED, 404);

        $hubId = (int) $user->hub_id;
        $districtIds = District::query()->where('hub_id', $hubId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $belongsToHub = (int) ($row->hub_id ?? 0) === $hubId
            || in_array((int) ($row->district_id ?? 0), $districtIds, true);
        abort_unless($belongsToHub, 403);

        $items = $row->{$collection};
        if (is_string($items)) {
            $items = json_decode($items, true);
        }
        $media = collect(is_array($items) ? $items : [])->values()->get(max(0, $index));
        abort_unless(is_array($media), 404);

        $path = trim((string) ($media['path'] ?? ''));
        $mime = strtolower(trim((string) ($media['mime'] ?? '')));
        $filename = (string) ($media['original_name'] ?? basename($path));
        abort_if($path === '' || ! Storage::exists($path), 404);
        abort_unless(Str::startsWith($mime, 'image/') || preg_match('/\.(jpe?g|png|webp|gif)$/i', $filename), 404);
        abort_if(in_array($mime, ['image/heic', 'image/heif'], true), 404);

        return response()->file(Storage::path($path), [
            'Content-Type' => $mime !== '' ? $mime : 'image/jpeg',
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /** @return array<string, array{model: class-string, collections: list<string>}> */
    private function sources(): array
    {
        return [
            'technical_training' => [
                'model' => \App\Models\TechnicalTraining::class,
                'collections' => ['attendance_media_json'],
            ],
            'lakhpati_technical_training' => [
                'model' => \App\Models\LakhpatiTechnicalTraining::class,
                'collections' => ['workshop_photos_json'],
            ],
            'line_department_meeting' => [
                'model' => \App\Models\LineDepartmentMeeting::class,
                'collections' => ['proof_media_json', 'photos_json'],
            ],
            'community_org_outreach' => [
                'model' => \App\Models\CommunityOrganizationOutreachVisit::class,
                'collections' => ['photos_json'],
            ],
        ];
    }
}
