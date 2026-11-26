<?php

namespace App\Enums;

enum FormPurpose: string
{
    case Registration = 'registration';
    case AnswerCollection = 'answer_collection';
    case Feedback = 'feedback';
    case DKelas = 'd_kelas';
    /** Nilai lama: tetap dibaca agar data yang sudah ada tidak berubah perilaku. */
    case Other = 'other';

    /**
     * Purpose yang membuat jawaban pendaftaran: satu submission per user per event.
     *
     * @return list<string>
     */
    public static function registrationValues(): array
    {
        return [self::Registration->value, self::DKelas->value];
    }
}
