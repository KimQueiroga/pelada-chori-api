<?php

return [
    'vapid_public_key' => env('WEBPUSH_VAPID_PUBLIC_KEY'),
    'vapid_private_key' => env('WEBPUSH_VAPID_PRIVATE_KEY'),
    'vapid_subject' => env('WEBPUSH_VAPID_SUBJECT', env('APP_URL')),
];
