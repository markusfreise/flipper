<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * GitHub-Webhook (Push auf main). Prüft die HMAC-Signatur und legt eine Trigger-Datei an;
 * die systemd-Path-Unit flohmarkt-deploy.path startet daraufhin flohmarkt-deploy.sh als root.
 */
class DeployHookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('flohmarkt.deploy_secret');
        abort_unless($secret, 404);

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, (string) $request->header('X-Hub-Signature-256')), 403, 'Signatur ungültig.');

        if ($request->header('X-GitHub-Event') === 'ping') {
            return response()->json(['ok' => true, 'pong' => true]);
        }

        if ($request->input('ref') !== 'refs/heads/main') {
            return response()->json(['ok' => true, 'skipped' => 'nicht main']);
        }

        Storage::disk('local')->put('deploy/trigger', now()->toIso8601String().' '.$request->input('after'));

        return response()->json(['ok' => true, 'queued' => true], 202);
    }
}
