<?php

namespace App\Services\Registration;

use App\Enums\EmailNotificationType;
use App\Enums\FormPurpose;
use App\Mail\AnswerCollectionReceivedMail;
use App\Mail\DkelasAttendanceCardMail;
use App\Mail\FeedbackReceivedMail;
use App\Mail\RegistrationConfirmationMail;
use App\Models\Form;
use App\Models\FormAnswer;
use Illuminate\Mail\Mailable;

final class FormReceiptMailResolver
{
    public function notificationType(Form $form): EmailNotificationType
    {
        return match ($form->purpose()) {
            FormPurpose::AnswerCollection => EmailNotificationType::AnswerCollectionReceived,
            FormPurpose::Feedback => EmailNotificationType::FeedbackReceived,
            FormPurpose::DKelas => EmailNotificationType::ClassAttendanceCard,
            FormPurpose::Registration, FormPurpose::Other => EmailNotificationType::RegistrationSubmitted,
        };
    }

    public function make(FormAnswer $submission, FormReceiptContext $context): Mailable
    {
        $submission->loadMissing('form');

        return match ($submission->form->purpose()) {
            FormPurpose::AnswerCollection => new AnswerCollectionReceivedMail($submission, $context),
            FormPurpose::Feedback => new FeedbackReceivedMail($submission, $context),
            FormPurpose::DKelas => new DkelasAttendanceCardMail($submission, $context),
            FormPurpose::Registration, FormPurpose::Other => new RegistrationConfirmationMail(
                $submission,
                $context->answersSummary,
                $context->qrPngBinary,
            ),
        };
    }
}
