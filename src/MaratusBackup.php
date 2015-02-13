<?php

namespace Dzasa\MaratusPhpBackup;

use Alchemy\Zippy\Zippy;
use Dzasa\MaratusPhpBackup\Clients\Copy as MaratusCopy;
use Dzasa\MaratusPhpBackup\Clients\Dropbox as MaratusDropbox;
use Dzasa\MaratusPhpBackup\Clients\GoogleDrive as MaratusGoogleDrive;
use Dzasa\MaratusPhpBackup\Clients\Local as MaratusLocalStorage;
use Dzasa\MaratusPhpBackup\Databases\Couchdb as MaratusCouchdb;
use Dzasa\MaratusPhpBackup\Databases\Mongodb as MaratusMongodb;
use Dzasa\MaratusPhpBackup\Databases\Mysql as MaratusMysql;
use Dzasa\MaratusPhpBackup\Databases\Postgresql as MaratusPostgresql;
use Dzasa\MaratusPhpBackup\Databases\Redis as MaratusRedis;
use Dzasa\MaratusPhpBackup\Databases\Riak as MaratusRiak;
use Dzasa\MaratusPhpBackup\Databases\Sqlite as MaratusSqlite;
use Symfony\Component\Filesystem\Filesystem;

/**
 * MaratusBackup
 *
 * Backup MySQL database using FTP, Local storage, Online file storage like Google Drive, Dropbox
 *
 * @author Jasenko Rakovic <nacunik@gmail.com>
 */
class MaratusBackup {

	/**
	 * Database configurations and database backup results
	 */
	private $databases = array();
	private $databaseBackupResult = array();

	//Storages
	private $storages = array();

	//Google drive configurations
	private $gDrivesConfigs = array();
	private $dropboxConfigs = array();
	private $copyConfigs = array();

	/**
	 * Default archive type
	 *
	 * @var string $archive
	 */
	private $archiveType = "tar.gzip";

	/**
	 * Array of files to be stored on backup storage
	 *
	 * @var array $filesToStore
	 */
	private $filesToStore = array();

	/**
	 * Array of stored files
	 */
	private $filesStored = array();
	//Temp local backup path
	private $backupPath = "";

	function __construct($backupPath = '') {
		if ($backupPath == "") {
			$this->backupPath = sys_get_temp_dir() . "/";
		} else {
			$this->backupPath = rtrim($backupPath, '/') . '/';
		}
	}

	/**
	 * Enable backup of database
	 *
	 * @param array $config
	 */
	public function addDatabase($config = array()) {
		$config['backup_path'] = $this->backupPath;

		/**
		 * Process mysql backup part
		 */
		if (isset($config['type']) && $config['type'] == strtolower('mysql')) {
			$this->databases[] = new MaratusMysql($config);
		}

		/**
		 * Process postgresql backup part
		 */
		if (isset($config['type']) && $config['type'] == strtolower('postgresql')) {
			$this->databases[] = new MaratusPostgresql($config);
		}

		/**
		 * Process mongodb backup part
		 */
		if (isset($config['type']) && $config['type'] == strtolower('mongodb')) {
			$this->databases[] = new MaratusMongodb($config);
		}

		/**
		 * Process couchdb backup part
		 */
		if (isset($config['type']) && $config['type'] == strtolower('couchdb')) {
			$this->databases[] = new MaratusCouchdb($config);
		}

		/**
		 * Process redis backup part
		 */
		if (isset($config['type']) && $config['type'] == strtolower('redis')) {
			$this->databases[] = new MaratusRedis($config);
		}

		/**
		 * Process riak backup part
		 */
		if (isset($config['type']) && $config['type'] == strtolower('riak')) {
			$this->databases[] = new MaratusRiak($config);
		}

		/**
		 * Process sqlite backup part
		 */
		if (isset($config['type']) && $config['type'] == strtolower('sqlite')) {
			$this->databases[] = new MaratusSqlite($config);
		}
	}

	/**
	 * Add local or remote storage for later use to store files
	 * @param array $config Config for storage
	 */
	public function addStorage($config = array()) {
		//add storage for after processing
		$this->storages[] = $config;
	}

	/**
	 * Set archive type
	 *
	 * @param string $type
	 */
	public function setArchiveType($type) {
		$this->archiveType = $type;
	}

