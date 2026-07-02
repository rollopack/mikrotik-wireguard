<?php
// Custom mini-PHPUnit test runner

if (!class_exists('TestCase')) {
class TestCase {
    protected int $assertions = 0;

    public function assertEquals($expected, $actual, string $message = '') {
        $this->assertions++;
        if ($expected !== $actual) {
            $expectedStr = var_export($expected, true);
            $actualStr = var_export($actual, true);
            throw new Exception("Assertion failed: Expected $expectedStr, got $actualStr. $message");
        }
    }

    public function assertTrue($condition, string $message = '') {
        $this->assertions++;
        if (!$condition) {
            throw new Exception("Assertion failed: Expected true. $message");
        }
    }

    public function assertFalse($condition, string $message = '') {
        $this->assertions++;
        if ($condition) {
            throw new Exception("Assertion failed: Expected false. $message");
        }
    }

    public function assertNotEmpty($value, string $message = '') {
        $this->assertions++;
        if (empty($value)) {
            throw new Exception("Assertion failed: Expected not empty. $message");
        }
    }

    public function assertNull($value, string $message = '') {
        $this->assertions++;
        if ($value !== null) {
            $expectedStr = var_export($value, true);
            throw new Exception("Assertion failed: Expected null, got $expectedStr. $message");
        }
    }

    public function assertNotNull($value, string $message = '') {
        $this->assertions++;
        if ($value === null) {
            throw new Exception("Assertion failed: Expected not null. $message");
        }
    }

    public function assertEqualsIgnoringCase($expected, $actual, string $message = '') {
        $this->assertions++;
        if (strcasecmp((string)$expected, (string)$actual) !== 0) {
            $expectedStr = var_export($expected, true);
            $actualStr = var_export($actual, true);
            throw new Exception("Assertion failed: Expected $expectedStr (case-insensitive), got $actualStr. $message");
        }
    }

    public function getAssertionCount(): int {
        return $this->assertions;
    }
}
}

// Autoloader for src classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../src/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Auto-discover all test files
$testFiles = glob(__DIR__ . '/*Test.php');

$passed = 0;
$failed = 0;
$totalAssertions = 0;

echo "\033[1;34m=== WireGuard Peer Manager Test Runner ===\033[0m\n\n";

foreach ($testFiles as $file) {
    if (!file_exists($file)) {
        continue;
    }
    
    // Track classes before requiring
    $classesBefore = get_declared_classes();
    require_once $file;
    $classesAfter = get_declared_classes();
    $newClasses = array_diff($classesAfter, $classesBefore);
    
    // If the file was already included somehow, fall back to class matching name
    if (empty($newClasses)) {
        $basename = basename($file, '.php');
        if (class_exists($basename)) {
            $newClasses = [$basename];
        }
    }

    foreach ($newClasses as $class) {
        if (is_subclass_of($class, 'TestCase')) {
            $reflection = new ReflectionClass($class);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
            
            echo "Class: \033[1m$class\033[0m\n";
            
            foreach ($methods as $method) {
                if (str_starts_with($method->name, 'test')) {
                    $methodName = $method->name;
                    echo "  -> Running $methodName... ";
                    
                    // Instantiate fresh for each test method
                    $instance = new $class();
                    
                    try {
                        if (method_exists($instance, 'setUp')) {
                            $instance->setUp();
                        }
                        
                        $instance->$methodName();

                        if (method_exists($instance, 'tearDown')) {
                            $instance->tearDown();
                        }
                        echo "\033[32m[PASS]\033[0m\n";
                        $passed++;
                    } catch (Exception $e) {
                        echo "\033[31m[FAIL]\033[0m\n";
                        echo "     \033[31mError: " . $e->getMessage() . "\033[0m\n";
                        echo "     Stack trace:\n" . $e->getTraceAsString() . "\n\n";
                        $failed++;
                    }
                    $totalAssertions += $instance->getAssertionCount();
                }
            }
        }
    }
}

echo "\n\033[1mSummary:\033[0m\n";
echo "Tests Passed: \033[32m$passed\033[0m\n";
echo "Tests Failed: \033[31m$failed\033[0m\n";
echo "Assertions: $totalAssertions\n";

if ($failed > 0) {
    exit(1);
} else {
    exit(0);
}
