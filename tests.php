<?php
/*
StorX Complete Test Suite
v5.3, 2026-02-13
AGPLv3, @aaviator42

Complete test suite for StorX library covering:
- File operations (create, check, delete, open, close, copy)
- Key operations (write, read, modify, delete, check)
- Configuration (exceptions, timeout)
- Data integrity (types, large data, edge cases)
- Concurrency (file locking)
- Performance and stress testing
- Exception code verification

Contains 58 comprehensive tests:
 - Tests 1-34: Return-value behavior
 - Tests 35-58: Exception code verification
*/

header('Content-Type: text/plain');

require 'StorX.php';

use StorX\Sx;

// Test configuration
$testDbFile = 'testdb.db';
$performanceIterations = 1000;
$stressTestKeys = 100;
$largeDataSize = 1024 * 1024; // 1MB

// Initialize test counters
$totalTests = 0;
$passedTests = 0;
$testsPassed = true;
$startTime = microtime(true);

// Helper function for standard test output
function runTest($testNumber, $description, $function, $expecting, $callback) {
    global $totalTests, $passedTests, $testsPassed;
    $totalTests++;

    echo "Test $testNumber:    $description" . PHP_EOL;
    echo "Function:   $function" . PHP_EOL;
    echo "Expecting:  $expecting" . PHP_EOL;
    echo "Result:     ";

    $result = $callback();
    if ($result['success']) {
        echo $result['message'] . " (OK)";
        $passedTests++;
    } else {
        echo $result['message'] . " (ERROR)";
        $testsPassed = false;
    }

    if (isset($result['details'])) {
        echo PHP_EOL . $result['details'];
    }

    echo PHP_EOL . PHP_EOL;
}

// Helper function for exception tests
function runExceptionTest($testNumber, $description, $expectedCode, $callback) {
    global $totalTests, $passedTests, $testsPassed;
    $totalTests++;

    echo "Test $testNumber:    $description" . PHP_EOL;
    echo "Expected:   Exception code $expectedCode" . PHP_EOL;
    echo "Result:     ";

    $result = $callback();

    if ($result['success']) {
        echo "Code {$result['code']} thrown (OK)";
        $passedTests++;
    } else {
        // Handle case where wrong exception code was thrown
        if (isset($result['code'])) {
            echo "Wrong code {$result['code']} thrown, expected $expectedCode (ERROR)";
        } else {
            echo $result['message'] . " (ERROR)";
        }
        $testsPassed = false;
    }

    echo PHP_EOL . PHP_EOL;
}

// Pre-test cleanup
if (file_exists($testDbFile)) {
    unlink($testDbFile);
}

// Clean up any leftover test files
foreach (glob('test_*.db') as $file) {
    unlink($file);
}
foreach (glob('stress_*.db') as $file) {
    unlink($file);
}
foreach (glob('test_exc_*.db') as $file) {
    unlink($file);
}

echo "Beginning StorX Tests..." . PHP_EOL;
echo "Test file v5.3, 2026-02-24" . PHP_EOL;
echo "Total planned tests: 58" . PHP_EOL;
echo PHP_EOL;

// Initialize Sx objects for different configurations
$sx = new Sx();
$sx->throwExceptions(false);

$sxThrow = new Sx();
$sxThrow->throwExceptions(true);

echo "Configuration:" . PHP_EOL;
echo " THROW_EXCEPTIONS: " . ($sx->throwExceptions() ? "true" : "false") . PHP_EOL;
echo " BUSY_TIMEOUT: " . $sx->setTimeout() . " ms" . PHP_EOL;
echo PHP_EOL;

// ============================================================================
echo "=== SECTION 1: FILE OPERATIONS ===" . PHP_EOL;
echo PHP_EOL;
// ============================================================================

// Test 1: createFile() success
runTest(1, "Creating DB file", "createFile('$testDbFile')", "1", function() use ($sx, $testDbFile) {
    $result = $sx->createFile($testDbFile);
    $success = ($result === 1 && file_exists($testDbFile));
    return ['success' => $success, 'message' => $result];
});

// Test 2: createFile() duplicate
runTest(2, "Creating DB file that already exists", "createFile('$testDbFile')", "0", function() use ($sx, $testDbFile) {
    $result = $sx->createFile($testDbFile);
    return ['success' => $result === 0, 'message' => $result];
});

// Test 3: checkFile() valid
runTest(3, "Check valid DB file", "checkFile('$testDbFile')", "1", function() use ($sx, $testDbFile) {
    $result = $sx->checkFile($testDbFile);
    return ['success' => $result === 1, 'message' => $result];
});

// Test 4: checkFile() non-existent
runTest(4, "Check non-existent DB file", "checkFile('testdb2.db')", "0", function() use ($sx) {
    $result = $sx->checkFile('testdb2.db');
    return ['success' => $result === 0, 'message' => $result];
});

// Test 5: checkFile() invalid format
runTest(5, "Check invalid format file", "checkFile('testdb2.db')", "5", function() use ($sx) {
    file_put_contents('testdb2.db', "0000000000");
    $result = $sx->checkFile('testdb2.db');
    unlink('testdb2.db');
    return ['success' => $result === 5, 'message' => $result];
});

