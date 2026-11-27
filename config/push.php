<?php

/*
|--------------------------------------------------------------------------
| Member app push notifications (Firebase Cloud Messaging)
|--------------------------------------------------------------------------
| Each logical channel (PushMessage::CHANNELS) maps to the Android notification channel the app creates
| and the sound file it ships (res/raw, without extension). Android 8+ plays the channel's own sound;
| "sound" is used by older Android versions. Change an id only together with the app.
*/

return [
    'channels' => [
        // Gym / general notifications, new program, AI report ready, test pushes.
        'general' => ['id' => 'fitz_general', 'sound' => 'fitz_chime'],
        // Meal, workout, streak, check-in and weigh-in reminders.
        'reminders' => ['id' => 'fitz_reminders', 'sound' => 'fitz_chime'],
        // Water reminders.
        'water' => ['id' => 'fitz_water', 'sound' => 'water_drop'],
        // Membership, payment and renewal.
        'membership' => ['id' => 'fitz_membership', 'sound' => 'fitz_chime'],
        // Gym announcements.
        'announcements' => ['id' => 'fitz_announcements', 'sound' => 'fitz_chime'],
    ],
];
