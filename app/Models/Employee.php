<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'role', 'mobile', 'specialization', 'active', 'join_date',
    ];

    protected $casts = [
        'active' => 'boolean',
        'join_date' => 'date',
    ];

    /**
     * Scope to get only active employees
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
