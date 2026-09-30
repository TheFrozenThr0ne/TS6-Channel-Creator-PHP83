# TS6 Channel Creator – PHP 8.3+

Modernized version of the original Xuxe/TS3-Channel-Creator.

Live Demo: [https://GamersCentral.de/ts6channel/](https://gamerscentral.de/ts6channel/)

<img width="2050" height="966" alt="image" src="https://github.com/user-attachments/assets/c8d091d2-69c3-45b5-b603-ee049e667678" />

## Requirements

* PHP 8.3+
* Composer
* TeamSpeak 3 Server 3.4.0+ recommended
* ServerQuery access
* PHP extensions: ctype, json, mbstring, openssl
* For reCAPTCHA: working HTTPS access from the web server to Google

The project uses **`planetteamspeak/ts3-php-framework` 1.3.x** via Composer and no longer includes the old `libs/TeamSpeak3` library.

## Installation

1. Extract the ZIP archive.

2. Run the following command in the project directory:

   `composer install --no-dev --optimize-autoloader`

   or use `install.bat` / `install.sh`.

3. Edit `config.php`. For local/private settings, you can additionally create `config.local.php`.

4. Upload the directory to your web server.

5. Configure the web server so that only the project directory is publicly accessible.

## TeamSpeak Configuration

The most important settings are:

* `ts3_host`
* `ts3_q_port`
* `ts3_s_port`
* `ts3_username`
* `ts3_password`
* `cpid`
* `chadmin_group_id`
* `allowed_groups`

**Important:** `allowed_groups` must not be empty if users are supposed to be allowed to create channels.

Example:

```php
$allowed_groups = [6, 7];
```

Whenever possible, use a dedicated ServerQuery user with only the required permissions instead of `serveradmin`.

## UID Detection

The old version attempts to determine the UID based on the IP address of the website visitor. This is only reliable if TeamSpeak and the web connection appear to use the same public IP from the TeamSpeak server's perspective.

The new version:

* still attempts automatic detection via IP;
* additionally provides a direct UID check;
* always uses `clientGetByUid()` when creating a channel.

This means the tool also works with NAT, proxies, Cloudflare, VPNs, etc., as long as the UID is entered manually.

## PHP 8.3+

The application itself is designed for PHP 8.3+. The official TS3 PHP Framework 1.3.0 was released with PHP 8.3 support.

## Notes

* `storage/` must be writable by PHP if the IP cooldown is enabled.
* If reCAPTCHA is not required, leave `public` and `secret` empty. For a public installation, reCAPTCHA is recommended.
* HTTPS is strongly recommended for any public installation.

## Changes Compared to the Original

* Removed the old bundled TS3 framework
* Composer / TS3 PHP Framework 1.3.x
* PHP 8.3+ `strict_types`
* Direct UID lookup
* Fixed group whitelist issues
* Fixed PHP string concatenation issues
* Proper JSON POST handling
* Server-side validation
* More secure cookie/cooldown logic
* Modern Fetch API instead of AngularJS/jQuery
* reCAPTCHA as a Composer dependency
* IPv6-friendly ServerQuery URI
* Proper handling of passwords/UIDs
