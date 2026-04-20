<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FieldModifyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(
            $this->field(),
            $this->fieldGlobalMetadata(),
            $this->fieldRulesMetadata()
        );
    }

    public function field()
    {
        return [
            'fields.*.id' => 'nullable',
            'fields.*.label' => 'required|max:100',
            'fields.*.description' => 'nullable|max:1000',
            'fields.*.name' => 'required',
            'fields.*.type' => 'required|string', // types for field
            'fields.*.metadata' => 'required|array',
            'fields.*.order' => 'required|numeric'
        ];
    }

    public function fieldGlobalMetadata()
    {
        return [
            'fields.*.metadata.placeholder' => 'nullable',
            'fields.*.metadata.type' => [
                'required_if:fields.*.type,input',
                'prohibited_unless:fields.*.type,input',
                Rule::in('text', 'number', 'email', 'password', 'tel')
            ], // types for input
            'fields.*.metadata.is_multiple' => [
                'required_if:fields.*.type,select',
                'prohibited_unless:fields.*.type,select',
                'boolean'
            ],

        ];
    }

    public function fieldRulesMetadata()
    {
        return [
            'fields.*.metadata.rules' => 'nullable|array',

            // 1. Universal Rule
            'fields.*.metadata.rules.required' => 'nullable|boolean',

            // 2. Teks & Angka (input, textarea)
            'fields.*.metadata.rules.min' => [
                'nullable',
                'integer',
                'min:0',
                'prohibited_unless:fields.*.type,input,textarea'
            ],
            'fields.*.metadata.rules.max' => [
                'nullable',
                'integer',
                'gt:fields.*.metadata.rules.min',
                'prohibited_unless:fields.*.type,input,textarea'
            ],

            // 3. Regex (input)
            'fields.*.metadata.rules.regex' => [
                'nullable',
                'string',
                'prohibited_unless:fields.*.type,input'
            ],

            // 4. Select
            'fields.*.metadata.rules.in' => [
                'nullable',
                'string', // format: "opsi1,opsi2,opsi3"
                'prohibited_unless:fields.*.type,select'
            ],

            // 5. Multiple (select, fileUpload)
            'fields.*.metadata.rules.multiple' => [
                'nullable',
                'boolean',
                'prohibited_unless:fields.*.type,select,fileUpload'
            ],

            // 6. Date (datePicker)
            'fields.*.metadata.rules.min_date' => [
                'nullable',
                'date',
                'prohibited_unless:fields.*.type,datePicker'
            ],
            'fields.*.metadata.rules.max_date' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    // Ambil index dari attribute (contoh: "fields.0.metadata.rules.max_date")
                    //---------------------------------------------^
                    $index = explode('.', $attribute)[1];

                    // Ambil nilai min_date dari input request pada index yang sama
                    $minDate = request()->input("fields.{$index}.metadata.rules.min_date");

                    // Validasi HANYA jika min_date ada isinya
                    if ($minDate && strtotime($value) < strtotime($minDate)) {
                        $fail("Tanggal maksimal tidak boleh lebih kecil dari tanggal minimal (" . $minDate . ").");
                    }
                },
                'prohibited_unless:fields.*.type,datePicker'
            ],

            // 7. File Upload
            'fields.*.metadata.rules.mimes' => [
                'nullable',
                'string', // format: "pdf,png,jpg"
                'prohibited_unless:fields.*.type,fileUpload'
            ],
            'fields.*.metadata.rules.max_size' => [
                'nullable',
                'integer',
                'min:1',
                'prohibited_unless:fields.*.type,fileUpload'
            ],
        ];
    }
}
