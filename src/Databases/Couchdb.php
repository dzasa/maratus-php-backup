<?php

namespace Dzasa\MaratusPhpBackup\Databases;

use Crypt_RSA;
use Net_SCP;
use Net_SSH2;
use Symfony\Component\Process\Process;

/**
 * Back up couchdb database or databases on local or remote via ssh
 */
class Couchdb {

	//ssh access
	private $host = "localhost";
	private $port = "22";
	private $user = "";
	private $pass = "";

	//use private key when accessing via ssh
	private $privateKey = "";
	private $privateKeyPass = "";

	//remote backup or localbackup
	private $remote = false;

	//database dir on local or remote
	private $databaseDir = null;

	//backup single database or all databases
	private $database = "";

	//backup path on local
	private $backupPath = "";

	//backup file name
	private $backupFilename = "";

	//backup result
	private $result = array(
		'status' => null,
		'message' => "",
	);

	/**
	 * Prepare stuff for some cooking :)
	 */
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

	/**
	 * Dump some data
	 */
	public function dump() {

		//dump from remote host
		if (!$this->remote) {
			$localDumpResult = $this->dumpLocal();

			if (!$localDumpResult) {
				return $this->result;
			}
		}
		//dump from local
		else {
			$remoteDumpResult = $this->dumpRemote();

			if (!$remoteDumpResult) {
				return $this->result;
			}
		}

		//if everything is ok, then prepare result for main backup class
		$this->result['status'] = 1;
		$this->result['message'] = "Successful backup of Couchdb in local file: " . $this->backupPath . $this->backupFilename;
		$this->result['backup_path'] = $this->backupPath;
		$this->result['backup_filename'] = $this->backupFilename;
		$this->result['backup_name'] = $this->backupName;
		$this->result['full_path'] = $this->backupPath . $this->backupFilename;
		$this->result['host'] = $this->host;

		return $this->result;
	}

	/**
	 * Local datababe(s) backup
	 */
	private function dumpLocal() {

		//do some research on local to find location of databases
		$couchDbConfigCommand = 'couch-config --db-dir';
		$couchDbConfig = new Process($couchDbConfigCommand);

		$couchDbConfig->run();

		//we couldn't find path, just return error
		if (!$couchDbConfig->isSuccessful()) {
			$this->result['status'] = 0;
			$this->result['message'] = $couchDbConfig->getErrorOutput();

			return $this->result;
		}

		//set database local directory
		$this->databaseDir = trim($couchDbConfig->getOutput());
		$this->databaseDir = rtrim($this->databaseDir, "/") . "/";

		$filesystem = new \Symfony\Component\Filesystem\Filesystem();

		//backup single database
		if ($this->database != '') {

			//check if database exists with extra check if extension wasnt set
			//in other case return error if everything fails
			if (!$filesystem->exists($this->databaseDir . $this->database)) {
				if (!$filesystem->exists($this->databaseDir . $this->database . ".couch")) {
					$this->result['status'] = 0;
					$this->result['message'] = "Defined Couchdb does not exist!";

					return $this->result;
				} else {
					$this->database = $this->database . ".couch";
				}
			}

			//copy our database to top backup path
			$filesystem->copy($this->databaseDir . $this->database, $this->backupPath . $this->backupFilename);
		}
		//we backup all database we can find
		else {
			$filesystem->mkdir($this->backupPath . $this->backupFilename);

			$copyAllDatabasesCommand = 'cp -Ra ' . $this->databaseDir . '. ' . $this->backupPath . $this->backupFilename;
			$copyAllDatabases = new Process($copyAllDatabasesCommand);

			$copyAllDatabases->run();

			//if backup fails return error with some extra info
			if (!$copyAllDatabases->isSuccessful()) {
				$this->result['status'] = 0;
				$this->result['message'] = $copyAllDatabases->getErrorOutput();

				return false;
			}
		}

		return true;
	}

	//getting remote datase(s)
	private function dumpRemote() {

		//set basic ssh
		$ssh = new Net_SSH2($this->host, $this->port);

		//check if we should use private key
		if ($this->privateKey != "") {
			$key = new Crypt_RSA();

			//ad if private key is password protected
			if ($this->privateKeyPass != "") {
				$key->setPassword($this->privateKeyPass);
			}

			//load private key
			$key->loadKey(file_get_contents($this->privateKey));

			//and do final login
			$login = $ssh->login($this->user, $key);
		} else {

			//do login with user and pass only
			$login = $ssh->login($this->user, $this->pass);
		}

		//if login fails return error
		if (!$login) {
			$this->result['status'] = 0;
			$this->result['message'] = "Unable to login on SSH!";

			return false;
		}

		//find database location on remote host
		$couchDbConfigCommand = "couch-config --db-dir";
		$ssh->write($couchDbConfigCommand . "\n");
		$output = explode("\n", $ssh->read("couchdb"));

		$databaseDir = end($output);
		$this->databaseDir = rtrim($databaseDir, "/") . "/";

		//prepare scp to take our database(s) from remote host
		$scp = new Net_SCP($ssh);

		//backup single database
		if ($this->database != '') {

			//do some extra check with database extention while checking if database exist
			//otherwise return error
			if (!$scp->get($this->databaseDir . $this->database, $this->backupPath . $this->backupFilename)) {
				if (!$scp->get($this->databaseDir . $this->database . ".couch", $this->backupPath . $this->backupFilename)) {
					$this->result['status'] = 0;
					$this->result['message'] = "Unable to copy remote database(s) or wrong database name!";

					return $this->result;
				}
			}
		}
		//backup all databases from remote host
		else {

			//get list of database to download
			$this->backupFilename = rtrim($this->backupFilename, "/") . "/";
			$couchDbConfigCommand = "ls -a " . $this->databaseDir;
			$output = explode("\n", $ssh->exec($couchDbConfigCommand));
			$filesToDownload = [];

			//prepare list to download, clean it a lil bit :)
			foreach ($output as $oValue) {
				if (strlen($oValue) < 3 || substr($oValue, 0, 1) == "_" || substr($oValue, 0, 1) == '.') {
					continue;
				} else {
					$filesToDownload[] = $oValue;
				}
			}

			//create backup dir if does't exist
			if (!is_dir($this->backupPath . $this->backupFilename)) {
				mkdir($this->backupPath . $this->backupFilename);
			}

			//transfer all files from remote host to local
			foreach ($filesToDownload as $remoteFile) {
				$scp->get($this->databaseDir . $remoteFile, $this->backupPath . $this->backupFilename . $remoteFile);
			}

		}

		return true;
	}

}
