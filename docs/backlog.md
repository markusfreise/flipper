# Backlog & Umsetzungsstand

Stand: 2026-11-08, v1.0.0

## Tasks (PRD §17)

| ID | Titel | Stand |
|---|---|---|
| TASK-01 | Repo & Umgebung provisionieren | ✅ Bootstrap-Skripte im Repo. ⏳ GitHub-Repo `markusfreise/flohmarkt` anlegen und pushen |
| TASK-02 | Laravel 13 + Vue 3 + batcave-ui v2 Grundgerüst | ✅ |
| TASK-03 | Migrations, Models, Policy, Factories | ✅ |
| TASK-04 | Auth, Rate-Limit, `user:create`/`user:password` | ✅ |
| TASK-05 | Upload, ExifNormalizer, HEIC→JPEG | ✅ (HEIC: Browser wandelt, wenn er kann; sonst Server per `heif-convert`/ImageMagick) |
| TASK-06 | ClaudeClient | ✅ offizielles PHP-SDK, Retry 3× mit Backoff über die Queue |
| TASK-07 | IdentifyProduct | ✅ |
| TASK-08 | CheckPhotos | ✅ Auflösungsprüfung (< 2000 px) serverseitig |
| TASK-09 | RembgClient + RemoveBackground | ✅ gegen echten `rembg s` getestet |
| TASK-10 | PhotoComposer | ✅ |
| TASK-11 | ResearchProduct | ✅ `web_search_20260209`, max. 5 Suchen |
| TASK-12 | PriceAdvisor + ShippingAdvisor | ✅ |
| TASK-13 | WriteListingText + revise | ✅ |
| TASK-14 | ZIP, Downloads, Prune | ✅ |
| TASK-15 | Listing-API + Statusmaschine | ✅ |
| TASK-16–23 | Frontend | ✅ |
| TASK-24 | Deploy-Script, Webhook, systemd, ntfy | ✅ geschrieben, ⏳ auf der Batcave noch nicht ausgeführt |
| TASK-25 | Tests | ✅ PHPUnit 36/36, Vitest 19/19; E2E mit Playwright (iPhone 13) durchgelaufen |
| TASK-26 | Erster Live-Gang | ⏳ offen, siehe unten |

## Abweichungen vom PRD (mit Grund)

1. **Modell `claude-sonnet-5-5` statt `claude-sonnet-4-5`.** Sonnet 4.5 wird am 30.11.2026 abgeschaltet. Per `ANTHROPIC_MODEL` änderbar.
2. **batcave-ui v2.0.0 sieht anders aus als im PRD beschrieben.** Das getaggte v2 ist die kachink-UI: kein `bc-`-Präfix, keine Vue-Komponenten, Einbindung als Git-Submodul (`resources/batcave-ui`), nicht als npm-Paket. Umgesetzt mit den echten Klassen. `.toast` gibt es in v2.0.0 schon; „Kopiert" wird direkt am Feld angezeigt (TODO-04 damit erledigt).
3. **Dateirouten über die Web-Session** (`/files/listings/...`) statt `/api/v1/...`: Sanctum erkennt Anfragen ohne Referer (Downloads auf dem iPhone) nicht als eingeloggt.
4. **Kamera und Galerie getrennt**: `capture="environment"` öffnet auf dem iPhone nur die Kamera, die Galerie wäre gesperrt.
5. **Zusätzlich:** `POST /listings/{id}/retry`, `PATCH /photos/{id}` (Titelbild, Nahaufnahme), Spalten `status_before_failure` und `upload_path` (unverändertes Original neben dem EXIF-normalisierten JPEG).
6. **Speicherort** `storage/app/private/listings` (Laravel-13-Default) statt `storage/app/listings`.
7. **Deploy** baut in Release-Verzeichnissen und schaltet einen Symlink um (fail-closed). Der Webhook schreibt nur eine Trigger-Datei, systemd (`flohmarkt-deploy.path`) startet das Skript als root. So braucht www-data kein sudo.
8. **Verpackungszuschlag** 1 cm/Seite + 200 g: Mit mehr wäre Luna (Referenzlauf: Päckchen S) in Päckchen M gerutscht.
9. **Bilder werden direkt nach dem Freistellen komponiert**, nicht erst nach den Antworten. Spart Wartezeit, Schritt 2 zeigt schon die fertigen Freisteller.

## Live-Gang (TASK-26), was Markus tun muss

1. Privates Repo `markusfreise/flohmarkt` anlegen und diesen Stand pushen (`main` und `develop`).
2. `ANTHROPIC_API_KEY` in `/opt/infra/.env` auf 114 eintragen (der Rest wird generiert).
3. Auf dem Mac: `./_bootstrap/bootstrap-batcave.sh` (ggf. `CADDY_SNIPPET=<name>` setzen, falls das TLS-Snippet im Caddyfile anders heißt; das Skript validiert und rollt sonst zurück).
4. Cloudflare Public Hostname setzen (einziger Handgriff, das Skript zeigt die Werte an).
5. Optional `_project-assets/family.json` befüllen und `php8.3 artisan db:seed --class=FamilySeeder --force` auf 114.
6. Monatliches Kostenlimit im Anthropic-Dashboard setzen.

## Manuelle Abnahme (PRD §16) – vor Live mit echten Luna-Fotos

- [ ] Luna-Fotos → Erkennung „Funko Pop! Harry Potter #14 Luna Lovegood", Jahr 2016
- [ ] Foto-Check nennt Sichtfenster/Unterlage und fehlende Rückseite
- [ ] Preis 10–15 € Kleinanzeigen, Quellen verlinkt
- [ ] Text: „Zustand", keine Gedankenstriche, kein Werbesprech, Kleinanzeigen-Versandzeile
- [ ] ZIP enthält `01.jpg … 0n.jpg` + `inserat.txt`
- [ ] Zweites Konto sieht das Inserat nicht (automatisiert getestet, live nochmal prüfen)

Mit Fixtures (ohne echte KI) ist der Flow in Chromium/iPhone-Viewport komplett durchgelaufen; mit echtem Claude-Key noch nicht.

## Offene Punkte (PRD §19)

| ID | Thema | Stand |
|---|---|---|
| TODO-01 | Cloudflare Public Hostname | manuell beim Live-Gang |
| TODO-02 | Aufbewahrung 90 Tage | umgesetzt, konfigurierbar per `LISTING_RETENTION_DAYS` |
| TODO-03 | Stimme „markus" | offen (v1 neutrale Stimme) |
| TODO-04 | Toast | erledigt (v2.0.0 hat `.toast`, Feedback am Feld) |
| TODO-05 | API-Kostenlimit | Markus im Anthropic-Dashboard |
| TODO-06 | HEIC | umgesetzt (Browser-Canvas, sonst `heif-convert`/ImageMagick; Bootstrap installiert `libheif-examples`) |
| TODO-07 | Familie onboarden | `family.json` oder `user:create` |
