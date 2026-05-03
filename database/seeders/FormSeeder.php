<?php

namespace Database\Seeders;

use App\Enums\EventFormVisibility;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FormSeeder extends Seeder
{
    public function run(): void
    {
        $publishedEvents = Event::where('status', 'published')
            ->whereNull('deleted_at')
            ->get();

        foreach ($publishedEvents as $event) {
            $registrationForm = Form::create([
                'title' => 'Registration Form',
                'description' => "Registration form for {$event->title}",
                'visible_for' => [EventFormVisibility::Public->value],
                'closed_at' => $event->registration_end,
                'event_id' => $event->id,
            ]);

            $this->createRegistrationFields($registrationForm);

            if (rand(0, 1)) {
                $feedbackForm = Form::create([
                    'title' => 'Feedback Survey',
                    'description' => "Post-event feedback for {$event->title}",
                    'visible_for' => [EventFormVisibility::Participant->value],
                    'closed_at' => $event->end_date->copy()->addDays(7),
                    'event_id' => $event->id,
                ]);

                $this->createFeedbackFields($feedbackForm);
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
