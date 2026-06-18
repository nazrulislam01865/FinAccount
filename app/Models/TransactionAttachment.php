<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TransactionAttachment extends Model
{
    protected $fillable = [
        'company_id',
        'transaction_id',
        'uploaded_by',
        'original_name',
        'stored_path',
        'mime_type',
        'size_bytes',
        'is_image',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_image' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (TransactionAttachment $attachment): void {
            if ($attachment->stored_path !== '') {
                Storage::disk('public')->delete($attachment->stored_path);
            }
        });
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->original_name ?: basename($this->stored_path);
    }
}
