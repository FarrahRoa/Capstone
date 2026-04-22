<?php

return [
    'roles' => [
        'student' => [
            'calendar.view',
            'reservation.create',
            'reservation.view_own',
        ],
        'faculty' => [
            'calendar.view',
            'reservation.create',
            'reservation.view_own',
        ],
        'staff' => [
            'calendar.view',
            'reservation.create',
            'reservation.view_own',
        ],
        'student_assistant' => [
            'calendar.view',
            'reservation.view_all',
            'reservation.approve',
            'reservation.reject',
        ],
        'librarian' => [
            'calendar.view',
            'reservation.view_all',
            'reservation.approve',
            'reservation.reject',
            'reports.view',
            'spaces.manage',
        ],
        'admin' => [
            'calendar.view',
            'reservation.create',
            'reservation.view_own',
            'reservation.view_all',
            'reservation.approve',
            'reservation.reject',
            'reservation.override',
            'reports.view',
            'reports.export',
            'spaces.manage',
            'users.manage',
            'policies.manage',
            'system.cloud_sync',
        ],
    ],
];
