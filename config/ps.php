<?php

return [
    'year_zero' => 1989,

    'admin_tools_password' => env('ADMIN_TOOLS_PASSWORD'),

    'colors' => [
        'Monday' => 'rose',
        'Tuesday' => 'blue',
        'Wednesday' => 'yellow',
        'Thursday' => 'purple',
        'Friday' => 'cyan',
        'Saturday' => 'lime',
        'Sunday' => 'orange',
    ],

    'special_channel_suffixes' => [
        '00' => 'Emergencies',
        '01' => 'Coordinator Announcements',
        '02' => 'Coordinator + Shift Lead Announcements',
        '03' => 'Test Messages',
        '10' => 'Monday Off-Bands',
        '11' => 'Monday Breakfast',
        '12' => 'Monday Lunch',
        '13' => 'Monday Dinner',
        '14' => 'Monday Snacks',
        '19' => 'Monday All Wristband Groups',
        '20' => 'Tuesday Off-Bands',
        '21' => 'Tuesday Breakfast',
        '22' => 'Tuesday Lunch',
        '23' => 'Tuesday Dinner',
        '24' => 'Tuesday Snacks',
        '29' => 'Tuesday All Wristband Groups',
        '30' => 'Wednesday Off-Bands',
        '31' => 'Wednesday Breakfast',
        '32' => 'Wednesday Lunch',
        '33' => 'Wednesday Dinner',
        '34' => 'Wednesday Snacks',
        '39' => 'Wednesday All Wristband Groups',
        '40' => 'Thursday Off-Bands',
        '41' => 'Thursday Breakfast',
        '42' => 'Thursday Lunch',
        '43' => 'Thursday Dinner',
        '44' => 'Thursday Snacks',
        '49' => 'Thursday All Wristband Groups',
        '50' => 'Friday Off-Bands',
        '51' => 'Friday Breakfast',
        '52' => 'Friday Lunch',
        '53' => 'Friday Dinner',
        '54' => 'Friday Snacks',
        '59' => 'Friday All Wristband Groups',
        '60' => 'Saturday Off-Bands',
        '61' => 'Saturday Breakfast',
        '62' => 'Saturday Lunch',
        '63' => 'Saturday Dinner',
        '64' => 'Saturday Snacks',
        '69' => 'Saturday All Wristband Groups',
        '70' => 'Sunday Off-Bands',
        '71' => 'Sunday Breakfast',
        '72' => 'Sunday Lunch',
        '73' => 'Sunday Dinner',
        '74' => 'Sunday Snacks',
        '79' => 'Sunday All Wristband Groups',
        '81' => 'Monday (After Sale) Breakfast',
        '82' => 'Monday (After Sale) Lunch',
        '83' => 'Monday (After Sale) Dinner',
        '84' => 'Monday (After Sale) Snacks',
        '99' => 'Firehose',
    ],

    'group_zero' => [
        'Thursday' => [
            'shift_end_timestamps' => [
                ['14:00', '14:30'],
            ],
            'shift_start_timestamps' => [
                ['16:30', '20:30'],
            ],
        ],
    ],

    'anchor' => [
        'weekday' => 'Sunday',
        'month' => 5,
        'occurrence_in_month' => 2,
        'anchor_to' => 'end',
    ],

    'hours' => [
        'Thursday' => [
            'gates' => '12:00',
            'wristbands' => '14:00',
            'off_bands_estimate' => '16:00',
            'open' => '14:30',
            'close' => '20:30',
        ],
        'Friday' => [
            'gates' => '04:00',
            'wristbands' => '06:30',
            'open' => '09:00',
            'close' => '20:00',
        ],
        'Saturday' => [
            'gates' => '06:00',
            'wristbands' => '08:00',
            'open' => '10:00',
            'close' => '18:00',
        ],
        'Sunday' => [
            'gates' => '06:00',
            'wristbands' => '09:00',
            'open' => '10:00',
            'close' => '14:00',
        ],
    ],

    'historical_clear_rates' => [
        'Thursday' => 10,
        'Friday' => 7.4,
        'Saturday' => 6.2,
        'Sunday' => 4.1,
    ],

    'historical_group_counts' => [
        'Thursday' => 5,
        'Friday' => 50,
        'Saturday' => 30,
        'Sunday' => 15,
    ],
];
