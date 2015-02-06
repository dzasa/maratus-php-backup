<?php

namespace Dzasa\MaratusPhpBackup\Databases;

use Symfony\Component\Process\Process;

class Mysql {

    private $host = "localhost";
    private $port = "3306";
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
        } else {
            $this->database = "--all-databases";
        }

        $this->backupPath = $config['backup_path'];


        if ($this->database == "--all-databases") {
            $this->backupFilename = "all-databases-" . date("Y-m-d-H-i-s") . ".sql";
            $this->backupName = "all-databases-" . date("Y-m-d-H-i-s");
        } else {
            $this->backupFilename = $this->database . "-" . date("Y-m-d-H-i-s") . ".sql";
            $this->backupName = $this->database . "-" . date("Y-m-d-H-i-s");
        }
    }

    public function dump() {
        if ($this->pass != "") {
            $this->auth = sprintf("--host='%s' --port='%d' --user='%s' --password='%s'", $this->host, $this->port, $this->user, $this->pass);
        } else {
            $this->auth = sprintf("--host='%s' --port='%d' --user='%s'", $this->host, $this->port, $this->user);
        }

        $command = sprintf('mysqldump --opt --quick --hex-blob --max_allowed_packet=500M '
                . '--add-drop-table=true --complete-insert=true --compress  '
                . '--lock-tables=false %s %s > %s', $this->auth, $this->database, $this->backupPath . $this->backupFilename);

        $process = new Process($command);
        $process->setTimeout(null);

        $process->run();

        if (!$process->isSuccessful()) {
            $this->result['status'] = 0;
            $this->result['message'] = $process->getErrorOutput();
        } else {
            $this->result['status'] = 1;
            $this->result['message'] = "Successful backup of MySQL in local file: " . $this->backupPath . $this->backupFilename;
            $this->result['backup_path'] = $this->backupPath;
            $this->result['backup_filename'] = $this->backupFilename;
            $this->result['backup_name'] = $this->backupName;
            $this->result['full_path'] = $this->backupPath . $this->backupFilename;
            $this->result['host'] = $this->host;
        }

        return $this->result;
    }

}
