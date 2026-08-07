<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobCardComplaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_card_id', 'description',
    ];

    public function jobCard()
    {
        return $this->belongsTo(JobCard::class);
    }
}
