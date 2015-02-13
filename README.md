Maratus PHP Backup
===================

Backup more types of databases, local files and store them on locally, ftp, google drive, dropbox etc.

Features
-------------------

* Available storage clients
..* Local
..* FTP
..* Google Drive
..* Dropbox
..* Copy.com
* Available databases
..* MySQL
..* Postgresql
..* SQLite
..* MongoDB
..* Redis
..* Riak
..* CouchDB
* Sending files in compressed format - ZIP, GNU tar, BSD tar
* Connect to remote host via SSH with private key(password protected too) or normal username/password, compress and download with SCP

Documentation and Info
------------------------------------
Full documentation and extra info can be found on GitHub project page [ProjectPage]



Usage
-----

```php

require 'vendor/autoload.php';

use Dzasa\MaratusPhpBackup\MaratusBackup;

$backup = new MaratusBackup();

$dbConfig = array(
   'type' => 'mysql',
   'host' => "localhost",
   'port' => 3306,
   'user' => 'root',
   'pass' => '',
   'database' => ''
);

$backup->addDatabase($dbConfig);

$dbConfigPg = array(
   'type' => 'postgresql',
   'host' => "localhost",
   'port' => 5432,
   'user' => '',
   'pass' => '',
   'database' => ''
);

$backup->addDatabase($dbConfigPg);

$dbConfigMongo = array(
   'type' => 'mongodb',
   'database' => '',
   'host' => '',
   'user' => 'dzasa',
   'pass' => ''
);

$backup->addDatabase($dbConfigMongo);

$couchDbConfig = array(
'type' => 'couchdb',
'remote' => true,
'host' => 'localhost',
'user' => 'root',
'pass' => '',
'database' => '',
);
$backup->addDatabase($couchDbConfig);

$dbConfig2 = array(
   'type' => 'mysql',
   'host' => "localhost",
   'port' => 3306,
   'user' => '',
   'pass' => '',
   'database' => ''
);
$backup->addDatabase($dbConfig2);

$dBoxConfig = array(
   'access_token' => ''
);

$backup->addDropbox($dBoxConfig);

$gDriveConfig = array(
   'client_id' => '',
   'client_secret' => '',
   'token_file' => 'gdrive-token.json',
   'auth_code' => ''
);
$backup->addGdrive($gDriveConfig);

$redisConfig = array(
'type' => 'redis',
'remote' => true,
'host' => '192.168.1.1',
'user' => 'root',
'private_key' => '',
'private_key_pass' => '',
'database_path' => "/var/lib/redis/dump.rdb",
);

$backup->addDatabase($redisConfig);

$riakConfig = array(
'type' => 'riak',
'remote' => true,
'host' => '192.168.1.1',
'user' => 'root',
'private_key' => '',
'private_key_pass' => '@',
'bitcask_path' => '/var/lib/riak/bitcask',
'leveldb_path' => '/var/lib/riak/leveldb',
'strong_consistency_path' => '/var/lib/riak/ensembles',
'remote_compress' => 'zip',
);
$backup->addDatabase($riakConfig);

$sqliteConfig = array(
	'type' => 'sqlite',
	'remote' => true,
	'host' => '192.168.1.1',
	'user' => 'root',
	'private_key' => '',
	'private_key_pass' => '',
	'remote_compress' => 'zip',
	'database_path' => '/root/backup',
);
$backup->addDatabase($sqliteConfig);

$copyConfig = array(
	'type' => 'copy',
	'consumer_key' => '',
	'consumer_secret' => '',
	'access_token' => '',
	'token_secret' => '',
);

$backup->addStorage($copyConfig);

$localStorageConfig = array(
	'type' => 'local',
	'save_dir' => 'test2',
);

$backup->addStorage($localStorageConfig);

$ftpStorage = array(
	'type' => 'ftp',
	'host' => '192.168.1.1',
	'user' => '',
	'pass' => '',
	'remote_dir' => 'test2',
);
$backup->addStorage($ftpStorage);

$backup->backup("tar.bz2");

print_r($backup->getDatabaseBackupResult());
echo "-----------------------------\n";
print_r($backup->getStorageBackupResult());


```


About
=====

Requirements
------------

- PHP Zippy
- Copy.com PHP SDK
- Dropbox SDK
- Google API Client
- PHPSeclib
- Symfony Filesystem
- Symfony Proccess
- Guzzle


Submitting bugs and feature requests
------------------------------------
Bugs and feature request are tracked on [GitHub]


Version
----

Soon :)


Author
------
Jasenko Rakovic - naucnik@gmail.com

License
----

Licensed under the MIT License - see the LICENSE file for details

[GitHub]:https://github.com/dzasa/maratus-php-backup
[ProjectPage]:http://dzasa.github.io/maratus-php-backup
