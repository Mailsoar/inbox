<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailFolderMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_account_id',
        'email_provider_id',
        'folder_type',
        'folder_name',
        'display_name',
        'is_additional_inbox',
        'sort_order',
    ];

    protected $casts = [
        'is_additional_inbox' => 'boolean',
    ];

    /**
     * The email account this mapping belongs to
     */
    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    /**
     * The email provider this mapping belongs to
     */
    public function emailProvider(): BelongsTo
    {
        return $this->belongsTo(EmailProvider::class);
    }
}