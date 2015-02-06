<?php

namespace Dzasa\MaratusPhpBackup\Clients;

use \Dropbox as dbx;

class Dropbox {

    private $accessToken = null;
    private $client = null;
    private $accountInfo = null;

    public function __construct($config = array()) {

        $this->accessToken = $config['access_token'];

        $this->auth();
    }

    private function auth() {
        $this->client = new dbx\Client($this->accessToken, "MaratusBackup/1.0");

        $this->accountInfo = $this->client->getAccountInfo();
    }

    public function store($fullPath, $dropboxPath) {

        $file = fopen($fullPath, "rb");
        $result = $this->client->uploadFileChunked("/" . $dropboxPath, dbx\WriteMode::add(), $file);
        fclose($file);

        return $result;
    }

}
