<?php

/*
|--------------------------------------------------------------------------
| Member consents (member app)
|--------------------------------------------------------------------------
| Bump a version whenever its text changes in a way members must agree to again: members who agreed to an
| older version get "needs_update" in GET /me/consents. {gym_name} is replaced with the member's gym.
*/

return [
    'progress_photos' => [
        'version' => 1,
        'text' => 'I agree that {gym_name} may store the progress photos I upload to track my transformation. '
            .'Only I and my gym\'s staff can see them. I can delete any photo, or all of them, at any time.',
    ],

    'ai_coach' => [
        'version' => 2,
        'text' => 'Your progress data (weight, measurements, attendance, workout/diet adherence and, for photo comparisons, the photos you choose) '
            .'is sent to an AI provider to write your report. The photos you choose are sent only for photo comparisons and are not used to train AI models; '
            .'providers that store or train on your data are not used. '
            .'Your reports are also visible to your gym admin and your trainer. Your medical notes are never sent. '
            .'You can withdraw consent at any time; your AI reports and progress photos are then deleted.',
    ],
];
