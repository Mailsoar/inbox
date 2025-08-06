<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessingMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_id',
        'total_accounts',
        'processed_accounts',
        'failed_accounts',
        'total_emails_found',
        'processing_time_ms',
        'error_details',
        'provider_stats'
    ];

    protected $casts = [
        'error_details' => 'array',
        'provider_stats' => 'array',
    ];
}