<?php
/*
StorX Test File
v5.0, 2024-12-27
AGPLv4, @aaviator42
*/

header('Content-Type: text/plain');

require 'StorX.php'; // Make sure to include your StorX library

use StorX\Sx;

// Initialize Sx object for use by tests
$sx = new Sx();
$sx->throwExceptions(false);

// Pre-test cleanup
// Delete 'testdb.db' if it already exists
$sx->deleteFile('testdb.db');

// Begin tests
echo "Beginning StorX Tests..." . PHP_EOL;
echo "Test file v5.0, 2024-12-27 \n\n";

// Track if any tests fail
$testsPassed = true;

// Test constants
echo "Testing constants:\n";
echo " THROW_EXCEPTIONS: " . ($sx->throwExceptions() ? "true" : "false") . PHP_EOL;
echo " BUSY_TIMEOUT: " . $sx->setTimeout() . " ms" . PHP_EOL;
echo PHP_EOL;

// Test 1: createFile() 1
$filename = 'testdb.db';
$result = $sx->createFile($filename);
echo "Test 1:     Creating DB file testdb.db" . PHP_EOL;
echo "Function:   createFile('$filename') " . PHP_EOL;
echo "Expecting:  1" . PHP_EOL;
echo "Result:     ";
if ( $result === 1 && file_exists('testdb.db')){
	echo "$result (OK)";
} else {
	echo "$result (ERROR)";
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 2: createFile() 2
$filename = 'testdb.db';
$result = $sx->createFile($filename);
echo "Test 2:     Creating DB file testdb.db again" . PHP_EOL;
echo "Function:   createFile('$filename') " . PHP_EOL;
echo "Expecting:  0" . PHP_EOL;
echo "Result:     ";
if ( $result === 0 ){
	echo "$result (OK)";
} else {
	echo "$result (ERROR)";
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 3: checkFile() 1
$filename = 'testdb.db';
$result = $sx->checkFile($filename);
echo "Test 3:     Check DB file testdb.db" . PHP_EOL;
echo "Function:   checkFile('$filename') " . PHP_EOL;
echo "Expecting:  1" . PHP_EOL;
echo "Result:     ";
if ( $result === 1 ){
	echo "$result (OK)";
} else {
	echo "$result (ERROR)";
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 4: checkFile() 2
$filename = 'testdb2.db';
$result = $sx->checkFile($filename);
echo "Test 4:     Check non-existent DB file testdb2.db" . PHP_EOL;
echo "Function:   checkFile('$filename') " . PHP_EOL;
echo "Expecting:  0" . PHP_EOL;
echo "Result:     ";
if ( $result === 0 ){
	echo "$result (OK)";
} else {
	echo "$result (ERROR)";
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 5: checkFile() 3
$filename = 'testdb2.db';
file_put_contents($filename, "0000000000");
$result = $sx->checkFile($filename);
unlink($filename);
echo "Test 5:     Check invalid DB file testdb2.db" . PHP_EOL;
echo "Function:   checkFile('$filename') " . PHP_EOL;
echo "Expecting:  5" . PHP_EOL;
echo "Result:     ";
if ( $result === 5 ){
	echo "$result (OK)";
} else {
	echo "$result (ERROR)";
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 6: deleteFile() 1
$filename = 'testdb.db';
$result = $sx->deleteFile($filename);
echo "Test 6:     Delete DB file testdb.db" . PHP_EOL;
echo "Function:   deleteFile('$filename') " . PHP_EOL;
echo "Expecting:  1" . PHP_EOL;
echo "Result:     ";
if ( $result === 1 && !file_exists('testdb.db')){
	echo "$result (OK)";
} else {
	echo "$result (ERROR)";
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 7: deleteFile() 2
$filename = 'testdb.db';
$result = $sx->deleteFile($filename);
echo "Test 7:     Delete non-existent DB file testdb.db" . PHP_EOL;
echo "Function:   deleteFile('$filename') " . PHP_EOL;
echo "Expecting:  1" . PHP_EOL;
echo "Result:     ";
if ( $result === 1 ){
	echo "$result (OK)";
} else {
	echo "$result (ERROR)";
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 8: openFile() 1
$filename = 'testdb.db';
// $sx->createFile($filename);
$result = $sx->openFile($filename);
echo "Test 8:     Open non-existent DB file testdb.db" . PHP_EOL;
echo "Function:   openFile('$filename') " . PHP_EOL;
echo "Expecting:  0" . PHP_EOL;
echo "Result:     ";
if ( $result === 0 ){
	echo "$result (OK)";
} else {
	echo "$result (ERROR)";
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 9: write and read key
$filename = 'testdb.db';
$test_value = "test_000";
$sx->createFile($filename);
$sx->openFile($filename, 1);
$sx->writeKey('test_key', $test_value);
$sx->closeFile();
$sx->openFile($filename);
$read_value = $sx->returnKey('test_key');
$sx->closeFile();
echo "Test 9:     Write and read key to/from DB file testdb.db" . PHP_EOL;
echo "Functions:  createFile(), openFile(), writeKey(), closeFile(), returnKey(), closeFile() " . PHP_EOL;
echo "Expecting:  To write data to file and read it back" . PHP_EOL;
echo "Result:     ";
if ( $test_value === $read_value ){
	echo "Test data successfully written to and read from file (OK)" . PHP_EOL;
	echo "Data written: " . var_export($test_value, true) . PHP_EOL;
	echo "Data read:    " . var_export($read_value, true);
} else {
	echo "Test data written to file doesn't match data read from file (ERROR)" . PHP_EOL;
	echo "Data written: " . var_export($test_value, true) . PHP_EOL;
	echo "Data read:    " . var_export($read_value, true);	
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 10: deleteKey() 1
$filename = 'testdb.db';
$sx->openFile($filename, 1);
$result = $sx->deleteKey('test_key');
$sx->commitFile();
$sx->openFile($filename, 1);
$read_value = $sx->returnKey('test_key');
$sx->closeFile();
echo "Test 10:    Delete key from DB file testdb.db" . PHP_EOL;
echo "Functions:  openFile(), deleteKey(), commitFile(), returnKey(), closeFile() " . PHP_EOL;
echo "Expecting:  To delete data from file" . PHP_EOL;
echo "Result:     ";
if ($result == 1 && $read_value === "STORX_ERROR"){
	echo "Key successfully deleted from file (OK)";
} else {
	echo "Unable to delete key from file (ERROR)" . PHP_EOL;
	echo "Data read:    " . var_export($read_value, true);	
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 11: deleteKey() 2
$filename = 'testdb.db';
$sx->openFile($filename, 1);
$result = $sx->deleteKey('test_key');
$sx->closeFile();
echo "Test 11:    Delete non-existent key from DB file testdb.db" . PHP_EOL;
echo "Function:   deleteKey()" . PHP_EOL;
echo "Expecting:  1" . PHP_EOL;
echo "Result:     ";
if ($result == 1){
	echo "$result (OK)";
} else {
	echo "$result (ERROR)";
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 12: deleteKey() 3
$filename = 'testdb.db';
$sx->openFile($filename, 1);
$sx->writeKey('test_key', 'test_value_0000');
$sx->closeFile();
$sx->openFile($filename, 0); // open for readsonly
$result = $sx->deleteKey('test_key');
$sx->closeFile();
echo "Test 12:    Delete key from DB file testdb.db without opening it for writing" . PHP_EOL;
echo "Function:   deleteKey()" . PHP_EOL;
echo "Expecting:  0" . PHP_EOL;
echo "Result:     ";
if ($result == 0){
	echo "$result (OK)";
} else {
	echo "$result (ERROR)";
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 13: checkKey() 1
$filename = 'testdb.db';
$sx->openFile($filename, 1);
$sx->modifyKey("test_key", "test_value_0000");
$result = $sx->checkKey("test_key");
$sx->closeFile();
echo "Test 13:    Check key in DB file testdb.db" . PHP_EOL;
echo "Function:   checkKey()" . PHP_EOL;
echo "Expecting:  1" . PHP_EOL;
echo "Result:     ";
if ($result == 1){
	echo "$result (OK)";
} else {
	echo "$result (ERROR)";
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 14: checkKey() 2
$filename = 'testdb.db';
$sx->openFile($filename, 1);
$result = $sx->checkKey("test_key_2");
$sx->closeFile();
echo "Test 14:    Check non-existent key in DB file testdb.db" . PHP_EOL;
echo "Function:   checkKey()" . PHP_EOL;
echo "Expecting:  0" . PHP_EOL;
echo "Result:     ";
if ($result == 0){
	echo "$result (OK)";
} else {
	echo "$result (ERROR)";
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 15: modifyKey() 1
$filename = 'testdb.db';
$test_value = 'test_value_0001';
$sx->openFile($filename, 1);
$result = $sx->modifyKey("test_key", $test_value);
$sx->readKey("test_key", $read_value);
$sx->closeFile();
echo "Test 15:    Modify key in DB file testdb.db" . PHP_EOL;
echo "Function:   modifyKey()" . PHP_EOL;
echo "Expecting:  1" . PHP_EOL;
echo "Result:     ";
if ($result === 1 && $read_value === $test_value){
	echo "$result (OK)";
} else {
	echo "$result (ERROR)" . PHP_EOL;
	echo "Data read:    " . var_export($read_value, true);	
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 16: writeKey() 2
$filename = 'testdb.db';
$key_name = 'INV@LID';
$sx->openFile($filename, 1);
$sx->deleteKey('test_key');
$result = $sx->writeKey($key_name, 'test_value_0001');
$sx->closeFile();
echo "Test 16:    Write key with invalid name to DB file testdb.db" . PHP_EOL;
echo "Function:   writeKey()" . PHP_EOL;
echo "Expecting:  0" . PHP_EOL;
echo "Result:     ";
if ($result === 0){
	echo "$result (OK)";
} else {
	echo "$result (ERROR)" . PHP_EOL;
	echo "Data written:    " . var_export($sx->returnKey($key_name), true);	
	$testsPassed = false;
}
echo PHP_EOL . PHP_EOL;

// Test 17: modify multiple keys, read them and ensure data matches
$filename = 'testdb.db';
$keys_write = [
	'key_1' => [1, 2, 3],
	'key_2' => ["abc", "def"],
	'key_3' => 500,
	'key_4' => NULL,
	'key_5' => [123, 50.5, "abc", NULL, true, false],
	'key_6' => [-101.1, -60, 0, 60, 101.1],
	'key_7' => ["ping" => "pong", "foo" => "bar"],
	'key_8' => [[0, 1, 2, 3], ['a', 'b', 'c', 'd'], [true, false, null]]
];
$sx->openFile($filename, 1);
$sx->modifyMultipleKeys($keys_write);
$sx->readAllKeys($keys_read);

$result = (md5(var_export($keys_write, true)) === md5(var_export($keys_read, true)));
$sx->closeFile();
$sx->deleteFile($filename);
echo "Test 17:    Modify/read multiple keys to/from DB file testdb.db" . PHP_EOL;
echo "Function:   modifyMultipleKeys(), readAllKeys()" . PHP_EOL;
echo "Expecting:  Keys written to file to be identical to keys read from file" . PHP_EOL;
echo "Result:     ";
if ($result === true){
	echo "written_data === read_data (OK)";
} else {
	echo "($result) written_data !== read_data (ERROR)" . PHP_EOL;
	echo "Data written: " . PHP_EOL . (var_export($keys_write, true)) . PHP_EOL;
	echo "Data read:    " . PHP_EOL . (var_export($keys_read, true));
	$testsPassed = false;	
}
echo PHP_EOL . PHP_EOL;

echo "Tests completed!" . PHP_EOL;
if ($testsPassed === true){
	echo "FINAL RESULTS: ALL TESTS PASSED!!!";
} else {
	echo "FINAL RESULTS: ALL TESTS *DID NOT* PASS!!!" . PHP_EOL;
	echo "See errors above." . PHP_EOL;
}

?>
