# PRD: Flohmarkt

**Version:** 1.0.0 · **Datum:** 2026-11-08 · **Sprache:** DE (UI, Fehlermeldungen, Commits) / EN (Code, DB-Felder) · **Slug:** `flohmarkt` · **Status:** Umgesetzt (v1.0.0, siehe `docs/backlog.md` für Abweichungen)

**Ursprungs-Prompt (Markus, 8.11.2026):** „Ist es möglich, daraus eine ganz kleine Webapp zu bauen, die auch andere Familienmitglieder nutzen können: Bild(er)-Upload und dann ein Walkthrough mit fertigen Bildern und Texten am Ende für Copy/Paste? Schreibe ein PRD für eine einfache Web-App. Nutze batcave-ui und den Deployment-Skill. Ich möchte so wenig wie möglich damit zu tun haben. Code soll das danach tutti fertig bauen."

> **Hinweis zur Umsetzung:** Abweichungen vom ursprünglichen Text sind mit **[Umsetzung]** markiert und in `docs/backlog.md` begründet.

---

## 1. Projektübersicht

| Feld | Wert |
|---|---|
| Projektname | Flohmarkt |
| Kurzbeschreibung | Aus Handyfotos eines Gegenstands entsteht in vier Schritten ein fertiges Kleinanzeigen-/eBay-Inserat: Produkt erkannt, Fotos freigestellt, Preis und Versand recherchiert, Text geschrieben. Am Ende: Bilder zum Download, Texte zum Kopieren. Das Einstellen selbst macht der Mensch. |
| Zielgruppe | Familie Freise (4–6 Personen), eingeloggt mit eigenem Konto. Keine Öffentlichkeit. |
| Vorlage | Claude-Skill `/verkaufen` (Referenzlauf Funko Pop Luna Lovegood, Kleinanzeigen-Anzeige 3565037782). Die App ist die Produktform dieser Skill ohne Chat. |
| Projektstatus | Umsetzung v1.0.0 abgeschlossen, Live-Gang offen (TASK-26) |
| Verantwortlich | Markus Freise (Owner), Umsetzung durch Claude Code |
| Betrieb | Batcave, `https://flohmarkt.croeso.de` |

### Kernidee

Ein Walkthrough, kein Werkzeugkasten. Die App führt durch vier Schritte und trifft alle Entscheidungen selbst, die sie treffen kann. Der Mensch bestätigt, korrigiert, beantwortet drei Fragen und kopiert am Ende.

```
1 Fotos → 2 Erkennung bestätigen → 3 Drei Fragen → 4 Ergebnis (Bilder + Texte)
```

Grundsätze, 1:1 aus der Skill übernommen und in Code gegossen:

1. **Das Produkt wird nie verändert.** Nur der Hintergrund wird entfernt. Keine Retusche, kein Upscaling, keine „Verschönerung". Nahaufnahmen von Mängeln werden gar nicht bearbeitet.
2. **Der Text behauptet nur, was belegt ist.** Features aus der Recherche mit Quelle, Zustand aus den Antworten. Keine erfundenen Zahlen, kein Werbesprech.
3. **Foto-Check bei jedem Lauf.** Die App sagt, welche Aufnahmen fehlen (Rückseite, Nahaufnahme Kante …) und wie man sie macht. Sie blockiert nicht.
4. **Ehrlicher Zustand.** Die Überschrift im Text heißt „Zustand", nicht „Zustand, ehrlich".

### Abgrenzung (Out of Scope, v1.0)

- Kein automatisches Einstellen bei Kleinanzeigen oder eBay (Login, AGB, Captcha). Copy/Paste ist der Flow.
- Keine Verkaufsverwaltung (verkauft/nicht verkauft, Nachrichten, Preisverhandlung).
- Keine Mehrsprachigkeit. Deutsch.
- Keine Personal-Brand-Stimme „markus" aus `/texter`. Die App schreibt in einer neutralen, freundlichen Privatverkäufer-Stimme (du-Form). Markus' Stimme ist TODO-03.
- Keine Mobile-App, kein PWA-Offline. Responsive Web reicht; Upload direkt aus der iPhone-Kamera über `<input type="file" capture>`.
- Kein eBay-Sofortkauf-Export (CSV/API). Nur Text + Artikelmerkmale zum Kopieren.

---

## 2. Techstack

