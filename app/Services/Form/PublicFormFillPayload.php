<?php

namespace App\Services\Form;

use App\Models\Event;
use App\Models\Form;
use App\Services\Du\DuCourseFieldService;
use App\Support\FormFieldTypeMapping;

/** Builds the public form fill payload without authenticated access checks. */
final class PublicFormFillPayload
{
    /** @return array<string, mixed> */
    public static function build(Event $event, Form $form): array
    {
        $form->load(['formFields' => fn ($query) => $query->orderBy('order')]);
        return [
            'event' => ['id' => $event->id, 'slug' => $event->slug, 'title' => $event->title],
            'form' => ['id' => $form->id, 'title' => $form->title, 'description' => $form->description, 'closed_at' => null, 'banner_url' => $form->banner_url, 'banner_caption' => $form->banner_caption, 'referral_enabled' => $form->isReferralActive()],
            'fields' => self::fieldsForFill($form),
            'sections' => self::sections($form),
            'submitUrl' => route('dashboard.forms.submission', ['event' => $event, 'form' => $form]),
            'referralPosition' => $form->referralPosition(), 'referralCheckUrl' => route('dashboard.forms.referral-check', ['event' => $event, 'form' => $form]),
            'accessStatus' => 'allowed', 'accessMessage' => null, 'registrationMode' => 'single', 'memberSlots' => 0, 'pendingInvitationUrl' => null,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function fieldsForFill(Form $form): array
    {
        $service = app(DuCourseFieldService::class);

        return $form->formFields
            ->map(static fn ($field): array => $service->applyOptions(FormFieldTypeMapping::fieldToInertia($field)))
            ->values()
            ->all();
    }

    /** @return list<array{id: string, title: string}> */
    private static function sections(Form $form): array
    {
        $metadata = is_array($form->metadata) ? $form->metadata : [];
        $sections = collect($metadata['sections'] ?? [])
            ->filter(fn ($section): bool => is_array($section) && is_string($section['id'] ?? null))
            ->map(fn (array $section, int $index): array => [
                'id' => $section['id'],
                'title' => is_string($section['title'] ?? null) && $section['title'] !== '' ? $section['title'] : 'Bagian '.($index + 1),
            ])->values()->all();
        return $sections === [] ? [['id' => 'section-1', 'title' => 'Bagian 1']] : $sections;
    }
}
