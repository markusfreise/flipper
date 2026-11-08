# Flipper – Hinweise für Claude Code

Aus Handyfotos ein fertiges Kleinanzeigen-/eBay-Inserat. Spezifikation: `PRD_Flipper_v1.0.0.md`, Stand und Abweichungen: `docs/backlog.md`.

## Sprache
- UI, Fehlermeldungen, Commit-Messages: **Deutsch**. Code, DB-Felder, Klassennamen: Englisch.
- Commits: `<typ>(TASK-xx): <beschreibung>` (feat, fix, chore, refactor, test, docs). Ein Task = ein Commit.
- Branches: `main` = live (Webhook deployt automatisch!), Arbeit auf `develop` bzw. `feature/TASK-xx-…`.

## Grundsätze (nicht verhandelbar, aus der Skill /verkaufen)
1. Das Produkt wird nie verändert. Nur Hintergrund entfernen; Nahaufnahmen (`kind=detail`) gar nicht bearbeiten, nur verkleinern.
2. Der Text behauptet nur Belegtes. Ausschmückungen landen in `text.invented`.
3. Foto-Check bei jedem Lauf, blockiert nie.
4. Überschrift „Zustand", nie „Zustand, ehrlich". Keine Gedankenstriche im Text (`WriteListingText::cleanBody`).

## Architektur in einem Satz
`ListingFlow` ist die Statusmaschine (photos → identifying → identified → researching → questions → writing → ready, failed); jeder Job ruft am Ende eine `ListingFlow`-Methode, die unter Zeilensperre entscheidet, was als Nächstes passiert. Das Frontend leitet den Schritt nur aus `status` ab (`stores/wizard.js → stepFor`) und pollt alle 2 s.

## Wichtige Stellen
- KI: `App\Contracts\LlmClient` → `ClaudeClient` (offizielles SDK `anthropic-ai/sdk`). Prompts in `app/Services/Claude/*Prompt.php`, Grundsätze in `Principles.php`. Ohne Websuche: Structured Outputs (`outputConfig.format`), mit Websuche: JSON aus dem letzten Textblock.
- `FLIPPER_FAKE_AI=true` → `FixtureLlmClient` mit `tests/Fixtures/luna/*.json` (lokal ohne Key, in Tests).
- Bilder: `ExifNormalizer` (EXIF, HEIC), `RembgClient`, `PhotoComposer` (Port von `listing_photos.py`: 1280×1600, Verlauf 246→214, Kontaktschatten).
- Preise: `PriceAdvisor` (Median verkaufter Stücke, glatt gerundet, eBay +20 % auf ,90, 25 % Abschlag bei Gebrauchsspuren/Zubehör fehlt), Versand: `ShippingAdvisor` + `config/flipper.php`.
- Dateien liegen auf der local-Disk unter `storage/app/private/listings/{uuid}/{originals,thumbs,cutouts,final,zip}` und werden über Web-Routen `/files/...` (Session-Auth) ausgeliefert.

## UI
- batcave-ui v2.0.0 als Git-Submodul `resources/batcave-ui` (Klassen `.card`, `.btn`, `.form__*`, `.badge`, `.alert`, `.empty`, `.spinner` …, kein `bc-`-Präfix). Framework-Klassen nie überschreiben.
- Keine `<style>`-Blöcke in `.vue`. Eigene BEM-Blöcke in `resources/scss/_components.scss` / `_views.scss`. Wird ein Block generisch → nach batcave-ui.
- Mobile first, alles muss bei 390 px bedienbar sein.

## Befehle
```bash
composer run dev                  # Server + Queue + Vite
php artisan test                  # PHPUnit (Postgres-DB flipper_test)
npm test                          # Vitest
npm run build
php artisan user:create "Name" mail@example.com
```
- `npm install` braucht `legacy-peer-deps` (steht in `.npmrc`).
- Job-Timeout 300 s, daher `DB_QUEUE_RETRY_AFTER=360`. Nicht unter das Timeout senken.

## Deploy
Push auf `main` → GitHub-Webhook → `/deploy-hook` → systemd → `/usr/local/bin/flipper-deploy.sh` (fail-closed, Releases + Symlink, pg_dump vor migrate, ntfy). Siehe `.claude/commands/deploy.md`.
