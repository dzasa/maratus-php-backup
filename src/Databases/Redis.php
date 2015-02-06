<?php

namespace Dzasa\MaratusPhpBackup\Databases;

use Net_SCP;
use Net_SSH2;
use Crypt_RSA;

class Redis {

    private $host = "localhost";
    private $port = "22";
    private $user = "";
    private $pass = "";
    private $privateKey = "";
    private $privateKeyPass = "";
    private $remote = false;
    private $databasePath = "";
    private $backupPath = "";
    private $backupFilename = "";
    private $result = array(
        'status' => null,
        'message' => ""
    );

    function __construct($config = array()) {


        if (isset($config['remote']) && $config['remote'] == true) {
            $this->remote = true;

            if (isset($config['host'])) {
                $this->host = $config['host'];
            }

            if (isset($config['port'])) {
                $this->port = $config['port'];
            }

            if (isset($config['user'])) {
                $this->user = $config['user'];
            }

            if (isset($config['pass'])) {
                $this->pass = $config['pass'];
            }

            if (isset($config['private_key'])) {
                $this->privateKey = $config['private_key'];
            }

            if (isset($config['private_key_pass'])) {
                $this->privateKeyPass = $config['private_key_pass'];
            }
        }

        if (isset($config['database_path'])) {
            $this->databasePath = $config['database_path'];
        }

        $this->backupPath = $config['backup_path'];


        $this->backupFilename = date("Y-m-d-H-i-s") . ".rdb";
        $this->backupName = date("Y-m-d-H-i-s");
    }

    public function dump() {
        if (!$this->remote) {
            $localDumpResult = $this->dumpLocal();

            if (!$localDumpResult) {
                return $this->result;
            }
        } else {
            $remoteDumpResult = $this->dumpRemote();

            if (!$remoteDumpResult) {
                return $this->result;
            }
        }


        $this->result['status'] = 1;
        $this->result['message'] = "Successful backup of Redis in local file: " . $this->backupPath . $this->backupFilename;
        $this->result['backup_path'] = $this->backupPath;
        $this->result['backup_filename'] = $this->backupFilename;
        $this->result['backup_name'] = $this->backupName;
        $this->result['full_path'] = $this->backupPath . $this->backupFilename;
        $this->result['host'] = $this->host;


        return $this->result;
    }

    private function dumpLocal() {
        $filesystem = new \Symfony\Component\Filesystem\Filesystem();


        if (!$filesystem->exists($this->databasePath)) {
            $this->result['status'] = 0;
            $this->result['message'] = "Defined Redis dump does not exist!";

            return $this->result;
        }

        $filesystem->copy($this->databasePath, $this->backupPath . $this->backupFilename);

        return true;
    }

    private function dumpRemote() {
        $ssh = new Net_SSH2($this->host, $this->port);



        if ($this->privateKey != "") {
            $key = new Crypt_RSA();

            if ($this->privateKeyPass != "") {
                $key->setPassword($this->privateKeyPass);
            }

            $key->loadKey(file_get_contents($this->privateKey));

            $login = $ssh->login($this->user, $key);
        } else {
            $login = $ssh->login($this->user, $this->pass);
        }

        if (!$login) {
            $this->result['status'] = 0;
            $this->result['message'] = "Unable to login on SSH!";

            return false;
        }

        $scp = new Net_SCP($ssh);
        if (!$scp->get($this->databasePath, $this->backupPath . $this->backupFilename)) {
            $this->result['status'] = 0;
            $this->result['message'] = "Unable to copy remote database(s) or wrong database name!";

            return false;
        }

        return true;
    }

}
