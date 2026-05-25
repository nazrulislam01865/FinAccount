<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportExport extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'report_name',
        'filters_json',
        'status',
        'file_path',
        'error_message',
        'requested_at',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'filters_json' => 'array',
        'requested_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
