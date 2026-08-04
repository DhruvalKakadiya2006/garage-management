<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobCardLabour extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_card_id', 'labour_master_id', 'technician_id', 'rate', 'qty', 'line_total',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'qty' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function jobCard()
    {
        return $this->belongsTo(JobCard::class);
    }

    public function labourMaster()
    {
        return $this->belongsTo(LabourMaster::class);
    }

    public function technician()
    {
        return $this->belongsTo(Employee::class, 'technician_id');
    }
}
