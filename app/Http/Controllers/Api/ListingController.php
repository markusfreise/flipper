<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Jobs\IdentifyProduct;
use App\Jobs\WriteListingText;
use App\Models\Listing;
use App\Services\ListingFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListingController extends Controller
{
    public function index(Request $request)
    {
        $listings = $request->user()->listings()->with('photos')->latest()->get();

        return ListingResource::collection($listings);
    }

    public function store(Request $request): JsonResponse
    {
        $listing = $request->user()->listings()->create(['status' => 'photos']);

        return response()->json(['id' => $listing->id], 201);
    }

    public function show(Listing $listing): ListingResource
    {
        Gate::authorize('view', $listing);

        return new ListingResource($listing->load('photos'));
    }

    public function destroy(Listing $listing): JsonResponse
    {
        Gate::authorize('delete', $listing);
        $listing->deleteFiles();
        $listing->delete();

        return response()->json(['ok' => true]);
    }

    /** "Neu erkennen" */
    public function identify(Listing $listing): ListingResource
    {
        Gate::authorize('update', $listing);
        abort_if($listing->photos()->doesntExist(), 422, 'Erst Fotos hochladen.');

        $listing->update(['product' => null, 'status' => 'identifying', 'last_error' => null]);
        IdentifyProduct::dispatch($listing->id); // Foto-Check bleibt, nur die Erkennung wird wiederholt.

        return new ListingResource($listing->fresh('photos'));
    }

    /** Korrigierte Erkennung speichern ("Stimmt so") und Recherche starten. */
    public function product(Request $request, Listing $listing): ListingResource
    {
        Gate::authorize('update', $listing);
        abort_unless(in_array($listing->status, ['identified', 'researching', 'questions', 'writing', 'ready', 'failed'], true), 409, 'Die Erkennung läuft noch.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'number' => ['nullable', 'string', 'max:60'],
            'year' => ['nullable', 'integer', 'min:1800', 'max:'.(date('Y') + 1)],
            'variant' => ['nullable', 'string', 'max:120'],
        ]);

        $previous = $listing->product ?? [];
        $changed = collect($data)->contains(fn ($v, $k) => ($previous[$k] ?? null) != $v);

        $listing->product = [
            ...$previous,
            ...$data,
            'source' => $changed ? 'user' : ($previous['source'] ?? 'ai'),
        ];
        $listing->research = null;
        $listing->save();

        ListingFlow::startResearch($listing);

        return new ListingResource($listing->fresh('photos'));
    }

    /** Drei Antworten speichern, Text und Bilder starten. */
    public function answers(Request $request, Listing $listing): ListingResource
    {
        Gate::authorize('update', $listing);
        abort_unless(in_array($listing->status, ['researching', 'questions', 'writing', 'ready'], true), 409, 'Erst die Erkennung bestätigen.');

        $data = $request->validate([
            'background' => ['required', 'string', 'max:2000'],
            'condition' => ['array'],
            'condition.*' => ['string', 'max:60'],
            'condition_notes' => ['nullable', 'string', 'max:2000'],
            'platform' => ['required', Rule::in(['kleinanzeigen', 'ebay', 'both'])],
            'pickup' => ['boolean'],
        ], [
            'background.required' => 'Erzähl kurz, warum du das hattest und warum es weg soll.',
        ]);

        $listing->update([
            'answers' => [
                'background' => trim($data['background']),
                'condition' => array_values($data['condition'] ?? []),
                'condition_notes' => $data['condition_notes'] ?? null,
                'platform' => $data['platform'],
                'pickup' => (bool) ($data['pickup'] ?? true),
            ],
            'text' => null,
            'zip_path' => null,
        ]);
        ListingFlow::answersGiven($listing);

        return new ListingResource($listing->fresh('photos'));
    }

    public function revise(Request $request, Listing $listing): ListingResource
    {
        Gate::authorize('update', $listing);
        abort_unless($listing->status === 'ready' && $listing->text, 409, 'Der Text ist noch nicht fertig.');
        abort_if($listing->text_revisions >= config('flohmarkt.max_text_revisions'), 429, 'Keine Überarbeitungen mehr übrig.');

        $data = $request->validate(['hint' => ['required', 'string', 'max:1000']], ['hint.required' => 'Was soll anders werden?']);

        $listing->update(['status' => 'writing', 'text_revisions' => $listing->text_revisions + 1]);
        WriteListingText::dispatch($listing->id, $data['hint']);

        return new ListingResource($listing->fresh('photos'));
    }

    public function retry(Listing $listing): ListingResource
    {
        Gate::authorize('update', $listing);
        abort_unless($listing->status === 'failed', 409, 'Da ist nichts schiefgegangen.');
        ListingFlow::retry($listing);

        return new ListingResource($listing->fresh('photos'));
    }

    public function download(Listing $listing): BinaryFileResponse
    {
        Gate::authorize('view', $listing);
        abort_unless($listing->zip_path && Storage::disk('local')->exists($listing->zip_path), 404);

        $name = 'inserat-'.(Str::slug($listing->product['name'] ?? '') ?: 'flohmarkt').'.zip';

        return response()->download(Storage::disk('local')->path($listing->zip_path), $name);
    }
}