| Schicht | Technologie | Begründung |
|---|---|---|
| Backend | Laravel 13 (PHP 8.3, auf 114 explizit `php8.3`) | Auth, Queue, Jobs, Dateiverwaltung. Hausstack. |
| Auth | Laravel Sanctum (Session-Cookie, SPA) | Familienkonten mit E-Mail + Passwort, per Seeder/Artisan angelegt. Keine Registrierung. |
| Rollen | – | Alle Nutzer gleichberechtigt. Jeder sieht nur eigene Inserate. `users.is_admin` als Platzhalter (Markus), ohne Funktion in v1. |
| Frontend | Vue 3 (Composition API, `<script setup>`) + Vue Router + Pinia | SPA mit vier Schritten, Wizard-State in Pinia, Polling der Job-Stati. |
| UI | **batcave-ui v2.0.0** als Git-Submodul `resources/batcave-ui` + app-eigene BEM-Blocks. **[Umsetzung]** Das getaggte v2 ist die kachink-UI: Klassen ohne `bc-`-Präfix (`.card`, `.btn`, `.form__input`, `.badge`, `.empty`, `.spinner`, `.alert`, `.toast` …), keine Vue-Komponentenschicht, Einbindung als Submodul statt npm. | Batcave-Pflicht. Kein Tailwind. |
| HTTP-Client | Axios | Sanctum-CSRF-Interceptor, 419-Retry. |
| KI | **Anthropic Claude API** über das offizielle PHP-SDK `anthropic-ai/sdk`, Vision + Web-Search-Tool (`web_search_20260209`). **[Umsetzung]** Default-Modell `claude-sonnet-5-5` statt `claude-sonnet-4-5` (wird am 30.11.2026 abgeschaltet), konfigurierbar per `ANTHROPIC_MODEL`. | Ein Anbieter für Erkennung, Foto-Check, Recherche, Preis und Text. Key von Markus hinterlegt (`ANTHROPIC_API_KEY` in `/opt/infra/.env`). |
| Freistellen | **rembg** als HTTP-Dienst (`danielgatis/rembg` Docker-Image, `rembg s`) auf LXC 111, Modell `isnet-general-use` | Laravel ruft `POST http://192.168.2.111:7000/api/remove`. Lokal: gleicher Container via Docker Desktop oder `pip install "rembg[cpu,cli]" && rembg s`. |
| Bildverarbeitung | Intervention Image v4 (GD) + GD für die Komposition | Freisteller auf Verlaufshintergrund mit Kontaktschatten (Port von `listing_photos.py`), 1280×1600 (4:5). |
| Datenbank | **PostgreSQL 16 auf LXC 110** (`192.168.2.110:5432`), DB `flohmarkt`, Rolle `flohmarkt_app` | `jsonb` für Recherche-Ergebnisse. |
| Dateispeicher | Lokale Disk LXC 114, `storage/app/private/listings/{uuid}/` **[Umsetzung: Laravel-13-Default-Root `app/private`]** | Originale, Freisteller, fertige JPGs, ZIP. |
| Queue | Laravel Queue, Driver `database`, systemd-Unit `flohmarkt-queue` | Alle KI- und Bildjobs asynchron, Frontend pollt. |
| Scheduler | `schedule:run` via systemd-Timer | Tägliches Löschen von Inseraten älter als 90 Tage inkl. Dateien. |
| Benachrichtigung | ntfy (`NTFY_URL` aus `/opt/infra/.env`), Topic `flohmarkt` | Deploy OK/FAILED, fehlgeschlagene Jobs. |
| Lokale Umgebung | Laravel Valet (`flohmarkt.test`) + Vite + lokale Postgres (`flohmarkt_local`) | Muss ohne Batcave laufen. rembg lokal via Docker. Ohne API-Key: `FLOHMARKT_FAKE_AI=true`. |
| Testing | PHPUnit (Feature/Unit), Vitest + Vue Test Utils | Jobs mit Fixture-Antworten aus dem Luna-Referenzlauf (`tests/Fixtures/luna/`). |
| Versionierung | Git, GitHub privat `markusfreise/flohmarkt` | `main` = live, `develop` = Arbeit. |

---

## 3. Projektstruktur

