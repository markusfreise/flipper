<?php

namespace Tests\Feature;

use App\Contracts\LlmClient;
use App\Jobs\CheckPhotos;
use App\Jobs\IdentifyProduct;
use App\Jobs\RemoveBackground;
use App\Jobs\ResearchProduct;
use App\Jobs\WriteListingText;
use App\Models\Listing;
use App\Models\User;
use App\Services\Claude\FixtureLlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ListingFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FixtureLlmClient $llm;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->llm = new FixtureLlmClient(base_path('tests/Fixtures/luna'));
        $this->app->instance(LlmClient::class, $this->llm);
    }

    private function fakeRembg(bool $ok = true): void
    {
        Http::fake([
            '*/api/remove' => $ok
                ? Http::response(file_get_contents(base_path('tests/Fixtures/luna/cutout.png')), 200, ['Content-Type' => 'image/png'])
                : Http::response('boom', 500),
        ]);
    }

    private function photo(string $name = 'luna.jpg'): UploadedFile
    {
        return new UploadedFile(base_path('tests/Fixtures/luna/photo.jpg'), $name, 'image/jpeg', null, true);
    }

    public function test_upload_dispatches_identification_jobs(): void
    {
        Queue::fake();
        $listing = Listing::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->post("/api/v1/listings/{$listing->id}/photos", [
                'photos' => [$this->photo(), $this->photo('detail.jpg')],
                'kinds' => ['overview', 'detail'],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', 'identifying')
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.photos.0.is_cover', true)
            ->assertJsonPath('data.photos.1.kind', 'detail');

        Queue::assertPushed(IdentifyProduct::class);
        Queue::assertPushed(CheckPhotos::class);
        Queue::assertPushed(RemoveBackground::class, 2);
        Storage::disk('local')->assertExists("listings/{$listing->id}/originals/01.jpg");
        Storage::disk('local')->assertExists("listings/{$listing->id}/originals/01-upload.jpg");
        Storage::disk('local')->assertExists("listings/{$listing->id}/thumbs/01.jpg");
    }

    public function test_more_than_ten_photos_are_rejected(): void
    {
        Queue::fake();
        $listing = Listing::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->post("/api/v1/listings/{$listing->id}/photos", [
                'photos' => array_fill(0, 11, $this->photo()),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.photos.0', 'Höchstens 10 Fotos pro Inserat.');
    }

    public function test_complete_flow_with_luna_fixtures(): void
    {
        $this->fakeRembg();
        $listing = Listing::factory()->for($this->user)->create();
        $api = $this->actingAs($this->user);

        // Schritt 1: Upload → Erkennung, Foto-Check, Freisteller (Queue läuft synchron)
        $api->post("/api/v1/listings/{$listing->id}/photos", [
            'photos' => [$this->photo(), $this->photo('ecke.jpg')],
            'kinds' => ['overview', 'detail'],
        ], ['Accept' => 'application/json'])->assertOk();

        $data = $api->getJson("/api/v1/listings/{$listing->id}")->assertOk()->json('data');
        $this->assertSame('identified', $data['status']);
        $this->assertSame('Funko Pop! Harry Potter #14 Luna Lovegood', $data['product']['name']);
        $this->assertSame(2016, $data['product']['year']);
        $this->assertSame('ai', $data['product']['source']);
        $this->assertTrue($data['photo_check']['quality']['see_through']);
        $this->assertNotEmpty($data['photo_check']['missing']);
        $this->assertSame(['composed', 'composed'], array_column($data['photos'], 'status'));
        $this->assertSame([1280, 1600], array_slice(getimagesize(Storage::disk('local')->path("listings/{$listing->id}/final/01.jpg")), 0, 2));

        // Schritt 2: "Stimmt so" mit Korrektur → Recherche → Fragen
        $data = $api->putJson("/api/v1/listings/{$listing->id}/product", [
            'name' => 'Funko Pop! Harry Potter #14 Luna Lovegood',
            'brand' => 'Funko', 'number' => '14', 'year' => 2016, 'variant' => 'Standard',
        ])->assertOk()->json('data');
        $this->assertSame('questions', $data['status']);
        $this->assertSame('user', $data['product']['source']);
        $this->assertNotEmpty($data['research']['sources']);
        $this->assertSame(5, $this->llm->calls[2]['webSearches']);

        // Schritt 3: Antworten → Text → ZIP → ready
        $api->putJson("/api/v1/listings/{$listing->id}/answers", ['platform' => 'both'])
            ->assertUnprocessable()->assertJsonValidationErrors('background');

        $data = $api->putJson("/api/v1/listings/{$listing->id}/answers", [
            'background' => 'Für meine Tochter gekauft, nie ausgepackt. Platz schaffen.',
            'condition' => ['OVP ungeöffnet', 'Nichtraucherhaushalt', 'Tierfrei'],
            'platform' => 'both',
            'pickup' => true,
        ])->assertOk()->json('data');

        $this->assertSame('ready', $data['status']);
        $this->assertSame(12.0, (float) $data['pricing']['kleinanzeigen_eur']);
        $this->assertSame(14.9, (float) $data['pricing']['ebay_eur']);
        $this->assertSame('DHL Päckchen S', $data['pricing']['shipping']['ebay']['service']);
        $this->assertSame('über Kleinanzeigen versichert, ab 3,99 €', $data['pricing']['shipping']['kleinanzeigen_text']);
        $this->assertLessThanOrEqual(65, mb_strlen($data['text']['title']));
        $this->assertStringNotContainsString('–', $data['text']['body']);
        $this->assertStringContainsString("Zustand:\n", $data['text']['body']);
        $this->assertSame(['die Regale werden umgebaut'], $data['text']['invented']);
        $this->assertSame(['Marke' => 'Funko', 'Serie' => 'Harry Potter', 'Nummer' => '14', 'EAN/UPC' => '849803065691', 'Material' => 'Vinyl'], $data['ebay_attributes']);
        $this->assertNotNull($data['zip_url']);

        // ZIP: 01.jpg, 02.jpg, inserat.txt
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path("listings/{$listing->id}/zip/inserat.zip")));
        $names = array_map(fn ($i) => $zip->getNameIndex($i), range(0, $zip->numFiles - 1));
        sort($names);
        $this->assertSame(['01.jpg', '02.jpg', 'inserat.txt'], $names);
        $this->assertStringContainsString('Kleinanzeigen: 12,00 € VB', $zip->getFromName('inserat.txt'));
        $zip->close();

        $this->get("/files/listings/{$listing->id}/download.zip")->assertOk()->assertDownload('inserat-funko-pop-harry-potter-14-luna-lovegood.zip');
        $this->get($data['photos'][0]['final_url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // Überarbeiten (max. 5)
        $data = $api->postJson("/api/v1/listings/{$listing->id}/text/revise", ['hint' => 'kürzer'])->assertOk()->json('data');
        $this->assertSame(1, $data['text_revisions']);
        $this->assertSame(4, $data['revisions_left']);
        $this->assertSame(2, $data['text']['revision']);
        $this->assertStringContainsString('kürzer', $this->llm->calls[array_key_last($this->llm->calls)]['content'][0]['text']);

        Listing::find($listing->id)->update(['text_revisions' => 5]);
        $api->postJson("/api/v1/listings/{$listing->id}/text/revise", ['hint' => 'noch kürzer'])->assertStatus(429);

        // Löschen entfernt Dateien
        $api->deleteJson("/api/v1/listings/{$listing->id}")->assertOk();
        Storage::disk('local')->assertMissing("listings/{$listing->id}");
    }

    public function test_answers_before_research_finish_waits_for_research(): void
    {
        Queue::fake();
        $listing = Listing::factory()->for($this->user)->create([
            'status' => 'researching',
            'product' => ['name' => 'Funko Pop! Luna'],
        ]);

        $this->actingAs($this->user)->putJson("/api/v1/listings/{$listing->id}/answers", [
            'background' => 'Platz schaffen.', 'platform' => 'kleinanzeigen',
        ])->assertOk()->assertJsonPath('data.status', 'writing');

        Queue::assertNotPushed(WriteListingText::class);

        // Recherche fertig → Text startet
        (new ResearchProduct($listing->id))->handle($this->llm);
        Queue::assertPushed(WriteListingText::class);
        $this->assertSame('writing', $listing->fresh()->status);
    }

    public function test_failed_cutout_falls_back_to_original(): void
    {
        $this->fakeRembg(false);
        $listing = Listing::factory()->for($this->user)->create(['status' => 'identified', 'product' => ['name' => 'x'], 'photo_check' => []]);

        $this->actingAs($this->user)->post("/api/v1/listings/{$listing->id}/photos", [
            'photos' => [$this->photo()],
        ], ['Accept' => 'application/json'])->assertOk();

        $photo = $listing->photos()->first();
        $this->assertSame('cutout_failed', $photo->status);
        Storage::disk('local')->assertExists($photo->final_path);
        $this->assertSame('identified', $listing->fresh()->status);
    }

    public function test_failed_job_marks_listing_and_retry_restarts_it(): void
    {
        Queue::fake();
        $listing = Listing::factory()->for($this->user)->create(['status' => 'researching', 'product' => ['name' => 'x']]);

        (new ResearchProduct($listing->id))->failed(new \RuntimeException('Claude-API-Fehler: overloaded_error'));

        $listing->refresh();
        $this->assertSame('failed', $listing->status);
        $this->assertSame('researching', $listing->status_before_failure);
        $this->assertStringContainsString('Recherche hat nicht geklappt', $listing->last_error);

        $this->actingAs($this->user)->postJson("/api/v1/listings/{$listing->id}/retry")
            ->assertOk()->assertJsonPath('data.status', 'researching');
        Queue::assertPushed(ResearchProduct::class);
    }

    public function test_prune_deletes_old_listings_with_files(): void
    {
        $old = Listing::factory()->for($this->user)->create(['created_at' => now()->subDays(91)]);
        $new = Listing::factory()->for($this->user)->create();
        Storage::disk('local')->put("listings/{$old->id}/originals/01.jpg", 'x');

        $this->artisan('listings:prune')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($new);
        Storage::disk('local')->assertMissing("listings/{$old->id}/originals/01.jpg");
    }
}