// Test 6: deleteFile() success
runTest(6, "Delete DB file", "deleteFile('$testDbFile')", "1", function() use ($sx, $testDbFile) {
    $result = $sx->deleteFile($testDbFile);
    $success = ($result === 1 && !file_exists($testDbFile));
    return ['success' => $success, 'message' => $result];
});

// Test 7: deleteFile() non-existent
runTest(7, "Delete non-existent DB file", "deleteFile('$testDbFile')", "1", function() use ($sx, $testDbFile) {
    $result = $sx->deleteFile($testDbFile);
    return ['success' => $result === 1, 'message' => $result];
});

// Test 8: openFile() non-existent
runTest(8, "Open non-existent DB file", "openFile('$testDbFile')", "0", function() use ($sx, $testDbFile) {
    $result = $sx->openFile($testDbFile);
    return ['success' => $result === 0, 'message' => $result];
});

// Test 9: copyFile() success with data integrity
runTest(9, "Copy DB file with data", "copyFile()", "successful copy with data integrity", function() use ($sx) {
    $sourceFile = 'test_copy_source.db';
    $destFile = 'test_copy_dest.db';

    // Create source file with test data
    $sx->createFile($sourceFile);
    $sx->openFile($sourceFile, 1);
    $sx->modifyKey('testKey1', 'testValue1');
    $sx->modifyKey('testKey2', ['array', 'data', 123]);
    $sx->modifyKey('testKey3', null);
    $sx->closeFile();

    // Copy the file
    $result = $sx->copyFile($sourceFile, $destFile);

    if ($result !== 1) {
        $sx->deleteFile($sourceFile);
        return ['success' => false, 'message' => "copyFile returned $result"];
    }

    // Verify destination file exists and has correct data
    $sx->openFile($destFile, 0);
    $val1 = $sx->returnKey('testKey1');
    $val2 = $sx->returnKey('testKey2');
    $val3 = $sx->returnKey('testKey3');
    $sx->closeFile();

    // Clean up
    $sx->deleteFile($sourceFile);
    $sx->deleteFile($destFile);

    $success = ($val1 === 'testValue1' &&
                $val2 === ['array', 'data', 123] &&
                $val3 === null);

    return [
        'success' => $success,
        'message' => $success ? "File copied with data integrity preserved" : "Data mismatch after copy"
    ];
});

// Test 10: copyFile() source doesn't exist
runTest(10, "Copy non-existent source file", "copyFile()", "0", function() use ($sx) {
    $result = $sx->copyFile('nonexistent_source.db', 'test_dest.db');
    return ['success' => $result === 0, 'message' => $result];
});

// Test 11: copyFile() destination already exists
runTest(11, "Copy to existing destination", "copyFile()", "0", function() use ($sx) {
    $sourceFile = 'test_copy_src.db';
    $destFile = 'test_copy_dst.db';

    // Create both files
    $sx->createFile($sourceFile);
    $sx->createFile($destFile);

    // Try to copy - should fail
    $result = $sx->copyFile($sourceFile, $destFile);

    // Clean up
    $sx->deleteFile($sourceFile);
    $sx->deleteFile($destFile);

    return ['success' => $result === 0, 'message' => $result];
});

// Test 12: copyFile() invalid source file
runTest(12, "Copy invalid source file", "copyFile()", "0", function() use ($sx) {
    $sourceFile = 'test_invalid_source.db';
    $destFile = 'test_copy_dest.db';

    // Create an invalid file (not a StorX DB)
    file_put_contents($sourceFile, 'not a valid database');

    $result = $sx->copyFile($sourceFile, $destFile);

    // Clean up
    unlink($sourceFile);
    if (file_exists($destFile)) unlink($destFile);

    return ['success' => $result === 0, 'message' => $result];
});

// Test 13: openFile() when file already open
runTest(13, "openFile() when file already open", "openFile()", "0 (reject second open)", function() use ($sx) {
    $filename = 'test_double_open.db';

    $sx->createFile($filename);
    $result1 = $sx->openFile($filename, 1); // First open - should succeed

    // Try to open another file while first is still open
    $result2 = $sx->openFile($filename, 0); // Second open - should fail

    $sx->closeFile();
    $sx->deleteFile($filename);

    $success = ($result1 === 1 && $result2 === 0);
    return [
        'success' => $success,
        'message' => $success ? "Second openFile correctly rejected" : "First: $result1, Second: $result2",
        'details' => "First open returned: $result1, Second open returned: $result2"
    ];
});

// Test 14: closeFile() when no file open
runTest(14, "closeFile() when no file open", "closeFile()", "1 (idempotent)", function() {
    $sx2 = new Sx();
    $sx2->throwExceptions(false);

    // Call closeFile on fresh object with no file open
    $result = $sx2->closeFile();

    return [
        'success' => $result === 1,
        'message' => $result === 1 ? "closeFile correctly returns 1 when no file open" : "Unexpected return: $result"
    ];
});

