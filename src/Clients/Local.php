<?php

namespace Dzasa\MaratusPhpBackup\Clients;

/**
 * Store files on local storage
 */
class Local {

	//local directory where to save file
	private $saveDir = null;

	//result
	private $result = array();

	/**
	 * Prepare
	 */
	public function __construct($config = array()) {

		$this->saveDir = $config['save_dir'];
	}

	/**
	 * Store it locally
	 * @param  string $fullPath    Full path from local system being saved
	 * @param  string $filename Filename to use on saving
	 */
	public function store($fullPath, $filename) {

		//return error on not set save dir
		if ($this->saveDir == false) {
			$result = array(
				'error' => 1,
				'message' => "Directory to save is not defined!",
			);

			return $result;
		}
		//dir doesn't exist
		else if (is_dir($this->saveDir) == false) {
			$result = array(
				'error' => 1,
				'message' => "Directory to save does not exist!",
			);

			return $result;
		}

		//prepare dir path to be valid :)
		$this->saveDir = rtrim($this->saveDir, "/") . "/";

		try {

			//save file to local dir
			copy($fullPath, $this->saveDir . $filename);

			//prepare and return result
			$result = array(
				'storage_path' => $this->saveDir . $filename,
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
