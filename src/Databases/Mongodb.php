<?php

namespace Dzasa\MaratusPhpBackup\Databases;

use Symfony\Component\Process\Process;

class Mongodb {

    private $host = "localhost";
    private $port = "27017";
    private $user = "";
    private $pass = "";
    private $database = "";
    private $backupPath = "";
    private $backupFilename = "";
    private $result = array(
        'status' => null,
        'message' => ""
    );

    function __construct($config = array()) {
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

        if (isset($config['database'])) {
            $this->database = $config['database'];
        }

        $this->backupPath = $config['backup_path'];


        if ($this->database == "") {
            $this->backupFilename = "all-databases-" . date("Y-m-d-H-i-s");
            $this->backupName = "all-databases-" . date("Y-m-d-H-i-s");
        } else {
            $this->backupFilename = $this->database . "-" . date("Y-m-d-H-i-s");
            $this->backupName = $this->database . "-" . date("Y-m-d-H-i-s");
        }
    }

    public function 
            dump() {
        if ($this->user != '' && $this->pass != "") {
            $this->auth = sprintf("--host='%s' --port='%d' --username='%s' --password='%s'", $this->host, $this->port, $this->user, $this->pass);
        } else if ($this->user != '' && $this->pass == "") {
            $this->auth = sprintf("--host='%s' --port='%d' --username='%s'", $this->host, $this->port, $this->user);
        } else {
            $this->auth = sprintf("--host='%s' --port='%d'", $this->host, $this->port);
        }

        if ($this->database != '') {
            $command = sprintf('mongodump %s -db %s -out %s', $this->auth, $this->database, $this->backupPath . $this->backupFilename);
        } else {
            $command = sprintf('mongodump %s -out %s', $this->auth, $this->backupPath . $this->backupFilename);
        }



        $process = new Process($command);
        $process->setTimeout(null);

        $process->run();

        if (!$process->isSuccessful()) {
            $this->result['status'] = 0;
            $this->result['message'] = $process->getErrorOutput();
        } else {
            $this->result['status'] = 1;
            $this->result['message'] = "Successful backup of Mongodb in local file: " . $this->backupPath . $this->backupFilename;
            $this->result['backup_path'] = $this->backupPath;
            $this->result['backup_filename'] = $this->backupFilename;
            $this->result['backup_name'] = $this->backupName;
            $this->result['full_path'] = $this->backupPath . $this->backupFilename;
            $this->result['host'] = $this->host;
        }

        return $this->result;
    }

}
