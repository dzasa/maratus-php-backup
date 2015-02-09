<?php

namespace Dzasa\MaratusPhpBackup\Databases;

use Crypt_RSA;
use Net_SCP;
use Net_SSH2;
use Symfony\Component\Process\Process;

class Couchdb {

	private $host = "localhost";
	private $port = "22";
	private $user = "";
	private $pass = "";
	private $privateKey = "";
	private $privateKeyPass = "";
	private $remote = false;
	private $databaseDir = null;
	private $database = "";
	private $backupPath = "";
	private $backupFilename = "";
	private $result = array(
		'status' => null,
		'message' => "",
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

		$couchDbConfigCommand = "couch-config --db-dir";
		$ssh->write($couchDbConfigCommand . "\n");
		$output = explode("\n", $ssh->read("couchdb"));

		$databaseDir = end($output);
		$this->databaseDir = rtrim($databaseDir, "/") . "/";

		$scp = new Net_SCP($ssh);

		if ($this->database != '') {

			if (!$scp->get($this->databaseDir . $this->database, $this->backupPath . $this->backupFilename)) {
				if (!$scp->get($this->databaseDir . $this->database . ".couch", $this->backupPath . $this->backupFilename)) {
					$this->result['status'] = 0;
					$this->result['message'] = "Unable to copy remote database(s) or wrong database name!";

					return $this->result;
				}
			}
		} else {
			$this->backupFilename = rtrim($this->backupFilename, "/") . "/";
			$couchDbConfigCommand = "ls -a " . $this->databaseDir;
			$output = explode("\n", $ssh->exec($couchDbConfigCommand));
			$filesToDownload = [];

			foreach ($output as $oValue) {
				if (strlen($oValue) < 3 || substr($oValue, 0, 1) == "_" || substr($oValue, 0, 1) == '.') {
					continue;
				} else {
					$filesToDownload[] = $oValue;
				}
			}

			if (!is_dir($this->backupPath . $this->backupName)) {
				mkdir($this->backupPath . $this->backupName);
			}

			foreach ($filesToDownload as $remoteFile) {
				$scp->get($this->databaseDir . $remoteFile, $this->backupPath . $this->backupFilename . $remoteFile);
			}

		}

		return true;
	}

}
