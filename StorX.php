<?php
/*
StorX - PHP flat-file storage
by @aaviator42

StorX.php version: 3.2 
StorX DB file format version: 3.0

2021-07-28


*/


namespace StorX;
use Exception;

const THROW_EXCEPTIONS = 1; //0: return error codes, 1: throw exceptions


function createFile($filename){
	//RETURN VALUES:
	//0 if file doesn't exist and we're unable to create it
	//1 if file successfully created
	//2 if StorX DB file already exists of same version
	//3 if StorX DB file already exists, but of different version
	//4 if an SQLite3 file already exists but not a StorX DB
	//5 if a file already exists but it's not an SQLite3 file
	
	if(!file_exists($filename)){
		
		//file doesn't exist
		try {
			//create+open DB, and then lock it for writing
			$tempDB = new \SQLite3($filename, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
			$tempDB->enableExceptions(true);
			$tempDB->exec("BEGIN EXCLUSIVE;");
		}
		catch (Exception $e) { 
			//unable to create+open+lock DB
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: createFile()] [SQLite]: " . $e->getMessage() . PHP_EOL);
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
						VALUES ('StorXInfo', '3.0')");
				
		//close DB
		try {
			$tempDB->exec("COMMIT;");
		} 
		catch (Exception $e) {
			//unable to commit changes to new DB
			$tempDB->close();
			unlink($filename);
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: createFile()] [SQLite]: " . $e->getMessage() . PHP_EOL);
			} else {
				return 0; 
			}
		}
		$tempDB->close();
		return 1; // ALL OKAY!	CREATED: DB file, table 'main', info row
		
	} else {
		//DB file already exists
		//now we'll check it to see if it is of the same StorX version
		
		try {
			//open DB
			$tempDB = new \SQLite3($filename, SQLITE3_OPEN_READONLY);
			$tempDB->enableExceptions(true);
		} catch (Exception $e) {
			
			//unable to open DB file so we'll return 5
			//because that means that the file isn't a valid SQLite3 file
			
			
			//It could also mean that the file is completely valid but we 
			//just couldn't open it for some reason (eg: disk error), but 
			//we can't really check if that's the case, so 
			// :shrug:
			
			//in any case, because we couldn't open the file, we cannot check
			//whether or not it's a valid StorX DB.
			return 5;
		}
		
		//file open successfully
		$results = $tempDB->query("SELECT keyValue FROM main WHERE keyName='StorXInfo'");
		if($results->fetchArray()["keyValue"] === NULL){
			//File is not a valid StorX DB!
			$tempDB->close();
			return 4;
		} else if($results->fetchArray()["keyValue"] === "3.0"){
			//File is a valid StorX DB!
			$tempDB->close();
			return 2;
		} else {
			//"StorXInfo" value doesn't match.
			//this means that the SQLite3 file is of a different StorX version
			$tempDB->close();
			return 3;
		}
	}

}

function checkFile($filename){
	//RETURN VALUES:
	//0 if file doesn't exist
	//1 if StorX DB file of correct version exists
	//2 NULL
	//3 if StorX DB file exists, but of different version
	//4 if an SQLite3 file exists but not a StorX DB
	//5 if a file exists but it's not an SQLite3 file
	
	if(!file_exists($filename)){
		
		//file doesn't exist 
		return 0;
		
	} else {
		
		//file already exists
		try {
			//open DB
			$tempDB = new \SQLite3($filename, SQLITE3_OPEN_READONLY);
			$tempDB->enableExceptions(true);
			$results = $tempDB->query("SELECT keyValue FROM main WHERE keyName='StorXInfo'");
		} catch (Exception $e) {
			//unable to open DB file
			return 5;
		}
		
		//file opened successfully
		$StorXInfo = $results->fetchArray()["keyValue"];
		
		if($StorXInfo === NULL){
			//File is not a valid StorX DB!
			$tempDB->close();
			return 4;
		} else if($StorXInfo === "3.0"){
			//File is a valid StorX DB of the same version
			$tempDB->close();
			return 1;
		} else {
			//File is not a StorX DB of the same version
			$tempDB->close();
			return 3;
		}
	}	
}

