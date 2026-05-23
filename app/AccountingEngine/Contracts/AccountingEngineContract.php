<?php

namespace App\AccountingEngine\Contracts;

use App\AccountingEngine\DTO\PostingPreview;
use App\AccountingEngine\DTO\PostingResult;
use App\AccountingEngine\DTO\TransactionInput;
use Illuminate\Http\UploadedFile;

interface AccountingEngineContract
{
    public function preview(TransactionInput $input): PostingPreview;

    public function post(TransactionInput $input, ?UploadedFile $attachment = null): PostingResult;
}
