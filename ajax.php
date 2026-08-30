<?php
declare(strict_types=1);


/*
 * ============================================================
 * Logging
 * ============================================================
 */

ini_set('log_errors', '1');

ini_set(
    'error_log',
    __DIR__ . '/storage/ts3-debug.log'
);


error_log(
    '[TS3 Channel Creator] REQUEST: '
    . ($_SERVER['REQUEST_METHOD'] ?? '?')
    . ' '
    . ($_SERVER['REQUEST_URI'] ?? '?')
);


require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/ChannelCreator.php';
require_once __DIR__ . '/src/AbuseGuard.php';


header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'X-Content-Type-Options: nosniff'
);


/**
 * Send JSON response.
 */
function responseJson(
    int $code,
    string $header,
    string $msg = '',
    array $extra = []
): never {

    http_response_code(
        $code >= 400
            ? $code
            : 200
    );


    echo json_encode(
        array_merge(
            [
                'code' =>
                    $code,

                'header' =>
                    $header,

                'msg' =>
                    $msg,
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );


    exit;
}


/**
 * Read JSON request body.
 */
function requestData(): array
{
    $raw = file_get_contents(
        'php://input'
    );


    if (
        $raw === false
        || trim($raw) === ''
    ) {

        responseJson(
            400,
            'Error :(',
            'Empty request.'
        );
    }


    try {

        $data = json_decode(
            $raw,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

    } catch (JsonException) {

        responseJson(
            400,
            'Error :(',
            'Invalid JSON request.'
        );
    }


    if (!is_array($data)) {

        responseJson(
            400,
            'Error :(',
            'Invalid request.'
        );
    }


    return $data;
}


/**
 * Get web client IP.
 *
 * This IP is used for cooldown/security logging only.
 *
 * Automatic UID detection does NOT use this value because
 * STRATO may provide a proxy/server address.
 */
function clientIp(
    bool $trustProxyHeaders
): string {

    if ($trustProxyHeaders) {

        foreach (
            [
                'HTTP_CF_CONNECTING_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_REAL_IP',
            ] as $key
        ) {

            if (
                empty(
                    $_SERVER[$key]
                )
            ) {
                continue;
            }


            $candidate = trim(
                explode(
                    ',',
                    (string)$_SERVER[$key]
                )[0]
            );


            if (
                filter_var(
                    $candidate,
                    FILTER_VALIDATE_IP
                )
            ) {
                return $candidate;
            }
        }
    }


    return filter_var(
        $_SERVER['REMOTE_ADDR'] ?? '',
        FILTER_VALIDATE_IP
    ) ?: '0.0.0.0';
}


/**
 * Verify Google reCAPTCHA.
 */
function recaptchaVerify(
    string $secret,
    string $token,
    string $ip
): void {

    /*
     * Empty secret = disabled.
     */
    if ($secret === '') {
        return;
    }


    if ($token === '') {

        responseJson(
            403,
            'Error :(',
            'Please complete the reCAPTCHA.'
        );
    }


    try {

        $recaptcha =
            new \ReCaptcha\ReCaptcha(
                $secret
            );


        $result =
            $recaptcha->verify(
                $token,
                $ip
            );

    } catch (Throwable $e) {

        error_log(
            '[TS3 Channel Creator] '
            . 'reCAPTCHA failed: '
            . $e::class
            . ' - '
            . $e->getMessage()
        );


        responseJson(
            502,
            'Error :(',
            'reCAPTCHA verification failed.'
        );
    }


    if (!$result->isSuccess()) {

        $errors =
            $result->getErrorCodes();


        responseJson(
            403,
            'Error :(',
            $errors[0]
                ?? 'reCAPTCHA verification failed.'
        );
    }
}


/*
 * ============================================================
 * Request type
 * ============================================================
 *
 * type=1 = UID check / automatic detection
 * type=0 = channel creation
 */

$type = filter_input(
    INPUT_GET,
    'type',
    FILTER_VALIDATE_INT
);


if (
    $type === false
    || $type === null
) {

    responseJson(
        400,
        'Bad Request',
        'Invalid request type.'
    );
}


/*
 * Web server IP.
 *
 * ONLY used for cooldown/logging.
 */
$ip = clientIp(
    (bool)$trust_proxy_headers
);


error_log(
    '[TS3 Channel Creator] '
    . 'SERVER IP='
    . $ip
);


/*
 * Create ChannelCreator.
 */
$creator = new ChannelCreator([

    'ts3_host' =>
        $ts3_host,

    'ts3_q_port' =>
        $ts3_q_port,

    'ts3_s_port' =>
        $ts3_s_port,

    'ts3_username' =>
        $ts3_username,

    'ts3_password' =>
        $ts3_password,

    'ts3_nick' =>
        $ts3_nick,

    'cpid' =>
        $cpid,

    'chadmin_group_id' =>
        $chadmin_group_id,

    'channel_description' =>
        $channel_description,

    'channel_topic' =>
        $channel_topic,

    'server_conn_url' =>
        $server_conn_url,

    'allowed_groups' =>
        $allowed_groups,
]);


try {

    /*
     * Connect to TeamSpeak.
     */
    $server =
        $creator->connect();


    /*
     * ========================================================
     * TYPE 1
     * UID check / automatic detection
     * ========================================================
     */
    if ($type === 1) {

        /*
         * ----------------------------------------------------
         * Automatic detection
         * ----------------------------------------------------
         *
         * The browser sends its public IP as ?ip=...
         */
        $browserIp = trim(
            (string)(
                $_GET['ip'] ?? ''
            )
        );


        if (
            $browserIp !== ''
            && filter_var(
                $browserIp,
                FILTER_VALIDATE_IP
            )
        ) {

            error_log(
                '[TS3 Channel Creator] '
                . 'AUTO IP='
                . $browserIp
            );


            /*
             * Only allow automatic IP lookup when explicitly
             * enabled in config.php.
             */
            if (!$enable_ip_uid_lookup) {

                responseJson(
                    403,
                    'Auto detection disabled',
                    'Automatic UID detection is disabled.'
                );
            }


            $client =
                $creator->getClientByIp(
                    $server,
                    $browserIp
                );


            if ($client !== null) {

                responseJson(
                    200,
                    'Authenticated',
                    'Client found.',
                    [
                        'uuid' =>
                            (string)$client[
                                'client_unique_identifier'
                            ],

                        'name' =>
                            (string)$client[
                                'client_nickname'
                            ],
                    ]
                );
            }


            responseJson(
                404,
                'Client not found.',
                'No connected TeamSpeak client was found for your public IP.'
            );
        }


        /*
         * ----------------------------------------------------
         * Manual UID lookup
         * ----------------------------------------------------
         */
        $uuid = trim(
            (string)(
                $_GET['uuid'] ?? ''
            )
        );


        if ($uuid === '') {

            responseJson(
                422,
                'UID required',
                'Please enter your TeamSpeak Unique ID.'
            );
        }


        try {

            $client =
                $creator->getClientByUid(
                    $server,
                    $uuid
                );

        } catch (Throwable $e) {

            error_log(
                '[TS3 Channel Creator] '
                . 'Manual UID lookup failed: '
                . $e::class
                . ' - '
                . $e->getMessage()
            );


            responseJson(
                404,
                'Client not found.',
                'This TeamSpeak Unique ID is not currently connected to the selected server.'
            );
        }


        responseJson(
            200,
            'Authenticated',
            'Client found.',
            [
                'uuid' =>
                    (string)$client[
                        'client_unique_identifier'
                    ],

                'name' =>
                    (string)$client[
                        'client_nickname'
                    ],
            ]
        );
    }


    /*
     * ========================================================
     * TYPE 0
     * Create channel
     * ========================================================
     */
    if ($type === 0) {

        error_log(
            '[TS3 Channel Creator] '
            . 'TYPE=0 CREATE'
        );


        $data =
            requestData();


        $uuid = trim(
            (string)(
                $data['uuid'] ?? ''
            )
        );


        $channelName = trim(
            (string)(
                $data['channelname'] ?? ''
            )
        );


        $password =
            (string)(
                $data['password'] ?? ''
            );


        $codec =
            (int)(
                $data['codec'] ?? 1
            );


        $quality =
            (int)(
                $data['quality'] ?? 7
            );


        $captcha =
            trim(
                (string)(
                    $data['captcha_resp'] ?? ''
                )
            );


        error_log(
            '[TS3 Channel Creator] '
            . 'CREATE UUID='
            . $uuid
        );


        /*
         * UID validation.
         */
        if ($uuid === '') {

            responseJson(
                422,
                'Error :(',
                'Please enter your TeamSpeak Unique ID.'
            );
        }


        if (strlen($uuid) > 128) {

            responseJson(
                422,
                'Error :(',
                'The TeamSpeak Unique ID is too long.'
            );
        }


        /*
         * Channel name validation.
         */
        if (
            $channelName === ''
            || mb_strlen($channelName) > 40
        ) {

            responseJson(
                422,
                'Error :(',
                'The channel name must contain 1 to 40 characters.'
            );
        }


        /*
         * Password validation.
         */
        if (
            $password === ''
            || mb_strlen($password) > 40
        ) {

            responseJson(
                422,
                'Error :(',
                'The password must contain 1 to 40 characters.'
            );
        }


        /*
         * Codec validation.
         */
        if (!in_array(
            $codec,
            [1, 2, 3],
            true
        )) {
            $codec = 1;
        }


        /*
         * Quality.
         */
        $quality = max(
            1,
            min(
                10,
                $quality
            )
        );


        /*
         * reCAPTCHA.
         */
        recaptchaVerify(
            (string)$secret,
            $captcha,
            $ip
        );


        /*
         * Cooldown.
         */
        $guard = new AbuseGuard(
            __DIR__
            . '/storage/cooldown.json',
            (int)$cooldown_seconds
        );


        if ($guard->blocked($ip)) {

            responseJson(
                429,
                'Error :(',
                'You cannot create another channel yet.'
            );
        }


        /*
         * Bad words.
         */
        $badwords = array_values(
            array_filter(
                array_map(
                    static fn($value): string =>
                        trim((string)$value),
                    $badwords
                ),
                static fn(string $value): bool =>
                    $value !== ''
            )
        );


        if ($badwords !== []) {

            $channelName =
                str_ireplace(
                    $badwords,
                    'XXXX',
                    $channelName
                );
        }


        /*
         * Block IPv4 addresses in channel name.
         */
        if (preg_match(
            '/(?<!\d)(?:\d{1,3}\.){3}\d{1,3}(?!\d)/',
            $channelName
        )) {

            responseJson(
                403,
                'Error :(',
                'IP addresses or domains are not allowed in the channel name.'
            );
        }


        /*
         * Block URLs.
         */
        if (preg_match(
            '/(?:https?:\/\/|www\.)/i',
            $channelName
        )) {

            responseJson(
                403,
                'Error :(',
                'IP addresses or domains are not allowed in the channel name.'
            );
        }


        /*
         * ----------------------------------------------------
         * Validate UID against TeamSpeak
         * ----------------------------------------------------
         *
         * IMPORTANT:
         *
         * We do NOT use the web server IP here.
         *
         * The UID detected by the browser is used directly.
         */
        try {

            error_log(
                '[TS3 Channel Creator] '
                . 'CREATE: validating UID='
                . $uuid
            );


            $client =
                $creator->getClientByUid(
                    $server,
                    $uuid
                );


            error_log(
                '[TS3 Channel Creator] '
                . 'CREATE: UID validated, client='
                . (string)$client[
                    'client_nickname'
                ]
            );

        } catch (Throwable $e) {

            error_log(
                '[TS3 Channel Creator] '
                . 'CREATE: UID validation FAILED: '
                . $e::class
                . ' - '
                . $e->getMessage()
            );


            responseJson(
                404,
                'Client not found.',
                'Your TeamSpeak Unique ID was not found on the selected server. Make sure TeamSpeak is connected and the UID is correct.'
            );
        }


        /*
         * Check allowed server group.
         */
        if (!$creator->isAllowed($client)) {

            error_log(
                '[TS3 Channel Creator] '
                . 'CREATE: user not allowed. '
                . 'UID='
                . $uuid
            );


            responseJson(
                403,
                'Not Authorized',
                'You are not in a whitelisted TeamSpeak server group.'
            );
        }


        /*
         * ----------------------------------------------------
         * Create channel
         * ----------------------------------------------------
         */
        try {

            $result =
                $creator->createChannel(
                    $server,
                    [
                        'channelname' =>
                            $channelName,

                        'password' =>
                            $password,

                        'codec' =>
                            $codec,

                        'quality' =>
                            $quality,

                        'ip' =>
                            $ip,
                    ]
                );

        } catch (Throwable $e) {

            error_log(
                '[TS3 Channel Creator] '
                . 'CREATE CHANNEL FAILED: '
                . $e::class
                . ' - '
                . $e->getMessage()
            );


            responseJson(
                500,
                'TS3-Error',
                'Channel konnte nicht erstellt werden.'
            );
        }


        /*
         * Only start cooldown after successful creation.
         */
        $guard->touch($ip);


        /*
         * Success.
         */
        error_log(
            '[TS3 Channel Creator] '
            . 'CREATE SUCCESS CID='
            . $result['cid']
        );


        responseJson(
            200,
            'All fine! :)',
            'Channel created.',
            [
                'code' =>
                    1,

                'token' =>
                    $result['token'],

                'url' =>
                    $result['url'],

                'cid' =>
                    $result['cid'],
            ]
        );
    }


    /*
     * Unknown request type.
     */
    responseJson(
        400,
        'Bad Request',
        'Unknown request type.'
    );

} catch (Throwable $e) {

    error_log(
        sprintf(
            '[TS3 Channel Creator] '
            . '%s: %s in %s:%d',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        )
    );


    responseJson(
        500,
        'TS3-Error',
        'TeamSpeak connection or operation failed. Check the PHP error log for details.'
    );
}