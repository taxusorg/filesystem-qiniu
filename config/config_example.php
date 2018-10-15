<?php
/**
 * Add to Laravel/config/filesystems.php
 */
return [

    'disks' => [

        'diskname' => [
            'driver' => 'qiniu',
            'key' => 'your key',
            'secret' => 'your secret',
            'bucket' => 'your bucket',
            'visibility' => 'public',
            'domain' => 'yout domain with Qiniu',
        ],

    ],

];
