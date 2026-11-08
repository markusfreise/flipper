<?php

namespace App\Contracts;

interface LlmClient
{
    /**
     * Schickt einen Prompt an das Modell und liefert ein JSON-Objekt zurück.
     *
     * @param  array<int, array<string, mixed>>  $content  User-Content-Blöcke (Text, Bilder)
     * @param  array<string, mixed>  $schema  JSON-Schema der Antwort
     * @param  int  $webSearches  0 = ohne Websuche, sonst max. Anzahl Suchen
     * @return array{data: array<string, mixed>, sources: list<string>}
     */
    public function json(string $system, array $content, array $schema, int $webSearches = 0): array;
}
