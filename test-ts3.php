<?php

require __DIR__ . '/vendor/autoload.php';

use PlanetTeamSpeak\TeamSpeak3Framework\TeamSpeak3;

echo "1. Starting\n";

$ts3 = TeamSpeak3::factory(
    'serverquery://serveradmin:SERVER_ADMIN_PASSWORD@SERVER_IP_ADRESS:10011/?server_port=9987&nickname=ChannelCreator-&blocking=0'
);

echo "2. Connected\n";

$server = $ts3;

echo "3. Server selected\n";

$info = $server->getInfo();

echo "4. Server info OK\n";

$clients = $server->clientList();

echo "5. Client list OK\n";

foreach ($clients as $client) {
    echo sprintf(
        "ID=%s | Type=%s | Name=%s | UID=%s\n",
        $client['clid'],
        $client['client_type'],
        (string)$client['client_nickname'],
        (string)$client['client_unique_identifier']
    );
}

echo "6. Test complete\n";

// WICHTIG:
// Noch KEIN $ts3->disconnect()
// Noch KEIN unset($ts3)
// Einfach Prozess beenden.

exit(0);