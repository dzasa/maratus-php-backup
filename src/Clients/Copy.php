<?php

namespace Dzasa\MaratusPhpBackup\Clients;

use Barracuda\Copy as BarracudaCopy;

/**
 * Auth and upload file to Copy.com
 */
class Copy {

	private $consumerKey = null;
	private $consumerSecret = null;
	private $accessToken = null;
	private $tokenSecret = null;
	private $client = null;
	private $accountInfo = null;

	public function __construct($config = array()) {

		$this->consumerKey = $config['consumer_key'];
		$this->consumerSecret = $config['consumer_secret'];
		$this->accessToken = $config['access_token'];
		$this->tokenSecret = $config['token_secret'];

		$this->auth();
	}

	/**
	 * Authenticate to Copy API
	 */
	private function auth() {
		$this->client = new BarracudaCopy\API($this->consumerKey, $this->consumerSecret, $this->accessToken, $this->tokenSecret);
	}

	/**
	 * Upload file to Co.com
	 * @param  string $fullPath Full path to file being uploaded
	 * @param  string $copyPath Path on Copy.com
	 * @return array  $result   Upload result
	 */
	public function store($fullPath, $copyPath) {

		// open a file to upload
		$handler = fopen($fullPath, 'rb');

		// upload the file in 1MB chunks
		$parts = array();
		while ($data = fread($handler, 1024 * 1024)) {
			$part = $this->client->sendData($data);
			array_push($parts, $part);
		}

		// close the file
		fclose($handler);

		// finalize the file
		$result = $this->client->createFile("/" . $copyPath, $parts);

		return $result;
	}

}
