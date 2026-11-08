Deploy auf die Batcave (LXC 114):
1. `php artisan test`, `npm test`, `npm run build` müssen grün sein.
2. `develop` nach `main` mergen und pushen. Der GitHub-Webhook (`/deploy-hook`) löst `flipper-deploy.sh` aus.
3. Ohne Webhook: `ssh web /usr/local/bin/flipper-deploy.sh` (Alias in ~/.ssh/config, ProxyJump batcave) (mit `--force`, um ohne neuen Commit neu zu bauen).
4. Ergebnis kommt per ntfy (Topic `flipper`). Log: `journalctl -u flipper-deploy -n 100`.
5. Prüfen: `curl -sI https://flipper.croeso.de/up` → 200.
Rollback: `ln -sfn /var/www/flipper-releases/<alter-sha> /var/www/flipper && systemctl reload php8.3-fpm`. DB-Backups vor jedem Deploy unter `/var/backups/flipper/`.