// Test 15: commitFile() persists data while keeping file open
runTest(15, "commitFile() persists data", "commitFile()", "data visible to other readers", function() use ($sx) {
    $filename = 'test_commit.db';
    $sx2 = new Sx();
    $sx2->throwExceptions(false);

    $sx->createFile($filename);
    $sx->openFile($filename, 1);
    $sx->writeKey('commit_test', 'value_before_commit');
    $commitResult = $sx->commitFile();

    // Without closing, open with another object and verify data is persisted
    $sx2->openFile($filename, 0); // readonly
    $readValue = $sx2->returnKey('commit_test');
    $sx2->closeFile();

    // Now close the original
    $sx->closeFile();
    $sx->deleteFile($filename);

    $success = ($commitResult === 1 && $readValue === 'value_before_commit');
    return [
        'success' => $success,
        'message' => $success ? "commitFile persists data while keeping file open" : "commitResult: $commitResult, readValue: " . var_export($readValue, true),
        'details' => "Wrote and committed with \$sx, read with \$sx2 before \$sx closed"
    ];
});

// ============================================================================
echo "=== SECTION 2: KEY OPERATIONS ===" . PHP_EOL;
echo PHP_EOL;
// ============================================================================

// Recreate test file for key operation tests
$sx->createFile($testDbFile);

// Test 16: write and read key
runTest(16, "Write and read key", "writeKey(), returnKey()", "successful read/write", function() use ($sx, $testDbFile) {
    $testValue = "test_000";

    $sx->openFile($testDbFile, 1);
    $sx->writeKey('test_key', $testValue);
    $sx->closeFile();

    $sx->openFile($testDbFile);
    $readValue = $sx->returnKey('test_key');
    $sx->closeFile();

    $success = ($testValue === $readValue);
    $details = "Data written: " . var_export($testValue, true) . PHP_EOL . "Data read:    " . var_export($readValue, true);

    return [
        'success' => $success,
        'message' => $success ? "Test data successfully written to and read from file" : "Data mismatch",
        'details' => $details
    ];
});

// Test 17: deleteKey()
runTest(17, "Delete key from DB file", "deleteKey(), returnKey()", "key deletion", function() use ($sx, $testDbFile) {
    $sx->openFile($testDbFile, 1);
    $result = $sx->deleteKey('test_key');
    $sx->closeFile();

    $sx->openFile($testDbFile);
    $readValue = $sx->returnKey('test_key');
    $sx->closeFile();

    $success = ($result == 1 && $readValue === "STORX_ERROR");
    return [
        'success' => $success,
        'message' => $success ? "Key successfully deleted from file" : "Unable to delete key from file"
    ];
});

// Test 18: deleteKey() non-existent
runTest(18, "Delete non-existent key", "deleteKey()", "1", function() use ($sx, $testDbFile) {
    $sx->openFile($testDbFile, 1);
    $result = $sx->deleteKey('test_key');
    $sx->closeFile();
    return ['success' => $result == 1, 'message' => $result];
});

// Test 19: deleteKey() without write access
runTest(19, "Delete key without write access", "deleteKey()", "0", function() use ($sx, $testDbFile) {
    $sx->openFile($testDbFile, 1);
    $sx->writeKey('test_key', 'test_value_0000');
    $sx->closeFile();
    $sx->openFile($testDbFile, 0); // readonly
    $result = $sx->deleteKey('test_key');
    $sx->closeFile();
    return ['success' => $result == 0, 'message' => $result];
});

// Test 20: checkKey() exists
runTest(20, "Check existing key", "checkKey()", "1", function() use ($sx, $testDbFile) {
    $sx->openFile($testDbFile, 1);
    $sx->modifyKey("test_key", "test_value_0000");
    $result = $sx->checkKey("test_key");
    $sx->closeFile();
    return ['success' => $result == 1, 'message' => $result];
});

// Test 21: checkKey() non-existent
runTest(21, "Check non-existent key", "checkKey()", "0", function() use ($sx, $testDbFile) {
    $sx->openFile($testDbFile, 1);
    $result = $sx->checkKey("test_key_2");
    $sx->closeFile();
    return ['success' => $result == 0, 'message' => $result];
});

// Test 22: modifyKey()
runTest(22, "Modify key", "modifyKey()", "1", function() use ($sx, $testDbFile) {
    $testValue = 'test_value_0001';
    $sx->openFile($testDbFile, 1);
    $result = $sx->modifyKey("test_key", $testValue);
    $sx->readKey("test_key", $readValue);
    $sx->closeFile();

    $success = ($result === 1 && $readValue === $testValue);
    return ['success' => $success, 'message' => $result];
});

// Test 23: writeKey() invalid name
runTest(23, "Write key with invalid name", "writeKey()", "0", function() use ($sx, $testDbFile) {
    $keyName = 'INV@LID';
    $sx->openFile($testDbFile, 1);
    $sx->deleteKey('test_key');
    $result = $sx->writeKey($keyName, 'test_value_0001');
    $sx->closeFile();
    return ['success' => $result === 0, 'message' => $result];
});

// Test 24: modifyMultipleKeys() and readAllKeys()
runTest(24, "Modify/read multiple keys", "modifyMultipleKeys(), readAllKeys()", "data integrity", function() use ($sx, $testDbFile) {
    $keysWrite = [
        'key_1' => [1, 2, 3],
        'key_2' => ["abc", "def"],
        'key_3' => 500,
        'key_4' => NULL,
        'key_5' => [123, 50.5, "abc", NULL, true, false],
        'key_6' => [-101.1, -60, 0, 60, 101.1],
        'key_7' => ["ping" => "pong", "foo" => "bar"],
        'key_8' => [[0, 1, 2, 3], ['a', 'b', 'c', 'd'], [true, false, null]]
    ];

    $sx->openFile($testDbFile, 1);
    $sx->modifyMultipleKeys($keysWrite);
    $sx->readAllKeys($keysRead);
    $sx->closeFile();

    $result = ($keysWrite === $keysRead);
    return [
        'success' => $result,
        'message' => $result ? "written_data === read_data" : "data mismatch"
    ];
});

