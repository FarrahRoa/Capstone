<?php

return [
    'cookie' => env('TRUSTED_DEVICE_COOKIE', 'xu_trusted_device'),
    'lifetime_days' => max(1, (int) env('TRUSTED_DEVICE_LIFETIME_DAYS', 30)),
];
