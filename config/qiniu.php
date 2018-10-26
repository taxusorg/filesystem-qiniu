<?php

return [

    'default' => 'default',

    'disks' => [

        'default' => [
            'key' => env('QINIU_KEY'),
            'secret' => env('QINIU_SECRET'),
            'buckets' => [
                'your_bucket_name' => [
                    'domain' => 'your_bucket_url'
                ],
                /*
                'your_bucket_name2' => [
                    'domain' => 'your_bucket_url2'
                ],
                */
            ],
            'default' => 'your_bucket_name',
        ],

    ],
];