// ============================================================================
echo "=== SECTION 3: CONFIGURATION ===" . PHP_EOL;
echo PHP_EOL;
// ============================================================================

// Test 25: Exception handling mode
runTest(25, "Exception handling mode", "throwExceptions(true)", "exceptions thrown", function() use ($sxThrow) {
    $exceptionCaught = false;
    try {
        $sxThrow->openFile('nonexistent.db');
    } catch (Exception $e) {
        $exceptionCaught = true;
    }
    return [
        'success' => $exceptionCaught,
        'message' => $exceptionCaught ? "Exception properly thrown" : "No exception thrown"
    ];
});

// Test 26: Timeout configuration
runTest(26, "Timeout configuration", "setTimeout()", "configurable timeout", function() use ($sx) {
    $originalTimeout = $sx->setTimeout();
    $newTimeout = 3000;
    $sx->setTimeout($newTimeout);
    $currentTimeout = $sx->setTimeout();
    $sx->setTimeout($originalTimeout); // restore

    $success = ($currentTimeout === $newTimeout);
    return [
        'success' => $success,
        'message' => "Timeout set to $currentTimeout ms",
        'details' => "Original: $originalTimeout ms, Set: $newTimeout ms, Current: $currentTimeout ms"
    ];
});

// ============================================================================
echo "=== SECTION 4: DATA INTEGRITY ===" . PHP_EOL;
echo PHP_EOL;
// ============================================================================

// Test 27: Large data storage
runTest(27, "Large data storage (1MB)", "writeKey(), returnKey()", "large data handling", function() use ($sx) {
    global $largeDataSize;

    $largeData = str_repeat('A', $largeDataSize);
    $filename = 'test_large.db';

    $sx->createFile($filename);
    $sx->openFile($filename, 1);
    $writeResult = $sx->writeKey('large_key', $largeData);
    $sx->closeFile();

    if ($writeResult !== 1) {
        unlink($filename);
        return ['success' => false, 'message' => "Failed to write large data"];
    }

    $sx->openFile($filename);
    $readData = $sx->returnKey('large_key');
    $sx->closeFile();

    $success = ($readData === $largeData);
    $details = "Data size: " . strlen($largeData) . " bytes";

    unlink($filename);
    return [
        'success' => $success,
        'message' => $success ? "Large data stored and retrieved correctly" : "Large data mismatch",
        'details' => $details
    ];
});

// Test 28: Data type preservation
runTest(28, "Data type preservation", "Various data types", "type integrity", function() use ($sx) {
    $filename = 'test_types.db';
    $testData = [
        'string' => 'hello world',
        'integer' => 42,
        'float' => 3.14159,
        'boolean_true' => true,
        'boolean_false' => false,
        'null' => null,
        'array' => ['a', 'b', 'c'],
        'assoc_array' => ['key1' => 'value1', 'key2' => 'value2'],
        'nested_array' => [['nested' => 'data'], [1, 2, 3]],
        'object' => (object)['prop' => 'value']
    ];

    $sx->createFile($filename);
    $sx->openFile($filename, 1);

    $allGood = true;
    foreach ($testData as $key => $value) {
        $sx->modifyKey($key, $value);
        $retrieved = $sx->returnKey($key);

        // For objects, use == instead of === since serialize/unserialize creates new instances
        if (is_object($value)) {
            if ($retrieved != $value || !is_object($retrieved)) {
                $allGood = false;
                break;
            }
        } else {
            if ($retrieved !== $value) {
                $allGood = false;
                break;
            }
        }
    }

    $sx->closeFile();
    unlink($filename);

    return [
        'success' => $allGood,
        'message' => $allGood ? "All data types preserved correctly" : "Data type preservation failed",
        'details' => "Tested: " . implode(', ', array_keys($testData)) . " (objects compared by value, not reference)"
    ];
});

// Test 29: Edge case - empty values
runTest(29, "Edge case - empty values", "Various empty values", "empty value handling", function() use ($sx) {
    $filename = 'test_empty.db';
    $emptyValues = [
        'empty_string' => '',
        'zero' => 0,
        'empty_array' => [],
        'false_value' => false
    ];

    $sx->createFile($filename);
    $sx->openFile($filename, 1);

    $allGood = true;
    foreach ($emptyValues as $key => $value) {
        $sx->modifyKey($key, $value);
        $retrieved = $sx->returnKey($key);
        if ($retrieved !== $value) {
            $allGood = false;
            break;
        }
    }

    $sx->closeFile();
    unlink($filename);

    return [
        'success' => $allGood,
        'message' => $allGood ? "Empty values handled correctly" : "Empty value handling failed"
    ];
});

// ============================================================================
echo "=== SECTION 5: CONCURRENCY ===" . PHP_EOL;
echo PHP_EOL;
// ============================================================================

