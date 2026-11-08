<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeployHookTest extends TestCase
{
    public function test_rejects_wrong_signature_and_queues_main_push(): void
    {
        Storage::fake('local');
        config(['flohmarkt.deploy_secret' => 's3cret']);
        $body = json_encode(['ref' => 'refs/heads/main', 'after' => 'abc123']);

        $this->call('POST', '/deploy-hook', [], [], [], ['HTTP_X-Hub-Signature-256' => 'sha256=falsch', 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertForbidden();

        $sig = 'sha256='.hash_hmac('sha256', $body, 's3cret');
        $this->call('POST', '/deploy-hook', [], [], [], ['HTTP_X-Hub-Signature-256' => $sig, 'HTTP_X-GitHub-Event' => 'push', 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(202);

        Storage::disk('local')->assertExists('deploy/trigger');
    }
}
