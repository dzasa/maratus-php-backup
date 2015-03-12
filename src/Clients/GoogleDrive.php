<?php

namespace Dzasa\MaratusPhpBackup\Clients;

use Google_Client;
use Google_Service_Drive;

class GoogleDrive {

	/**
	 * Google drive configuration
	 *
	 */
	private $clientId = null;
	private $clientSecret = null;
	private $authCode = null;
	private $client = null;
	private $service = null;
	private $tokenFile;

	/**
	 * Prepare and auth on Google
	 */
	function __construct($config = array()) {
		if (isset($config['client_id'])) {
			$this->clientId = $config['client_id'];
		}

		if (isset($config['client_secret'])) {
			$this->clientSecret = $config['client_secret'];
		}

		if (isset($config['auth_code'])) {
			$this->authCode = $config['auth_code'];
		}

		if (isset($config['token_file'])) {
			$this->tokenFile = $config['token_file'];
		}

		//do auth
		$this->auth();
	}

	/**
	 * Auth on Google
	 */
	private function auth() {
		$this->client = new Google_Client();

		$this->client->setClientId($this->clientId);
		$this->client->setClientSecret($this->clientSecret);
		$this->client->setAccessType("offline");
		$this->client->setRedirectUri("urn:ietf:wg:oauth:2.0:oob");
		$this->client->setScopes(array("https://www.googleapis.com/auth/drive"));

		//auth and save token for future use
		if (!file_exists($this->tokenFile)) {
			$accessToken = $this->client->authenticate($this->authCode);

			//save token to file
			file_put_contents($this->tokenFile, $accessToken);
		} else {
			/**
			 * We have token file, take the refresh token and get new access token
			 */
			$accessTokenInfo = json_decode(file_get_contents($this->tokenFile));

			//set data to post for token refreshing
			$postData = array(
				"client_id" => $this->clientId,
				"client_secret" => $this->clientSecret,
				"refresh_token" => $accessTokenInfo->refresh_token,
				"grant_type" => "refresh_token",
			);

			$query = http_build_query($postData);

			//sed request to refresh the token
			$ch = curl_init("https://accounts.google.com/o/oauth2/token");
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$output = curl_exec($ch);
			curl_close($ch);

			//decode result and add old refresh token and new time
			$tokenData = json_decode($output, true);
			$tokenData['refresh_token'] = $accessTokenInfo->refresh_token;
			$tokenData['created'] = time();
			$accessToken = json_encode($tokenData);

			//and write it to token file for future use
			file_put_contents($this->tokenFile, $accessToken);
		}

		$this->client->setAccessToken($accessToken);

		$this->service = new Google_Service_Drive($this->client);
	}

	/**
	 * Upload file to Google drive
	 *
	 * @param  string $fullPath    Full path to local file being uploaded
	 * @param  string $title       File Title
	 * @param  string $description File description
	 * @return array  $result      Result from Google Drive after upload
	 */
	public function store($fullPath, $title, $description) {

		$file = new \Google_Service_Drive_DriveFile();
		$file->setTitle($title);
		$file->setDescription($description);
		$file->setMimeType($this->getMimeType($fullPath));

		//set chunks in 1MB
		$chunkSizeBytes = 1 * 1024 * 1024;

		$this->client->setDefer(true);
		$request = $this->service->files->insert($file);

		// Create a media file upload to represent our upload process.
		$media = new \Google_Http_MediaFileUpload(
			$this->client, $request, $this->getMimeType($fullPath), null, true, $chunkSizeBytes
		);
		$media->setFileSize(filesize($fullPath));

		// Upload the various chunks. $status will be false until the process is complete
		$status = false;

		$handle = fopen($fullPath, "rb");
		while (!$status && !feof($handle)) {
			$chunk = fread($handle, $chunkSizeBytes);
			$status = $media->nextChunk($chunk);
		}

		// The final value of $status will be the data from the API for the object
		// that has been uploaded.
		$result = false;
		if ($status != false) {
			$result = $status;
		}

		fclose($handle);
		// Reset to the client to execute requests immediately in the future.
		$this->client->setDefer(false);

		return $result;
	}

	/**
	 * Get file Mime Type
	 * @param  string $file Path to file
	 */
	private function getMimeType($file) {
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mimeType = finfo_file($finfo, $file);
		finfo_close($finfo);

		return $mimeType;
	}

}
