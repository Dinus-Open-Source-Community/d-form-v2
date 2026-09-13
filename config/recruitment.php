<?php

return [
    /*
    | Comma-separated staff emails for correction request notifications.
    | Falls back to users with recruitment.corrections.review permission when empty.
    */
    'staff_notification_email' => env('RECRUITMENT_STAFF_EMAIL'),
];
