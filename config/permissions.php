<?php

return [
    [
        'name' => 'T p uploaders',
        'flag' => 'tpuploader.index',
    ],
    [
        'name' => 'Create',
        'flag' => 'tpuploader.create',
        'parent_flag' => 'tpuploader.index',
    ],
    [
        'name' => 'Edit',
        'flag' => 'tpuploader.edit',
        'parent_flag' => 'tpuploader.index',
    ],
    [
        'name' => 'Delete',
        'flag' => 'tpuploader.destroy',
        'parent_flag' => 'tpuploader.index',
    ],
];
