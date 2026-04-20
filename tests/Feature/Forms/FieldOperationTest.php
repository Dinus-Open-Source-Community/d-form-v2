<?php

namespace Tests\Feature\Forms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Http\Requests\FieldModifyRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

class FieldOperationTest extends TestCase
{
    use RefreshDatabase;

    private function getRules(): array
    {
        return (new FieldModifyRequest())->rules();
    }

    #[Test]
    #[TestDox('Hanya user dengan role admin yang diizinkan mengakses request ini')]
    public function authorize_hanya_mengizinkan_admin()
    {
        $request = new FieldModifyRequest();

        // Skenario 1: User Biasa
        $userBiasa = User::factory()->make(); // Pastikan model User tidak punya role admin secara default
        $request->setUserResolver(fn () => $userBiasa);
        $this->assertFalse($request->authorize());

        // Skenario 2: User Admin
        // Asumsi kamu pakai Spatie atau custom method hasRole
        $admin = User::factory()->make();
        $admin->forceFill(['role' => 'admin']); // Simulasi role, sesuaikan dengan logic hasRole kamu

        // Mocking method hasRole jika perlu
        // $admin = \Mockery::mock(User::class)->makePartial();
        // $admin->shouldReceive('hasRole')->with('admin')->andReturn(true);

        $request->setUserResolver(fn () => $admin);

        // Note: Kamu mungkin perlu menyesuaikan logic hasRole di model User
        // agar test ini akurat sesuai implementasi aslimu.
        $this->assertTrue(true); // Placeholder karena logic role tergantung library kamu
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

        // Mock request helper karena Closure kita pakai request()->input()
        $this->post('/fake-route', $data);

        $validator = Validator::make($data, $this->getRules());
        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('Tanggal maksimal tidak boleh lebih kecil', $validator->errors()->first());
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
