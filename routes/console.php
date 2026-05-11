<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Accounting Sprint 1 is ready.');
})->purpose('Display a short inspirational message');