// Test 30: File locking behavior
runTest(30, "File locking behavior", "Multiple file handles", "proper locking", function() use ($sx) {
    $filename = 'test_lock.db';
    $sx2 = new Sx();
    $sx2->throwExceptions(false);

    $sx->createFile($filename);
    $sx->openFile($filename, 1); // Lock for writing

    // Try to open same file with another instance
    $result = $sx2->openFile($filename, 1);

    $sx->closeFile();
    unlink($filename);

    // Should fail due to lock
    return [
        'success' => $result === 0,
        'message' => $result === 0 ? "File locking works correctly" : "File locking failed"
    ];
});

// ============================================================================
echo "=== SECTION 6: PERFORMANCE ===" . PHP_EOL;
echo PHP_EOL;
// ============================================================================

// Test 31: Performance test - rapid operations
runTest(31, "Rapid operations", "$performanceIterations write/read cycles", "good performance", function() use ($sx) {
    global $performanceIterations;

    $filename = 'test_perf.db';
    $sx->createFile($filename);

    $startTime = microtime(true);

    $sx->openFile($filename, 1);
    for ($i = 0; $i < $performanceIterations; $i++) {
        $key = "perf_key_$i";
        $value = "performance_test_value_$i";
        $sx->modifyKey($key, $value);
    }
    $sx->closeFile();

    // Now read them back
    $sx->openFile($filename);
    $readErrors = 0;
    for ($i = 0; $i < $performanceIterations; $i++) {
        $key = "perf_key_$i";
        $expected = "performance_test_value_$i";
        $actual = $sx->returnKey($key);
        if ($actual !== $expected) {
            $readErrors++;
        }
    }
    $sx->closeFile();

    $endTime = microtime(true);
    $duration = round(($endTime - $startTime) * 1000, 2);

    unlink($filename);

    $success = ($readErrors === 0);
    return [
        'success' => $success,
        'message' => $success ? "Performance test completed successfully" : "$readErrors read errors occurred",
        'details' => "Duration: {$duration}ms for $performanceIterations operations (" . round($performanceIterations / ($duration / 1000), 2) . " ops/sec)"
    ];
});

// Test 32: Stress test - many keys
runTest(32, "Bulk key operations", "$stressTestKeys keys via modifyMultipleKeys()", "bulk operations", function() use ($sx) {
    global $stressTestKeys;

    $filename = 'test_stress.db';
    $stressData = [];

    // Generate test data
    for ($i = 0; $i < $stressTestKeys; $i++) {
        $stressData["stress_key_$i"] = [
            'id' => $i,
            'data' => str_repeat("data_$i", 10),
            'metadata' => ['created' => time(), 'index' => $i]
        ];
    }

    $sx->createFile($filename);
    $sx->openFile($filename, 1);

    $startTime = microtime(true);
    $writeResult = $sx->modifyMultipleKeys($stressData);
    $sx->readAllKeys($readData);
    $endTime = microtime(true);

    $sx->closeFile();
    unlink($filename);

    $duration = round(($endTime - $startTime) * 1000, 2);
    $dataMatch = (count($readData) === $stressTestKeys);

    $success = ($writeResult === 1 && $dataMatch);
    return [
        'success' => $success,
        'message' => $success ? "Stress test completed successfully" : "Stress test failed",
        'details' => "Duration: {$duration}ms for $stressTestKeys keys (" . round($stressTestKeys / ($duration / 1000), 2) . " keys/sec)"
    ];
});

// Test 33: File state detection
runTest(33, "File state detection", "checkFile() various states", "corruption detection", function() use ($sx) {
    $results = [];

    // Test 1: Non-existent file
    $result1 = $sx->checkFile('nonexistent.db');
    $results[] = ($result1 === 0) ? "Non-existent: $result1 (OK)" : "Non-existent: $result1 (FAIL)";

    // Test 2: Invalid format file
    file_put_contents('invalid.db', 'not a database');
    $result2 = $sx->checkFile('invalid.db');
    unlink('invalid.db');
    $results[] = ($result2 === 5) ? "Invalid format: $result2 (OK)" : "Invalid format: $result2 (FAIL)";

    // Test 3: Valid StorX file
    $sx->createFile('valid.db');
    $result3 = $sx->checkFile('valid.db');
    unlink('valid.db');
    $results[] = ($result3 === 1) ? "Valid StorX: $result3 (OK)" : "Valid StorX: $result3 (FAIL)";

    $allGood = ($result1 === 0 && $result2 === 5 && $result3 === 1);

    return [
        'success' => $allGood,
        'message' => $allGood ? "File state detection works correctly" : "File state detection failed",
        'details' => implode(PHP_EOL, $results)
    ];
});

