<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\DuCourse;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Du\DuCourseFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DuFieldMappingController extends Controller
{
    /**
     * Maps one immutable option value of a DU-enabled field to one active cached course.
     *
     * Fails closed with 422 for a field without the flag, an option that is not part of
     * the field, or a course that is missing/inactive in the local cache.
     */
    public function __invoke(
        Request $request,
        Event $event,
        Form $form,
        DuCourseFieldService $fields,
    ): JsonResponse {
        abort_unless((string) $form->event_id === (string) $event->id, 404);
        $this->authorize('update', $event);

        $validated = $request->validate([
            'field_id' => ['required', 'uuid'],
            'option_value' => ['required', 'string', 'max:255'],
            'course_uid' => ['required', 'string', 'max:255'],
        ]);

        $field = $form->formFields()->whereKey($validated['field_id'])->first();
        abort_if($field === null, 404);

        if (($field->metadata['du_redeem_enabled'] ?? null) !== true) {
            throw ValidationException::withMessages([
                'field_id' => 'Field ini tidak mengaktifkan redeem code DU.',
            ]);
        }

        $optionValue = $validated['option_value'];
        if (! in_array($optionValue, $fields->optionLabels($field), true)) {
            throw ValidationException::withMessages([
                'option_value' => 'Opsi tidak tersedia pada field ini.',
            ]);
        }

        $course = DuCourse::query()
            ->where('uid', $validated['course_uid'])
            ->where('is_active', true)
            ->first(['uid', 'title']);

        if ($course === null) {
            throw ValidationException::withMessages([
                'course_uid' => 'Course DU tidak ditemukan atau tidak aktif.',
            ]);
        }

        DB::transaction(function () use ($field, $optionValue, $course): void {
            $locked = FormField::query()->whereKey($field->getKey())->lockForUpdate()->firstOrFail();
            $metadata = $locked->metadata instanceof Collection
                ? $locked->metadata->all()
                : (array) $locked->metadata;

            $mapping = is_array($metadata['du_option_map'] ?? null) ? $metadata['du_option_map'] : [];
            $mapping[$optionValue] = [
                'course_uid' => $course->uid,
                'course_title' => $course->title,
            ];
            $metadata['du_option_map'] = $mapping;

            $locked->update(['metadata' => $metadata]);
        });

        return response()->json([
            'course_uid' => $course->uid,
            'course_title' => $course->title,
        ]);
    }
}
