<?php

namespace App\Services\Claude;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Contracts\LlmClient;
use Throwable;

/**
 * Dünne Schicht über dem offiziellen Anthropic-PHP-SDK.
 * Wiederholungen mit Backoff (30 s, 2 min, 5 min) übernimmt die Queue (Job::$backoff).
 */
class ClaudeClient implements LlmClient
{
    private ?Client $client = null;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly float $timeout = 180.0,
    ) {}

    public function json(string $system, array $content, array $schema, int $webSearches = 0): array
    {
        if (! $this->apiKey) {
            throw new ClaudeException('ANTHROPIC_API_KEY fehlt in der .env.');
        }

        $params = [
            'model' => $this->model,
            'maxTokens' => 16000,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $content]],
        ];

        if ($webSearches > 0) {
            // Mit Websuche kommt das JSON als letzter Textblock (Zitate vertragen sich nicht mit output_config.format).
            $params['tools'] = [[
                'type' => 'web_search_20260209',
                'name' => 'web_search',
                'maxUses' => $webSearches,
            ]];
            $params['system'] .= "\n\nAntworte nach der Recherche ausschließlich mit einem einzigen JSON-Objekt nach diesem Schema, ohne Text davor oder danach:\n"
                .json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $params['outputConfig'] = ['format' => ['type' => 'json_schema', 'schema' => $schema]];
        }

        $sources = [];
        $text = '';

        try {
            // pause_turn: lange Server-Tool-Runden werden fortgesetzt, max. 3 Mal.
            for ($round = 0; $round < 4; $round++) {
                $message = $this->client()->messages->create(...$params);

                foreach ($message->content as $block) {
                    if ($block->type === 'text') {
                        $text = $block->text; // letzter Textblock zählt
                    } elseif ($block->type === 'web_search_tool_result' && is_array($block->content)) {
                        foreach ($block->content as $result) {
                            if (($result->type ?? null) === 'web_search_result') {
                                $sources[] = $result->url;
                            }
                        }
                    }
                }

                if ($message->stopReason === 'refusal') {
                    throw new ClaudeException('Das Modell hat die Anfrage abgelehnt.');
                }
                if ($message->stopReason !== 'pause_turn') {
                    break;
                }
                $params['messages'][] = ['role' => 'assistant', 'content' => $message->content];
            }
        } catch (ClaudeException $e) {
            throw $e;
        } catch (APIStatusException $e) {
            throw new ClaudeException('Claude-API-Fehler: '.($e->type?->value ?? 'unbekannt').' – '.$e->getMessage(), previous: $e);
        } catch (Throwable $e) {
            throw new ClaudeException('Claude nicht erreichbar: '.$e->getMessage(), previous: $e);
        }

        return ['data' => self::extractJson($text), 'sources' => array_values(array_unique($sources))];
    }

    /** Holt das JSON-Objekt aus einer Textantwort (auch aus ```json-Zäunen). */
    public static function extractJson(string $text): array
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $text, $m)) {
            $text = $m[1];
        } elseif (($start = strpos($text, '{')) !== false && ($end = strrpos($text, '}')) !== false) {
            $text = substr($text, $start, $end - $start + 1);
        }

        $data = json_decode($text, true);
        if (! is_array($data)) {
            throw new ClaudeException('Antwort war kein gültiges JSON.');
        }

        return $data;
    }

    private function client(): Client
    {
        return $this->client ??= new Client(
            apiKey: $this->apiKey,
            requestOptions: ['timeout' => $this->timeout, 'maxRetries' => 2],
        );
    }
}