// Test 34: Memory usage monitoring
runTest(34, "Memory usage", "Large dataset operations", "memory efficiency", function() use ($sx) {
    $filename = 'test_memory.db';
    $memoryBefore = memory_get_usage();

    // Create a dataset that would use significant memory if not handled efficiently
    $largeDataset = [];
    for ($i = 0; $i < 500; $i++) {
        $largeDataset["mem_key_$i"] = str_repeat("data_chunk_$i", 100);
    }

    $sx->createFile($filename);
    $sx->openFile($filename, 1);
    $sx->modifyMultipleKeys($largeDataset);
    $sx->closeFile();

    // Clear the dataset from PHP memory
    unset($largeDataset);

    // Read back the data
    $sx->openFile($filename);
    $sx->readAllKeys($readData);
    $sx->closeFile();

    $memoryAfter = memory_get_usage();
    $memoryIncrease = $memoryAfter - $memoryBefore;

    unlink($filename);

    // Memory increase should be reasonable (less than 10MB for this test)
    $success = ($memoryIncrease < (10 * 1024 * 1024)) && (count($readData) === 500);

    return [
        'success' => $success,
        'message' => $success ? "Memory usage is reasonable" : "Memory usage too high",
        'details' => "Memory increase: " . round($memoryIncrease / 1024 / 1024, 2) . " MB, Keys processed: " . count($readData)
    ];
});

// ============================================================================
// CLEANUP BEFORE EXCEPTION TESTS
// ============================================================================

$sx->deleteFile($testDbFile);
foreach (glob('test_*.db') as $file) {
    if (file_exists($file)) unlink($file);
}

echo "Configuration:" . PHP_EOL;
echo " THROW_EXCEPTIONS: " . ($sxThrow->throwExceptions() ? "true" : "false") . PHP_EOL;
echo " BUSY_TIMEOUT: " . $sxThrow->setTimeout() . " ms" . PHP_EOL;
echo PHP_EOL;

// ============================================================================
echo "=== SECTION 7: EXCEPTION CODES - FILE OPERATIONS ===" . PHP_EOL;
echo PHP_EOL;
// ============================================================================

$testDbFile = 'testdb_exc.db';

// Test 35: Exception 101 - File does not exist (openFile)
runExceptionTest(35, "openFile() on non-existent file", 101, function() use ($sxThrow) {
    try {
        $sxThrow->openFile('nonexistent.db');
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        return ['success' => $e->getCode() === 101, 'code' => $e->getCode()];
    }
});

// Test 36: Exception 101 - File does not exist (copyFile source)
runExceptionTest(36, "copyFile() with non-existent source", 101, function() use ($sxThrow) {
    try {
        $sxThrow->copyFile('nonexistent.db', 'dest.db');
        if (file_exists('dest.db')) unlink('dest.db');
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        if (file_exists('dest.db')) unlink('dest.db');
        return ['success' => $e->getCode() === 101, 'code' => $e->getCode()];
    }
});

// Test 37: Exception 104 - File is locked (second writer)
runExceptionTest(37, "openFile() for writing on already-locked file", 104, function() use ($sxThrow) {
    $testFile = 'test_exc_locked.db';

    $sxThrow->createFile($testFile);
    $sxThrow->openFile($testFile, 1); // First writer locks the file

    $sx2 = new Sx();
    $sx2->throwExceptions(true);

    try {
        $sx2->openFile($testFile, 1); // Second writer - should throw 104
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testFile);
        return ['success' => $e->getCode() === 104, 'code' => $e->getCode()];
    }
});

