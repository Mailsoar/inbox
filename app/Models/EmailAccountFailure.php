<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailAccountFailure extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_account_id',
        'failure_type',
        'error_message',
        'failure_count',
        'retry_after',
        'is_disabled'
    ];

    protected $casts = [
        'retry_after' => 'datetime',
        'is_disabled' => 'boolean',
    ];

    /**
     * Get the email account associated with this failure
     */
    public function emailAccount()
    {
        return $this->belongsTo(EmailAccount::class);
    }
}