<?php

namespace Dzasa\MaratusPhpBackup\Databases;

use Crypt_RSA;
use Net_SCP;
use Net_SSH2;

class Riak {

	private $host = "localhost";
	private $port = "22";
	private $user = "";
	private $pass = "";
	private $privateKey = "";
	private $privateKeyPass = "";
	private $remote = false;
	private $bitcaskPath = "";
	private $levelDbPath = "";
	private $strongConsistencyPath = "";
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

			if (isset($config['private_key'])) {
				$this->privateKey = $config['private_key'];
			}

			if (isset($config['private_key_pass'])) {
				$this->privateKeyPass = $config['private_key_pass'];
			}
		}

		if (isset($config['bitcask_path'])) {
			$this->bitcaskPath = rtrim($config['bitcask_path'], "/") . "/";
		}

		if (isset($config['leveldb_path'])) {
			$this->levelDbPath = rtrim($config['leveldb_path'], "/") . "/";
		}

		if (isset($config['strong_consistency_path'])) {
			$this->strongConsistencyPath = rtrim($config['strong_consistency_path'], "/") . "/";
		}

		$this->backupPath = $config['backup_path'];

		$this->backupFilename = "all-databases-" . date("Y-m-d-H-i-s");
		$this->backupName = "all-databases-" . date("Y-m-d-H-i-s");
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
		$this->result['message'] = "Successful backup of RIAK in local file: " . $this->backupPath . $this->backupFilename;
		$this->result['backup_path'] = $this->backupPath;
		$this->result['backup_filename'] = $this->backupFilename;
		$this->result['backup_name'] = $this->backupName;
		$this->result['full_path'] = $this->backupPath . $this->backupFilename;
		$this->result['host'] = $this->host;

		return $this->result;
	}

	private function dumpLocal() {
		$filesystem->mkdir($this->backupPath . $this->backupFilename);

		$copyAllDatabasesCommand = 'cp -Ra ' . $this->databaseDir . '. ' . $this->backupPath . $this->backupFilename;
		$copyAllDatabases = new Process($copyAllDatabasesCommand);

		$copyAllDatabases->run();

		if (!$copyAllDatabases->isSuccessful()) {
			$this->result['status'] = 0;
			$this->result['message'] = $copyAllDatabases->getErrorOutput();

			return false;
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

		$scp = new Net_SCP($ssh);

		/**
		 * *
		 * *
		 * Stop RIAK and do backup
		 * *
		 * *
		 */
		$stopCommand = "riak stop";
		$ssh->exec($stopCommand);

		$this->backupFilename = rtrim($this->backupFilename, "/") . "/";
		if (!is_dir($this->backupPath . $this->backupName)) {
			mkdir($this->backupPath . $this->backupName);
		}

		/**
		 * Back up Bitcask if there is any
		 */
		$checkBitcaskDirCommand = '[ -d ' . $this->bitcaskPath . ' ] && echo "1"';
		$checkBitcaskDir = $ssh->exec($checkBitcaskDirCommand);

		if (trim($checkBitcaskDir) == 1) {
			$bcPath = explode("/", rtrim($this->bitcaskPath, "/"));
			$bcDir = end($bcPath);
			$bcDir = rtrim($bcDir, "/") . "/";

			if (!is_dir($this->backupPath . $this->backupFilename . $bcDir)) {
				mkdir($this->backupPath . $this->backupFilename . $bcDir);
			}

			$bitcaskFoldersCommand = "ls -d " . $this->bitcaskPath . "*/";
			$outputBitcask = explode("\n", $ssh->exec($bitcaskFoldersCommand));

			foreach ($outputBitcask as $bcValue) {
				if (trim($bcValue) == "") {
					continue;
				} else {
					$bcPathDirs = explode("/", rtrim($bcValue, "/"));
					$bcLastDir = end($bcPathDirs);

					if (!is_dir($this->backupPath . $this->backupFilename . $bcDir . $bcLastDir)) {
						mkdir($this->backupPath . $this->backupFilename . $bcDir . $bcLastDir);
					}

					$bcLastDir = rtrim($bcLastDir, "/") . "/";

					$bcEachFolderCommand = "ls -a " . $bcValue;
					$bcEachOutput = explode("\n", $ssh->exec($bcEachFolderCommand));

					foreach ($bcEachOutput as $bcFile) {
						if (strlen($bcFile) < 3 || substr($bcFile, 0, 1) == '.') {
							continue;
						} else {
							$scp->get($bcValue . $bcFile, $this->backupPath . $this->backupFilename . $bcDir . $bcLastDir . $bcFile);
						}
					}
				}
			}
		}

		/**
		 * Back up LevelDB if there is any
		 */
		$checkLeveldbDirCommand = '[ -d ' . $this->levelDbPath . ' ] && echo "1"';
		$checkLeveldbDir = $ssh->exec($checkLeveldbDirCommand);
		if (trim($checkLeveldbDir) == 1) {
			$ldbPath = explode("/", rtrim($this->levelDbPath, "/"));
			$ldbDir = end($ldbPath);
			$ldbDir = rtrim($ldbDir, "/") . "/";

			if (!is_dir($this->backupPath . $this->backupFilename . $ldbDir)) {
				mkdir($this->backupPath . $this->backupFilename . $ldbDir);
			}

			$leveldbFoldersCommand = "ls -d " . $this->levelDbPath . "*/";
			$outputLevelDB = explode("\n", $ssh->exec($leveldbFoldersCommand));

			foreach ($outputLevelDB as $ldbValue) {
				if (trim($ldbValue) == "") {
					continue;
				} else {
					$ldbPathDirs = explode("/", rtrim($ldbValue, "/"));
					$ldbLastDir = end($ldbPathDirs);

					if (!is_dir($this->backupPath . $this->backupFilename . $ldbDir . $ldbLastDir)) {
						mkdir($this->backupPath . $this->backupFilename . $ldbDir . $ldbLastDir);
					}

					$ldbLastDir = rtrim($ldbLastDir, "/") . "/";

					/**
					 * Get files from subfolders
					 */
					$leveldbSubFoldersCommand = "find $ldbValue -maxdepth 1 -type d";
					$outputLevelDBSub = explode("\n", $ssh->exec($leveldbSubFoldersCommand));

					foreach ($outputLevelDBSub as $ldbSubValue) {
						if (trim($ldbSubValue) == "" || trim($ldbSubValue) == $ldbValue) {
							continue;
						} else {
							$ldbSubPathDirs = explode("/", rtrim($ldbSubValue, "/"));
							$ldbLastSubDir = end($ldbSubPathDirs);

							if (!is_dir($this->backupPath . $this->backupFilename . $ldbDir . $ldbLastDir . $ldbLastSubDir)) {
								mkdir($this->backupPath . $this->backupFilename . $ldbDir . $ldbLastDir . $ldbLastSubDir);
							}

							$ldbLastSubDir = rtrim($ldbLastSubDir, "/") . "/";

							$ldbEachSubFolderCommand = "ls -a " . $ldbSubValue;
							$ldbEachSubOutput = explode("\n", $ssh->exec($ldbEachSubFolderCommand));

							foreach ($ldbEachSubOutput as $ldbSubFile) {
								if (strlen($ldbSubFile) < 3 || substr($ldbSubFile, 0, 1) == '.') {
									continue;
								} else {
									$scp->get($ldbValue . $ldbLastSubDir . $ldbSubFile, $this->backupPath . $this->backupFilename . $ldbDir . $ldbLastDir . $ldbLastSubDir . $ldbSubFile);
								}
							}
						}
					}

					/**
					 * Add all other files
					 */

					$ldbEachFolderCommand = "find $ldbValue -maxdepth 1 -type f";

					$ldbEachOutput = explode("\n", $ssh->exec($ldbEachFolderCommand));

					foreach ($ldbEachOutput as $ldbFile) {
						if (strlen($ldbFile) < 3 || substr($ldbFile, 0, 1) == '.') {
							continue;
						} else {
							$path = explode("/", $ldbFile);
							$fileName = end($path);

							$scp->get($ldbValue . $fileName, $this->backupPath . $this->backupFilename . $ldbDir . $ldbLastDir . $fileName);
						}
					}
				}
			}
		}

		/**
		 * Backup Strong consistency
		 */
		$checkStrongConDirCommand = '[ -d ' . $this->strongConsistencyPath . ' ] && echo "1"';
		$checkStrongConDir = $ssh->exec($checkStrongConDirCommand);
		if (trim($checkStrongConDir) == 1) {

			$SCPath = explode("/", rtrim($this->strongConsistencyPath, "/"));
			$SCDir = end($SCPath);
			$SCDir = rtrim($SCDir, "/") . "/";

			if (!is_dir($this->backupPath . $this->backupFilename . $SCDir)) {
				mkdir($this->backupPath . $this->backupFilename . $SCDir);
			}

			$SCFilesCommand = "find $this->strongConsistencyPath -maxdepth 1 -type f";

			$SCOutput = explode("\n", $ssh->exec($SCFilesCommand));

			foreach ($SCOutput as $SCFile) {
				if (strlen($SCFile) < 3 || substr($SCFile, 0, 1) == '.') {
					continue;
				} else {
					$path = explode("/", $SCFile);
					$fileName = end($path);

					$scp->get($this->strongConsistencyPath . $fileName, $this->backupPath . $this->backupFilename . $SCDir . $fileName);
				}
			}
		}

		/**
		 * *
		 * *
		 * Start RIAK again
		 * *
		 * *
		 */
		$startCommand = "riak start";
		$ssh->exec($startCommand);

		return true;
	}

}