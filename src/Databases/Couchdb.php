<?php

namespace Dzasa\MaratusPhpBackup\Databases;

use Symfony\Component\Process\Process;
use Net_SFTP;
use Net_SSH2;

class Couchdb {

    private $host = "localhost";
    private $port = "22";
    private $user = "";
    private $pass = "";
    private $remote = false;
    private $databaseDir = null;
    private $database = "";
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
        }

        if (isset($config['database'])) {
            $this->database = $config['database'];
        }

        $this->backupPath = $config['backup_path'];


        if ($this->database == "") {
            $this->backupFilename = "all-databases-" . date("Y-m-d-H-i-s");
            $this->backupName = "all-databases-" . date("Y-m-d-H-i-s");
        } else {
            $this->backupFilename = $this->database . "-" . date("Y-m-d-H-i-s") . ".couch";
            $this->backupName = $this->database . "-" . date("Y-m-d-H-i-s");
        }
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
        $this->result['message'] = "Successful backup of Couchdb in local file: " . $this->backupPath . $this->backupFilename;
        $this->result['backup_path'] = $this->backupPath;
        $this->result['backup_filename'] = $this->backupFilename;
        $this->result['backup_name'] = $this->backupName;
        $this->result['full_path'] = $this->backupPath . $this->backupFilename;
        $this->result['host'] = $this->host;


        return $this->result;
    }

    private function dumpLocal() {
        $couchDbConfigCommand = 'couch-config --db-dir';
        $couchDbConfig = new Process($couchDbConfigCommand);

        $couchDbConfig->run();

        if (!$couchDbConfig->isSuccessful()) {
            $this->result['status'] = 0;
            $this->result['message'] = $couchDbConfig->getErrorOutput();

            return $this->result;
        }
        $this->databaseDir = trim($couchDbConfig->getOutput());
        $this->databaseDir = rtrim($this->databaseDir, "/") . "/";

        $filesystem = new \Symfony\Component\Filesystem\Filesystem();
        if ($this->database != '') {

            if (!$filesystem->exists($this->databaseDir . $this->database)) {
                if (!$filesystem->exists($this->databaseDir . $this->database . ".couch")) {
                    $this->result['status'] = 0;
                    $this->result['message'] = "Defined Couchdb does not exist!";

                    return $this->result;
                } else {
                    $this->database = $this->database . ".couch";
                }
            }

            $filesystem->copy($this->databaseDir . $this->database, $this->backupPath . $this->backupFilename);
        } else {
            $filesystem->mkdir($this->backupPath . $this->backupFilename);

            $copyAllDatabasesCommand = 'cp -Ra ' . $this->databaseDir . '. ' . $this->backupPath . $this->backupFilename;
            $copyAllDatabases = new Process($copyAllDatabasesCommand);

            $copyAllDatabases->run();

            if (!$copyAllDatabases->isSuccessful()) {
                $this->result['status'] = 0;
                $this->result['message'] = $copyAllDatabases->getErrorOutput();

                return false;
            }
        }

        return true;
    }

    private function dumpRemote() {
        $ssh = new Net_SSH2($this->host, $this->port);

        if (!$ssh->login($this->user, $this->pass)) {
            $this->result['status'] = 0;
            $this->result['message'] = "Unable to login on SSH!";

            return false;
        }

        $couchDbConfigCommand = "couch-config --db-dir";
        //$ssh->enablePTY();
        $ssh->write($couchDbConfigCommand . "\n");
        $output = explode("\n", $ssh->read("couchdb"));


        $databaseDir = end($output);
        $this->databaseDir = rtrim($databaseDir, "/") . "/";


        $sftp = new Net_SFTP($this->host, $this->port);
        if (!$sftp->login($this->user, $this->pass)) {
            $this->result['status'] = 0;
            $this->result['message'] = "Unable to login on SSH!";

            return false;
        }
        if ($this->database != '') {

            if (!$sftp->get($this->databaseDir . $this->database, $this->backupPath . $this->backupFilename)) {
                if (!$sftp->get($this->databaseDir . $this->database . ".couch", $this->backupPath . $this->backupFilename)) {
                    $this->result['status'] = 0;
                    $this->result['message'] = "Unable to copy remote database(s) or wrong database name!";

                    return $this->result;
                }
            }
        }

        return true;
    }

}
