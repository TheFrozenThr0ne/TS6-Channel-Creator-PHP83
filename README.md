# TS3 Channel Creator – PHP 8.3+

Modernisierte Fassung des ursprünglichen Xuxe/TS3-Channel-Creator.

Live Demo: [https://FreeTS3Channel.GamersCentral.de/](https://freets3channel.gamerscentral.de/)

<img width="2050" height="966" alt="image" src="https://github.com/user-attachments/assets/c8d091d2-69c3-45b5-b603-ee049e667678" />


## Voraussetzungen

- PHP 8.3+
- Composer
- TeamSpeak 3 Server 3.4.0+ empfohlen
- ServerQuery-Zugang
- PHP-Erweiterungen: ctype, json, mbstring, openssl
- Für reCAPTCHA: funktionierender HTTPS-Zugriff vom Webserver zu Google

Das Projekt verwendet **`planetteamspeak/ts3-php-framework` 1.3.x** per Composer und kein mitgeliefertes altes `libs/TeamSpeak3` mehr.

## Installation

1. ZIP entpacken.
2. Im Ordner ausführen:

   `composer install --no-dev --optimize-autoloader`

   oder `install.bat` / `install.sh`.

3. `config.php` bearbeiten. Für lokale/private Einstellungen kann zusätzlich `config.local.php` angelegt werden.
4. Den Ordner auf den Webserver legen.
5. Webserver so konfigurieren, dass nur der Projektordner öffentlich erreichbar ist.

## TeamSpeak-Konfiguration

Die wichtigsten Werte:

- `ts3_host`
- `ts3_q_port`
- `ts3_s_port`
- `ts3_username`
- `ts3_password`
- `cpid`
- `chadmin_group_id`
- `allowed_groups`

**Wichtig:** `allowed_groups` darf nicht leer sein, wenn Benutzer zugelassen werden sollen.

Beispiel:

```php
$allowed_groups = [6, 7];
```

Verwende möglichst einen eigenen ServerQuery-Benutzer mit nur den benötigten Rechten statt `serveradmin`.

## UID-Erkennung

Die alte Version versucht die UID anhand der IP-Adresse des Webseitenbesuchers zu finden. Das ist nur zuverlässig, wenn TeamSpeak und Webzugriff aus Sicht des TeamSpeak-Servers dieselbe öffentliche IP verwenden.

Die neue Version:

- versucht weiterhin automatisch per IP zu erkennen;
- bietet aber zusätzlich eine direkte UID-Prüfung;
- verwendet beim Erstellen immer `clientGetByUid()`.

Damit funktioniert das Tool auch bei NAT, Proxy, Cloudflare, VPN usw., sofern die UID manuell angegeben wird.

## PHP 8.3+

Die Anwendung selbst ist auf PHP 8.3+ ausgelegt. Das offizielle TS3 PHP Framework 1.3.0 wurde mit PHP-8.3-Support veröffentlicht.

## Hinweise

- `storage/` muss für PHP beschreibbar sein, wenn der IP-Cooldown aktiviert ist.
- Wenn reCAPTCHA nicht verwendet werden soll, `public` und `secret` leer lassen. Für eine öffentliche Installation wird reCAPTCHA empfohlen.
- HTTPS wird für eine öffentliche Installation dringend empfohlen.

## Änderungen gegenüber dem Original

- altes mitgeliefertes TS3 Framework entfernt
- Composer/TS3 PHP Framework 1.3.x
- PHP 8.3+ `strict_types`
- direkte UID-Suche
- Fehler bei Gruppen-Whitelist behoben
- Fehler bei PHP-Stringverkettung behoben
- echtes JSON-POST
- serverseitige Validierung
- sicherere Cookie-/Cooldown-Logik
- moderne Fetch-API statt AngularJS/jQuery
- reCAPTCHA als Composer-Abhängigkeit
- IPv6-freundliche ServerQuery-URI
- Passwörter/UIDs korrekt behandelt
