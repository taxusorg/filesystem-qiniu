<?php
/**
 * Add to Laravel/config/filesystems.php
 */
return [

    'disks' => [

        'diskname' => [
            'driver' => 'qiniu',
            //'disk' => 'set in qiniu', //disk name set in config/qiniu.php Or don't set if use defalut
            //'bucket' => 'set in qiniu', //bucket name set in config/qiniu.php Or don't set if use defalut
        ],

    ],

];
