<?php
/*
StorX Comprehensive Test Suite
v5.1, 2025-08-07
AGPLv3, @aaviator42

Complete test suite for StorX library covering:
- Core functionality (file operations, key management)
- Data type preservation and serialization
- Exception handling and configuration
- Performance testing and stress testing
- Memory efficiency and large data handling
- File locking and concurrent access simulation
- Edge cases and error recovery scenarios

Contains 27 comprehensive tests validating all aspects of StorX functionality.
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

// Helper function for test output
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

echo "Beginning StorX Enhanced Tests..." . PHP_EOL;
echo "Enhanced test file v5.1, 2025-08-07" . PHP_EOL;
echo "Total planned tests: Comprehensive suite with original + enhanced tests" . PHP_EOL;
echo PHP_EOL;

// Initialize Sx objects for different configurations
$sx = new Sx();
$sx->throwExceptions(false);

$sxThrow = new Sx();
$sxThrow->throwExceptions(true);

echo "Testing configurations:" . PHP_EOL;
echo " THROW_EXCEPTIONS (default): " . ($sx->throwExceptions() ? "true" : "false") . PHP_EOL;
echo " THROW_EXCEPTIONS (test): " . ($sxThrow->throwExceptions() ? "true" : "false") . PHP_EOL;
echo " BUSY_TIMEOUT: " . $sx->setTimeout() . " ms" . PHP_EOL;
echo PHP_EOL;

echo "=== SECTION 1: ORIGINAL CORE FUNCTIONALITY TESTS ===" . PHP_EOL;
echo PHP_EOL;

// Original Test 1: createFile() success
runTest(1, "Creating DB file testdb.db", "createFile('$testDbFile')", "1", function() use ($sx, $testDbFile) {
    $result = $sx->createFile($testDbFile);
    $success = ($result === 1 && file_exists($testDbFile));
    return ['success' => $success, 'message' => $result];
});

// Original Test 2: createFile() duplicate
runTest(2, "Creating DB file testdb.db again", "createFile('$testDbFile')", "0", function() use ($sx, $testDbFile) {
    $result = $sx->createFile($testDbFile);
    return ['success' => $result === 0, 'message' => $result];
});

// Original Test 3: checkFile() valid
runTest(3, "Check DB file testdb.db", "checkFile('$testDbFile')", "1", function() use ($sx, $testDbFile) {
    $result = $sx->checkFile($testDbFile);
    return ['success' => $result === 1, 'message' => $result];
});

// Original Test 4: checkFile() non-existent
runTest(4, "Check non-existent DB file testdb2.db", "checkFile('testdb2.db')", "0", function() use ($sx) {
    $result = $sx->checkFile('testdb2.db');
    return ['success' => $result === 0, 'message' => $result];
});

// Original Test 5: checkFile() invalid format
runTest(5, "Check invalid DB file testdb2.db", "checkFile('testdb2.db')", "5", function() use ($sx) {
    file_put_contents('testdb2.db', "0000000000");
    $result = $sx->checkFile('testdb2.db');
    unlink('testdb2.db');
    return ['success' => $result === 5, 'message' => $result];
});

// Original Test 6: deleteFile() success
runTest(6, "Delete DB file testdb.db", "deleteFile('$testDbFile')", "1", function() use ($sx, $testDbFile) {
    $result = $sx->deleteFile($testDbFile);
    $success = ($result === 1 && !file_exists($testDbFile));
    return ['success' => $success, 'message' => $result];
});

// Original Test 7: deleteFile() non-existent
runTest(7, "Delete non-existent DB file testdb.db", "deleteFile('$testDbFile')", "1", function() use ($sx, $testDbFile) {
    $result = $sx->deleteFile($testDbFile);
    return ['success' => $result === 1, 'message' => $result];
});

// Original Test 8: openFile() non-existent
runTest(8, "Open non-existent DB file testdb.db", "openFile('$testDbFile')", "0", function() use ($sx, $testDbFile) {
    $result = $sx->openFile($testDbFile);
    return ['success' => $result === 0, 'message' => $result];
});

// Original Test 9: write and read key
runTest(9, "Write and read key to/from DB file", "writeKey(), returnKey()", "successful read/write", function() use ($sx, $testDbFile) {
    $testValue = "test_000";
    
    $sx->createFile($testDbFile);
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

// Original Test 10: deleteKey()
runTest(10, "Delete key from DB file", "deleteKey(), returnKey()", "key deletion", function() use ($sx, $testDbFile) {
    $sx->openFile($testDbFile, 1);
    $result = $sx->deleteKey('test_key');
    $sx->commitFile();
    $sx->openFile($testDbFile, 1);
    $readValue = $sx->returnKey('test_key');
    $sx->closeFile();
    
    $success = ($result == 1 && $readValue === "STORX_ERROR");
    return [
        'success' => $success,
        'message' => $success ? "Key successfully deleted from file" : "Unable to delete key from file"
    ];
});

// Original Test 11: deleteKey() non-existent
runTest(11, "Delete non-existent key from DB file", "deleteKey()", "1", function() use ($sx, $testDbFile) {
    $sx->openFile($testDbFile, 1);
    $result = $sx->deleteKey('test_key');
    $sx->closeFile();
    return ['success' => $result == 1, 'message' => $result];
});

// Original Test 12: deleteKey() without write access
runTest(12, "Delete key without write access", "deleteKey()", "0", function() use ($sx, $testDbFile) {
    $sx->openFile($testDbFile, 1);
    $sx->writeKey('test_key', 'test_value_0000');
    $sx->closeFile();
    $sx->openFile($testDbFile, 0); // readonly
    $result = $sx->deleteKey('test_key');
    $sx->closeFile();
    return ['success' => $result == 0, 'message' => $result];
});

// Original Test 13: checkKey() exists
runTest(13, "Check existing key in DB file", "checkKey()", "1", function() use ($sx, $testDbFile) {
    $sx->openFile($testDbFile, 1);
    $sx->modifyKey("test_key", "test_value_0000");
    $result = $sx->checkKey("test_key");
    $sx->closeFile();
    return ['success' => $result == 1, 'message' => $result];
});

// Original Test 14: checkKey() non-existent
runTest(14, "Check non-existent key in DB file", "checkKey()", "0", function() use ($sx, $testDbFile) {
    $sx->openFile($testDbFile, 1);
    $result = $sx->checkKey("test_key_2");
    $sx->closeFile();
    return ['success' => $result == 0, 'message' => $result];
});

// Original Test 15: modifyKey()
runTest(15, "Modify key in DB file", "modifyKey()", "1", function() use ($sx, $testDbFile) {
    $testValue = 'test_value_0001';
    $sx->openFile($testDbFile, 1);
    $result = $sx->modifyKey("test_key", $testValue);
    $sx->readKey("test_key", $readValue);
    $sx->closeFile();
    
    $success = ($result === 1 && $readValue === $testValue);
    return ['success' => $success, 'message' => $result];
});

// Original Test 16: writeKey() invalid name
runTest(16, "Write key with invalid name", "writeKey()", "0", function() use ($sx, $testDbFile) {
    $keyName = 'INV@LID';
    $sx->openFile($testDbFile, 1);
    $sx->deleteKey('test_key');
    $result = $sx->writeKey($keyName, 'test_value_0001');
    $sx->closeFile();
    return ['success' => $result === 0, 'message' => $result];
});

// Original Test 17: modifyMultipleKeys()
runTest(17, "Modify/read multiple keys", "modifyMultipleKeys(), readAllKeys()", "data integrity", function() use ($sx, $testDbFile) {
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
    
    $result = (md5(var_export($keysWrite, true)) === md5(var_export($keysRead, true)));
    return [
        'success' => $result,
        'message' => $result ? "written_data === read_data" : "data mismatch"
    ];
});

echo "=== SECTION 2: ENHANCED FUNCTIONALITY TESTS ===" . PHP_EOL;
echo PHP_EOL;

// Enhanced Test 18: Exception handling mode
runTest(18, "Exception handling mode", "throwExceptions(true)", "exceptions thrown", function() use ($sxThrow) {
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

// Enhanced Test 19: Timeout configuration
runTest(19, "Timeout configuration", "setTimeout()", "configurable timeout", function() use ($sx) {
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

// Enhanced Test 20: Large data storage
runTest(20, "Large data storage test", "writeKey(), returnKey() with 1MB data", "large data handling", function() use ($sx) {
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

// Enhanced Test 21: Concurrent file operations simulation
runTest(21, "File locking behavior", "Multiple file handles", "proper locking", function() use ($sx) {
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

// Enhanced Test 22: Data type preservation
runTest(22, "Data type preservation", "Various data types", "type integrity", function() use ($sx) {
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

// Enhanced Test 23: Performance test - rapid operations
runTest(23, "Performance test - rapid operations", "$performanceIterations write/read cycles", "good performance", function() use ($sx) {
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

// Enhanced Test 24: Stress test - many keys
runTest(24, "Stress test - bulk key operations", "$stressTestKeys keys via modifyMultipleKeys()", "bulk operations", function() use ($sx) {
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

// Enhanced Test 25: Edge case - empty values
runTest(25, "Edge case handling - empty values", "Various empty values", "empty value handling", function() use ($sx) {
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

// Enhanced Test 26: File recovery simulation
runTest(26, "File recovery after corruption", "checkFile() various states", "corruption detection", function() use ($sx) {
    $results = [];
    
    // Test 1: Non-existent file
    $result1 = $sx->checkFile('nonexistent.db');
    $results[] = ($result1 === 0) ? "✓ Non-existent: $result1" : "✗ Non-existent: $result1";
    
    // Test 2: Invalid format file
    file_put_contents('invalid.db', 'not a database');
    $result2 = $sx->checkFile('invalid.db');
    unlink('invalid.db');
    $results[] = ($result2 === 5) ? "✓ Invalid format: $result2" : "✗ Invalid format: $result2";
    
    // Test 3: Valid StorX file
    $sx->createFile('valid.db');
    $result3 = $sx->checkFile('valid.db');
    unlink('valid.db');
    $results[] = ($result3 === 1) ? "✓ Valid StorX: $result3" : "✗ Valid StorX: $result3";
    
    $allGood = !in_array(false, array_map(function($r) { return strpos($r, '✓') === 0; }, $results));
    
    return [
        'success' => $allGood,
        'message' => $allGood ? "File state detection works correctly" : "File state detection failed",
        'details' => implode(PHP_EOL, $results)
    ];
});

// Enhanced Test 27: Memory usage monitoring
runTest(27, "Memory usage test", "Large dataset operations", "memory efficiency", function() use ($sx) {
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

// Clean up any remaining test files
$sx->deleteFile($testDbFile);
foreach (glob('test_*.db') as $file) {
    if (file_exists($file)) unlink($file);
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
    echo "FINAL RESULTS: ALL TESTS PASSED!!!" . PHP_EOL;
    echo "StorX library is functioning correctly across all test scenarios." . PHP_EOL;
} else {
    echo "FINAL RESULTS: ALL TESTS *DID NOT* PASS!!!" . PHP_EOL;
    echo "See errors above for details on failed tests." . PHP_EOL;
}

echo PHP_EOL;
echo "Enhanced test coverage includes:" . PHP_EOL;
echo "• All original functionality tests (1-17)" . PHP_EOL;
echo "• Exception handling and configuration tests" . PHP_EOL;
echo "• Large data and performance tests" . PHP_EOL;
echo "• Concurrent access and locking tests" . PHP_EOL;
echo "• Data type preservation and edge cases" . PHP_EOL;
echo "• Stress testing with bulk operations" . PHP_EOL;
echo "• File corruption detection and recovery" . PHP_EOL;
echo "• Memory efficiency validation" . PHP_EOL;

?>