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
        $this->app->bind(LlmClient::class, fn () => config('flohmarkt.fake_ai')
            ? new FixtureLlmClient(base_path('tests/Fixtures/luna'))
            : new ClaudeClient(
                config('flohmarkt.anthropic.api_key'),
                config('flohmarkt.anthropic.model'),
                (float) config('flohmarkt.anthropic.timeout'),
            ));

        $this->app->bind(RembgClient::class, fn () => new RembgClient(
            config('flohmarkt.rembg.url'),
            config('flohmarkt.rembg.model'),
            (int) config('flohmarkt.rembg.timeout'),
        ));
    }

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip())
            ->response(fn () => response()->json(['message' => 'Zu viele Versuche. Bitte eine Minute warten.'], 429)));
    }
}
