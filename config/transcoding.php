<?php

return [
    'cleanup_after_hours' => env('CLEANUP_AFTER_HOURS', 24),
    
    'quality_settings' => [
        '1080p' => ['width' => 1920, 'height' => 1080, 'bitrate' => '5000k'],
        '720p' => ['width' => 1280, 'height' => 720, 'bitrate' => '2500k'],
        '480p' => ['width' => 854, 'height' => 480, 'bitrate' => '1000k'],
        '360p' => ['width' => 640, 'height' => 360, 'bitrate' => '750k'],
        '240p' => ['width' => 426, 'height' => 240, 'bitrate' => '500k'],
    ],
];
