<?php

namespace Dzasa\MaratusPhpBackup\Clients;

use \Dropbox as dbx;

class Dropbox {

	/**
	 * Dropbox api configuration
	 */
	private $accessToken = null;
	private $client = null;

	/**
	 * Prepare and auth to Dropbox
	 */
	public function __construct($config = array()) {

		$this->accessToken = $config['access_token'];

		$this->auth();
	}

	/**
	 * Auth to Dropbox
	 * @return array Upload result from Dropbox
	 */
	private function auth() {
		$this->client = new dbx\Client($this->accessToken, "MaratusBackup/1.0");
	}

	/**
	 * Upload file to Dropbox
	 * @param  string $fullPath    Full path from local system being uploaded to Dropbox
	 * @param  string $dropboxPath Path on Dropbox where to store the file
	 * @return [type] $result      Upload result from Dropbox
	 */
	public function store($fullPath, $dropboxPath) {

		//open the file
		$file = fopen($fullPath, "rb");

		//send file in chunks
		$result = $this->client->uploadFileChunked("/" . $dropboxPath, dbx\WriteMode::add(), $file);

		//cclose the file
		fclose($file);

		return $result;
	}

}