	public function backup($archiveType = '') {

		/**
		 * Set compress type
		 */
		if ($archiveType != '') {
			$this->setArchiveType($archiveType);
		}

		/**
		 * Back up database or databases
		 */
		foreach ($this->databases as $db) {
			$databaseBackupResult = $db->dump();

			$this->databaseBackupResult[] = $databaseBackupResult;

			/**
			 * Everything is OK with database backup, go to prepare and compress
			 */
			if ($databaseBackupResult['status'] == 1) {
				if ($db instanceof MaratusMysql) {
					$this->prepareForStoring("mysql", $databaseBackupResult);
				} else if ($db instanceof MaratusPostgresql) {
					$this->prepareForStoring("postgresql", $databaseBackupResult);
				} else if ($db instanceof MaratusPostgresql) {
					$this->prepareForStoring("mongodb", $databaseBackupResult);
				} else if ($db instanceof MaratusCouchdb) {
					$this->prepareForStoring("couchdb", $databaseBackupResult);
				} else if ($db instanceof MaratusRedis) {
					$this->prepareForStoring("redis", $databaseBackupResult);
				} else if ($db instanceof MaratusRiak) {
					$this->prepareForStoring("riak", $databaseBackupResult);
				} else if ($db instanceof MaratusSqlite) {
					$this->prepareForStoring("sqlite", $databaseBackupResult);
				} else {
					continue;
				}
			}
		}

		/**
		 * Store files
		 */
		$this->store();

		return $this->filesStored;
	}

	/**
	 *
	 * Prepare file for storing on defined storage like G Drive, FTP or local place
	 *
	 * @param string $type mysql or local files
	 * @param array $details
	 */
	private function prepareForStoring($type, $details = array()) {
		$zippy = Zippy::load();
		$filename = $this->backupPath . date("Y-m-d-H-i-s") . "-" . $type . "." . $this->archiveType;

		if (is_dir($details['full_path'])) {
			$zippy->create($filename, array($details['full_path']), $recursive = true);
		} else {
			$zippy->create($filename, array($details['full_path']), $recursive = false);
		}

		$this->filesToStore[] = array(
			'type' => $type,
			'file_path' => $filename,
			'file_name' => $details['backup_name'] . "." . $this->archiveType,
			'backup_name' => $details['backup_name'],
			'host' => $details['host'],
		);

		$filesystem = new Filesystem();

		$filesystem->remove($details['full_path']);
	}

	/**
	 * Store all prepared files to remote storage or local
	 */
	public function store() {

		foreach ($this->filesToStore as $fileToStore) {
			$storedFile = null;

			$title = strtoupper($fileToStore['type']) . "-" . $fileToStore['host'] . "-" . $fileToStore['file_name'];
			$name = strtoupper($fileToStore['type']) . "-" . $fileToStore['host'] . "-" . $fileToStore['backup_name'];
			$description = strtoupper($fileToStore['type']) . " on " . $fileToStore['host'] . " backup file";

			//store files to remote storage or local
			foreach ($this->storages as $storage) {

				//store it to google drive
				if (isset($storage['type']) && $storage['type'] == strtolower('gdrive')) {

					$drive = new MaratusGoogleDrive($storage);

					$storedFile = $drive->store($fileToStore['file_path'], $title, $description);

				}
				//store it to dropbox
				else if (isset($storage['type']) && $storage['type'] == strtolower('dropbox')) {

					$dBox = new MaratusDropbox($storage);

					$storedFile = $dBox->store($fileToStore['file_path'], $title);

				}
				//store it to copy.com
				else if (isset($storage['type']) && $storage['type'] == strtolower('copy')) {

					$copy = new MaratusCopy($storage);

					$storedFile = $copy->store($fileToStore['file_path'], $title);

				}
				//store it to local place
				else if (isset($storage['type']) && $storage['type'] == strtolower('local')) {

					$localStorage = new MaratusLocalStorage($storage);

					$storedFile = $localStorage->store($fileToStore['file_path'], $title);

				}

				//add result
				$this->filesStored[] = array(
					'type' => $storage['type'],
					'name' => $name,
					'file_name' => $title,
					'description' => $description,
					'storege_result' => is_object($storedFile) ? (array) $storedFile : $storedFile,
				);

			}

			/**
			 * Remove backup file from disk
			 */
			$filesystem = new Filesystem();
			$filesystem->remove($fileToStore['file_path']);
		}
	}

	//return full result from all databases backup
	public function getDatabaseBackupResult() {
		return $this->databaseBackupResult;
	}

	//return full result of storage backup
	public function getStorageBackupResult() {
		return $this->filesStored;
	}

}
