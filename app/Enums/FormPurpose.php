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
}
