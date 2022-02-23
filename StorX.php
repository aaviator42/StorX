<?php
/*
StorX - PHP flat-file storage
by @aaviator42


StorX.php version: 4.1

StorX DB file format version: 3.1

2022-02-22

*/


namespace StorX;

//DEFAULT VALUES---
const THROW_EXCEPTIONS = TRUE; //false: return error codes, true: throw exceptions
const BUSY_TIMEOUT = 1500; //milliseconds to wait if file is busy

///---------

use Exception;

//main object for DB operations
class Sx{
	private $DBfile;		//filename of the datafile
	private $fileHandle;	//resource handle for datafile
	
	private $fileStatus = 0;	//0: file closed, 	1: file open
	
	private $lockStatus = 0;	//0: lock open, 	1: write locked
								//if 0, can only read
	
	private $throwExceptions = THROW_EXCEPTIONS;
	private $busyTimeout = BUSY_TIMEOUT;
	
	public function throwExceptions($throwExceptions = NULL){
		if(!empty($throwExceptions)){
			$this->throwExceptions = (bool)$throwExceptions;
		}
		return $this->throwExceptions;
	}
	
	public function setTimeout($busyTimeout = NULL){
		if(!empty($busyTimeout)){
			$this->busyTimeout = (int)$busyTimeout;
		}
		return $this->busyTimeout;
	}
	
	
	//FILE OPERATIONS
	public function createFile($filename){
		//RETURN VALUES:
		//0 if we're unable to create the file for whatever reason
		//1 if file successfully created
		
		if(!file_exists($filename)){
			
			//file doesn't exist
			try {
				//create+open DB, and then lock it for writing
				$tempDB = new \SQLite3($filename, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
				$tempDB->busyTimeout($this->busyTimeout);
				$tempDB->enableExceptions(true);
				$tempDB->exec("BEGIN EXCLUSIVE;");
			}
			catch (Exception $e) { 
				//unable to create+open+lock DB
				if($this->throwExceptions){
					throw new Exception("[StorX: createFile()] [SQLite]: " . $e->getMessage() . PHP_EOL, 300);
				} else {
					return 0; 
				}
			} 
			
			//DB create+open was successful

			//creating table 'main'
			$tempDB->exec("	CREATE TABLE IF NOT EXISTS main (
							keyName STRING PRIMARY KEY,
							keyValue STRING)");
			
			//creating info row in table 'main'
			$tempDB->exec("	INSERT INTO main
							VALUES ('StorXInfo', 'v3.1')");
					
			//close DB
			try {
				$tempDB->exec("COMMIT;");
			} 
			catch (Exception $e) {
				//unable to commit changes to new DB
				$tempDB->close();
				unlink($filename);
				if($this->throwExceptions){
					throw new Exception("[StorX: createFile()] [SQLite]: " . $e->getMessage() . PHP_EOL, 300);
				} else {
					return 0; 
				}
			}
			$tempDB->close();
			return 1; // ALL OKAY!	CREATED: DB file, table 'main', info row
			
		} else {
			//unable to create DB file
			if($this->throwExceptions){
				throw new Exception("[StorX: createFile()] Unable to create file [$filename]." . PHP_EOL, 106);
			} else {
				return 0;
			}
		}
	}

	public function checkFile($filename){
		//RETURN VALUES:
		//0 if file doesn't exist
		//1 if StorX DB file of correct version exists
		//2 if StorX DB file exists, but of different version
		//3 if an SQLite3 file exists but not a StorX DB
		//4 if an SQLite3 file exists but it's locked
		//5 if a file exists but it's not of the SQLite3 format
		
		if(!file_exists($filename)){
			
			//file doesn't exist 
			return 0;
			
		} else {
			
			//file already exists
			try {
				//open DB
				$tempDB = new \SQLite3($filename, SQLITE3_OPEN_READONLY);
				$tempDB->busyTimeout($this->busyTimeout);
				$tempDB->enableExceptions(true);
				$results = $tempDB->query("SELECT keyValue FROM main WHERE keyName='StorXInfo'");
			} catch (Exception $e) {
				//unable to open DB file
				if(strpos($e->getMessage(), "database is locked") !== false){
					//DB locked
					return 4;
				} else {
					//DB wrong format
					return 5;
				}
			}
			
			//file opened successfully
			try {
				$StorXInfo = $results->fetchArray()["keyValue"];
			} catch (Exception $e) {
				//unable to open DB file
				if(strpos($e->getMessage(), "database is locked") !== false){
					//DB locked
					return 4;
				} else {
					//DB wrong format
					return 5;
				}
			}
			
			if($StorXInfo === NULL){
				//File is not a valid StorX DB!
				$tempDB->close();
				return 3;
			} else if($StorXInfo === "v3.1"){
				//File is a valid StorX DB of the same version
				$tempDB->close();
				return 1;
			} else {
				//File is not a StorX DB of the same version
				$tempDB->close();
				return 2;
			}
		}	
	}

	public function deleteFile($filename){
		
		if(!file_exists($filename)){
			//file doesn't exist
			return 1;
		}
		
		
		try {
			//open DB
			$tempDB = new \SQLite3($filename, SQLITE3_OPEN_READWRITE);
			$tempDB->busyTimeout($this->busyTimeout);
			$tempDB->enableExceptions(true);
			$tempDB->exec("BEGIN EXCLUSIVE;");
			
		}
		catch (Exception $e) {
			//unable to open DB
			
			if(!file_exists($filename)){
				//file doesn't exist
				return 1;
			}
			
			//unable to delete file
			if($this->throwExceptions){
				throw new Exception("[StorX: deleteFile()] Unable to delete file [$filename]." . PHP_EOL, 107);				
			} else {
				return 0; 
			}
		} 
		
		//DB open
		try {
			//delete 'main' table with ALL DATA
			$tempDB->exec("DROP TABLE main");
			$tempDB->exec("COMMIT;");
		}
		catch (Exception $e) {
			//unable to drop 'main' table
			if($this->throwExceptions){
				throw new Exception("[StorX: deleteFile()] [SQLite]: " . $e->getMessage() . PHP_EOL, 300);				
			} else {
				return 0; 
			}
		} 
		//table 'main' dropped
		
		if(unlink($filename)){
			$tempDB->close();
			//deleted successfully
			return 1;
		} else {
			$tempDB->close();
			if($this->throwExceptions){
				throw new Exception("[StorX: deleteFile()] Unable to delete file [$filename]." . PHP_EOL, 107);				
			} else {
				return 0; 
			}
		}
	}

	public function openFile($filename, $mode = 0){
		
		if(!file_exists($filename)){
			//DBfile does not exist
			if($this->throwExceptions){
				throw new Exception("[StorX: openFile()] File [$filename] does not exist.", 101);
			} else {
				return 0;  
			}
		}
		
		$fileCheck = $this->checkFile($filename);
		if($fileCheck !== 1){
			//something is wrong
			
			if($fileCheck !== 1){
				//something is still wrong
				if($fileCheck === 4){
					if($this->throwExceptions){
						throw new Exception("[StorX: openFile()] File [$filename] is locked." . PHP_EOL, 104);
					} else {
						return 0;  
					}
					
				} else {
					//file is not of StorX type
					if($this->throwExceptions){
						throw new Exception("[StorX: openFile()] File [$filename] is not of matching StorX version." . PHP_EOL, 105);
					} else {
						return 0;  
					}
				}
			}
		}
		
		//mode values: 0 = readonly, 1 = readwrite
		if($mode == 1){			
			try {
				//open DB for readwrite
				$this->fileHandle = new \SQLite3($filename, SQLITE3_OPEN_READWRITE);
				$this->fileHandle->enableExceptions(true);
				$this->fileHandle->busyTimeout($this->busyTimeout);
				$this->lockStatus = 1;	//File locked for readwrite
				$this->fileStatus = 1;	//File open
				$this->DBfile = $filename;
			} 
			catch (Exception $e) {
				//unable to open DB for readwrite
				
				if($this->throwExceptions){
					throw new Exception("[StorX: openFile()] [SQLite]: " . $e->getMessage() . PHP_EOL, 300);				
				} else {
					return 0; 
				}
			} 
			
			//If control reached here, then the DB was successfully opened for readwrite
			//Because we're opening for readwrite, we now need to begin a transaction
			try {
				$this->fileHandle->exec("BEGIN EXCLUSIVE;");
			}
			catch (exception $e){
				//unable to begin transaction on DB
				
				if(strpos($e->getMessage(), "database is locked") !== false){
					if($this->throwExceptions){
						throw new Exception("[StorX: openFile()] File [$filename] is locked." . PHP_EOL, 104);				
					} else {
						return 0; 
					}
				} else {
					if($this->throwExceptions){
						throw new Exception("[StorX: openFile()] [SQLite]: " . $e->getMessage() . PHP_EOL, 300);				
					} else {
						return 0; 
					}
				}
			}				
			
			//if control reaches here, then the file opened successfully for readwrite, and transaction begun
			//function complete
			return 1;
		} else {
				//open DB for readonly
			try {
				//open file
				$this->fileHandle = new \SQLite3($filename, SQLITE3_OPEN_READONLY);
				$this->fileHandle->enableExceptions(true);
				$this->fileHandle->busyTimeout($this->busyTimeout);
				$this->lockStatus = 0;	//File NOT locked
				$this->fileStatus = 1;	//File open
				$this->DBfile = $filename;
			} 
			catch (Exception $e) {
				//unable to open DB for readonly
				//because we're using transactions, this is super unlikely, but still...
				if($this->throwExceptions){				
					throw new Exception("[StorX: openFile()] [SQLite]: " . $e->getMessage() . PHP_EOL, 300);
				} else {
					return 0; 
				}
			} 
			
			//If control reached here, then the DB was successfully opened for readonly
			//Because we're opening for readonly, we don't need to worry about locking the file
			return 1;
		}
	}

	public function closeFile(){
		if(isset($this->fileHandle)){
			
			if($this->lockStatus === 1){
				try {
					$this->fileHandle->exec("COMMIT;");
				}
				catch (Exception $e){
					//unable to commit transaction to DB
					//This is super unlikely, but still...
					if($this->throwExceptions){
						throw new Exception("[StorX: closeFile()] [SQLite]: " . $e->getMessage() . PHP_EOL, 300);				
					} else {
						return 0; 
					}
				}
			}
			
			$this->fileHandle->close();
		}
		unset($this->fileHandle);
		unset($this->fileStatus);
		unset($this->lockStatus);
		unset($this->DBfile);
		return 1;
	}
	
	public function commitFile(){
		if(isset($this->fileHandle)){
			
			if($this->lockStatus === 1){
				try {
					
					$this->fileHandle->exec("COMMIT;");
					$this->fileHandle->exec("BEGIN EXCLUSIVE;");
					return 1;
				}
				catch (Exception $e){
					//unable to commit transaction to DB
					//This is super unlikely, but still...
					
					if($this->throwExceptions){
						throw new Exception("[StorX: commitFile()] [SQLite]: " . $e->getMessage() . PHP_EOL, 300);						
					} else {
						return 0; 
					}
				}
			}
			return 1;
			
		} else {
			//no file open
			if($this->throwExceptions){
				throw new Exception("[StorX: commitFile()]: No file open." . PHP_EOL, 102);						
			} else {
				return 0; 
			}
		}
	}
	
	
	//KEY OPERATIONS
	public function readKey($keyName, &$store){
		if($keyName === "StorXInfo"){
			$store = "3.1";
			return 1;
		}
		$keyNameEncoded = base64_encode($keyName);
		
		if(!$this->fileStatus){
			//no file open
			if($this->throwExceptions){
				throw new Exception("[StorX: readKey()] No file open." . PHP_EOL, 102);				
			} else {
				return 0;
			}
		}
		
		$result = $this->fileHandle->query("SELECT COUNT(*) FROM main WHERE keyName='$keyNameEncoded'");
		if($result->fetchArray(SQLITE3_NUM)[0] === 0){
			if($this->throwExceptions){
				throw new Exception("[StorX: readKey()] Key [$keyName] doesn't exist in file [$this->DBfile]." . PHP_EOL, 201);				
			} else {
				return 0;	//key not found!
			}
		}
		
		$result = $this->fileHandle->query("SELECT keyValue FROM main WHERE keyName='$keyNameEncoded'");
		$store = unserialize(base64_decode($result->fetchArray(SQLITE3_NUM)[0]));	//storing value in $store
		return 1;
	}
	
	public function readAllKeys(&$store){
		if(!$this->fileStatus){
			//no file open
			if($this->throwExceptions){
				throw new Exception("[StorX: readAllKeys()] No file open." . PHP_EOL, 102);				
			} else {
				return 0;
			}
		}
		
		try {
			$result = $this->fileHandle->query("SELECT * FROM main"); //read all rows
			$resultArray = $result->fetchArray(SQLITE3_ASSOC); //skip StorXInfo row
			$resultArray = $result->fetchArray(SQLITE3_ASSOC);
		}
		
		catch (Exception $e){
			//unable to read keys
			//This is super unlikely, but still...
			
			if($this->throwExceptions){
				throw new Exception("[StorX: readAllKeys()] [SQLite]: " . $e->getMessage() . PHP_EOL, 300);						
			} else {
				return 0; 
			}
		}
		
		$output = array();
		while($resultArray !== false){
			$output[base64_decode($resultArray["keyName"])] = unserialize(base64_decode($resultArray["keyValue"]));
			$resultArray = $result->fetchArray(SQLITE3_ASSOC);
		}
		
		$store = $output;
		return 1;
	}

	public function returnKey($keyName){
		if($keyName === "StorXInfo"){
			return "3.1";
		}
		
		$keyNameEncoded = base64_encode($keyName);
		
		if(!$this->fileStatus){
			//no file open
			if($this->throwExceptions){
				throw new Exception("[StorX: returnKey()] No file open." . PHP_EOL, 102);				
			} else {
				return "STORX_ERROR";
			}
		}
		
		$result = $this->fileHandle->query("SELECT COUNT(*) FROM main WHERE keyName='$keyNameEncoded'");
		if($result->fetchArray(SQLITE3_NUM)[0] === 0){
			if($this->throwExceptions){
				throw new Exception("[StorX: returnKey()] Key doesn't exist in file [$this->DBfile]." . PHP_EOL, 201);				
			} else {
				return "STORX_ERROR";	//key not found!
			}
		}
		
		$result = $this->fileHandle->query("SELECT keyValue FROM main WHERE keyName='$keyNameEncoded'");
		return unserialize(base64_decode($result->fetchArray(SQLITE3_NUM)[0]));	//returning value
	}
	
	public function writeKey($keyName, $keyValue){
		if($keyName === "StorXInfo"){
			if($this->throwExceptions){
				throw new Exception("[StorX: writeKey()] Don't be naughty!" . PHP_EOL, 666);				
			} else {
				return 0; 
			}
		}
		
		$keyNameEncoded = base64_encode($keyName);
		
		if(!$this->fileStatus){
			//no file open
			if($this->throwExceptions){
				throw new Exception("[StorX: writeKey()] No file open." . PHP_EOL, 102);				
			} else {
				return 0; 
			}
		}
		if(!$this->lockStatus){
			//file not locked
			if($this->throwExceptions){
				throw new Exception("[StorX: writeKey()] File [$this->DBfile] not locked for writing." . PHP_EOL, 103);				
			} else {
				return 0; 
			}
		}
		
		$result = $this->fileHandle->query("SELECT COUNT(*) FROM main WHERE keyName='$keyNameEncoded'");
		if($result->fetchArray(SQLITE3_NUM)[0] !== 0){
			//key already exists!
			if($this->throwExceptions){
				throw new Exception("[StorX: writeKey()] Key [$keyName] already exists in file [$this->DBfile]." . PHP_EOL, 202);				
			} else {
				return 0; 
			}
		}
		
		//base64 because serialize()'s output contains chars that break SQLite commands
		$keyValue = base64_encode(serialize($keyValue));
		try {
			$this->fileHandle->exec("INSERT INTO main VALUES ('$keyNameEncoded', '$keyValue')");
		} 
		catch (Exception $e) {
			if($this->throwExceptions){
				throw new Exception("[StorX: writeKey()] [SQLite]: " . $e->getMessage() . PHP_EOL, 300);				
			} else {
				return 0; 
			}
		}
		return 1;
	}
	
	public function modifyKey($keyName, $keyValue){
		if($keyName === "StorXInfo"){
			if($this->throwExceptions){
				throw new Exception("[StorX: modifyKey()] Don't be naughty!" . PHP_EOL, 666);				
			} else {
				return 0; 
			}
		}
		
		$keyNameEncoded = base64_encode($keyName);
		
		if(!$this->fileStatus){
			//no file open
			if($this->throwExceptions){
				throw new Exception("[StorX: modifyKey()] No file open." . PHP_EOL, 102);				
			} else {
				return 0; 
			}
		}
		
		if(!$this->lockStatus){
			//file not locked
			if($this->throwExceptions){
				throw new Exception("[StorX: modifyKey()] File [$this->DBfile] not locked for writing." . PHP_EOL, 103);				
			} else {
				return 0; 
			}
		}
		
		$result = $this->fileHandle->query("SELECT COUNT(*) FROM main WHERE keyName='$keyNameEncoded'");
		if($result->fetchArray(SQLITE3_NUM)[0] !== 0){
			//key already exists
			
			$keyValue = base64_encode(serialize($keyValue));			
			try {
				$this->fileHandle->exec("UPDATE main SET keyValue='$keyValue' WHERE keyName='$keyNameEncoded'");
			}
			catch (Exception $e) {
				if($this->throwExceptions){
					throw new Exception("[StorX: modifyKey()] [SQLite]: " . $e->getMessage() . PHP_EOL, 200);				
				} else {
					return 0; 
				}
			}
			return 1;
		} else {
			//key doesn't exist
			$keyValue = base64_encode(serialize($keyValue));
			try {
				$this->fileHandle->exec("INSERT INTO main VALUES ('$keyNameEncoded', '$keyValue')");
			}
			catch (Exception $e) {
				if($this->throwExceptions){
					throw new Exception("[StorX: modifyKey()] [SQLite]: " . $e->getMessage() . PHP_EOL, 200);				
				} else {
					return 0; 
				}
			}
			return 1;
		}
	}
	
	public function modifyMultipleKeys($keyArray){
		if(!$this->fileStatus){
			//no file open
			if($this->throwExceptions){
				throw new Exception("[StorX: modifyMultipleKeys()] No file open." . PHP_EOL, 102);				
			} else {
				return 0; 
			}
		}
		
		if(!$this->lockStatus){
			//file not locked
			if($this->throwExceptions){
				throw new Exception("[StorX: modifyMultipleKeys()] File [$this->DBfile] not locked for writing." . PHP_EOL, 103);				
			} else {
				return 0; 
			}
		}
		
		foreach($keyArray as $keyName => $keyValue){
			if($keyName === "StorXInfo"){
				continue;
			}
			
			$keyNameEncoded = base64_encode($keyName);
			
			$result = $this->fileHandle->query("SELECT COUNT(*) FROM main WHERE keyName='$keyNameEncoded'");
			if($result->fetchArray(SQLITE3_NUM)[0] !== 0){
				//key already exists
				
				$keyValue = base64_encode(serialize($keyValue));			
				try {
					$this->fileHandle->exec("UPDATE main SET keyValue='$keyValue' WHERE keyName='$keyNameEncoded'");
				}
				catch (Exception $e) {
					if($this->throwExceptions){
						throw new Exception("[StorX: modifyMultipleKeys()] [SQLite]: " . $e->getMessage() . PHP_EOL, 300);				
					} else {
						return 0; 
					}
				}
			} else {
				//key doesn't exist
				$keyValue = base64_encode(serialize($keyValue));
				try {
					$this->fileHandle->exec("INSERT INTO main VALUES ('$keyNameEncoded', '$keyValue')");
				}
				catch (Exception $e) {
					if($this->throwExceptions){
						throw new Exception("[StorX: modifyMultipleKeys()] [SQLite]: " . $e->getMessage() . PHP_EOL, 300);				
					} else {
						return 0; 
					}
				}
			}
		}
		return 1;
	}
	
	public function checkKey($keyName){
		if($keyName === "StorXInfo"){
			if($this->throwExceptions){
				throw new Exception("[StorX: checkKey()] Don't be naughty!" . PHP_EOL, 666);				
			} else {
				return 1; 
			}
		}
		
		$keyNameEncoded = base64_encode($keyName);
		
		if(!$this->fileStatus){
			//no file open
			if($this->throwExceptions){
				throw new Exception("[StorX: checkKey()] No file open." . PHP_EOL, 102);				
			} else {
				return 0; 
			}
		}
		$result = $this->fileHandle->query("SELECT COUNT(*) FROM main WHERE keyName='$keyNameEncoded'");
		if($result->fetchArray(SQLITE3_NUM)[0] === 0){
			return 0;	//key not found!
		}
		return 1;
	}
	
	public function deleteKey($keyName){
		if($keyName === "StorXInfo"){
			if($this->throwExceptions){
				throw new Exception("[StorX: deleteKey()] Don't be naughty!" . PHP_EOL, 666);				
			} else {
				return 0; 
			}
		}
		
		$keyNameEncoded = base64_encode($keyName);
		
		if(!$this->fileStatus){
			//no file open
			if($this->throwExceptions){
				throw new Exception("[StorX: deleteKey()] No file open." . PHP_EOL, 102);				
			} else {
				return 0; 
			}
		}
		
		if(!$this->lockStatus){
			//file not locked
			if($this->throwExceptions){
				throw new Exception("[StorX: deleteKey()] File [$this->DBfile] not locked for writing." . PHP_EOL, 103);				
			} else {
				return 0; 
			}
		}
		
		try {
			$this->fileHandle->exec("DELETE FROM main WHERE keyName='$keyNameEncoded'");
		}
		catch (Exception $e) { 
			//unable to delete key
			if($this->throwExceptions){
				throw new Exception("[StorX: deleteKey()] [SQLite]: " . $e->getMessage() . PHP_EOL, 300);
			} else {
				return 0; 
			}
		}
		return 1; //key deleted!
	}
	
}
