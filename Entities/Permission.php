<?php

namespace Modules\Permission\Entities;



class Permission extends \Spatie\Permission\Models\Permission
{
    protected $casts = [
        'is_active' => 'boolean',
    ];
}
