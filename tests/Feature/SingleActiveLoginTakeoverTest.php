<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ActiveLoginSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\TestCase;

class SingleActiveLoginTakeoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_login_replaces_and_destroys_the_previous_session(): void
    {
        $user = User::factory()->create();
        $handler = new ArraySessionHandler(120);
        $service = app(ActiveLoginSession::class);

        $oldSessionId = str_repeat('a', 40);
        $newSessionId = str_repeat('b', 40);

        $oldRequest = $this->requestWithSession($handler, $oldSessionId);
        $oldRequest->session()->put('authenticated_user_id', $user->id);
        $oldRequest->session()->save();

        $this->assertFalse($service->claim($oldRequest, $user));
        $this->assertSame($oldSessionId, $user->fresh()->active_session_id);
        $this->assertNotSame('', $handler->read($oldSessionId));

        $newRequest = $this->requestWithSession($handler, $newSessionId);

        $this->assertTrue($service->claim($newRequest, $user->fresh()));
        $this->assertSame($newSessionId, $user->fresh()->active_session_id);
        $this->assertSame('', $handler->read($oldSessionId));
        $this->assertTrue($service->consumeReplacement($oldRequest));
        $this->assertFalse($service->consumeReplacement($oldRequest));
    }

    private function requestWithSession(ArraySessionHandler $handler, string $sessionId): Request
    {
        $session = new Store('hisebghor-test-session', $handler, $sessionId);
        $session->start();

        $request = Request::create('/dashboard', 'GET');
        $request->setLaravelSession($session);

        return $request;
    }
}
