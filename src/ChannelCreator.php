<?php
declare(strict_types=1);

use PlanetTeamSpeak\TeamSpeak3Framework\TeamSpeak3;

final class ChannelCreator
{
    public function __construct(
        private readonly array $config
    ) {
    }


    /**
     * Connect to TeamSpeak ServerQuery.
     *
     * IMPORTANT:
     *
     * blocking=0 is intentional.
     *
     * It avoids the shutdown/destructor memory problem
     * encountered with TeamSpeak PHP Framework 1.3.0.
     */
    public function connect(): object
    {
        $user = rawurlencode(
            (string)$this->config['ts3_username']
        );

        $pass = rawurlencode(
            (string)$this->config['ts3_password']
        );

        $host = (string)$this->config['ts3_host'];


        /*
         * IPv6 hosts require brackets in the URI.
         */
        if (
            filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV6
            ) !== false
            && !str_starts_with($host, '[')
        ) {
            $host = '[' . $host . ']';
        }


        $uri = sprintf(
            'serverquery://%s:%s@%s:%d/?server_port=%d&nickname=%s&blocking=0',
            $user,
            $pass,
            $host,
            (int)$this->config['ts3_q_port'],
            (int)$this->config['ts3_s_port'],
            rawurlencode(
                (string)$this->config['ts3_nick']
            )
        );


        /** @var object $server */
        $server = TeamSpeak3::factory($uri);