function deleteFile($filename){
	
	if(!file_exists($filename)){
		//file doesn't exist
		return 1;
	}
	
	
	if(checkFile($filename) !== 1 && checkFile($filename) !== 3){
		//not a StorX DB file
		if(THROW_EXCEPTIONS){
			throw new Exception("[StorX: deleteFile()] $filename is not a StorX DB file." . PHP_EOL);
		} else {
			return 0;
		}
	}
		
	
	try {
		//open DB
		$tempDB = new \SQLite3($filename, SQLITE3_OPEN_READWRITE);
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
		if(THROW_EXCEPTIONS){
			throw new Exception("[StorX: deleteFile()] [SQLite]: " . $e->getMessage() . PHP_EOL);				
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
		if(THROW_EXCEPTIONS){
			throw new Exception("[StorX: deleteFile()] [SQLite]: " . $e->getMessage() . PHP_EOL);				
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
		if(THROW_EXCEPTIONS){
			throw new Exception("[StorX: deleteFile()] Unable to delete $filename." . PHP_EOL);				
		} else {
			return 0; 
		}
	}
}


//main object for actual DB operations
class Sx{
	private $DBfile;		//filename of the datafile
	private $fileHandle;	//resource handle for datafile
	
	private $fileStatus = 0;	//0: file closed, 	1: file open
	
	private $lockStatus = 0;	//0: lock open, 	1: write locked
								//if 0, can only read
	
	//FILE OPERATIONS
	public function openFile($filename, $mode = 0){
		
		if(!file_exists($filename)){
			//DBfile does not exist
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: openFile()] File does not exist.");
			} else {
				return 0;  
			}
		}
		
		if(checkFile($filename) !== 1){
			//file is not of StorX type
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: openFile()] File not of StorX type.");
			} else {
				return 0;  
			}
		}
		
		//mode values: 0 = readonly, 1 = readwrite
		if($mode == 1){			
			try {
				//open DB for readwrite
				$this->fileHandle = new \SQLite3($filename, SQLITE3_OPEN_READWRITE);
				$this->fileHandle->enableExceptions(true);
				$this->lockStatus = 1;	//File locked for readwrite
				$this->fileStatus = 1;	//File open
				$this->DBfile = $filename;
			} 
			catch (Exception $e) {
				//unable to open DB for readwrite
				//because we're using transactions, this is super unlikely, but still...
				
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX: openFile()] [SQLite]: " . $e->getMessage() . PHP_EOL);				
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
				//This is super unlikely, but still...
				
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX: openFile()] [SQLite]: " . $e->getMessage() . PHP_EOL);				
				} else {
					return 0; 
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
				$this->lockStatus = 0;	//File NOT locked
				$this->fileStatus = 1;	//File open
				$this->DBfile = $filename;
			} 
			catch (Exception $e) {
				//unable to open DB for readonly
				//because we're using transactions, this is super unlikely, but still...
				if(THROW_EXCEPTIONS){				
					throw new Exception("[StorX: openFile()] [SQLite]: " . $e->getMessage() . PHP_EOL);
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
					if(THROW_EXCEPTIONS){
						throw new Exception("[StorX: closeFile()] [SQLite]: " . $e->getMessage() . PHP_EOL);				
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
					
					if(THROW_EXCEPTIONS){
						throw new Exception("[StorX: commitFile()] [SQLite]: " . $e->getMessage() . PHP_EOL);						
					} else {
						return 0; 
					}
				}
			}
			return 1;
			
		} else {
			//no file open
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: commitFile()]: No file open." . PHP_EOL);						
			} else {
				return 0; 
			}
	}
	
	
	//KEY OPERATIONS
	public function readKey($keyName, &$store){
		if(!$this->fileStatus){
			//no file open
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: readKey()] No file open." . PHP_EOL);				
			} else {
				return 0;
			}
		}
		
		$result = $this->fileHandle->query("SELECT COUNT(*) FROM main WHERE keyName='$keyName'");
		if($result->fetchArray(SQLITE3_NUM)[0] === 0){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: readKey()] Key doesn't exist in DB file." . PHP_EOL);				
			} else {
				return 0;	//key not found!
			}
		}
		
		$result = $this->fileHandle->query("SELECT keyValue FROM main WHERE keyName='$keyName'");
		$store = unserialize($result->fetchArray(SQLITE3_NUM)[0]);	//storing value in $store
		return 1;
	}

	public function returnKey($keyName){
		if(!$this->fileStatus){
			//no file open
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: returnKey()] No file open." . PHP_EOL);				
			} else {
				return "STORX_ERROR";
			}
		}
		
		$result = $this->fileHandle->query("SELECT COUNT(*) FROM main WHERE keyName='$keyName'");
		if($result->fetchArray(SQLITE3_NUM)[0] === 0){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: returnKey()] Key doesn't exist in DB file." . PHP_EOL);				
			} else {
				return "STORX_ERROR";	//key not found!
			}
		}
		
		$result = $this->fileHandle->query("SELECT keyValue FROM main WHERE keyName='$keyName'");
		return unserialize($result->fetchArray(SQLITE3_NUM)[0]);	//returning value
	}
	
	public function writeKey($keyName, $keyValue){
		if(!$this->fileStatus){
			//no file open
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: writeKey()] No file open." . PHP_EOL);				
			} else {
				return 0; 
			}
		}
		if(!$this->lockStatus){
			//file not locked
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: writeKey()] File [$this->DBfile] not locked for writing." . PHP_EOL);				
			} else {
				return 0; 
			}
		}
		
		$result = $this->fileHandle->query("SELECT COUNT(*) FROM main WHERE keyName='$keyName'");
		if($result->fetchArray(SQLITE3_NUM)[0] !== 0){
			//key already exists!
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: writeKey()] Key [$keyName] already exists." . PHP_EOL);				
			} else {
				return 0; 
			}
		}
		
		$keyValue = serialize($keyValue);
		$this->fileHandle->exec("INSERT INTO main VALUES ('$keyName', '$keyValue')");
		if($this->fileHandle->lastErrorCode() === 0){
			return 1; //successfully written key!
		} else {
			//key write fail
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: writeKey()] Unable to write key [$keyName]." . PHP_EOL);				
			} else {
				return 0; 
			}
		}
	}
	
	public function modifyKey($keyName, $keyValue){
		if(!$this->fileStatus){
			//no file open
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: modifyKey()] No file open." . PHP_EOL);				
			} else {
				return 0; 
			}
		}
		
		if(!$this->lockStatus){
			//file not locked
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: modifyKey()] File [$this->DBfile] not locked for writing." . PHP_EOL);				
			} else {
				return 0; 
			}
		}
		
		$result = $this->fileHandle->query("SELECT COUNT(*) FROM main WHERE keyName='$keyName'");
		if($result->fetchArray(SQLITE3_NUM)[0] !== 0){
			//key already exists
			
			$keyValue = serialize($keyValue);			
			$this->fileHandle->exec("UPDATE main SET keyValue='$keyValue' WHERE keyName='$keyName'");
			if($this->fileHandle->lastErrorCode() === 0){
				return 1; //successfully updated key!
			} else {
				//key update fail :(
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX: modifyKey()] Unable to update key [$keyName]." . PHP_EOL);				
				} else {
					return 0; 
				}
			}
		}
		
		//key doesn't exist
		$keyValue = serialize($keyValue);
		$this->fileHandle->exec("INSERT INTO main VALUES ('$keyName', '$keyValue')");
		if($this->fileHandle->lastErrorCode() === 0){
			return 1; //successfully written key!
		} else {
			//key write fail
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: modifyKey()] Unable to write key [$keyName]." . PHP_EOL);				
			} else {
				return 0; 
			}
		}
	}
	
	public function checkKey($keyName){
		if(!$this->fileStatus){
			//no file open
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: checkKey()] No file open." . PHP_EOL);				
			} else {
				return 0; 
			}
		}
		$result = $this->fileHandle->query("SELECT COUNT(*) FROM main WHERE keyName='$keyName'");
		if($result->fetchArray(SQLITE3_NUM)[0] === 0){
			return 0;	//key not found!
		}
		return 1;
	}
	
	public function deleteKey($keyName){
		if(!$this->fileStatus){
			//no file open
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: deleteKey()] No file open." . PHP_EOL);				
			} else {
				return 0; 
			}
		}
		
		if(!$this->lockStatus){
			//file not locked
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: deleteKey()] File [$this->DBfile] not locked for writing." . PHP_EOL);				
			} else {
				return 0; 
			}
		}
		
		try {
			$this->fileHandle->exec("DELETE FROM main WHERE keyName='$keyName'");
		}
		catch (Exception $e) { 
			//unable to delete key
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX: deleteKey()] [SQLite]: " . $e->getMessage() . PHP_EOL);
			} else {
				return 0; 
			}
		}
		return 1; //key deleted!
	}
	
}

