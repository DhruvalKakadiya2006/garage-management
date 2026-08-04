<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobCardComplaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_card_id', 'description', 'charge',
    ];

    protected $casts = [
        'charge' => 'decimal:2',
    ];

    public function jobCard()
    {
        return $this->belongsTo(JobCard::class);
    }
}
