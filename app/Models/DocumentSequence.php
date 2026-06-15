<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = ['company_id', 'category', 'prefix', 'next_number', 'padding'];
}
