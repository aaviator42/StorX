# StorX
Simple (but robust!) PHP key-value flat-file data storage library

Current library version: `4.1` | `2022-02-22`  
Current DB file version: `3.1`

License: `AGPLv3`

## About 

StorX is an easy and robust way to write data (objects) in flat files as "keys", which you can read and modify later.  

It was initially developed primarily to facilitate sharing of data between independent PHP scripts and sessions, but can be used in any context where you want to easily write/read data to/from files, but don't want to deal with the complexities of relational databases.

It is basically `serialize()` + file handling (`fopen(), fread(), fwrite()`) on steroids. Objects are stored as "keys" in "DB files". These files can be read from and written to concurrently with (almost) no risk of data corruption, which is impossible with regular PHP file handling.

It is technically an abstraction layer on top of SQLite3, and the DB files are essentially just [SQLite3 database files](https://www.sqlite.org/fileformat2.html), so you get the [robustness](https://www.sqlite.org/testing.html) of SQLite, but don't have to actually manually create DBs or formulate complicated queries just to be able to store and retrieve information. This also means that it's easy to export the data to other DBs.

 > You can also interface with StorX DB files stored on a different machine over the network/internet. Take a look at [StorX-API](https://github.com/aaviator42/StorX-API) and [StorX-Remote](https://github.com/aaviator42/StorX-Remote).

## Usage

 > **See a simple real-world usage example in the form of a website hit counter [here](https://github.com/aaviator42/hit-counter/)!**

The easiest way to understand what this library does is to see it in action:

```php

//StorX example

<?php

//include the StorX library
require 'StorX.php';	

//create Sx 'handle' object to work with the DB file
$sx = new \StorX\Sx;

//create a DB file
$sx->createFile('testDB.dat');

//open the file for writing
$sx->openFile('testDB.dat', 1);

//write stuff to the DB file
$sx->writeKey('username', 'Aavi'); //username is now 'Aavi'

//we can modify keys too!
$sx->modifyKey('username', 'Amit'); //username is now 'Amit'

//here's how we read a key
$sx->readKey('username', $username); 
//the value of key 'username' ('Amit') has now been stored in $username
//now we can do whatever we want with it
echo "User: $username"; //prints 'User: Amit'
echo "<br>";

//there's also a function to directly return the value of a key:
echo "User: " . $sx->returnKey('username'); //prints 'User: Amit'
echo "<br>";

//here's how we check if a key exists in the DB file
if($sx->checkKey('username')){
  echo "'username' key exists in DB file!";
  echo "<br>";
}

//deleting a key
$sx->deleteKey('password');

//commit changes to DB file and close it
$sx->closeFile();

```

## Stuff you should know

 * Key names can technically be any strings, but for compatibility stick with the same naming pattern as with PHP [variables](https://www.php.net/manual/en/language.variables.basics.php):
    > A valid variable name starts with a letter or underscore, followed by any number of letters, numbers, or underscores. 
 * Changes you make using `writeKey()`, `modifyKey()`, `modifyMultipleKeys()` or `deleteKey()` are immediately reflected in subsequent `readKey()`, `readAllKeys()` or `returnKey()` function calls, but are not saved to disk until you call either `closeFile()` or `commitFile()`. 
 * Exceptions are enabled by default, this behaviour can be changed by calling `\StorX\Sx::throwExceptions()` or by changing the value of the constant `THROW_EXCEPTIONS` at the beginning of `StorX.php`.
 * By default, StorX has a busy timeout of 1.5 seconds, this can be changed by calling `\StorX\Sx::setTimeout()` or by changing the value of the constant `BUSY_TIMEOUT` at the beginning of `StorX.php`.
 * Because keyValues are serialized before storage, they can be objects of any class (or text/variables/NULL/arrays/etc).  
 * `StorXInfo` is the only reserved key name. Don't use it!


## Installation
1. Save `StorX.php` on your server. You can rename it.
2. Include the file: `require 'StorX.php';`.

## Functions
To work with a StorX DB file, we use a file handle object (sorta similar to how [fopen()](https://www.php.net/manual/en/function.fopen.php) works).

We create one like this:

```php
$sx = new \StorX\Sx;
```

We then interface with this object using the following functions.

Conditions where `e` is marked with `*` will throw an exception if exceptions are enabled.


### 1. `\StorX\Sx::throwExceptions(<bool>)`

If an error is encountered, StorX will throw an exception if set to `true`.  
The default value for this can be changed by modifying the `THROW_EXCEPTIONS` constant in `StorX.php`.

```php
$sx = new \StorX\Sx;

$sx->throwExceptions(true); //enable exceptions

```

Returns `true` if exceptions are enabled, `false` if they're not.


### 2. `\StorX\Sx::setTimeout(<milliseconds>)`

Tells SQLite how long to try for in case the DB file is locked. Learn more [here](https://www.sqlite.org/c3ref/busy_timeout.html).  
The default value for this can be changed by modifying the `BUSY_TIMEOUT` constant in `StorX.php`.


```php
$sx = new \StorX\Sx;

$sx->setTimeout(2500); //set timeout of 2.5 seconds

```

Returns the current set timeout value.


### 3. `\StorX\Sx::createFile(<filename>)`

Create a StorX DB file. 

```php
$sx = new \StorX\Sx;

if($sx->createFile('testDB.dat') === 1){
  echo 'testDB.dat created!';
} else {
  echo 'error creating testDB.dat';
}
```

returned value | e | meaning
---------------|---|-------
`0`            |*  | unable to create file
`1`            |   | file successfully created

### 4.  `\StorX\Sx::deleteFile(<filename>)`

Delete everything in a StorX DB file, then delete the file itself. Will work on all StorX DB files, regardless of DB file version. 

```php
$sx = new \StorX\Sx;

if($sx->deleteFile('testDB.dat') === 1){
  echo 'testDB.dat deleted!';
} else {
  echo 'error deleting testDB.dat';
}
```
returned value | e | meaning
---------------|---|-------
`0`            |*  | unable to delete data 
`0`            |*  | not a StorX DB file
`1`            |   | file does not exist
`1`            |   | file *successfully* deleted

### 5. `\StorX\Sx::checkFile(<username>)`

Check if a StorX DB file exists.

```php
$sx = new \StorX\Sx;

if($sx->checkFile('testDB.dat') === 1){
  echo 'testDB.dat exists as a StorX DB file!';
} else {
  echo 'testDB.dat doesn't exist as a StorX DB file!';
}
```

returned value | e | meaning
---------------|---|-------
`0`            |   | the file doesn't exist 
`1`            |   | a StorX file of the correct version (3.1) exists
`2`            |   | a StorX DB file exists, but of a different version
`3`            |   | an SQLite3 file exists but it's not a StorX DB
`4`            |   | an SQLite3 file exists but it's locked
`5`            |   | a file exists but it's not an SQLite3 file


###  6. `\StorX\Sx::openFile(filename, mode)`

Opens a StorX DB file for reading (and optionally) writing. 

* If `mode` is `1`, file is opened for reading and writing.  
* If mode is `0` or empty, file is opened for reading only.


```php
$sx = new \StorX\Sx;

if($sx->openFile('testDB.dat', 1)){
  echo 'opened testDB.dat successfully';
} else {
  echo 'error opening testDB.dat';
}
```

returned value | e | meaning
---------------|---|-------
`0`            |*  | file doesn't exist 
`0`            |*  | unable to open file 
`1`            |   | successfully opened file

###  7. `\StorX\Sx::closeFile(filename, mode)`

Closes a StorX DB file. If file was opened for writing then changes are saved before it's closed.

```php
if($sx->closeFile('testDB.dat', 1)){
  echo 'closed testDB.dat successfully';
} else {
  echo 'error closing testDB.dat';
}
```

returned value | e | meaning
---------------|---|-------
`0`            |*  | unable to save changes and close file
`1`            |   | changes have been saved and the file has been closed
`1`            |   | no file open


###  8. `\StorX\Sx::commitFile(filename, mode)`

Saves changes made to an open StorX DB file, but keeps it open.

```php
if($sx->commitFile('testDB.dat', 1)){
  echo 'saved changes made to testDB.dat successfully';
} else {
  echo 'error saving changes to testDB.dat';
}
```

returned value | e | meaning
---------------|---|-------
`0`            |*  | unable to save changes to file
`0`            |*  | no file open
`1`            |   | changes have been saved 


###  9. `\StorX\Sx::readKey(keyName, store)`

Reads a key and saves the value in `store`.

```php

$name = '';  //this statement is for readability, not strictly required
$sx->readKey('username', $name);
//value of 'username' key is now in $name

echo $name; //echo value of 'username' key
```

returned value | e | meaning
---------------|---|-------
`0`            |*  | no file open
`0`            |*  | key not found in DB file
`1`            |   | key read successfully, and value stored in `store`.



###  10. `\StorX\Sx::returnKey(keyName)`

Reads a key and returns the value.  
Use of this function is discouraged, because if exceptions are disabled and the key read fails, then detecting the failure is messy.
Use `readKey()` instead whenever possible!

```php

echo $sx->returnKey('username'); //echo value of 'username' key
```

returned value | e | meaning
---------------|---|-------
"`STORX_ERROR`"  |*  | no file open
"`STORX_ERROR`"  |*  | key not found in DB file


###  11. `\StorX\Sx::readAllKeys(store)`

Reads all keys and saves them as an associative array in `store`.

The array is structured as:

```php
array(
 key1 => val1,
 key2 => val2,
 key3 => val3
 ...
)
```

Usage:

```php

$sx->readAllKeys($keyArray);
//all keys are now in $keyArray

echo $keyArray['username']; //echo value of 'username' key
```

returned value | e | meaning
---------------|---|-------
`0`            |*  | no file open
`0`            |*  | unable to read keys from DB file
`1`            |   | keys read successfully, and stored in `store`.




###  12. `\StorX\Sx::writeKey(keyName, keyValue)`

Writes the key along with the value to the open DB file. The value can be text, a variable, an array, NULL, or an object of any class. Will fail if a key with the same keyName already exists.

```php

$sx->writeKey('username', 'aavi001'); 

$array = array("foo", "bar", "hello", "world"); //array
$sx->writeKey('words', $array);

$rt = new RT(); //object of a class
$sx->writeKey('RTobj', $rt);
```


returned value | e | meaning
---------------|---|-------
`0`            |*  | no file open
`0`            |*  | file not locked (not opened for writing)
`0`            |*  | another key with the same keyName already exists
`0`            |*  | unable to write key
`1`            |   | key written successfully



###  13. `\StorX\Sx::modifyKey(keyName, keyValue)`

Modifies a key's value in the open DB file. If the key does not exist in the file then it is created. 

Like with `writeKey()`, the value can be text, a variable, an array, NULL, or an object of any class.

```php

$sx->modifyKey('username', 'amit009'); 

```


returned value | e | meaning
---------------|---|-------
`0`            |*  | no file open
`0`            |*  | file not locked (not opened for writing)
`0`            |*  | unable to modify key
`0`            |*  | unable to write key
`1`            |   | key value modified successfully



###  14. `\StorX\Sx::modifyMultipleKeys(keyArray)`

Reads multiple keys from an array and modifies them in the open DB file. 

`keyArray` should be an associative array like such:

```php
array(
 key1 => val1,
 key2 => val2,
 key3 => val3
 ...
)
```

If a key does not exist in the file then it is created. 

As with `modifyKey()` and `writeKey()`, the value can be text, a variable, an array, NULL, or an object of any class.

```php

$array1 = array("v1" => 100, "v2" => 200, "v3" => "300");

$sx->modifyMultipleKeys($array1); 

```


returned value | e | meaning
---------------|---|-------
`0`            |*  | no file open
`0`            |*  | file not locked (not opened for writing)
`0`            |*  | unable to modify key
`0`            |*  | unable to write key
`1`            |   | key values modified successfully



###  14. `\StorX\Sx::checkKey(keyName)`

Checks if a key exists in the open DB file.

```php

if($sx->checkKey('username')){
  echo 'username key exists in file!';
} else {
  echo 'username key doesn't exist in file!';
}

```


returned value | e | meaning
---------------|---|-------
`0`            |*  | no file open
`0`            |   | key doesn't exist in DB file
`1`            |   | key exists in DB file




###  15. `\StorX\Sx::deleteKey(keyName)`

Deletes a key from the open DB file. 


```php

if($sx->deleteKey('username')){
  echo 'username key deleted from file!';
} else {
  echo 'username key can't be deleted from file!';
}

```


returned value | e | meaning
---------------|---|-------
`0`            |*  | no file open
`0`            |*  | file not locked (not opened for writing)
`0`            |*  | unable to delete key
`1`            |   | key deleted successfully

## Exception Codes

If exceptions are enabled, the thrown exceptions can have these exception codes:


Code |  Meaning
-----|--------
101 | File does not exist
102 | No file open
103 | File is not locked for writing (but should be)
104 | File is locked (but shouldn't be)
105 | File not of matching StorX version
106 | Unable to create file 
107 | Unable to delete file 
108 | Unable to open file 
109 | Unable to commit changes to file
-|-
201 | Key doesn't exist 	
202 | Key already exists 	
203 | Unable to read key(s)
204 | Unable to write/modify key(s)
205 | Unable to delete key 
-|-
300 | SQLite3 error 



## Requirements
1. [Supported versions of PHP](https://www.php.net/supported-versions.php). At the time of writing, that's PHP `7.3+`. StorX will almost certainly work on older versions, but we don't test it on those, so be careful, do your own testing.
2. PHP's `sqlite3` extension. Almost always enabled by default.


## Keys and DB files
Keys are [serialized](https://www.php.net/manual/en/function.serialize.php) and then stored in an [SQLite3 database file](https://www.sqlite.org/fileformat2.html).

Because these are just regular SQLite3 DB files, you can access them using any software or library that supports the format.

As of StorX DB file version 3.1, the DB file contains a single table, `main`:

```
+------------------------+
| keyName     | keyValue |
+-------------|----------+
| StorXInfo   | v3.1     |
|             |          |
| key1        | val1     |
| key2        | val2     |
| key3        | val3     |
| ...         | ...      |
|             |          |
+-------------|----------+
```

Key names are stored in the column `keyName` as base64-encoded strings, and the corresponding data is stored in the column `keyValue` as strings in the PHP serialized format: `base64_encode(serialize(<data>))`.



-----
Documentation updated `2022-02-22`
