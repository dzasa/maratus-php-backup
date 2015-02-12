<?php

namespace Dzasa\MaratusPhpBackup\Databases;

use Crypt_RSA;
use Net_SCP;
use Net_SSH2;
use Symfony\Component\Process\Process;

/**
 * Back up couchdb database or databases on local or remote via ssh
 */
class Sqlite {

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

	//path to database
	private $databasePath = "";

	//backup path on local or remote
	private $backupPath = "";

	//backup file name
	private $backupFilename = "";

	//enable compression on remote host
	private $remoteCompress = "tar.gz";

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

		if (isset($config['database_path'])) {
			$this->databasePath = $config['database_path'];
		}

		//set database backup path
		$this->backupPath = $config['backup_path'];

		//set file Title
		$this->backupName = "sqlite-" . date("Y-m-d-H-i-s");

		//set remote compression
		if (isset($config['remote_compress'])) {
			$this->remoteCompress = $config['remote_compress'];

			// set filename
			$this->backupFilename = date("Y-m-d-H-i-s") . "." . $config['remote_compress'];
		} else {

			// set filename
			$this->backupFilename = date("Y-m-d-H-i-s") . ".db";

		}

	}

	/**
	 * Dump some data
	 */
	public function dump() {

		//dump from remote host
		if ($this->remote == false) {
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
		$this->result['message'] = "Successful backup of SQLite in local file: " . $this->backupPath . $this->backupFilename;
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

		if ($this->databasePath == '') {
			$this->result['status'] = 0;
			$this->result['message'] = "You have to define database path!";

			return false;
		}

		//check if database exists, in other case return error
		$filesystem = new \Symfony\Component\Filesystem\Filesystem();
		if (!$filesystem->exists($this->databasePath)) {
			$this->result['status'] = 0;
			$this->result['message'] = "Defined Couchdb does not exist!";

			return false;
		}

		//make a dump of our database
		$dumpDbCommand = "sqlite3 " . $this->databasePath . " '.dump' > " . $this->backupPath . $this->backupFilename;
		$dumpDb = new Process($dumpDbCommand);

		$dumpDb->run();

		//something bad happend, return error
		if (!$dumpDb->isSuccessful()) {
			$this->result['status'] = 0;
			$this->result['message'] = $couchDbConfig->getErrorOutput();

			return false;
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

		//check if database exists, in other case return error
		if ($this->databasePath == '') {
			$this->result['status'] = 0;
			$this->result['message'] = "You have to define database path!";

			return false;
		}

		//prepare scp to take our dump from remote host
		$scp = new Net_SCP($ssh);

		//set temp file for backup
		$backupTmpName = uniqid() . ".db";

		//prepare dump sqlite database command
		$remotebackupCommand = "sqlite3 " . $this->databasePath . " '.dump' > " . $backupTmpName . " && echo '1'";

		//dump it
		$remotebackup = $ssh->exec($remotebackupCommand);

		//check if everything is fine with backup process
		if (trim($remotebackup) != 1) {
			$this->result['status'] = 0;
			$this->result['message'] = "Unable to do remote backup!";

			return false;
		}

		//compress files on remote host and return it to local filesystem
		if ($this->remoteCompress != false) {
			$backupTmpArchiveName = uniqid();

			if ($this->remoteCompress == "tar.gz") {

				//backup using tar.gz compression
				$remoteCompressCommand = 'tar czf ' . $backupTmpArchiveName . '.tar.gz ' . $backupTmpName . ' && echo "done"';
				$remoteCompress = $ssh->exec($remoteCompressCommand);

				//secure copy from remote host to local filesystem
				if (strpos(trim($remoteCompress), 'done') !== false) {
					$scp->get($backupTmpArchiveName . '.tar.gz', $this->backupPath . $this->backupFilename);

				} else {
					$this->result['status'] = 0;
					$this->result['message'] = "Unable to do remote backup compress!";

					$ssh->exec("\\rm " . $backupTmpName);

					return false;
				}

				//delete temp file create on remote server
				$ssh->exec("\\rm " . $backupTmpArchiveName . '.tar.gz');
			} else if ($this->remoteCompress == "zip") {

				//backup using zip compression
				$remoteCompressCommand = 'zip -q -r ' . $backupTmpArchiveName . '.zip ' . $backupTmpName . ' && echo "done"';
				$remoteCompress = $ssh->exec($remoteCompressCommand);

				//secure copy from remote host to local filesystem
				if (strpos(trim($remoteCompress), 'done') !== false) {
					$scp->get($backupTmpArchiveName . '.zip', $this->backupPath . $this->backupFilename);
				} else {
					$this->result['status'] = 0;
					$this->result['message'] = "Unable to do remote backup compress!";

					$ssh->exec("\\rm " . $backupTmpName);

					return false;
				}

				//delete temp file create on remote server
				$ssh->exec("\\rm " . $backupTmpArchiveName . '.zip');
			}
		} else {
			$scp->get($backupTmpName, $this->backupPath . $this->backupFilename);
		}

		//delete temp file create on remote server
		$ssh->exec("\\rm " . $backupTmpName);

		return true;
	}

}
