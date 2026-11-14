<?php

return [
    /* Broadcast email is disabled by default: routes, policy, scheduler, jobs,
     * and UI are gated. FEATURE_BROADCAST=true re-enables it; transactional email is unaffected. */
    'broadcast' => (bool) env('FEATURE_BROADCAST', false),
];
