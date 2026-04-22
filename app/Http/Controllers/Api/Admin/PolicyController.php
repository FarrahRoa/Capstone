<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PolicyDocument;
use App\Models\Space;
use App\Support\ApiResponse;
use App\Support\ConfabGuidelinesComparison;
use App\Support\SpaceGuidelineDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PolicyController extends Controller
{
    /**
     * @return array{slug: string, content: string, updated_at: string|null, confab_guidelines_content: string, confab_guidelines_updated_at: string|null, confab_room_comparisons: list<array<string, mixed>>, spaces: list<array<string, mixed>>}
     */
    private function reservationGuidelinesAdminData(): array
    {
        $doc = PolicyDocument::reservationGuidelines();
        $confabDoc = PolicyDocument::confabReservationGuidelines();

        $spaces = Space::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type', 'capacity', 'is_confab_pool', 'guideline_details'])
            ->map(fn (Space $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'type' => $s->type,
                'capacity' => $s->capacity,
                'is_confab_pool' => (bool) $s->is_confab_pool,
                'guideline_details' => SpaceGuidelineDetails::forApi($s->guideline_details),
            ])
            ->values()
            ->all();

        return [
            'slug' => $doc->slug,
            'content' => $doc->content,
            'updated_at' => $doc->updated_at?->toIso8601String(),
            'confab_guidelines_content' => $confabDoc->content,
            'confab_guidelines_updated_at' => $confabDoc->updated_at?->toIso8601String(),
            'confab_room_comparisons' => ConfabGuidelinesComparison::physicalConfabRoomsPayload(),
            'spaces' => $spaces,
        ];
    }

    public function showReservationGuidelines(): JsonResponse
    {
        return ApiResponse::data($this->reservationGuidelinesAdminData());
    }

    public function updateReservationGuidelines(Request $request): JsonResponse
    {
        $detailRules = [
            'space_guidelines.*.details.location' => 'nullable|string|max:2000',
            'space_guidelines.*.details.seating_capacity_note' => 'nullable|string|max:2000',
            'space_guidelines.*.details.others' => 'nullable|string|max:5000',
            'space_guidelines.*.details.internet_options' => 'nullable|array',
            'space_guidelines.*.details.internet_options.*' => ['string', Rule::in(SpaceGuidelineDetails::INTERNET_OPTION_VALUES)],
        ];
        foreach (SpaceGuidelineDetails::QUANTITY_KEYS as $key) {
            $detailRules['space_guidelines.*.details.'.$key] = 'nullable|integer|min:0|max:99';
        }

        $data = $request->validate(array_merge([
            'content' => 'required|string|max:100000',
            'confab_guidelines_content' => 'sometimes|nullable|string|max:100000',
            'space_guidelines' => 'nullable|array',
            'space_guidelines.*.space_id' => 'required|integer|exists:spaces,id',
            'space_guidelines.*.details' => 'nullable|array',
        ], $detailRules));

        foreach ($data['space_guidelines'] ?? [] as $i => $row) {
            $opts = $row['details']['internet_options'] ?? null;
            if (! is_array($opts) || $opts === []) {
                continue;
            }
            $san = SpaceGuidelineDetails::sanitizeInternetOptions($opts);
            if ($san !== null && SpaceGuidelineDetails::internetOptionsExclusiveNoneInvalid($san)) {
                throw ValidationException::withMessages([
                    'space_guidelines.'.$i.'.details.internet_options' => ['None cannot be combined with other internet options.'],
                ]);
            }
        }

        $doc = PolicyDocument::reservationGuidelines();

        DB::transaction(function () use ($data, $doc) {
            $doc->update(['content' => $data['content']]);

            if (array_key_exists('confab_guidelines_content', $data)) {
                PolicyDocument::confabReservationGuidelines()->update([
                    'content' => (string) ($data['confab_guidelines_content'] ?? ''),
                ]);
            }

            foreach ($data['space_guidelines'] ?? [] as $row) {
                $space = Space::query()->whereKey((int) $row['space_id'])->first();
                if (! $space || ! $space->is_active) {
                    continue;
                }
                $normalized = SpaceGuidelineDetails::normalize($row['details'] ?? null);
                $space->update(['guideline_details' => $normalized]);
            }
        });

        $doc->refresh();

        return ApiResponse::message('Reservation guidelines saved.', $this->reservationGuidelinesAdminData());
    }

    public function showOperatingHours(): JsonResponse
    {
        $doc = PolicyDocument::operatingHours();
        $hours = json_decode((string) $doc->content, true);
        if (!is_array($hours)) {
            $hours = PolicyDocument::defaultOperatingHours();
        }

        return ApiResponse::data([
            'slug' => $doc->slug,
            'hours' => [
                'day_start' => (string) ($hours['day_start'] ?? PolicyDocument::defaultOperatingHours()['day_start']),
                'day_end' => (string) ($hours['day_end'] ?? PolicyDocument::defaultOperatingHours()['day_end']),
            ],
            'updated_at' => $doc->updated_at?->toIso8601String(),
        ]);
    }

    public function updateOperatingHours(Request $request): JsonResponse
    {
        $data = $request->validate([
            'day_start' => ['required', 'date_format:H:i'],
            'day_end' => ['required', 'date_format:H:i'],
        ]);

        if (strcmp($data['day_end'], $data['day_start']) <= 0) {
            return response()->json([
                'message' => 'Invalid operating hours.',
                'errors' => [
                    'day_end' => ['End time must be later than start time.'],
                ],
            ], 422);
        }

        $doc = PolicyDocument::operatingHours();
        $doc->update([
            'content' => json_encode([
                'day_start' => $data['day_start'],
                'day_end' => $data['day_end'],
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $doc->refresh();

        return ApiResponse::message('Operating hours saved.', [
            'slug' => $doc->slug,
            'hours' => [
                'day_start' => $data['day_start'],
                'day_end' => $data['day_end'],
            ],
            'updated_at' => $doc->updated_at?->toIso8601String(),
        ]);
    }
}
