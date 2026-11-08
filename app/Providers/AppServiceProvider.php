<?php

namespace App\Providers;

use App\Contracts\LlmClient;
use App\Services\Claude\ClaudeClient;
use App\Services\Claude\FixtureLlmClient;
use App\Services\Rembg\RembgClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LlmClient::class, fn () => config('flipper.fake_ai')
            ? new FixtureLlmClient(base_path('tests/Fixtures/luna'))
            : new ClaudeClient(
                config('flipper.anthropic.api_key'),
                config('flipper.anthropic.model'),
                (float) config('flipper.anthropic.timeout'),
            ));

        $this->app->bind(RembgClient::class, fn () => new RembgClient(
            config('flipper.rembg.url'),
            config('flipper.rembg.model'),
            (int) config('flipper.rembg.timeout'),
        ));
    }

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip())
            ->response(fn () => response()->json(['message' => 'Zu viele Versuche. Bitte eine Minute warten.'], 429)));
    }
}
