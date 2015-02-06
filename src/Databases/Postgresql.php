<?php

namespace Dzasa\MaratusPhpBackup\Databases;

use Symfony\Component\Process\Process;

class Postgresql {

    private $host = "localhost";
    private $port = "5432";
    private $user = "root";
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
            $this->backupFilename = "all-databases-" . date("Y-m-d-H-i-s") . ".dump";
            $this->backupName = "all-databases-" . date("Y-m-d-H-i-s");
        } else {
            $this->backupFilename = $this->database . "-" . date("Y-m-d-H-i-s") . ".dump";
            $this->backupName = $this->database . "-" . date("Y-m-d-H-i-s");
        }
    }

    public function dump() {
        if ($this->pass != "") {
            $exportPasswordCommand = 'PGPASSWORD="' . $this->pass . '"';
            $exportPasswordProcess = new Process($exportPasswordCommand);

            $exportPasswordProcess->run();
        }

        $this->auth = sprintf("-U '%s' -h '%s' -p '%s'", $this->user, $this->host, $this->port);

        if ($this->database == "") {
            $command = sprintf('pg_dumpall %s > %s', $this->auth, $this->backupPath . $this->backupFilename);
        } else {
            $command = sprintf('pg_dump %s %s > %s', $this->auth, $this->database, $this->backupPath . $this->backupFilename);
        }


        $process = new Process($command);
        $process->setTimeout(null);

        $process->run();

        if (!$process->isSuccessful()) {
            $this->result['status'] = 0;
            $this->result['message'] = $process->getErrorOutput();
        } else {
            echo $process->getOutput();
            $this->result['status'] = 1;
            $this->result['message'] = "Successful backup of PostgreSQL in local file: " . $this->backupPath . $this->backupFilename;
            $this->result['backup_path'] = $this->backupPath;
            $this->result['backup_filename'] = $this->backupFilename;
            $this->result['backup_name'] = $this->backupName;
            $this->result['full_path'] = $this->backupPath . $this->backupFilename;
            $this->result['host'] = $this->host;
        }

        return $this->result;
    }

}
