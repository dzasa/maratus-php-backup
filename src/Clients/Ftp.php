<?php

namespace Dzasa\MaratusPhpBackup\Clients;

/**
 * Store files on ftp server
 */
class Ftp {

	//ftp host
	private $host = "localhost";

	//ftp port
	private $port = "21";

	//ftp username
	private $user = "root";

	//ftp password
	private $pass = "";

	//passive mode
	private $passive = true;

	//connection
	private $connection = null;

	//local directory where to save file
	private $remoteDir = null;

	//result
	private $result = array();

	/**
	 * Prepare
	 */
	public function __construct($config = array()) {

		if (isset($config['remote_dir'])) {
			$this->remoteDir = $config['remote_dir'];
		}

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

		$this->connect();
	}

	private function connect() {

		//prepare connection
		$this->connection = ftp_connect($this->host, $this->port);

		//login to ftp server
		$loginResponse = ftp_login($this->connection, $this->user, $this->pass);

		if ((!$this->connection) || (!$loginResponse)) {
			return false;
		}

		ftp_pasv($this->connection, $this->passive);

		ftp_set_option($this->connection, FTP_TIMEOUT_SEC, 300);
	}

	/**
	 * Store it locally
	 * @param  string $fullPath    Full path from local system being saved
	 * @param  string $filename Filename to use on saving
	 */
	public function store($fullPath, $filename) {

		if ($this->connection == false) {
			$result = array(
				'error' => 1,
				'message' => "Unable to connect to ftp server!",
			);

			return $result;
		}

		//prepare dir path to be valid :)
		$this->remoteDir = rtrim($this->remoteDir, "/") . "/";

		try {

			$originalDirectory = ftp_pwd($this->connection);

			// test if you can change directory to remote dir
			// suppress errors in case $dir is not a file or not a directory
			if (@ftp_chdir($this->connection, $this->remoteDir)) {

				// If it is a directory, then change the directory back to the original directory
				ftp_chdir($this->connection, $originalDirectory);
			}
			// try to make dir
			else {
				if (!ftp_mkdir($this->connection, $this->remoteDir)) {
					$result = array(
						'error' => 1,
						'message' => "Remote dir does not exist and unable to create it!",
					);
				}
			}

			//save file to local dir
			if (!ftp_put($this->connection, $this->remoteDir . $filename, $fullPath, FTP_BINARY)) {
				$result = array(
					'error' => 1,
					'message' => "Unable to send file to ftp server",
				);

				return $result;
			}

			//prepare and return result
			$result = array(
				'storage_path' => $this->remoteDir . $filename,
			);

			return $result;
		} catch (Exception $e) {

			//unable to copy file, return error
			$result = array(
				'error' => 1,
				'message' => $e->getMessage(),
			);

			return $result;
		}

	}

}