        return $server;
    }


    /**
     * Find a normal TeamSpeak client by Unique ID.
     *
     * We intentionally use clientList() instead of
     * clientGetByUid() because the latter caused memory
     * exhaustion in this environment.
     */
    public function getClientByUid(
        object $server,
        string $uuid
    ): object {
        $uuid = trim($uuid);


        if ($uuid === '') {
            throw new InvalidArgumentException(
                'TeamSpeak Unique ID is empty.'
            );
        }


        if (strlen($uuid) > 128) {
            throw new InvalidArgumentException(
                'TeamSpeak Unique ID is too long.'
            );
        }


        $clients = $server->clientList();


        foreach ($clients as $client) {

            /*
             * 0 = normal TeamSpeak client
             * 1 = ServerQuery client
             */
            if ((int)$client['client_type'] !== 0) {
                continue;
            }


            $clientUid = (string)(
                $client['client_unique_identifier'] ?? ''
            );


            if ($clientUid === $uuid) {

                error_log(
                    '[TS3 Channel Creator] UID validated. '
                    . 'UID='
                    . $uuid
                    . ' Name='
                    . (string)$client['client_nickname']
                );

                return $client;
            }
        }


        throw new RuntimeException(
            'TeamSpeak client with the supplied Unique ID is not connected.'
        );
    }


    /**
     * Automatically find a TeamSpeak client by public IP.
     *
     * The browser supplies the public IP because STRATO/PHP
     * may see a proxy/server IP instead of the visitor's IP.
     */
    public function getClientByIp(
        object $server,
        string $ip
    ): ?object {
        $ip = trim($ip);


        if (
            !filter_var(
                $ip,
                FILTER_VALIDATE_IP
            )
        ) {
            return null;
        }


        try {
            /*
             * Get all clients instead of using connection_client_ip
             * as a ServerQuery filter.
             */
            $clients = $server->clientList();

        } catch (Throwable $e) {

            error_log(
                '[TS3 Channel Creator] '
                . 'clientList() failed during IP lookup: '
                . $e::class
                . ': '
                . $e->getMessage()
            );

            return null;
        }


        foreach ($clients as $client) {

            /*
             * Ignore ServerQuery clients.
             */
            if ((int)$client['client_type'] !== 0) {
                continue;
            }


            $clientIp = trim(
                (string)(
                    $client['connection_client_ip'] ?? ''
                )
            );


            if ($clientIp === '') {
                continue;
            }


            /*
             * Convert IPv4-mapped IPv6 addresses:
             *
             * ::ffff:188.192.105.33
             *
             * becomes:
             *
             * 188.192.105.33
             */
            if (
                str_starts_with(
                    strtolower($clientIp),
                    '::ffff:'
                )
            ) {

                $mappedIp = substr(
                    $clientIp,
                    7
                );


                if (
                    filter_var(
                        $mappedIp,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_IPV4
                    ) !== false
                ) {
                    $clientIp = $mappedIp;
                }
            }


            if ($clientIp === $ip) {

                error_log(
                    '[TS3 Channel Creator] '
                    . 'Automatic UID detected. '
                    . 'IP='
                    . $ip
                    . ' UID='
                    . (string)$client[
                        'client_unique_identifier'
                    ]
                    . ' Name='
                    . (string)$client[
                        'client_nickname'
                    ]
                );


                return $client;
            }
        }


        error_log(
            '[TS3 Channel Creator] '
            . 'Automatic UID lookup failed. '
            . 'IP='
            . $ip
        );


        return null;
    }


    /**
     * Check whether a TeamSpeak client belongs to an
     * allowed server group.
     */
    public function isAllowed(
        object $client
    ): bool {
        $allowed = array_values(
            array_filter(
                array_map(
                    'intval',
                    (array)$this->config['allowed_groups']
                ),
                static fn(int $id): bool =>
                    $id > 0
            )
        );


        if ($allowed === []) {
            return false;
        }


        $groups = array_map(
            'intval',
            preg_split(
                '/[,\s]+/',
                (string)$client[
                    'client_servergroups'
                ],
                -1,
                PREG_SPLIT_NO_EMPTY
            )
        );


        return count(
            array_intersect(
                $allowed,
                $groups
            )
        ) > 0;
    }


    /**
     * Create channel and Channel Group token.
     */
    public function createChannel(
        object $server,
        array $data
    ): array {
        $codec = match (
            (int)$data['codec']
        ) {
            1 =>
                TeamSpeak3::CODEC_OPUS_VOICE,

            2 =>
                TeamSpeak3::CODEC_CELT_MONO,

            3 =>
                TeamSpeak3::CODEC_SPEEX_ULTRAWIDEBAND,

            default =>
                TeamSpeak3::CODEC_OPUS_VOICE,
        };


        $quality = max(
            1,
            min(
                10,
                (int)$data['quality']
            )
        );


        error_log(
            '[TS3 Channel Creator] '
            . 'CHADD: creating channel'
        );


        /*
         * =====================================================
         * Create channel
         * =====================================================
         */
        $channel = $server->channelCreate([

            'channel_name' =>
                (string)$data['channelname'],

            'channel_password' =>
                (string)$data['password'],

            'channel_topic' =>
                (string)$this->config[
                    'channel_topic'
                ],

            'channel_codec' =>
                $codec,

            'channel_codec_quality' =>
                $quality,

            'channel_flag_permanent' =>
                false,

            'channel_flag_semi_permanent' =>
                true,

            'cpid' =>
                (int)$this->config['cpid'],

            'channel_description' =>
                (string)$this->config[
                    'channel_description'
                ],
        ]);


        error_log(
            '[TS3 Channel Creator] '
            . 'CHADD: channel created'
        );


        /*
         * Framework 1.3.0 returns the channel ID
         * as an integer.
         */
        $cid = (int)$channel;


        if ($cid <= 0) {
            throw new RuntimeException(
                'TeamSpeak returned an invalid channel ID.'
            );
        }


        error_log(
            '[TS3 Channel Creator] '
            . 'CHADD: CID='
            . $cid
        );


        /*
         * =====================================================
         * Server log
         * =====================================================
         */
        try {

            $server->logAdd(
                'Channel '
                . $cid
                . ' created from IP:'
                . (string)$data['ip'],
                TeamSpeak3::LOGLEVEL_INFO
            );


            error_log(
                '[TS3 Channel Creator] '
                . 'CHADD: logAdd OK'
            );

        } catch (Throwable $e) {

            /*
             * Logging failure must not invalidate the
             * already-created channel.
             */
            error_log(
                '[TS3 Channel Creator] '
                . 'CHADD: logAdd FAILED: '
                . $e::class
                . ' - '
                . $e->getMessage()
            );
        }


        /*
         * =====================================================
         * Create Channel Group token
         * =====================================================
         *
         * Framework 1.3.0:
         *
         * privilegeKeyCreate(
         *     id1,
         *     id2,
         *     type,
         *     description
         * )
         *
         * Channel Group token:
         *
         * id1 = Channel Group ID
         * id2 = Channel ID
         * type = TOKEN_CHANNELGROUP
         */
        try {

            $token = $server->privilegeKeyCreate(
                (int)$this->config[
                    'chadmin_group_id'
                ],

                $cid,

                TeamSpeak3::TOKEN_CHANNELGROUP,

                'TOKEN created from CHADD.'
            );


            $token = (string)$token;


            if ($token === '') {
                throw new RuntimeException(
                    'TeamSpeak returned an empty privilege token.'
                );
            }


            error_log(
                '[TS3 Channel Creator] '
                . 'CHADD: privilegeKeyCreate OK'
            );

        } catch (Throwable $e) {

            error_log(
                '[TS3 Channel Creator] '
                . 'CHADD: privilegeKeyCreate FAILED: '
                . $e::class
                . ' - '
                . $e->getMessage()
            );

            throw $e;
        }


        /*
         * =====================================================
         * TeamSpeak connection URL
         * =====================================================
         */
        $url = rtrim(
            (string)$this->config[
                'server_conn_url'
            ],
            '?&'
        )
        . '?port='
        . (int)$this->config[
            'ts3_s_port'
        ]
        . '&cid='
        . $cid
        . '&channelpassword='
        . rawurlencode(
            (string)$data['password']
        )
        . '&token='
        . rawurlencode($token);


        return [
            'cid' =>
                $cid,

            'token' =>
                $token,

            'url' =>
                $url,
        ];
    }
}