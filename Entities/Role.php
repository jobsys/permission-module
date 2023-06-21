<?php

namespace Modules\Permission\Entities;

use Modules\Permission\Traits\Scopeable;

class Role extends \Spatie\Permission\Models\Role
{
    use Scopeable;
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public $appends = [
        'type'
    ];

    public function getTypeAttribute(): string
    {
        return 'role';
    }
}
