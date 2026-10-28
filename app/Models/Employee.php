<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $guarded = [];
    protected $casts = ['active' => 'boolean'];

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }

    public function templates()
    {
        return $this->hasMany(ShiftTemplate::class)->orderBy('weekday');
    }

    public function getInitialsAttribute(): string
    {
        $p = preg_split('/\s+/', trim($this->name));

        return mb_strtoupper(mb_substr($p[0], 0, 1).(count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
    }

    public function getFirstNameAttribute(): string
    {
        return explode(' ', trim($this->name))[0];
    }
}
