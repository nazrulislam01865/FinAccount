<?php

namespace Tests\Feature;

use App\AccountingEngine\AccountingEngine;
use App\AccountingEngine\Contracts\AccountingEngineContract;
use Tests\TestCase;

class AccountingEnginePhase1BindingTest extends TestCase
{
    public function test_accounting_engine_contract_resolves_to_phase_1_engine(): void
    {
        $engine = $this->app->make(AccountingEngineContract::class);

        $this->assertInstanceOf(AccountingEngine::class, $engine);
    }
}