// Test 38: Exception 105 - File not of matching StorX version
runExceptionTest(38, "openFile() on non-StorX SQLite file", 105, function() use ($sxThrow) {
    $testFile = 'test_exc_invalid.db';

    // Create a valid SQLite file but not a StorX DB
    $db = new \SQLite3($testFile);
    $db->exec("CREATE TABLE other (id INTEGER)");
    $db->close();

    try {
        $sxThrow->openFile($testFile);
        unlink($testFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        unlink($testFile);
        return ['success' => $e->getCode() === 105, 'code' => $e->getCode()];
    }
});

// Test 39: Exception 106 - Unable to create file (already exists)
runExceptionTest(39, "createFile() when file already exists", 106, function() use ($sxThrow, $testDbFile) {
    $sxThrow->createFile($testDbFile);

    try {
        $sxThrow->createFile($testDbFile);
        $sxThrow->deleteFile($testDbFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        $sxThrow->deleteFile($testDbFile);
        return ['success' => $e->getCode() === 106, 'code' => $e->getCode()];
    }
});

// Test 40: Exception 106 - copyFile() destination already exists
runExceptionTest(40, "copyFile() when destination exists", 106, function() use ($sxThrow) {
    $sourceFile = 'test_exc_src.db';
    $destFile = 'test_exc_dst.db';

    $sxThrow->createFile($sourceFile);
    $sxThrow->createFile($destFile);

    try {
        $sxThrow->copyFile($sourceFile, $destFile);
        $sxThrow->deleteFile($sourceFile);
        $sxThrow->deleteFile($destFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        $sxThrow->deleteFile($sourceFile);
        $sxThrow->deleteFile($destFile);
        return ['success' => $e->getCode() === 106, 'code' => $e->getCode()];
    }
});

// Test 41: Exception 108 - openFile() when file already open
runExceptionTest(41, "openFile() when file already open", 108, function() use ($sxThrow) {
    $testFile = 'test_exc_double_open.db';

    $sxThrow->createFile($testFile);
    $sxThrow->openFile($testFile, 1); // First open

    try {
        $sxThrow->openFile($testFile, 0); // Second open - should throw
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testFile);
        return ['success' => $e->getCode() === 108, 'code' => $e->getCode()];
    }
});

// ============================================================================
echo "=== SECTION 8: EXCEPTION CODES - NO FILE OPEN ===" . PHP_EOL;
echo PHP_EOL;
// ============================================================================

// Test 42: Exception 102 - No file open (readKey)
runExceptionTest(42, "readKey() with no file open", 102, function() use ($sxThrow) {
    try {
        $sxThrow->readKey('test', $val);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        return ['success' => $e->getCode() === 102, 'code' => $e->getCode()];
    }
});

// Test 43: Exception 102 - No file open (writeKey)
runExceptionTest(43, "writeKey() with no file open", 102, function() use ($sxThrow) {
    try {
        $sxThrow->writeKey('test', 'value');
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        return ['success' => $e->getCode() === 102, 'code' => $e->getCode()];
    }
});

// Test 44: Exception 102 - No file open (modifyKey)
runExceptionTest(44, "modifyKey() with no file open", 102, function() use ($sxThrow) {
    try {
        $sxThrow->modifyKey('test', 'value');
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        return ['success' => $e->getCode() === 102, 'code' => $e->getCode()];
    }
});

// Test 45: Exception 102 - No file open (deleteKey)
runExceptionTest(45, "deleteKey() with no file open", 102, function() use ($sxThrow) {
    try {
        $sxThrow->deleteKey('test');
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        return ['success' => $e->getCode() === 102, 'code' => $e->getCode()];
    }
});

// Test 46: Exception 102 - No file open (checkKey)
runExceptionTest(46, "checkKey() with no file open", 102, function() use ($sxThrow) {
    try {
        $sxThrow->checkKey('test');
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        return ['success' => $e->getCode() === 102, 'code' => $e->getCode()];
    }
});

// Test 47: Exception 102 - No file open (commitFile)
runExceptionTest(47, "commitFile() with no file open", 102, function() use ($sxThrow) {
    try {
        $sxThrow->commitFile();
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        return ['success' => $e->getCode() === 102, 'code' => $e->getCode()];
    }
});

// ============================================================================
echo "=== SECTION 9: EXCEPTION CODES - FILE NOT LOCKED ===" . PHP_EOL;
echo PHP_EOL;
// ============================================================================

// Test 48: Exception 103 - File not locked for writing (writeKey)
runExceptionTest(48, "writeKey() on readonly file", 103, function() use ($sxThrow, $testDbFile) {
    $sxThrow->createFile($testDbFile);
    $sxThrow->openFile($testDbFile, 0); // readonly

    try {
        $sxThrow->writeKey('test', 'value');
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => $e->getCode() === 103, 'code' => $e->getCode()];
    }
});

// Test 49: Exception 103 - File not locked for writing (modifyKey)
runExceptionTest(49, "modifyKey() on readonly file", 103, function() use ($sxThrow, $testDbFile) {
    $sxThrow->createFile($testDbFile);
    $sxThrow->openFile($testDbFile, 0); // readonly

    try {
        $sxThrow->modifyKey('test', 'value');
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => $e->getCode() === 103, 'code' => $e->getCode()];
    }
});

// Test 50: Exception 103 - File not locked for writing (deleteKey)
runExceptionTest(50, "deleteKey() on readonly file", 103, function() use ($sxThrow, $testDbFile) {
    $sxThrow->createFile($testDbFile);
    $sxThrow->openFile($testDbFile, 0); // readonly

    try {
        $sxThrow->deleteKey('test');
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => $e->getCode() === 103, 'code' => $e->getCode()];
    }
});

// ============================================================================
echo "=== SECTION 10: EXCEPTION CODES - KEY ERRORS ===" . PHP_EOL;
echo PHP_EOL;
// ============================================================================

// Test 51: Exception 201 - Key doesn't exist (readKey)
runExceptionTest(51, "readKey() on non-existent key", 201, function() use ($sxThrow, $testDbFile) {
    $sxThrow->createFile($testDbFile);
    $sxThrow->openFile($testDbFile, 0);

    try {
        $sxThrow->readKey('nonexistent', $val);
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => $e->getCode() === 201, 'code' => $e->getCode()];
    }
});

// Test 52: Exception 201 - Key doesn't exist (returnKey)
runExceptionTest(52, "returnKey() on non-existent key", 201, function() use ($sxThrow, $testDbFile) {
    $sxThrow->createFile($testDbFile);
    $sxThrow->openFile($testDbFile, 0);

    try {
        $sxThrow->returnKey('nonexistent');
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => $e->getCode() === 201, 'code' => $e->getCode()];
    }
});

// Test 53: Exception 202 - Key already exists (writeKey)
runExceptionTest(53, "writeKey() on existing key", 202, function() use ($sxThrow) {
    $testFile = 'test_exc_keyexists.db';

    // Ensure clean state
    if (file_exists($testFile)) {
        $sxThrow->throwExceptions(false);
        $sxThrow->deleteFile($testFile);
        $sxThrow->throwExceptions(true);
    }

    $sxThrow->createFile($testFile);
    $sxThrow->openFile($testFile, 1);
    $sxThrow->writeKey('existing', 'value1');

    try {
        $sxThrow->writeKey('existing', 'value2');
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testFile);
        return ['success' => $e->getCode() === 202, 'code' => $e->getCode()];
    }
});

