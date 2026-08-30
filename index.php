<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
?>
<!doctype html>

<html lang="de">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        TeamSpeak Channel Creator
    </title>

    <meta
        name="description"
        content="TeamSpeak Channel Creator"
    >

    <?php if ($public !== ''): ?>

        <script
            src="https://www.google.com/recaptcha/api.js"
            async
            defer
        ></script>

    <?php endif; ?>


    <style>

        :root {
            color-scheme: dark;
        }


        body {
            margin: 0;

            font-family:
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background: #111827;
            color: #f3f4f6;
        }


        .wrap {
            max-width: 760px;
            margin: 40px auto;
            padding: 20px;
        }


        .card {
            background: #1f2937;
            border: 1px solid #374151;
            border-radius: 16px;
            padding: 28px;

            box-shadow:
                0 12px 40px rgba(0, 0, 0, .25);
        }


        h1 {
            margin-top: 0;
        }


        label {
            display: block;
            margin: 18px 0 7px;
            font-weight: 600;
        }


        input,
        select {
            width: 100%;
            box-sizing: border-box;

            padding: 12px 13px;

            border-radius: 9px;
            border: 1px solid #4b5563;

            background: #111827;
            color: #fff;

            font-size: 16px;
        }


        input[type=range] {
            padding: 0;
        }


        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }


        button {
            margin-top: 20px;

            padding: 12px 18px;

            border: 0;
            border-radius: 9px;

            background: #dc2626;
            color: white;

            font-size: 16px;
            font-weight: 700;

            cursor: pointer;
        }


        button.secondary {
            background: #374151;
            margin-left: 8px;
        }


        button:disabled {
            opacity: .6;
            cursor: not-allowed;
        }


        .hint {
            color: #9ca3af;
            font-size: 14px;
            margin-top: 6px;
        }


        #status {
            margin-bottom: 18px;
            padding: 12px;

            border-radius: 9px;

            background: #111827;

            white-space: pre-wrap;
        }


        a {
            color: #fca5a5;
        }


        @media (max-width: 650px) {

            .row {
                grid-template-columns: 1fr;
            }


            .wrap {
                margin: 10px auto;
            }


            .card {
                padding: 20px;
            }
        }

    </style>

</head>


<body>

<div class="wrap">

    <div class="card">

        <h1>
            TeamSpeak Channel Creator
        </h1>


        <div id="status">
            Verbinde mit TeamSpeak…
        </div>


        <form id="creatorForm">

            <label for="uuid">
                TeamSpeak Unique ID
            </label>


            <input
                id="uuid"
                name="uuid"
                maxlength="128"
                autocomplete="off"
                required
            >


            <div class="hint">
                Die TeamSpeak Unique ID wird automatisch
                anhand deiner öffentlichen IP erkannt.
            </div>


            <div class="row">

                <div>

                    <label for="cname">
                        Channel Name
                    </label>


                    <input
                        id="cname"
                        name="channelname"
                        maxlength="40"
                        required
                    >

                </div>


                <div>

                    <label for="password">
                        Passwort
                    </label>


                    <input
                        id="password"
                        name="password"
                        maxlength="40"
                        type="password"
                        required
                    >

                </div>

            </div>


            <div class="row">

                <div>

                    <label for="codec">
                        Channel Codec
                    </label>


                    <select
                        id="codec"
                        name="codec"
                    >

                        <option value="1">
                            Opus Voice
                        </option>

                        <option value="2">
                            CELT Mono
                        </option>

                        <option value="3">
                            Speex Ultra-Wideband
                        </option>

                    </select>

                </div>


                <div>

                    <label for="quality">

                        Codec Quality:

                        <span id="qualityValue">
                            7
                        </span>

                    </label>


                    <input
                        id="quality"
                        name="quality"
                        type="range"
                        min="1"
                        max="10"
                        value="7"
                    >

                </div>

            </div>


            <?php if ($public !== ''): ?>

                <div style="margin-top:18px">

                    <div
                        class="g-recaptcha"
                        data-sitekey="<?= htmlspecialchars(
                            $public,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    ></div>

                </div>

            <?php endif; ?>


            <button
                id="createButton"
                type="submit"
            >
                Channel erstellen
            </button>


            <button
                id="lookupButton"
                class="secondary"
                type="button"
            >
                UID prüfen
            </button>

        </form>


        <?php if ($imprint_url !== ''): ?>

            <p style="margin-top:25px">

                <a
                    href="<?= htmlspecialchars(
                        $imprint_url,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
                    Impressum
                </a>

            </p>

        <?php endif; ?>

    </div>

</div>


<script>

const $ = (id) =>
    document.getElementById(id);


const statusBox =
    $('status');


const form =
    $('creatorForm');


const uuid =
    $('uuid');


const quality =
    $('quality');


const qualityValue =
    $('qualityValue');


const createButton =
    $('createButton');


const lookupButton =
    $('lookupButton');


/*
 * ============================================================
 * Quality slider
 * ============================================================
 */

quality.addEventListener(
    'input',
    () => {
        qualityValue.textContent =
            quality.value;
    }
);


/*
 * ============================================================
 * Status helper
 * ============================================================
 */

function status(
    header,
    msg = ''
) {

    statusBox.textContent =
        header +
        (
            msg
                ? "\n" + msg
                : ''
        );
}


/*
 * ============================================================
 * Generic API helper
 * ============================================================
 */

async function api(
    url,
    options = {}
) {

    const response =
        await fetch(
            url,
            {
                ...options,

                headers: {
                    'Accept':
                        'application/json',

                    ...(options.headers || {})
                },

                cache: 'no-store'
            }
        );


    const text =
        await response.text();


    let data;


    try {

        data =
            JSON.parse(text);

    } catch (error) {

        throw new Error(
            'Ungültige Serverantwort (HTTP '
            + response.status
            + '): '
            + text.substring(0, 500)
        );
    }


    return data;
}


