# Backlog & Umsetzungsstand

Stand: 2026-11-08, v1.0.0 (live)

## Tasks (PRD §17)

| ID | Titel | Stand |
|---|---|---|
| TASK-01 | Repo & Umgebung provisionieren | erledigt. Privates Repo `markusfreise/flipper` (main, develop), lokal `flipper.test` (Valet, Postgres 17, rembg per Docker auf Port 7010) |
| TASK-02 | Laravel 13 + Vue 3 + batcave-ui v2 Grundgerüst | erledigt |
| TASK-03 | Migrations, Models, Policy, Factories | erledigt |
| TASK-04 | Auth, Rate-Limit, `user:create`/`user:password` | erledigt |
| TASK-05 | Upload, ExifNormalizer, HEIC→JPEG | erledigt (HEIC: Browser wandelt, wenn er kann; sonst Server per `heif-convert`/ImageMagick) |
| TASK-06 | ClaudeClient | erledigt, offizielles PHP-SDK, Retry 3× mit Backoff über die Queue |
| TASK-07 | IdentifyProduct | erledigt |
| TASK-08 | CheckPhotos | erledigt, Auflösungsprüfung (< 2000 px) serverseitig |
| TASK-09 | RembgClient + RemoveBackground | erledigt, gegen echten `rembg s` getestet |
| TASK-10 | PhotoComposer | erledigt |
| TASK-11 | ResearchProduct | erledigt, `web_search_20260209`, max. 5 Suchen |
| TASK-12 | PriceAdvisor + ShippingAdvisor | erledigt |
| TASK-13 | WriteListingText + revise | erledigt |
| TASK-14 | ZIP, Downloads, Prune | erledigt |
| TASK-15 | Listing-API + Statusmaschine | erledigt |
| TASK-16–23 | Frontend | erledigt |
| TASK-24 | Deploy-Script, Webhook, systemd, ntfy | erledigt. Auf der Batcave eingerichtet, Push auf `main` deployt per Webhook |
| TASK-25 | Tests | erledigt. PHPUnit 36/36, Vitest 19/19; E2E mit Playwright (iPhone 13) mit Fixtures |
| TASK-26 | Erster Live-Gang | erledigt bis auf die manuelle Abnahme mit echten Fotos (siehe unten) |

## Abweichungen vom PRD (mit Grund)

1. **Modell `claude-sonnet-5-5` statt `claude-sonnet-4-5`.** Sonnet 4.5 wird am 30.11.2026 abgeschaltet. Per `ANTHROPIC_MODEL` änderbar.
2. **batcave-ui v2.0.0 sieht anders aus als im PRD beschrieben.** Das getaggte v2 ist die kachink-UI: kein `bc-`-Präfix, keine Vue-Komponenten, Einbindung als Git-Submodul (`resources/batcave-ui`), nicht als npm-Paket. Umgesetzt mit den echten Klassen. `.toast` gibt es in v2.0.0 schon; „Kopiert" wird direkt am Feld angezeigt (TODO-04 damit erledigt).
3. **Dateirouten über die Web-Session** (`/files/listings/...`) statt `/api/v1/...`: Sanctum erkennt Anfragen ohne Referer (Downloads auf dem iPhone) nicht als eingeloggt.
4. **Kamera und Galerie getrennt**: `capture="environment"` öffnet auf dem iPhone nur die Kamera, die Galerie wäre gesperrt.
5. **Zusätzlich:** `POST /listings/{id}/retry`, `PATCH /photos/{id}` (Titelbild, Nahaufnahme), Spalten `status_before_failure` und `upload_path` (unverändertes Original neben dem EXIF-normalisierten JPEG).
6. **Speicherort** `storage/app/private/listings` (Laravel-13-Default) statt `storage/app/listings`.
7. **Deploy** baut in Release-Verzeichnissen unter `/var/www` und schaltet einen Symlink um (fail-closed). Der Webhook schreibt nur eine Trigger-Datei, systemd (`flipper-deploy.path`) startet das Skript als root. So braucht www-data kein sudo.
8. **Verpackungszuschlag** 1 cm/Seite + 200 g: Mit mehr wäre Luna (Referenzlauf: Päckchen S) in Päckchen M gerutscht.
9. **Bilder werden direkt nach dem Freistellen komponiert**, nicht erst nach den Antworten. Spart Wartezeit, Schritt 2 zeigt schon die fertigen Freisteller.

## Betrieb auf der Batcave (Stand Live-Gang 2026-11-08)

- **114 (web):** `/var/www/flipper` → Symlink auf `/var/www/flipper-releases/<sha>`, Shared `/var/www/flipper-shared` (storage, .env). Dienste `flipper-queue`, `flipper-schedule.timer`, `flipper-deploy.path`. Secrets in `/opt/infra/.env` (`FLIPPER_*`, `ANTHROPIC_API_KEY` aus lucius-app, `NTFY_URL`).
- **GitHub:** lesende Deploy-Keys je Repo auf 114 (`github-flipper`, `github-batcave-ui` in `/root/.ssh/config`). Der Deploy schreibt die Submodul-URL auf den Alias um. Webhook `https://flipper.croeso.de/deploy-hook` (Push).
- **110 (data):** DB `flipper`, Rolle `flipper_app`. pg_hba brauchte keine Änderung (Regel für 192.168.2.0/24).
- **111 (docker):** Stack `/opt/stacks/rembg`, Container `rembg` auf `192.168.2.111:7000`.
- **113 (edge):** keine Änderung. Der Wildcard-Block `*.croeso.de` (DNS-01) reicht an 114:80 weiter, der Cloudflare-Tunnel hat ebenfalls schon einen Wildcard-Hostname. Ein eigener Caddy-Block wäre ein Restart für alle Seiten (Admin-API aus).
- Zugang vom Mac: 114 per `ssh web`, 110/111/113 per `ssh batcave pct exec <id>`.

### Noch zu tun (Markus)

1. Manuelle Abnahme mit echten Fotos (Liste unten). Der echte Lauf mit Claude-Key wurde beim Live-Gang ausgelassen, es lagen keine Fotos vor.
2. Optional `_project-assets/family.json` befüllen und auf 114 `cd /var/www/flipper && runuser -u www-data -- php8.3 artisan db:seed --class=FamilySeeder --force`.
3. Monatliches Kostenlimit im Anthropic-Dashboard setzen (TODO-05).

## Manuelle Abnahme (PRD §16) – mit echten Luna-Fotos, offen

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
| TODO-01 | Cloudflare Public Hostname | erledigt, Wildcard-Hostname im Tunnel deckt flipper.croeso.de ab |
| TODO-02 | Aufbewahrung 90 Tage | umgesetzt, konfigurierbar per `LISTING_RETENTION_DAYS` |
| TODO-03 | Stimme „markus" | offen (v1 neutrale Stimme) |
| TODO-04 | Toast | erledigt (v2.0.0 hat `.toast`, Feedback am Feld) |
| TODO-05 | API-Kostenlimit | Markus im Anthropic-Dashboard |
| TODO-06 | HEIC | umgesetzt (Browser-Canvas, sonst `heif-convert`/ImageMagick; Bootstrap installiert `libheif-examples`) |
| TODO-07 | Familie onboarden | `family.json` oder `user:create` |