```
flohmarkt/
├── PRD_Flohmarkt_v1.0.0.md, CLAUDE.md, README.md
├── .claude/commands/{next-task,prd-check,deploy}.md
├── docs/backlog.md
├── _bootstrap/{bootstrap-batcave.sh,bootstrap-local.sh,flohmarkt-deploy.sh,templates/}
├── _project-assets -> …/Dropbox-Personal/Coding/flohmarkt   (Symlink, gitignored)
├── app/
│   ├── Contracts/LlmClient.php
│   ├── Models/{User,Listing,ListingPhoto}.php
│   ├── Policies/ListingPolicy.php
│   ├── Http/Controllers/Api/{AuthController,ListingController,PhotoController}.php, DeployHookController.php
│   ├── Http/Resources/ListingResource.php
│   ├── Jobs/{IdentifyProduct,CheckPhotos,RemoveBackground,ComposeListingPhoto,ResearchProduct,WriteListingText,BuildDownloadZip}.php
│   ├── Services/
│   │   ├── ListingFlow.php            (Statusmaschine, Orchestrierung)
│   │   ├── Claude/{ClaudeClient,FixtureLlmClient,IdentifyPrompt,PhotoCheckPrompt,ResearchPrompt,ListingTextPrompt,Principles,Images}.php
│   │   ├── Rembg/RembgClient.php
│   │   ├── Images/{PhotoComposer,ExifNormalizer}.php
│   │   └── Pricing/{PriceAdvisor,ShippingAdvisor}.php
│   ├── Support/{ListingExport,Ntfy}.php
│   └── Console/Commands/{UserCreate,UserPassword,ListingsPrune}.php
├── config/flohmarkt.php
├── database/{migrations,factories,seeders/FamilySeeder.php}
├── resources/
│   ├── batcave-ui/                    (Git-Submodul, v2.0.0)
│   ├── js/{main.js,router.js,App.vue,api/,stores/{auth,wizard}.js,composables/,views/,components/,__tests__/}
│   ├── scss/{site.scss,_properties.scss,_components.scss,_views.scss}
│   └── views/app.blade.php
├── routes/{api,web,console}.php
└── tests/{Feature,Unit,Fixtures/luna}
```

---

## 4. Git-Workflow & Repo

- Repo: `git@github.com:markusfreise/flohmarkt.git` (privat)
- Branches: `main` (live auf Batcave) ← `develop` ← `feature/TASK-xx-kurzname`; `hotfix/*` von `main`
- Commits: `<typ>(TASK-xx): <beschreibung>` — feat, fix, chore, refactor, test, docs
- `.gitignore`: `_project-assets`, `/vendor`, `/node_modules`, `/public/build`, Listing-Dateien, `.env`, `.DS_Store`

---

## 5. Umgebungen & .env

Zwei Umgebungen: `local` (Mac) und `prod` (Batcave). Vorlage: `.env.example`; prod-Vorlage `_bootstrap/templates/env.production`, gerendert vom Deploy-Skript aus `/opt/infra/.env` (`FLOHMARKT_DB_PASS`, `FLOHMARKT_APP_KEY`, `FLOHMARKT_DEPLOY_SECRET`, `ANTHROPIC_API_KEY`, `NTFY_URL`). Nichts hardcodiert.

Datenbank auf 110: `CREATE DATABASE flohmarkt ENCODING 'UTF8' LC_COLLATE 'de_DE.UTF-8' LC_CTYPE 'de_DE.UTF-8' TEMPLATE template0;`

---

## 6. Auth & Berechtigungen

- Login per E-Mail + Passwort (Sanctum Session). Keine Registrierung, kein Passwort-Reset per Mail. Konten: `php artisan user:create name email` / `user:password email`.
- `FamilySeeder` legt Konten aus `_project-assets/family.json` an, sonst nur Markus mit Zufallspasswort.
- Jeder sieht ausschließlich seine Inserate (`ListingPolicy`).
- Kein Cloudflare Access. Schutz = App-Login + Rate-Limit 5 Versuche/Minute auf `/api/v1/login`.

---

## 7. User Stories & Use Cases

| ID | Als | möchte ich | damit |
|---|---|---|---|
| US-01 | Verkäufer:in | mich mit E-Mail und Passwort einloggen | nur ich meine Inserate sehe |
| US-02 | Verkäufer:in | 1–10 Fotos direkt aus der Handykamera hochladen | ich nicht erst Dateien suchen muss |
| US-03 | Verkäufer:in | sehen, was die App erkannt hat, und das korrigieren | der Text nicht auf einem Irrtum aufbaut |
| US-04 | Verkäufer:in | erfahren, welche Fotos noch fehlen und wie ich sie mache | das Inserat vollständig wird |
| US-05 | Verkäufer:in | fehlende Fotos nachladen, ohne von vorn zu beginnen | der Flow nicht abbricht |
| US-06 | Verkäufer:in | drei kurze Fragen beantworten | der Text persönlich und ehrlich wird |
| US-07 | Verkäufer:in | Titel, Text, Preis, Versand und Artikelmerkmale einzeln kopieren | ich sie in die App einfügen kann |
| US-08 | Verkäufer:in | die fertigen Bilder einzeln oder als ZIP herunterladen | ich sie hochladen kann |
| US-09 | Verkäufer:in | sehen, woher der Preisvorschlag stammt | ich ihn einschätzen kann |
| US-10 | Verkäufer:in | meine bisherigen Inserate sehen und wieder öffnen | ich nichts verliere |
| US-11 | Verkäufer:in | sehen, was gerade passiert | ich weiß, dass sie nicht hängt |
| US-12 | Markus | Konten per Artisan anlegen | niemand ein Registrierungsformular braucht |
| US-13 | Markus | bei Job-Fehlern eine ntfy-Meldung bekommen | ich nicht nachfragen muss |

