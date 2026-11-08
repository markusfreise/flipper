# Flohmarkt

Aus Handyfotos ein fertiges Kleinanzeigen-/eBay-Inserat, in vier Schritten:

1. **Fotos** hochladen (Kamera oder Galerie, 1–10 Stück, Mängel als Nahaufnahme markieren)
2. **Erkennung** prüfen: Was ist das? Plus Foto-Check, was noch fehlt
3. **Drei Fragen**: Hintergrund, Zustand, Plattform
4. **Ergebnis**: freigestellte Bilder (einzeln oder als ZIP), Titel, Text, Preis, Versand, eBay-Artikelmerkmale zum Kopieren

Für die Familie Freise, läuft auf der Batcave unter https://flohmarkt.croeso.de.

## Lokal starten

```bash
git clone --recurse-submodules git@github.com:markusfreise/flohmarkt.git
cd flohmarkt
./_bootstrap/bootstrap-local.sh
php artisan user:create "Markus" markus@freise.de --admin
composer run dev
```

Ohne Anthropic-Key: `FLOHMARKT_FAKE_AI=true` in `.env`, dann antwortet die App mit dem Luna-Referenzlauf.
Freistellen braucht einen rembg-Server auf `REMBG_URL` (das Bootstrap-Skript startet ihn per Docker). Ohne rembg wird das Original verwendet.

## Tests

```bash
php artisan test   # PHPUnit, braucht die Postgres-DB flohmarkt_test
npm test           # Vitest
```

## Betrieb

- Einrichtung Batcave: `./_bootstrap/bootstrap-batcave.sh`, danach Cloudflare Public Hostname setzen.
- Deploy: Push auf `main` (Webhook) oder `ssh root@192.168.2.114 /usr/local/bin/flohmarkt-deploy.sh`.
- Konten: `php8.3 artisan user:create "Name" mail@…`, Passwort neu: `user:password mail@…`.
- Alte Inserate (> 90 Tage) löscht der Scheduler nachts.

Mehr: `PRD_Flohmarkt_v1.0.0.md`, `docs/backlog.md`, `CLAUDE.md`.
