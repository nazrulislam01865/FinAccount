<?php

namespace App\Services\Accounting;

use App\Models\Transaction;
use App\Models\TransactionAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TransactionAttachmentService
{
    /**
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     */
    public function storeUploaded(Transaction $transaction, array|UploadedFile|null $files, User $user): void
    {
        if ($transaction->company_id !== $user->company_id) {
            abort(404);
        }

        $fileList = $files instanceof UploadedFile ? [$files] : ($files ?? []);
        $fileList = array_values(array_filter($fileList, fn ($file): bool => $file instanceof UploadedFile && $file->isValid()));

        foreach ($fileList as $file) {
            $this->storeOne($transaction, $file, $user);
        }
    }

    public function delete(TransactionAttachment $attachment, User $user): void
    {
        if ($attachment->company_id !== $user->company_id) {
            abort(404);
        }

        $attachment->delete();
    }

    private function storeOne(Transaction $transaction, UploadedFile $file, User $user): TransactionAttachment
    {
        $size = $file->getSize() ?: 0;
        $originalName = $file->getClientOriginalName();

        $duplicate = $transaction->attachments()
            ->where('original_name', $originalName)
            ->where('size_bytes', $size)
            ->exists();

        if ($duplicate) {
            return $transaction->attachments()
                ->where('original_name', $originalName)
                ->where('size_bytes', $size)
                ->firstOrFail();
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'file');
        $safeBaseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'attachment';
        $fileName = $safeBaseName.'-'.Str::random(12).'.'.$extension;
        $directory = 'transaction-attachments/company-'.$transaction->company_id.'/transaction-'.$transaction->id;
        $path = $file->storeAs($directory, $fileName, 'public');

        return $transaction->attachments()->create([
            'company_id' => $transaction->company_id,
            'uploaded_by' => $user->id,
            'original_name' => $originalName,
            'stored_path' => $path,
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'size_bytes' => $size ?: Storage::disk('public')->size($path),
            'is_image' => str_starts_with((string) ($file->getClientMimeType() ?: $file->getMimeType()), 'image/'),
        ]);
    }
}
