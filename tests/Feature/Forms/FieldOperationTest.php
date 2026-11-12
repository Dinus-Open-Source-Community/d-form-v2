<?php

namespace Tests\Feature\Forms;

use Tests\TestCase;
use App\Http\Requests\FieldModifyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

class FieldOperationTest extends TestCase
{
    private function getRules(): array
    {
        return (new FieldModifyRequest())->rules();
    }

    #[Test]
    #[TestDox('Validasi benar')]
    public function validasi_lolos_untuk_input_text_yang_benar()
    {
        $data = [
            'fields' => [
                [
                    'label' => 'Nama Lengkap',
                    'type' => 'input',
                    'name' => 'nama_lengkap',
                    'order' => 1,
                    'metadata' => [
                        'name' => 'fullname',
                        'type' => 'text',
                        'rules' => [
                            'required' => true,
                            'min' => 3
                        ]
                    ]
                ]
            ]
        ];

        $validator = Validator::make($data, $this->getRules());
        $this->assertTrue($validator->passes(), $validator->errors()->first());
    }

    #[Test]
    #[TestDox('Validasi gagal: type di metadata tidak sesuai dengan type di fields')]
    public function validasi_gagal_jika_metadata_type_dikirim_pada_bukan_tipe_input()
    {
        // Mencoba mengirim 'metadata.type' pada tipe 'textarea'
        // Ini harusnya kena 'prohibited_unless:fields.*.type,input'
        $data = [
            'fields' => [
                [
                    'label' => 'Alamat',
                    'type' => 'textarea',
                    'metadata' => [
                        'name' => 'address',
                        'type' => 'text', // Dilarang di sini
                    ]
                ]
            ]
        ];

        $validator = Validator::make($data, $this->getRules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('fields.0.metadata.type', $validator->errors()->toArray());
    }

    #[Test]
    #[TestDox('Validasi gagal: max date kurang dari min date')]
    public function validasi_gagal_jika_max_date_kurang_dari_min_date()
    {
        $data = [
            'fields' => [
                [
                    'label' => 'Tanggal Pinjam',
                    'type' => 'datePicker',
                    'name' => 'borrow_date',
                    'order' => 1,
                    'metadata' => [
                        'rules' => [
                            'min_date' => '2026-04-15',
                            'max_date' => '2026-04-10' // Error: Lebih kecil dari min_date
                        ]
                    ]
                ]
            ]
        ];

        $http = Request::create('http://localhost', 'POST', $data);
        $fr = FieldModifyRequest::createFrom($http, new FieldModifyRequest());
        $validator = Validator::make($fr->all(), $fr->rules());
        $fr->withValidator($validator);

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('Tanggal maksimal tidak boleh lebih kecil', (string) $validator->errors()->first());
    }

    #[Test]
    #[TestDox('Validasi gagal: is_multiple tidak dikirim')]
    public function validasi_wajib_is_multiple_jika_tipe_adalah_select()
    {
        $data = [
            'fields' => [
                [
                    'label' => 'Pilih Kelas',
                    'type' => 'select',
                    'name' => 'class_id',
                    'order' => 1,
                    'metadata' => [
                        // 'is_multiple' sengaja tidak dikirim
                    ]
                ]
            ]
        ];

        $validator = Validator::make($data, $this->getRules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('fields.0.metadata.is_multiple', $validator->errors()->toArray());
    }

    #[Test]
    #[TestDox('Validasi gagal: max lebih kecil dari min di input')]
    public function validasi_gagal_jika_max_lebih_kecil_dari_min_pada_input()
    {
        $data = [
            'fields' => [
                [
                    'label' => 'Umur',
                    'type' => 'input',
                    'name' => 'age',
                    'order' => 1,
                    'metadata' => [
                        'type' => 'number',
                        'rules' => [
                            'min' => 20,
                            'max' => 10 // Error: Harus gt (greater than) min
                        ]
                    ]
                ]
            ]
        ];

        $validator = Validator::make($data, $this->getRules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('fields.0.metadata.rules.max', $validator->errors()->toArray());
    }

    #[Test]
    #[TestDox('Validation gagal: Metadata name wajib ada untuk semua jenis field')]
    public function validation_gagal_jika_metadata_name_absen(): void
    {
        $data = [
            'fields' => [[
                'label' => 'Nama',
                'type' => 'input',
                'order' => 1,
                'metadata' => [
                    'type' => 'text',
                    // 'name' sengaja dihilangkan
                ]
            ]]
        ];

        $validator = Validator::make($data, $this->getRules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('fields.0.name', $validator->errors()->toArray());
    }

    #[Test]
    #[TestDox('Validation gagal: Input type harus sesuai dengan Rule::in (text, number, dll)')]
    public function validation_gagal_jika_input_type_tidak_valid(): void
    {
        $data = [
            'fields' => [[
                'label' => 'Power Level',
                'type' => 'input',
                'name' => 'power',
                'order' => 1,
                'metadata' => [
                    'name' => 'power',
                    'type' => 'invalid-type', // Tidak ada di list Laravel Rule::in
                ]
            ]]
        ];

        $validator = Validator::make($data, $this->getRules());
        $this->assertFalse($validator->passes());
    }

    #[Test]
    #[TestDox('Validation gagal: Textarea dilarang menggunakan metadata regex')]
    public function validation_gagal_jika_textarea_mencoba_pakai_regex(): void
    {
        $data = [
            'fields' => [[
                'label' => 'Bio',
                'type' => 'textarea',
                'name' => 'bio',
                'order' => 1,
                'metadata' => [
                    'rules' => ['regex' => '/^[a-z]$/'] // Prohibited for textarea
                ]
            ]]
        ];

        $validator = Validator::make($data, $this->getRules());
        $this->assertFalse($validator->passes());
    }

    #[Test]
    #[TestDox('Validation berhasil: Select field dengan is_multiple bernilai true')]
    public function validation_berhasil_untuk_select_multiple(): void
    {
        $data = [
            'fields' => [[
                'label' => 'Hobi',
                'type' => 'select',
                'name' => 'hobbies',
                'order' => 1,
                'metadata' => [
                    'is_multiple' => true
                ]
            ]]
        ];

        $validator = Validator::make($data, $this->getRules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    #[TestDox('Validation gagal: Select dilarang menggunakan metadata rules in (hanya untuk runtime)')]
    public function validation_gagal_jika_bukan_select_mencoba_pakai_rule_in(): void
    {
        $data = [
            'fields' => [[
                'label' => 'Nama',
                'type' => 'input',
                'name' => 'name',
                'order' => 1,
                'metadata' => [
                    'type' => 'text',
                    'rules' => ['in' => 'a,b,c'] // Prohibited for input
                ]
            ]]
        ];

        $validator = Validator::make($data, $this->getRules());
        $this->assertFalse($validator->passes());
    }

    #[Test]
    #[TestDox('Validation berhasil: FileUpload dengan mimes dan max_size yang valid')]
    public function validation_berhasil_untuk_fileupload_metadata(): void
    {
        $data = [
            'fields' => [[
                'label' => 'Foto KTP',
                'type' => 'fileUpload',
                'name' => 'identity_card',
                'order' => 1,
                'metadata' => [
                    'rules' => [
                        'mimes' => 'jpg,png,pdf',
                        'max_size' => 2048
                    ]
                ]
            ]]
        ];

        $validator = Validator::make($data, $this->getRules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    #[TestDox('Validasi upload metadata membatasi MIME dan batas multi-upload')]
    public function validation_menolak_upload_metadata_di_luar_allowlist_dan_batas_server(): void
    {
        $data = [
            'fields' => [[
                'label' => 'Dokumen',
                'type' => 'fileUpload',
                'name' => 'documents',
                'order' => 1,
                'metadata' => [
                    'multiple' => true,
                    'allowed_mime_types' => ['application/pdf', 'application/zip'],
                    'max_files' => 6,
                    'max_total_size_kb' => 20 * 1024 + 1,
                    'accepts' => 'pdf,docx',
                    'rules' => [],
                ],
            ]],
        ];

        $validator = Validator::make($data, $this->getRules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('fields.0.metadata.allowed_mime_types.1', $validator->errors()->toArray());
        $this->assertArrayHasKey('fields.0.metadata.max_files', $validator->errors()->toArray());
        $this->assertArrayHasKey('fields.0.metadata.max_total_size_kb', $validator->errors()->toArray());
    }

    #[Test]
    #[TestDox('Validasi tetap menerima metadata accepts legacy')]
    public function validation_berhasil_untuk_upload_dengan_accepts_legacy(): void
    {
        $data = [
            'fields' => [[
                'label' => 'Dokumen',
                'type' => 'fileUpload',
                'name' => 'document',
                'order' => 1,
                'metadata' => [
                    'accepts' => 'pdf,docx',
                    'rules' => ['mimes' => 'pdf,docx', 'max_size' => 5120],
                ],
            ]],
        ];

        $validator = Validator::make($data, $this->getRules());

        $this->assertTrue($validator->passes(), $validator->errors()->first());
    }

    #[Test]
    #[TestDox('Validation gagal: FileUpload dilarang menggunakan metadata min atau max angka')]
    public function validation_gagal_jika_fileupload_pakai_min_max_numeric(): void
    {
        $data = [
            'fields' => [[
                'label' => 'Dokumen',
                'type' => 'fileUpload',
                'name' => 'doc',
                'order' => 1,
                'metadata' => [
                    'rules' => ['min' => 5] // Prohibited for fileUpload
                ]
            ]]
        ];

        $validator = Validator::make($data, $this->getRules());
        $this->assertFalse($validator->passes());
    }

    #[Test]
    #[TestDox('Validation berhasil: DatePicker hanya mengisi max_date tanpa min_date')]
    public function validation_berhasil_datepicker_hanya_max_date(): void
    {
        $data = [
            'fields' => [[
                'label' => 'Deadline',
                'type' => 'datePicker',
                'name' => 'deadline',
                'order' => 1,
                'metadata' => [
                    'rules' => ['max_date' => '2026-12-31']
                ]
            ]]
        ];

        $validator = Validator::make($data, $this->getRules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    #[TestDox('Validation berhasil: Mengirimkan array fields dengan banyak tipe sekaligus')]
    public function validation_berhasil_untuk_multiple_fields_sekaligus(): void
    {
        $data = [
            'fields' => [
                [
                    'label' => 'Nama',
                    'type' => 'input',
                    'name' => 'name',
                    'order' => 1,
                    'metadata' => [
                        'type' => 'text'
                    ]
                ],
                [
                    'label' => 'Umur',
                    'type' => 'input',
                    'name' => 'age',
                    'order' => 2,
                    'metadata' => [
                        'type' => 'number'
                    ]
                ],
                [
                    'label' => 'Bio',
                    'type' => 'textarea',
                    'name' => 'bio',
                    'order' => 3,
                    'metadata' => [
                        'required' => false
                    ]
                ]
            ]
        ];

        $validator = Validator::make($data, $this->getRules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    #[TestDox('Validation gagal: Aturan required pada metadata.rules harus berupa boolean')]
    public function validation_gagal_jika_rule_required_bukan_boolean(): void
    {
        $data = [
            'fields' => [[
                'label' => 'Nama',
                'type' => 'input',
                'name' => 'name',
                'order' => 1,
                'metadata' => [
                    'type' => 'text',
                    'rules' => ['required' => 'yes'] // Harus boolean
                ]
            ]]
        ];

        $validator = Validator::make($data, $this->getRules());
        $this->assertFalse($validator->passes());
    }
}
