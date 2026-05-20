<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseItem extends Model
{
    public const MODULES = [
        'Transactions',
        'Reports',
        'Accounting Setup',
        'Cash & Bank',
        'System',
    ];

    public const UI_FUNCTIONS = [
        'UI',
        'Function',
        'UI + Function',
    ];

    public const ITEM_TYPES = [
        'New Feature',
        'Enhancement',
        'Bug Fix',
        'Configuration',
        'Report',
    ];

    public const RELEASE_VERSIONS = [
        'Major',
        'Minor',
        'Hotfix',
    ];

    public const STATUSES = [
        'Draft',
        'In Review',
        'Released',
    ];

    protected $fillable = [
        'release_date',
        'module',
        'ui_function',
        'item_type',
        'task',
        'note',
        'user_impact',
        'released_by',
        'release_version',
        'status',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'release_date' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