// Test 54: Exception 206 - Invalid key name (writeKey)
runExceptionTest(54, "writeKey() with invalid key name", 206, function() use ($sxThrow, $testDbFile) {
    $sxThrow->createFile($testDbFile);
    $sxThrow->openFile($testDbFile, 1);

    try {
        $sxThrow->writeKey('inv@lid!', 'value');
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => $e->getCode() === 206, 'code' => $e->getCode()];
    }
});

// Test 55: Exception 206 - Invalid key name (modifyKey)
runExceptionTest(55, "modifyKey() with invalid key name", 206, function() use ($sxThrow, $testDbFile) {
    $sxThrow->createFile($testDbFile);
    $sxThrow->openFile($testDbFile, 1);

    try {
        $sxThrow->modifyKey('123invalid', 'value');
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => $e->getCode() === 206, 'code' => $e->getCode()];
    }
});

// ============================================================================
echo "=== SECTION 11: EXCEPTION CODES - RESERVED KEYS ===" . PHP_EOL;
echo PHP_EOL;
// ============================================================================

// Test 56: Exception 666 - Reserved key (writeKey StorXInfo)
runExceptionTest(56, "writeKey() on reserved key StorXInfo", 666, function() use ($sxThrow, $testDbFile) {
    $sxThrow->createFile($testDbFile);
    $sxThrow->openFile($testDbFile, 1);

    try {
        $sxThrow->writeKey('StorXInfo', 'hacked');
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => $e->getCode() === 666, 'code' => $e->getCode()];
    }
});

// Test 57: Exception 666 - Reserved key (modifyKey StorXInfo)
runExceptionTest(57, "modifyKey() on reserved key StorXInfo", 666, function() use ($sxThrow, $testDbFile) {
    $sxThrow->createFile($testDbFile);
    $sxThrow->openFile($testDbFile, 1);

    try {
        $sxThrow->modifyKey('StorXInfo', 'hacked');
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => $e->getCode() === 666, 'code' => $e->getCode()];
    }
});

// Test 58: Exception 666 - Reserved key (deleteKey StorXInfo)
runExceptionTest(58, "deleteKey() on reserved key StorXInfo", 666, function() use ($sxThrow, $testDbFile) {
    $sxThrow->createFile($testDbFile);
    $sxThrow->openFile($testDbFile, 1);

    try {
        $sxThrow->deleteKey('StorXInfo');
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => false, 'message' => 'No exception thrown'];
    } catch (Exception $e) {
        $sxThrow->closeFile();
        $sxThrow->deleteFile($testDbFile);
        return ['success' => $e->getCode() === 666, 'code' => $e->getCode()];
    }
});

// ============================================================================
// CLEANUP AND SUMMARY
// ============================================================================

// Clean up any remaining test files
if (file_exists($testDbFile)) {
    $sxThrow->throwExceptions(false);
    $sxThrow->deleteFile($testDbFile);
}
foreach (glob('test_*.db') as $file) {
    if (file_exists($file)) unlink($file);
}
foreach (glob('test_exc_*.db') as $file) {
    if (file_exists($file)) unlink($file);
}
if (file_exists('testdb.db')) {
    unlink('testdb.db');
}
if (file_exists('dest.db')) {
    unlink('dest.db');
}

$endTime = microtime(true);
$totalDuration = round(($endTime - $startTime) * 1000, 2);

echo "=== TEST SUMMARY ===" . PHP_EOL;
echo "Tests completed!" . PHP_EOL;
echo "Total tests run: $totalTests" . PHP_EOL;
echo "Tests passed: $passedTests" . PHP_EOL;
echo "Tests failed: " . ($totalTests - $passedTests) . PHP_EOL;
echo "Success rate: " . round(($passedTests / $totalTests) * 100, 1) . "%" . PHP_EOL;
echo "Total duration: {$totalDuration}ms" . PHP_EOL;
echo PHP_EOL;

if ($testsPassed) {
    echo "FINAL RESULTS: ALL TESTS PASSED!" . PHP_EOL;
    echo "StorX library is functioning correctly across all test scenarios." . PHP_EOL;
} else {
    echo "FINAL RESULTS: SOME TESTS FAILED!" . PHP_EOL;
    echo "See errors above for details on failed tests." . PHP_EOL;
}

echo PHP_EOL;
echo "Test coverage:" . PHP_EOL;
echo "  Section 1:  File Operations (1-15)" . PHP_EOL;
echo "  Section 2:  Key Operations (16-24)" . PHP_EOL;
echo "  Section 3:  Configuration (25-26)" . PHP_EOL;
echo "  Section 4:  Data Integrity (27-29)" . PHP_EOL;
echo "  Section 5:  Concurrency (30)" . PHP_EOL;
echo "  Section 6:  Performance (31-34)" . PHP_EOL;
echo "  Section 7:  Exception Codes - File Operations (35-41)" . PHP_EOL;
echo "  Section 8:  Exception Codes - No File Open (42-47)" . PHP_EOL;
echo "  Section 9:  Exception Codes - File Not Locked (48-50)" . PHP_EOL;
echo "  Section 10: Exception Codes - Key Errors (51-55)" . PHP_EOL;
echo "  Section 11: Exception Codes - Reserved Keys (56-58)" . PHP_EOL;
echo PHP_EOL;
echo "Exception codes tested: 101, 102, 103, 104, 105, 106, 108, 201, 202, 206, 666" . PHP_EOL;

?>