Use Cases UC-01 (Happy Path), UC-02 (Erkennung korrigieren), UC-03 (Fotos nachladen), UC-04 (Freistellen schlägt fehl → Original verwenden), UC-05 (Claude antwortet nicht → 3 Versuche mit Backoff 30 s/2 min/5 min, dann `failed`, ntfy, Retry-Button), UC-06 (Text nachbessern, max. 5), UC-07 (Inserat löschen) wie im Original-PRD.

---

## 8. Datenmodell

Tabellen `users` (+ `is_admin`), `listings` (uuid, status, `status_before_failure` **[Umsetzung]**, jsonb `product`, `photo_check`, `research`, `answers`, `pricing`, `text`, `zip_path`, `text_revisions`, `last_error`), `listing_photos` (uuid, position, kind overview|detail, `original_path`, `upload_path` **[Umsetzung: unverändertes Original]**, `cutout_path`, `final_path`, width, height, status uploaded|cutting|cut|cutout_failed|composed, is_cover).

Statusmaschine:

```
photos → identifying → identified → researching → questions → writing → ready
                 ↘ failed (von jedem Zustand; Retry setzt auf den vorherigen Zustand)
```

---

## 9. API

Prefix `/api/v1`, JSON, `auth:sanctum` außer Login: `POST login`, `POST logout`, `GET me`, `GET|POST listings`, `GET|DELETE listings/{id}`, `POST listings/{id}/photos`, `PATCH|DELETE listings/{id}/photos/{photoId}` **[Umsetzung: PATCH für Titelbild/Nahaufnahme]**, `POST listings/{id}/identify`, `PUT listings/{id}/product`, `PUT listings/{id}/answers`, `POST listings/{id}/text/revise`, `POST listings/{id}/retry` **[Umsetzung]**.

Dateien **[Umsetzung]** über die Web-Session (funktioniert auch ohne Referer, z. B. iOS-Downloads): `GET /files/listings/{id}/download.zip`, `GET /files/listings/{id}/photos/{photoId}/final.jpg`, `…/thumb.jpg`.

---

## 10.–12. UI, Frontend, CSS

Wie im Original-PRD (Login, Liste, Wizard mit Schrittleiste und vier Karten, Fehlerzustand). Kamera und Galerie sind zwei getrennte Buttons **[Umsetzung: `capture` würde auf dem iPhone die Galerie sperren]**. „Kopiert"-Feedback direkt am Feld (`aria-live`), batcave-ui v2.0.0 bringt `.toast` bereits mit. Keine Styles in `.vue`-Dateien; App-Blocks: `site-header`, `wizard-steps`, `dropzone`, `photo-grid`, `photo-picker`, `photo-tips`, `photo-check`, `progress-lines`, `copy-field`, `listing-card`, `result-images`.

---

## 15. Hosting & Deployment (Batcave)

LXC 114 (nginx, php8.3-fpm), Postgres auf 110, rembg auf 111, Caddy + cloudflared auf 113. Einrichtung: `_bootstrap/bootstrap-batcave.sh` (idempotent, fail-closed). Einziger manueller Schritt: Cloudflare Public Hostname `flohmarkt.croeso.de` → `HTTPS 192.168.2.113:443`, Origin Server Name `flohmarkt.croeso.de`.

Deploy: `/usr/local/bin/flohmarkt-deploy.sh` — baut in `/srv/www/flohmarkt-releases/<sha>`, `pg_dump` vor `migrate`, schaltet den Symlink `/srv/www/flohmarkt` erst nach Erfolg um, Smoke-Test mit Rollback, ntfy OK/FAILED. Auslöser: GitHub-Webhook `POST /deploy-hook` (HMAC) schreibt eine Trigger-Datei, `flohmarkt-deploy.path` startet den Deploy als root.

Kosten: ≈ 3–8 Cent pro Inserat. Monatliches Limit im Anthropic-Dashboard setzen (TODO-05).

---

## 16. Testing

PHPUnit (Auth, kompletter Flow mit Luna-Fixtures, Fehler/Retry, Prune, Webhook, PriceAdvisor, ShippingAdvisor, PhotoComposer, Prompts) und Vitest (CopyField, wizard-Store/Polling, StepQuestions). Manuelle Abnahme vor Live: siehe `docs/backlog.md`.

---

## 17.–19. Backlog, Register, TODOs

Siehe `docs/backlog.md`.
