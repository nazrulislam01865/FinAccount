<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPageInquiry extends Model
{
    public const STATUS_NEW = 'New';
    public const STATUS_CONTACTED = 'Contacted';
    public const STATUS_CLOSED = 'Closed';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_CLOSED,
    ];

    protected $fillable = [
        'name',
        'business_name',
        'mobile',
        'email',
        'message',
        'status',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