/*
 * ============================================================
 * Automatic UID detection
 * ============================================================
 *
 * The browser determines its public IPv4.
 *
 * STRATO itself may see IPv6 / proxy IP, therefore we
 * deliberately do this in the browser.
 */
async function tryAutoDetect()
{
    try {

        status(
            'Ermittle öffentliche IP…'
        );


        const ipResponse =
            await fetch(
                'https://api.ipify.org?format=json',
                {
                    cache: 'no-store'
                }
            );


        if (!ipResponse.ok) {

            throw new Error(
                'Öffentliche IP konnte nicht ermittelt werden.'
            );
        }


        const ipData =
            await ipResponse.json();


        const publicIp =
            String(
                ipData.ip || ''
            ).trim();


        if (!publicIp) {

            throw new Error(
                'Keine öffentliche IP erhalten.'
            );
        }


        console.log(
            '[TS3 Creator] Browser public IP:',
            publicIp
        );


        status(
            'Prüfe TeamSpeak-Verbindung…'
        );


        const data =
            await api(
                'ajax.php?type=1&ip='
                + encodeURIComponent(publicIp)
                + '&_='
                + Date.now()
            );


        if (data.uuid) {

            uuid.value =
                data.uuid;


            status(
                'Hallo '
                + (data.name || '')
                + '!',
                'TeamSpeak UID wurde automatisch erkannt.'
            );


            return;
        }


        throw new Error(
            data.msg ||
            'TeamSpeak-Client wurde nicht gefunden.'
        );


    } catch (error) {

        console.error(
            '[TS3 Creator] '
            + 'Automatic UID detection failed:',
            error
        );


        status(
            'UID konnte nicht automatisch erkannt werden.',
            'Bitte TeamSpeak geöffnet und mit dem Server verbunden lassen. Danach "UID prüfen" klicken.'
        );
    }
}


/*
 * ============================================================
 * Manual UID check
 * ============================================================
 */

lookupButton.addEventListener(
    'click',
    async () => {

        const value =
            uuid.value.trim();


        if (!value) {

            status(
                'Fehler',
                'Bitte zuerst eine TeamSpeak Unique ID eintragen.'
            );

            return;
        }


        lookupButton.disabled =
            true;


        status(
            'Prüfe UID…'
        );


        try {

            const data =
                await api(
                    'ajax.php?type=1&uuid='
                    + encodeURIComponent(value)
                    + '&_='
                    + Date.now()
                );


            status(
                data.header ||
                'Antwort',

                data.msg ||
                (
                    data.name
                        ? 'Gefunden: ' + data.name
                        : ''
                )
            );


        } catch (error) {

            console.error(
                '[TS3 Creator] '
                + 'UID lookup failed:',
                error
            );


            status(
                'Fehler',
                error.message
            );

        } finally {

            lookupButton.disabled =
                false;
        }
    }
);


/*
 * ============================================================
 * Create channel
 * ============================================================
 */

form.addEventListener(
    'submit',
    async (event) => {

        event.preventDefault();


        console.log(
            '[TS3 Creator] CREATE BUTTON CLICKED'
        );


        const currentUuid =
            uuid.value.trim();


        if (!currentUuid) {

            status(
                'Fehler',
                'Keine TeamSpeak Unique ID vorhanden.'
            );

            return;
        }


        const payload = {

            uuid:
                currentUuid,

            channelname:
                $('cname').value.trim(),

            password:
                $('password').value,

            codec:
                Number(
                    $('codec').value
                ),

            quality:
                Number(
                    quality.value
                ),

            captcha_resp:
                window.grecaptcha
                    ? grecaptcha.getResponse()
                    : ''
        };


        createButton.disabled =
            true;


        status(
            'Channel wird erstellt…'
        );


        try {

            const response =
                await fetch(
                    'ajax.php?type=0&_='
                    + Date.now(),

                    {
                        method: 'POST',

                        headers: {
                            'Accept':
                                'application/json',

                            'Content-Type':
                                'application/json'
                        },

                        cache: 'no-store',

                        body:
                            JSON.stringify(
                                payload
                            )
                    }
                );


            const text =
                await response.text();


            let data;


            try {

                data =
                    JSON.parse(text);

            } catch (error) {

                throw new Error(
                    'Ungültige Serverantwort (HTTP '
                    + response.status
                    + '): '
                    + text.substring(0, 500)
                );
            }


            if (data.code === 1) {

                status(
                    data.header ||
                    'All fine! :)',

                    'Channel erstellt! Token: '
                    + data.token
                );


                if (data.url) {

                    const link =
                        document.createElement(
                            'a'
                        );


                    link.href =
                        data.url;


                    link.textContent =
                        'Mit TeamSpeak verbinden';


                    link.target =
                        '_blank';


                    link.rel =
                        'noopener';


                    statusBox.appendChild(
                        document.createElement(
                            'br'
                        )
                    );


                    statusBox.appendChild(
                        link
                    );
                }


            } else {

                status(
                    data.header ||
                    'Fehler',

                    data.msg ||
                    'Unbekannter Fehler.'
                );
            }


        } catch (error) {

            console.error(
                '[TS3 Creator] '
                + 'CREATE FAILED:',
                error
            );


            status(
                'Fehler beim Erstellen',
                error.message
            );

        } finally {

            if (
                window.grecaptcha
            ) {
                grecaptcha.reset();
            }


            createButton.disabled =
                false;
        }
    }
);


/*
 * ============================================================
 * Start automatic detection
 * ============================================================
 */

tryAutoDetect();

</script>

</body>

</html>