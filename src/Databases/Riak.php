<?php

namespace Dzasa\MaratusPhpBackup\Databases;

use Crypt_RSA;
use Net_SCP;
use Net_SSH2;
use Symfony\Component\Process\Process;

class Riak {

	//remote ssh host
	private $host = "localhost";

	//remote ssh port
	private $port = "22";

	//remote ssh username
	private $user = "";

	//remote ssh password
	private $pass = "";

	//remote ssh private key
	private $privateKey = "";

	//remote ssh private key password
	private $privateKeyPass = "";

	//remote or local backup
	private $remote = false;

	//path to bitcask files
	private $bitcaskPath = "";

	//path to leveldb files
	private $levelDbPath = "";

	//path to strongConsistencyPath files
	private $strongConsistencyPath = "";

	//enable remote compression
	private $remoteCompress = "tar.gz";

	//backup path
	private $backupPath = "";

	//file name of the backup
	private $backupFilename = "";

	//backup result
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

		if (isset($config['remote_compress'])) {
			$this->remoteCompress = $config['remote_compress'];
		}

		$this->backupPath = $config['backup_path'];

		$this->backupFilename = "riak-" . date("Y-m-d-H-i-s");
		$this->backupName = "riak-" . date("Y-m-d-H-i-s");
	}

	//backup our databases
	public function dump() {

		//backup remote files
		if (!$this->remote) {
			$localDumpResult = $this->dumpLocal();

			if (!$localDumpResult) {
				return $this->result;
			}
		}
		//remote backup
		else {
			$remoteDumpResult = $this->dumpRemote();

			if (!$remoteDumpResult) {
				return $this->result;
			}
		}

		//set and return backup result
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
		$filesystem = new \Symfony\Component\Filesystem\Filesystem();
		/**
		 * *
		 * *
		 * Stop RIAK and do backup
		 * *
		 * *
		 */
		if ($this->switchLocal(0) == false) {
			return false;
		}

		$filesystem->mkdir($this->backupPath . $this->backupFilename);
		$this->backupFilename = rtrim($this->backupFilename, "/") . "/";

		/**
		 * Copy bitcask files
		 */
		if (is_dir($this->bitcaskPath)) {
			$bcPath = explode("/", rtrim($this->bitcaskPath, "/"));
			$bcDir = end($bcPath);
			$bcDir = rtrim($bcDir, "/") . "/";
			$filesystem->mkdir($this->backupPath . $this->backupFilename . $bcDir);

			$copyAllBitcaskCommand = 'cp -Ra ' . $this->bitcaskPath . '. ' . $this->backupPath . $this->backupFilename . $bcDir;
			$copyAllBitcask = new Process($copyAllBitcaskCommand);

			$copyAllBitcask->run();
		}

		/**
		 * Copy leveldb files
		 */
		if (is_dir($this->levelDbPath)) {
			$ldbPath = explode("/", rtrim($this->levelDbPath, "/"));
			$ldbDir = end($ldbPath);
			$ldbDir = rtrim($ldbDir, "/") . "/";
			$filesystem->mkdir($this->backupPath . $this->backupFilename . $ldbDir);

			$copyAllLeveldbCommand = 'cp -Ra ' . $this->levelDbPath . '. ' . $this->backupPath . $this->backupFilename . $ldbDir;
			$copyAllLeveldb = new Process($copyAllLeveldbCommand);

			$copyAllLeveldb->run();

		}

		/**
		 * Copy Backup Strong consistency
		 */
		if (is_dir($this->strongConsistencyPath)) {
			$sConPath = explode("/", rtrim($this->strongConsistencyPath, "/"));
			$sConDir = end($sConPath);
			$sConDir = rtrim($sConDir, "/") . "/";
			$filesystem->mkdir($this->backupPath . $this->backupFilename . $sConDir);

			$copyAllSConCommand = 'cp -Ra ' . $this->strongConsistencyPath . '. ' . $this->backupPath . $this->backupFilename . $sConDir;
			$copyAllSCon = new Process($copyAllSConCommand);

			$copyAllSCon->run();

		}

		/**
		 *
		 *
		 * Start RIAK again
		 *
		 *
		 *
		 */
		$this->switchLocal(1);

		return true;
	}

	private function dumpRemote() {
		$ssh = new Net_SSH2($this->host, $this->port);

		//assign privatekey is set
		if ($this->privateKey != "") {
			$key = new Crypt_RSA();

			//assign private key password if set
			if ($this->privateKeyPass != "") {
				$key->setPassword($this->privateKeyPass);
			}

			//load private key
			$key->loadKey(file_get_contents($this->privateKey));

			//do login
			$login = $ssh->login($this->user, $key);
		} else {

			//do login
			$login = $ssh->login($this->user, $this->pass);
		}

		//unable to login
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
		if ($this->switchRemote(0) == false) {
			return false;
		}

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

			//compress our remote files
			if ($this->remoteCompress != false) {
				$backupTmpName = uniqid();

				if ($this->remoteCompress == "tar.gz") {
					$remoteCompressCommand = 'tar czf ' . $backupTmpName . '.tar.gz ' . $this->bitcaskPath . ' && echo "done"';
					$remoteCompress = $ssh->exec($remoteCompressCommand);

					if (strpos(trim($remoteCompress), 'done') !== false) {
						$scp->get($backupTmpName . '.tar.gz', $this->backupPath . $this->backupFilename . rtrim($bcDir, "/") . '.tar.gz');
					}

					$ssh->exec("\\rm " . $backupTmpName . '.tar.gz');
				} else if ($this->remoteCompress == "zip") {
					$remoteCompressCommand = 'zip -q -r ' . $backupTmpName . '.zip ' . $this->bitcaskPath . ' && echo "done"';
					$remoteCompress = $ssh->exec($remoteCompressCommand);

					if (strpos(trim($remoteCompress), 'done') !== false) {
						$scp->get($backupTmpName . '.zip', $this->backupPath . $this->backupFilename . rtrim($bcDir, "/") . '.zip');

					}

					$ssh->exec("\\rm " . $backupTmpName . '.zip');
				}
			} else {

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

			if ($this->remoteCompress != false) {
				$backupTmpName = uniqid();

				if ($this->remoteCompress == "tar.gz") {
					$remoteCompressCommand = 'tar czf ' . $backupTmpName . '.tar.gz ' . $this->levelDbPath . ' && echo "done"';
					$remoteCompress = $ssh->exec($remoteCompressCommand);

					if (strpos(trim($remoteCompress), 'done') !== false) {
						$scp->get($backupTmpName . '.tar.gz', $this->backupPath . $this->backupFilename . rtrim($ldbDir, "/") . '.tar.gz');
					}

					$ssh->exec("\\rm " . $backupTmpName . '.tar.gz');
				} else if ($this->remoteCompress == "zip") {
					$remoteCompressCommand = 'zip -q -r ' . $backupTmpName . '.zip ' . $this->levelDbPath . ' && echo "done"';
					$remoteCompress = $ssh->exec($remoteCompressCommand);

					if (strpos(trim($remoteCompress), 'done') !== false) {
						$scp->get($backupTmpName . '.zip', $this->backupPath . $this->backupFilename . rtrim($ldbDir, "/") . '.zip');
					}

					$ssh->exec("\\rm " . $backupTmpName . '.zip');
				}
			} else {

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

			if ($this->remoteCompress != false) {
				$backupTmpName = uniqid();

				if ($this->remoteCompress == "tar.gz") {
					$remoteCompressCommand = 'tar czf ' . $backupTmpName . '.tar.gz ' . $this->strongConsistencyPath . ' && echo "done"';
					$remoteCompress = $ssh->exec($remoteCompressCommand);

					if (strpos(trim($remoteCompress), 'done') !== false) {
						$scp->get($backupTmpName . '.tar.gz', $this->backupPath . $this->backupFilename . rtrim($SCDir, "/") . '.tar.gz');
					}

					$ssh->exec("\\rm " . $backupTmpName . '.tar.gz');
				} else if ($this->remoteCompress == "zip") {
					$remoteCompressCommand = 'zip -q -r ' . $backupTmpName . '.zip ' . $this->strongConsistencyPath . ' && echo "done"';
					$remoteCompress = $ssh->exec($remoteCompressCommand);

					if (strpos(trim($remoteCompress), 'done') !== false) {
						$scp->get($backupTmpName . '.zip', $this->backupPath . $this->backupFilename . rtrim($SCDir, "/") . '.zip');
					}

					$ssh->exec("\rm " . $backupTmpName . '.zip');
				}
			} else {

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
		}

		/**
		 * *
		 * *
		 * Start RIAK again
		 * *
		 * *
		 */
		$this->switchRemote(1);

		return true;
	}

	/**
	 * Start or stop remote RIAK
	 * @param  integer $state Start or Stop
	 * @return bool
	 */
	private function switchRemote($state = 0) {
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

		if ($state) {
			$command = "riak start";
		} else {
			$command = "riak stop";
		}

		$ssh->exec($command);

		return true;
	}

	/**
	 * Start or stop local RIAK
	 * @param  integer $state Start or Stop
	 * @return bool
	 */
	private function switchLocal($state = 0) {
		if ($state) {
			$command = "riak start";
		} else {
			$command = "riak stop";
		}
		$exec = new Process($command);

		$exec->run();

		if (!$exec->isSuccessful()) {
			$this->result['status'] = 0;
			$this->result['message'] = $exec->getErrorOutput();

			return $this->result;
		} else {

			return true;

		}
	}

}