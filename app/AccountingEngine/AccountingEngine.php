<?php

namespace App\AccountingEngine;

use App\AccountingEngine\Contracts\AccountingEngineContract;
use App\AccountingEngine\DTO\PostingPreview;
use App\AccountingEngine\DTO\PostingResult;
use App\AccountingEngine\DTO\TransactionInput;
use App\Services\Accounting\TransactionPostingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class AccountingEngine implements AccountingEngineContract
{
    public function __construct(
        private readonly TransactionPostingService $legacyPostingService,
    ) {
    }

    public function preview(TransactionInput $input): PostingPreview
    {
        $preview = $this->legacyPostingService->preview(
            $input->toLegacyPayload(),
            $input->userId,
            $input->isDraft()
        );

        return PostingPreview::fromLegacyPreview($preview);
    }

    public function post(TransactionInput $input, ?UploadedFile $attachment = null): PostingResult
    {
        return DB::transaction(function () use ($input, $attachment): PostingResult {
            $voucher = $this->legacyPostingService->save(
                $input->toLegacyPayload(),
                $attachment,
                $input->userId
            );

            return PostingResult::fromVoucher($voucher);
        });
    }
}
