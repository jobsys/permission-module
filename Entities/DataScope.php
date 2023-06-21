<?php

namespace Modules\Permission\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;


class DataScope extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scope' => 'array'
    ];

    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }
}
