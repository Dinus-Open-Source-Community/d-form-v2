# Kontrak

## Isi dynamic field untuk dikirimkan ke backend

null berarti boleh tidak diisi / dikirim. Otomatis tidak akan disimpan ke database (kecuali id karena kalau id tidak dikirim, berarti insert. kalau id dikirim, berarti update)

```json
{
    "id": "..." | null,
    "label": "...",
    "description": "..." | null,
    "type": "input" | "select" | "textarea" | "datePicker" | "fileUpload",
    "metadata": {
        "placeholder": "..." | null,
        "name": "...",
        "type": "text" | "email" | "number" | "password" | "tel" | null // null untuk selain input
        "rules": {
            // lihat di bagian rules dynamic field
        }
    }
}
```

### Rules untuk dynamic field

null berarti boleh tidak diisi / dikirim. Otomatis tidak ada validasi untuk bagian itu nantinya dari Backend

```json
{
    "required": true | false | null,

    // type input
    "min": 0,
    "max": 0,
    "regex": "...",

    // type select
    "in": "opsi_1,opsi_2,...",
    "multiple": true | false,

    // type textarea
    "min": 0,
    "max": 0,

    // type datePicker
    "min_date": "YYYY-MM-DD" | null,
    "max_date": "YYYY-MM-DD" | null,

    // type fileUpload
    "mimes": "opsi_1,opsi_2,..." | null,
    "max_size": 0 | null
}
```

## Form answers

```json
{
    "[name]": "Value",
    ...
}
```
