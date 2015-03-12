<?php

require 'vendor/autoload.php';

$gDriveConfig = array(
    'client_id' => '',
    'client_secret' => '',
    'auth_code' => ""
);

$client = new Google_Client();
// Get your credentials from the APIs Console
$client->setClientId($gDriveConfig['client_id']);
$client->setAccessType("offline");
$client->setApprovalPrompt("force");
$client->setClientSecret($gDriveConfig['client_secret']);
$client->setRedirectUri("urn:ietf:wg:oauth:2.0:oob");
$client->setScopes(array("https://www.googleapis.com/auth/drive"));

if (!file_exists("token.json")) {
    // Save token for future use
    $accessToken = $client->authenticate($gDriveConfig['auth_code']);
    file_put_contents("token.json", $accessToken);
}