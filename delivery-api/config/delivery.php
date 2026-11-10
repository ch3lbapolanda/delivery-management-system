<?php

return [
    'road_factor' => 1.3,

    'services' => [
        'instant' => [
            'base_price' => 5000,
            'price_per_km' => 2000,
            'additional_fee' => 3000,
            'minimum_price' => 10000,
        ],

        'scheduled' => [
            'base_price' => 4000,
            'price_per_km' => 1500,
            'additional_fee' => 0,
            'minimum_price' => 8000,
        ],
    ],
];