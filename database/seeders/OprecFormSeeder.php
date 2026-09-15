<?php

namespace Database\Seeders;

use App\Enums\EventFormVisibility;
use App\Enums\FormPurpose;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Recruitment\OprecFormDefinition;
use Illuminate\Database\Seeder;

class OprecFormSeeder extends Seeder
{
    public function run(): void
    {
        $event = Event::query()->where('slug', OprecFormDefinition::EVENT_SLUG)->first();
        if ($event === null) {
            throw new \RuntimeException('Jalankan EventSeeder dulu: event doscom-open-recruitment-2026 tidak ditemukan.');
        }

        $form = Form::query()->firstOrCreate(
            ['event_id' => $event->id, 'title' => OprecFormDefinition::FORM_TITLE],
            [
                'description' => 'Formulir pendaftaran anggota baru DOSCOM.',
                'visible_for' => [EventFormVisibility::Public],
                'closed_at' => null,
                'metadata' => [
                    'purpose' => FormPurpose::Other->value,
                    'oprec' => true,
                    'registration_mode' => null,
                    'requires_form_id' => null,
                ],
            ],
        );

        $meta = is_array($form->metadata) ? $form->metadata : [];
        if (($meta['purpose'] ?? null) !== FormPurpose::Other->value || ($meta['oprec'] ?? null) !== true) {
            $form->update(['metadata' => array_merge($meta, [
                'purpose' => FormPurpose::Other->value,
                'oprec' => true,
            ])]);
        }

        foreach ($this->fields() as $index => $field) {
            FormField::query()->updateOrCreate(
                ['form_id' => $form->id, 'name' => $field['name']],
                [
                    'input_type' => $field['input_type'],
                    'label' => $field['label'],
                    'description' => $field['description'],
                    'metadata' => $field['metadata'],
                    'order' => $index,
                    'is_append' => false,
                ],
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function fields(): array
    {
        $stepText = static fn (int $step, array $rules): array => array_merge($rules, ['step' => $step]);

        return [
            [
                'name' => 'full_name', 'input_type' => 'input', 'label' => 'Nama Lengkap',
                'description' => 'Sesuai identitas kampus.',
                'metadata' => ['builderType' => 'short_text', 'type' => 'short_text', 'placeholder' => 'cth. Ahmad Fauzi', 'rules' => $stepText(1, ['required' => true, 'max' => '255'])],
            ],
            [
                'name' => 'nim', 'input_type' => 'input', 'label' => 'NIM',
                'description' => 'Nomor Induk Mahasiswa aktif UDINUS.',
                'metadata' => ['builderType' => 'short_text', 'type' => 'short_text', 'placeholder' => 'cth. A11.2026.xxxxx', 'rules' => $stepText(1, ['required' => true, 'max' => '50', 'regex' => '^[A-Za-z0-9\-_.]+$'])],
            ],
            [
                'name' => 'semester', 'input_type' => 'selectInput', 'label' => 'Semester',
                'description' => null,
                'metadata' => ['builderType' => 'dropdown', 'options' => ['1', '2', '3'], 'rules' => $stepText(1, ['required' => true, 'in' => '1,2,3'])],
            ],
            [
                'name' => 'phone', 'input_type' => 'input', 'label' => 'Nomor WhatsApp',
                'description' => '10–15 digit, boleh diawali +.',
                'metadata' => ['builderType' => 'phone', 'type' => 'phone', 'placeholder' => 'cth. 081234567890', 'rules' => $stepText(1, ['required' => true, 'max' => '30', 'regex' => '^\\+?[0-9]{10,15}$'])],
            ],
            [
                'name' => 'personal_email', 'input_type' => 'input', 'label' => 'Email Pribadi',
                'description' => null,
                'metadata' => ['builderType' => 'email', 'type' => 'email', 'placeholder' => 'cth. nama@gmail.com', 'rules' => $stepText(1, ['required' => true, 'email' => true, 'max' => '255'])],
            ],
            [
                'name' => 'student_email', 'input_type' => 'input', 'label' => 'Email Kampus',
                'description' => null,
                'metadata' => ['builderType' => 'email', 'type' => 'email', 'placeholder' => 'cth. nama@students.dinus.ac.id', 'rules' => $stepText(1, ['required' => true, 'email' => true, 'max' => '255'])],
            ],
            [
                'name' => 'instagram_username', 'input_type' => 'input', 'label' => 'Username Instagram',
                'description' => 'Tanpa @.',
                'metadata' => ['builderType' => 'short_text', 'type' => 'short_text', 'placeholder' => 'cth. nama.akun', 'rules' => $stepText(1, ['required' => true, 'max' => '100', 'regex' => '^[A-Za-z0-9_.]+$'])],
            ],
            [
                'name' => 'primary_division_id', 'input_type' => 'selectInput', 'label' => 'Divisi Utama',
                'description' => 'Pilih divisi yang paling diminati.',
                'metadata' => ['builderType' => 'dropdown', 'options' => [], 'rules' => $stepText(2, ['required' => true])],
            ],
            [
                'name' => 'secondary_division_id', 'input_type' => 'selectInput', 'label' => 'Divisi Cadangan',
                'description' => 'Opsional, harus berbeda dari divisi utama.',
                'metadata' => ['builderType' => 'dropdown', 'options' => [], 'rules' => $stepText(2, [])],
            ],
            [
                'name' => 'portfolio_type', 'input_type' => 'radio', 'label' => 'Bentuk Portfolio',
                'description' => null,
                'metadata' => ['builderType' => 'radio', 'options' => 'url,file', 'rules' => $stepText(3, ['required' => true])],
            ],
            [
                'name' => 'portfolio_url', 'input_type' => 'input', 'label' => 'Link Portfolio',
                'description' => 'Wajib jika bentuk portfolio adalah link.',
                'metadata' => ['builderType' => 'short_text', 'type' => 'url', 'placeholder' => 'cth. https://...', 'rules' => $stepText(3, ['url' => true, 'max' => '500'])],
            ],
            [
                'name' => 'portfolio_file', 'input_type' => 'fileUpload', 'label' => 'File Portfolio (PDF)',
                'description' => 'Wajib jika bentuk portfolio adalah file. Maksimal 5 MB.',
                'metadata' => ['builderType' => 'fileUpload', 'rules' => $stepText(3, ['mimes' => 'pdf', 'max_size' => '5120'])],
            ],
            [
                'name' => 'cv', 'input_type' => 'fileUpload', 'label' => 'CV (PDF)',
                'description' => 'Maksimal 5 MB.',
                'metadata' => ['builderType' => 'fileUpload', 'rules' => $stepText(3, ['required' => true, 'mimes' => 'pdf', 'max_size' => '5120'])],
            ],
        ];
    }
}
