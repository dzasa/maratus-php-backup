<?php

require 'vendor/autoload.php';

$client = new Google_Client();
$client->setClientId("");
$client->setClientSecret("");
$client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
$client->setAccessType("offline");
$client->setApprovalPrompt("force");
$client->setScopes(array('https://www.googleapis.com/auth/drive'));


$authUrl = $client->createAuthUrl();

echo "Auth URL:\n$authUrl\n\n";
