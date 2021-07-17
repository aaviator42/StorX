# StorX
Simple PHP flat-file data storage library

Current library version: `3.1`  
Current DB file version: `3.0`

License: `AGPLv3`

## About 

StorX is a tiny PHP library that allows you to store data (objects) in flat files as "keys", which you can read and modify later.  

It was initially developed primarily to facilitate sharing of data between independent PHP scripts and sessions, but can be used in any context where you want to easily write/read data to/from files, but don't want to deal with the complexities of SQL/SQLite.

It is technically an abstraction layer on top of SQLite3, and the DB files are essentially just [SQLite3 database files](https://www.sqlite.org/fileformat2.html), so you get the robustness of SQLite, but don't have to actually manually make DBs or formulate complicated queries just to be able to store and retireve information. This also means that it's really easy to export the data to other DBs.

## Example usage

The easiest way to undestand what this does is to see an example of it in action:

```php

//StorX example

<?php

//include the StorX library
require 'StorX.php';	

//create a DB file
\StorX\createFile('testDB.dat');

//create Sx object to work with the DB file
$sx = new \Storx\Sx;

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

//commit hanges to DB file and close it
$sx->closeFile();

```

## Functions

Conditions where `e` is marked with `*` will throw an exception if exceptions are enabled.

### _File functions_

#### 1. `\StorX\createFile(<filename>)`

Create a StorX DB file. 

```php
if(\StorX\createFile('testDB.dat') === 1){
  echo 'testDB.dat created!';
} else {
  echo 'error creating testDB.dat';
}
```

returned value | e | meaning
---------------|---|-------
`0`            |*  | file doesn't exist and we're unable to create it
`1`            |   | file *successfully* created
`2`            |   | StorX DB file already exists of same version
`3`            |   | StorX DB file already exists, but of different version
`4`            |   | SQLite3 file already exists but not a StorX DB
`5`            |   | file already exists but it's not an SQLite3 file

#### 2.  `\StorX\deleteFile(<filename>)`

Delete everything in a StorX DB file, then delete the file itself. Will work on all StorX DB files, regardless of file version. 

```php
if(\StorX\deleteFile('testDB.dat') === 1){
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

#### 3. `\StorX\checkFile(<username>)`

Check if a StorX DB file exists.


```php
if(\StorX\checkFile('testDB.dat') === 1){
  echo 'testDB.dat exists as a StorX DB file!';
} else {
  echo 'testDB.dat doesn't exist as a StorX DB file!';
}
```

returned value | e | meaning
---------------|---|-------
`0`            |   | file doesn't exist 
`1`            |   | StorX file of the correct version (3.0) exists
`3`            |   | StorX DB file exists, but of different version
`4`            |   | SQLite3 file exists but not a StorX DB
`5`            |   | file exists but it's not an SQLite3 file


----

### _Handler object functions_

####  1. `\StorX\Sx::openFile(filename, mode)`

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
`0`            |   | file doesn't exist 
`0`            |   | file doesn't exist 
`0`            |   | file doesn't exist 


## Requirements
1. [Supported versions of PHP](https://www.php.net/supported-versions.php). At the time of writing, that's PHP `7.3+`. StorX will almost certainly work on older versions, but we don't test it on those, so be careful.
2. PHP's `sqlite3` extension. Almost always enabled by default.


## Installation
1. Save `StorX.php` on your server. You can rename it.
2. Include the file: `require StorX.php;`.





## Keys and DB files
Keys are [serialized](https://www.php.net/manual/en/function.serialize.php) and then stored in an [SQLite3 database file](https://www.sqlite.org/fileformat2.html).

Because these are just regular files, you can access them using any software or library that supports SQLite3 DB files.

As of StorX DB file version 3.0, the DB file contains a single table:

```
+------------------------+
| keyName     | keyValue |
+-------------|----------+
| StorXInfo   | v3.0     |
|             |          |
| key1        | val1     |
| key2        | val2     |
| key3        | val3     |
| ...         | ...      |
|             |          |
+-------------|----------+
```

Key names are stored in the column `keyName` as strings, and the corresponding data is stored in the column `keyValue` in the PHP serialized format.

------

Documentation incomplete, will be updated soon. 
