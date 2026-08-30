<?php
declare(strict_types=1);

/*
 * TeamSpeak 3 Channel Creator
 * Configuration
 */

$public = ""; // Google reCAPTCHA v2 Site Key
$secret = ""; // Google reCAPTCHA Secret Key

// TeamSpeak ServerQuery
$ts3_host = "127.0.0.1";
$ts3_q_port = 10011;
$ts3_s_port = 9987;

$ts3_username = "serveradmin";
$ts3_password = "";
$ts3_nick = "ChannelCreator-Bot";

// Channel settings
$cpid = 46;                // Parent channel ID
$chadmin_group_id = 5;     // Channel Admin group ID

$channel_description = "";
$channel_topic = "CHADD | WebUI @ " . date("H:i:s") . " - " . date("d.m.Y");

// TeamSpeak connection URL
$server_conn_url = "ts3server://127.0.0.1";

// Imprint
$imprint_url = "";

// Bad words
$badwords = [
    "ass",
    "badass"
];

/*
 * Server groups allowed to use the creator.
 *
 * Example:
 * [6, 7, 8, 10]
 *
 * The TeamSpeak client must have at least one
 * of these server groups.
 */
$allowed_groups = [6, 7, 8, 10];

/*
 * Cooldown per IP.
 *
 * 7 days = 604800 seconds.
 * Set to 0 to disable.
 */
$cooldown_seconds = 7 * 24 * 60 * 60;

/*
 * IP based UID detection is deliberately disabled.
 *
 * On hosting providers such as Strato the web server
 * usually sees a different IP than the TeamSpeak client.
 *
 * UID is therefore entered manually and checked directly
 * against the connected TeamSpeak clients.
 */
$enable_ip_uid_lookup = true;

/*
 * Only enable this when the website is behind a trusted
 * reverse proxy and you know exactly what you are doing.
 */
$trust_proxy_headers = false;


/*
 * Optional local configuration.
 *
 * Create config.local.php if you want to override
 * passwords/API keys/etc. without editing this file.
 */
$localConfig = __DIR__ . "/config.local.php";

if (is_file($localConfig)) {
    require $localConfig;
}