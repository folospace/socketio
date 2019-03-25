<?php

return [
    'server' => [
        'host' => '0.0.0.0',
        'port' => 3001,
        'token' => env('APP_KEY', 'forwarding_token'),
        'config' => [
            'dispatch_mode' => 2,
            'worker_num' => 4,
            'heartbeat_check_interval' => 10,   //10 seconds
            'heartbeat_idle_time' => 20,        //20 seconds
        ],
    ],

    'protocol' => [
        'config' => [
            'sid' => 'socketio',
            'upgrades' => ['websocket'],
            'pingInterval' => 10000,    //10 seconds
            'pingTimeout' => 20000,     //20 seconds
        ],
    ],

    'redis' => 'default',
];