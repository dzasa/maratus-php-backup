<?php

namespace Dzasa\MaratusPhpBackup;

use Alchemy\Zippy\Zippy;
use Dzasa\MaratusPhpBackup\Clients\Copy as MaratusCopy;
use Dzasa\MaratusPhpBackup\Clients\Dropbox as MaratusDropbox;
use Dzasa\MaratusPhpBackup\Clients\GoogleDrive as MaratusGoogleDrive;
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
	 * Add Google Drive configuration
	 *
	 * @param array $config
	 */
	public function addGdrive($config = array()) {
		$this->gDrivesConfigs[] = $config;
	}

	/**
	 * Add Dropbox configuration
	 *
	 * @param array $config
	 */
	public function addDropbox($config = array()) {
		$this->dropboxConfigs[] = $config;
	}

	/**
	 * Add Copy configuration
	 *
	 * @param array $config
	 */
	public function addCopy($config = array()) {
		$this->copyConfigs[] = $config;
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

	public function store() {

		foreach ($this->filesToStore as $fileToStore) {
			$storedFile = null;

			$title = strtoupper($fileToStore['type']) . "-" . $fileToStore['host'] . "-" . $fileToStore['file_name'];
			$name = strtoupper($fileToStore['type']) . "-" . $fileToStore['host'] . "-" . $fileToStore['backup_name'];
			$description = strtoupper($fileToStore['type']) . " on " . $fileToStore['host'] . " backup file";

			/**
			 * Store file to Google drive if we have any configs
			 */
			foreach ($this->gDrivesConfigs as $gConf) {
				$drive = new MaratusGoogleDrive($gConf);

				$storedFile = $drive->store($fileToStore['file_path'], $title, $description);

				if ($storedFile) {
					$this->filesStored[] = array(
						'type' => "gdrive",
						'name' => $name,
						'file_name' => $title,
						'download_url' => isset($storedFile->webContentLink) ? $storedFile->webContentLink : "",
					);
				}
			}

			/**
			 * Store file to Dropbox if we have any configs
			 */
			foreach ($this->dropboxConfigs as $dBoxConfig) {
				$dBox = new MaratusDropbox($dBoxConfig);

				$storedFile = $dBox->store($fileToStore['file_path'], $title);

				if ($storedFile) {
					$this->filesStored[] = array(
						'type' => "dropbox",
						'name' => $name,
						'file_name' => $title,
						'download_url' => isset($storedFile['path']) ? $storedFile['path'] : "",
					);
				}
			}

			/**
			 * Store file to Copy if we have any configs
			 */
			foreach ($this->copyConfigs as $copyConfig) {
				$copy = new MaratusCopy($copyConfig);

				$storedFile = $copy->store($fileToStore['file_path'], $title);

				if ($storedFile) {
					$this->filesStored[] = array(
						'type' => "copy",
						'name' => $name,
						'file_name' => $title,
						'download_url' => isset($storedFile->path) ? $storedFile->path : "",
					);
				}
			}

			/**
			 * Remove backup file from disk
			 */
			$filesystem = new Filesystem();
			$filesystem->remove($fileToStore['file_path']);
		}
	}

	public function getDatabaseBackupResult() {
		return $this->databaseBackupResult;
	}

	public function getStorageBackupResult() {
		return $this->filesStored;
	}

}
