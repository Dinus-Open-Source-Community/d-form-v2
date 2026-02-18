<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EventCategory extends Model
{
    use HasUuids;

    public $keyType = 'string';

    protected $fillable = [
        'name',
        'description'
    ];
}
