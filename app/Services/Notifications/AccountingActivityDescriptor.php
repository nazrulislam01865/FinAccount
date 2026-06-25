<?php

namespace App\Services\Notifications;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class AccountingActivityDescriptor
{
    /** @var array<string, array{label:string,index:string,parameters?:array<string,string>}> */
    private const MODULES = [
        'transactions' => ['label' => 'Transaction', 'index' => 'transactions.index'],
        'company-setup' => ['label' => 'Company Setup', 'index' => 'company-setup.edit'],
        'chart-of-accounts' => ['label' => 'Chart of Accounts', 'index' => 'chart-of-accounts.index'],
        'money-accounts' => ['label' => 'Money Account', 'index' => 'money-accounts.index'],
        'parties' => ['label' => 'Party', 'index' => 'parties.index'],
        'accounting-rules' => ['label' => 'Accounting Rule', 'index' => 'accounting-rules.index'],
        'transaction-heads' => ['label' => 'Transaction Head', 'index' => 'transaction-heads.index'],
        'master.business-types' => ['label' => 'Business Type', 'index' => 'master.business-types.index'],
        'master.currencies' => ['label' => 'Currency', 'index' => 'master.currencies.index'],
        'master.time-zones' => ['label' => 'Time Zone', 'index' => 'master.time-zones.index'],
        'master.financial-years' => ['label' => 'Financial Year', 'index' => 'master.financial-years.index'],
        'master.voucher-sequences' => ['label' => 'Voucher Numbering', 'index' => 'master.voucher-sequences.index'],
        'system.users' => ['label' => 'User', 'index' => 'system.users.index'],
        'system.role-matrix' => ['label' => 'Role Matrix', 'index' => 'system.role-matrix.index'],
        'system.settings' => ['label' => 'Brand Settings', 'index' => 'system.settings.index'],
        'dashboard.reset-demo' => ['label' => 'Demo Data', 'index' => 'dashboard'],
    ];

    /**
     * @return array{
     *   title:string,message:string,category:string,icon:string,url:string,
     *   actor_name:string,resource:string,resource_code:string,action:string
     * }|null
     */
    public function describe(Request $request): ?array
    {
        $routeName = (string) $request->route()?->getName();
        $module = $this->moduleConfiguration($routeName, $request);
        $actor = $request->user();

        if ($module === null || $actor === null) {
            return null;
        }

        $action = $this->action($request, $routeName);
        $recordCode = $this->recordCode($request);
        $message = trim($actor->name.' '.$action.' '.$module['label']);
        if ($recordCode !== '') {
            $message .= ' '.$recordCode;
        }
        $message .= '.';

        return [
            'title' => $module['label'].' '.Str::headline($action),
            'message' => $message,
            'category' => 'activity',
            'icon' => match ($action) {
                'created' => '➕',
                'deleted' => '🗑️',
                'reset' => '♻️',
                default => '✏️',
            },
            'url' => $this->moduleUrl($module),
            'actor_name' => (string) $actor->name,
            'resource' => $routeName,
            'resource_code' => $recordCode,
            'action' => $action,
        ];
    }

    public function supports(Request $request): bool
    {
        return $this->moduleConfiguration((string) $request->route()?->getName(), $request) !== null;
    }

    /** @return array{label:string,index:string,parameters?:array<string,string>}|null */
    private function moduleConfiguration(string $routeName, Request $request): ?array
    {
        if (Str::startsWith($routeName, 'master.') && in_array($routeName, ['master.store', 'master.update', 'master.destroy'], true)) {
            $section = (string) $request->route('section');
            $labels = [
                'party-types' => 'Party Type',
                'money-account-types' => 'Money Account Type',
                'transaction-categories' => 'Transaction Type',
            ];

            return isset($labels[$section])
                ? ['label' => $labels[$section], 'index' => 'master.index', 'parameters' => ['section' => $section]]
                : null;
        }

        foreach (self::MODULES as $prefix => $configuration) {
            if ($routeName === $prefix || Str::startsWith($routeName, $prefix.'.')) {
                return $configuration;
            }
        }

        return null;
    }

    private function action(Request $request, string $routeName): string
    {
        if ($routeName === 'dashboard.reset-demo') {
            return 'reset';
        }

        if ($request->isMethod('DELETE') || Str::endsWith($routeName, '.destroy')) {
            return 'deleted';
        }

        if ($request->isMethod('PUT') || $request->isMethod('PATCH') || Str::endsWith($routeName, ['.update', '.logo', '.favicon'])) {
            return 'updated';
        }

        if (Str::endsWith($routeName, ['.store', '.roles.store'])) {
            return 'created';
        }

        return 'updated';
    }

    private function recordCode(Request $request): string
    {
        foreach ((array) $request->route()?->parameters() as $value) {
            if (is_object($value)) {
                foreach (['code', 'account_code', 'name', 'id'] as $attribute) {
                    if (filled($value->{$attribute} ?? null)) {
                        return trim((string) $value->{$attribute});
                    }
                }
            }

            if (is_scalar($value) && ! in_array((string) $value, ['party-types', 'money-account-types', 'transaction-categories'], true)) {
                return trim((string) $value);
            }
        }

        foreach (['voucher_no', 'account_code', 'party_code', 'head_code', 'code', 'name', 'account_name', 'business_name', 'email'] as $key) {
            if (filled($request->input($key))) {
                return Str::limit(trim((string) $request->input($key)), 80, '');
            }
        }

        return '';
    }

    /** @param array{label:string,index:string,parameters?:array<string,string>} $module */
    private function moduleUrl(array $module): string
    {
        return Route::has($module['index'])
            ? route($module['index'], $module['parameters'] ?? [])
            : route('dashboard');
    }
}
