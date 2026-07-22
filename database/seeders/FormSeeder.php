<?php

namespace Database\Seeders;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormPurpose;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FormSeeder extends Seeder
{
    public function run(): void
    {
        $publishedEvents = Event::query()
            ->where('status', EventStatus::Published)
            ->whereNull('deleted_at')
            ->get();

        foreach ($publishedEvents as $event) {
            $registrationForm = Form::query()->firstOrCreate(
                [
                    'event_id' => $event->id,
                    'title' => 'Registration Form',
                ],
                [
                    'description' => "Registration form for {$event->title}",
                    'visible_for' => [EventFormVisibility::Public],
                    'closed_at' => $event->registration_end,
                    'banner_url' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1200',
                    'banner_caption' => 'Fill out the form below to secure your spot!',
                    'metadata' => [
                        'purpose' => FormPurpose::Registration->value,
                        'registration_mode' => 'single',
                        'requires_form_id' => null,
                        'max_team_size' => null,
                        'team_size' => null,
                    ],
                ],
            );

            $regMeta = is_array($registrationForm->metadata) ? $registrationForm->metadata : [];
            if (($regMeta['purpose'] ?? null) !== FormPurpose::Registration->value) {
                $registrationForm->update([
                    'metadata' => array_merge($regMeta, [
                        'purpose' => FormPurpose::Registration->value,
                        'registration_mode' => $regMeta['registration_mode'] ?? 'single',
                    ]),
                ]);
            }

            if ($registrationForm->formFields()->count() === 0) {
                $this->createRegistrationFields($registrationForm);
            }

            $includeFeedback = crc32($event->id) % 2 === 0;

            if ($includeFeedback) {
                $feedbackForm = Form::query()->firstOrCreate(
                    [
                        'event_id' => $event->id,
                        'title' => 'Feedback Survey',
                    ],
                    [
                        'description' => "Post-event feedback for {$event->title}",
                        'visible_for' => [EventFormVisibility::Participant],
                        'closed_at' => $event->end_date->copy()->addDays(7),
                        'banner_url' => 'https://images.unsplash.com/photo-1551818255-e6e10975bc17?w=1200',
                        'banner_caption' => 'Your feedback helps us improve future events.',
                        'metadata' => [
                            'purpose' => FormPurpose::Other->value,
                            'requires_form_id' => $registrationForm->id,
                            'registration_mode' => null,
                            'max_team_size' => null,
                            'team_size' => null,
                        ],
                    ],
                );

                $fbMeta = is_array($feedbackForm->metadata) ? $feedbackForm->metadata : [];
                if (
                    ($fbMeta['purpose'] ?? null) !== FormPurpose::Other->value
                    || ($fbMeta['requires_form_id'] ?? null) !== $registrationForm->id
                ) {
                    $feedbackForm->update([
                        'metadata' => array_merge($fbMeta, [
                            'purpose' => FormPurpose::Other->value,
                            'requires_form_id' => $registrationForm->id,
                            'registration_mode' => null,
                        ]),
                    ]);
                }

                if ($feedbackForm->formFields()->count() === 0) {
                    $this->createFeedbackFields($feedbackForm);
                }
            }
        }
    }

    private function createRegistrationFields(Form $form): void
    {
        $fields = [
            [
                'input_type' => 'input',
                'metadata' => [
                    'label' => 'Full Name',
                    'placeholder' => 'Enter your full name',
                    'required' => true,
                ],
            ],
            [
                'input_type' => 'input',
                'metadata' => [
                    'label' => 'Student ID (NIM)',
                    'placeholder' => 'e.g. A11.2026.xxxxx',
                    'required' => true,
                ],
            ],
            [
                'input_type' => 'input',
                'metadata' => [
                    'label' => 'Phone Number',
                    'placeholder' => '08xxxxxxxxxx',
                    'required' => true,
                ],
            ],
            [
                'input_type' => 'selectInput',
                'metadata' => [
                    'label' => 'Faculty',
                    'options' => [
                        'Computer Science',
                        'Engineering',
                        'Design',
                        'Business',
                        'Health Sciences',
                        'Humanities',
                    ],
                    'required' => true,
                ],
            ],
            [
                'input_type' => 'selectInput',
                'metadata' => [
                    'label' => 'Year of Study',
                    'options' => ['1st Year', '2nd Year', '3rd Year', '4th Year', 'Graduate'],
                    'required' => true,
                ],
            ],
            [
                'input_type' => 'textarea',
                'metadata' => [
                    'label' => 'Motivation',
                    'placeholder' => 'Tell us why you want to join this event',
                    'required' => false,
                ],
            ],
            [
                'input_type' => 'fileUpload',
                'metadata' => [
                    'label' => 'CV / Resume',
                    'accept' => '.pdf,.doc,.docx',
                    'maxSize' => 5120,
                    'required' => false,
                ],
            ],
        ];

        foreach ($fields as $index => $field) {
            FormField::create([
                'id' => Str::uuid()->toString(),
                'input_type' => $field['input_type'],
                'label' => $field['metadata']['label'],
                'name' => Str::slug($field['metadata']['label'], '_'),
                'metadata' => $field['metadata'],
                'form_id' => $form->id,
                'order' => $index,
            ]);
        }
    }

    private function createFeedbackFields(Form $form): void
    {
        $fields = [
            [
                'input_type' => 'selectInput',
                'metadata' => [
                    'label' => 'Overall Rating',
                    'options' => ['Excellent', 'Good', 'Average', 'Below Average', 'Poor'],
                    'required' => true,
                ],
            ],
            [
                'input_type' => 'textarea',
                'metadata' => [
                    'label' => 'What did you enjoy most?',
                    'placeholder' => 'Share your favorite part of the event',
                    'required' => false,
                ],
            ],
            [
                'input_type' => 'textarea',
                'metadata' => [
                    'label' => 'Suggestions for improvement',
                    'placeholder' => 'How can we make future events better?',
                    'required' => false,
                ],
            ],
            [
                'input_type' => 'selectInput',
                'metadata' => [
                    'label' => 'Would you attend again?',
                    'options' => ['Definitely', 'Probably', 'Maybe', 'Probably not', 'No'],
                    'required' => true,
                ],
            ],
        ];

        foreach ($fields as $index => $field) {
            FormField::create([
                'id' => Str::uuid()->toString(),
                'input_type' => $field['input_type'],
                'label' => $field['metadata']['label'],
                'name' => Str::slug($field['metadata']['label'], '_'),
                'metadata' => $field['metadata'],
                'form_id' => $form->id,
                'order' => $index,
            ]);
        }
    }
}
