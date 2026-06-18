<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AccountingNotificationService
{
    public function __construct(private readonly PusherChannelsService $pusher)
    {
    }

    /** @return Collection<int, User> */
    public function companyAdministrators(int $companyId): Collection
    {
        if (! Schema::hasTable('users')) {
            return collect();
        }

        $query = User::query()->where('company_id', $companyId);

        if (Schema::hasColumn('users', 'account_status')) {
            $query->where(function ($statusQuery): void {
                $statusQuery->where('account_status', User::ACCOUNT_STATUS_ACTIVE)
                    ->orWhereNull('account_status');
            });
        }

        if (Schema::hasTable('accounting_roles') && Schema::hasColumn('users', 'accounting_role_id')) {
            $query->whereHas('accountingRole', fn ($roleQuery) => $roleQuery
                ->whereIn('slug', ['super_admin', 'admin_user'])
                ->where('is_active', true));
        } else {
            $query->where('role', User::ROLE_SYSTEM_ADMIN);
        }

        return $query->get();
    }

    public function notifyCompanyAdministrators(int $companyId, array $data, ?string $dedupeKey = null): int
    {
        return $this->notifyUsers($this->companyAdministrators($companyId), $data, $dedupeKey);
    }

    public function notifyUserIdsAndCompanyAdministrators(
        int $companyId,
        array $userIds,
        array $data,
        ?string $dedupeKey = null
    ): int {
        $ids = collect($userIds)->map(fn ($id): int => (int) $id)->filter()->unique()->all();

        $users = User::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->get()
            ->merge($this->companyAdministrators($companyId))
            ->unique('id')
            ->values();

        return $this->notifyUsers($users, $data, $dedupeKey);
    }

    public function notifyUsers(iterable $users, array $data, ?string $dedupeKey = null): int
    {
        if (! Schema::hasTable('notifications')) {
            return 0;
        }

        $sent = 0;
        $normalized = $this->normalizeData($data);

        foreach (collect($users)->filter()->unique('id') as $user) {
            if (! $user instanceof User || ! $user->isAccountActive()) {
                continue;
            }

            if ($dedupeKey !== null && $this->alreadyDelivered((int) $user->id, $dedupeKey)) {
                continue;
            }

            $notificationId = (string) Str::uuid();
            $now = now();

            DB::transaction(function () use ($user, $normalized, $notificationId, $now, $dedupeKey): void {
                DB::table('notifications')->insert([
                    'id' => $notificationId,
                    'type' => 'hisebghor.accounting',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'data' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'read_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($dedupeKey !== null && Schema::hasTable('accounting_notification_deliveries')) {
                    DB::table('accounting_notification_deliveries')->insert([
                        'user_id' => $user->id,
                        'dedupe_key' => $dedupeKey,
                        'notification_id' => $notificationId,
                        'delivered_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }, 3);

            $this->pusher->triggerUser((int) $user->id, 'hisebghor-notification', [
                'id' => $notificationId,
                'data' => $normalized,
                'read_at' => null,
                'created_at' => $now->toIso8601String(),
            ]);

            $sent++;
        }

        return $sent;
    }

    private function alreadyDelivered(int $userId, string $dedupeKey): bool
    {
        return Schema::hasTable('accounting_notification_deliveries')
            && DB::table('accounting_notification_deliveries')
                ->where('user_id', $userId)
                ->where('dedupe_key', $dedupeKey)
                ->exists();
    }

    /** @return array<string, string> */
    private function normalizeData(array $data): array
    {
        return [
            'title' => trim((string) ($data['title'] ?? 'HisebGhor Notification')),
            'message' => trim((string) ($data['message'] ?? '')),
            'category' => trim((string) ($data['category'] ?? 'system')),
            'icon' => trim((string) ($data['icon'] ?? '🔔')),
            'url' => trim((string) ($data['url'] ?? '')),
            'actor_name' => trim((string) ($data['actor_name'] ?? '')),
            'resource' => trim((string) ($data['resource'] ?? '')),
            'resource_code' => trim((string) ($data['resource_code'] ?? '')),
            'action' => trim((string) ($data['action'] ?? '')),
            'occurred_at' => (string) ($data['occurred_at'] ?? now()->toIso8601String()),
        ];
    }
}
