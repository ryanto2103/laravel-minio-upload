#!/usr/bin/env php
<?php

/**
 * Laravel MinIO Upload - System Check Script
 * 
 * This script performs comprehensive checks on the Laravel MinIO upload application
 * to ensure all requirements are met and the system is properly configured.
 * 
 * Usage: php check.php [--verbose] [--json]
 */

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

// ANSI color codes for terminal output
$colors = [
    'reset' => "\033[0m",
    'red' => "\033[31m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'magenta' => "\033[35m",
    'cyan' => "\033[36m",
    'white' => "\033[37m",
    'bold' => "\033[1m",
];

// Parse command line arguments
$verbose = in_array('--verbose', $argv) || in_array('-v', $argv);
$jsonOutput = in_array('--json', $argv) || in_array('-j', $argv);
$noColor = in_array('--no-color', $argv);

// Disable colors if output is not to terminal or --no-color is set
if ($noColor || PHP_SAPI !== 'cli' || stream_isatty(STDOUT) === false) {
    $colors = array_fill_keys(array_keys($colors), '');
}

$results = [];
$errors = 0;
$warnings = 0;

/**
 * Output a message
 */
function output($message, $color = 'white', $newLine = true) {
    global $colors, $jsonOutput;
    
    if ($jsonOutput) return;
    
    $colored = $colors[$color] . $message . $colors['reset'];
    if ($newLine) {
        echo $colored . PHP_EOL;
    } else {
        echo $colored;
    }
}

/**
 * Output a section header
 */
function section($title) {
    global $colors, $jsonOutput;
    
    if ($jsonOutput) return;
    
    output('', 'reset');
    output(str_repeat('=', 60), 'cyan');
    output("  $title", 'cyan', true);
    output(str_repeat('=', 60), 'cyan');
    output('', 'reset');
}

/**
 * Record a check result
 */
function check($name, $status, $message = '', $details = []) {
    global $results, $errors, $warnings, $colors, $jsonOutput, $verbose;
    
    $result = [
        'name' => $name,
        'status' => $status, // 'pass', 'fail', 'warn'
        'message' => $message,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $results[] = $result;
    
    if ($status === 'fail') $errors++;
    if ($status === 'warn') $warnings++;
    
    if (!$jsonOutput) {
        $icon = match($status) {
            'pass' => '✓',
            'fail' => '✗',
            'warn' => '⚠',
            default => '•'
        };
        
        $color = match($status) {
            'pass' => 'green',
            'fail' => 'red',
            'warn' => 'yellow',
            default => 'white'
        };
        
        output("  $icon  $name", $color);
        
        if ($message && ($verbose || $status !== 'pass')) {
            output("     └─ $message", 'white');
        }
        
        if ($verbose && !empty($details)) {
            foreach ($details as $key => $value) {
                output("     └─ $key: $value", 'cyan');
            }
        }
    }
}

/**
 * Check PHP version
 */
function checkPhpVersion() {
    $required = '8.2';
    $current = PHP_VERSION;
    $status = version_compare($current, $required, '>=') ? 'pass' : 'fail';
    
    check(
        "PHP Version (required: >=$required)",
        $status,
        "Current: $current",
        ['Required' => $required, 'Current' => $current]
    );
}

/**
 * Check required PHP extensions
 */
function checkPhpExtensions() {
    $required = ['pdo', 'mbstring', 'xml', 'curl', 'json', 'zip'];
    $optional = ['redis', 'bcmath', 'gd', 'fileinfo'];
    
    foreach ($required as $ext) {
        $loaded = extension_loaded($ext);
        check(
            "PHP Extension: $ext (required)",
            $loaded ? 'pass' : 'fail',
            $loaded ? "Extension loaded" : "Extension not found - required for Laravel",
            ['Extension' => $ext, 'Loaded' => $loaded ? 'Yes' : 'No']
        );
    }
    
    foreach ($optional as $ext) {
        $loaded = extension_loaded($ext);
        if (!$loaded) {
            check(
                "PHP Extension: $ext (optional)",
                'warn',
                "Extension not found - recommended for better performance",
                ['Extension' => $ext, 'Loaded' => $loaded ? 'Yes' : 'No']
            );
        }
    }
}

/**
 * Check Composer dependencies
 */
function checkComposerDependencies() {
    $composerJson = 'composer.json';
    $vendorDir = 'vendor';
    $autoloadFile = 'vendor/autoload.php';
    
    // Check if composer.json exists
    check(
        "Composer file exists",
        file_exists($composerJson) ? 'pass' : 'fail',
        file_exists($composerJson) ? "Found: $composerJson" : "File not found: $composerJson"
    );
    
    // Check if vendor directory exists
    check(
        "Vendor directory exists",
        is_dir($vendorDir) ? 'pass' : 'fail',
        is_dir($vendorDir) ? "Directory exists" : "Run 'composer install' to create",
        ['Path' => $vendorDir]
    );
    
    // Check if autoload.php exists
    check(
        "Autoload file exists",
        file_exists($autoloadFile) ? 'pass' : 'fail',
        file_exists($autoloadFile) ? "Found" : "Run 'composer install' to generate",
        ['Path' => $autoloadFile]
    );
    
    // Check for required packages
    if (file_exists($autoloadFile)) {
        $packages = [
            'laravel/framework' => 'Laravel Framework',
            'league/flysystem-aws-s3-v3' => 'AWS S3 Flysystem Adapter (for MinIO)'
        ];
        
        foreach ($packages as $package => $name) {
            $path = "$vendorDir/$package";
            check(
                "Package: $name",
                is_dir($path) ? 'pass' : 'fail',
                is_dir($path) ? "Installed" : "Not found - run 'composer install'",
                ['Package' => $package]
            );
        }
    }
}

/**
 * Check environment configuration
 */
function checkEnvironment() {
    $envFile = '.env';
    $envExample = '.env.example';
    
    // Check if .env exists
    check(
        "Environment file (.env) exists",
        file_exists($envFile) ? 'pass' : 'fail',
        file_exists($envFile) ? "Found" : "Copy $envExample to $envFile and configure",
        ['File' => $envFile]
    );
    
    if (!file_exists($envFile)) {
        return;
    }
    
    $envContent = file_get_contents($envFile);
    $envVars = [];
    
    // Parse .env file
    foreach (explode("\n", $envContent) as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) continue;
        
        if (preg_match('/^([^=]+)=(.*)$/', $line, $matches)) {
            $envVars[trim($matches[1])] = trim($matches[2]);
        }
    }
    
    // Check required environment variables
    $required = [
        'APP_KEY' => 'Application key',
        'APP_ENV' => 'Application environment',
        'APP_DEBUG' => 'Debug mode',
        'DB_CONNECTION' => 'Database connection',
        'FILESYSTEM_DISK' => 'Filesystem disk'
    ];
    
    foreach ($required as $key => $name) {
        $exists = isset($envVars[$key]) && !empty($envVars[$key]);
        check(
            "Env: $name ($key)",
            $exists ? 'pass' : 'fail',
            $exists ? "Set: " . ($key === 'APP_KEY' ? '***' : $envVars[$key]) : "Not configured",
            ['Key' => $key, 'Set' => $exists ? 'Yes' : 'No']
        );
    }
    
    // Check MinIO/S3 specific variables
    $minioVars = [
        'AWS_ACCESS_KEY_ID' => 'MinIO Access Key',
        'AWS_SECRET_ACCESS_KEY' => 'MinIO Secret Key',
        'AWS_DEFAULT_REGION' => 'MinIO Region',
        'AWS_BUCKET' => 'MinIO Bucket',
        'AWS_ENDPOINT' => 'MinIO Endpoint',
        'AWS_USE_PATH_STYLE_ENDPOINT' => 'Use Path Style Endpoint'
    ];
    
    $filesystemDisk = $envVars['FILESYSTEM_DISK'] ?? '';
    $usingMinio = in_array($filesystemDisk, ['minio', 's3']);
    
    foreach ($minioVars as $key => $name) {
        $exists = isset($envVars[$key]);
        $value = $envVars[$key] ?? '';
        
        if ($usingMinio) {
            check(
                "Env: $name ($key)",
                $exists && !empty($value) ? 'pass' : 'fail',
                $exists ? ($key === 'AWS_SECRET_ACCESS_KEY' ? 'Set: ***' : "Set: $value") : "Required for MinIO but not configured",
                ['Key' => $key, 'Using MinIO' => $usingMinio]
            );
        } else {
            if ($exists) {
                check(
                    "Env: $name ($key)",
                    'warn',
                    "Set but FILESYSTEM_DISK is not 'minio' or 's3'",
                    ['Key' => $key, 'Value' => $key === 'AWS_SECRET_ACCESS_KEY' ? '***' : $value]
                );
            }
        }
    }
    
    // Check if FILESYSTEM_DISK is set to minio or s3
    if ($usingMinio) {
        check(
            "Filesystem disk configured for MinIO/S3",
            'pass',
            "FILESYSTEM_DISK=$filesystemDisk",
            ['Disk' => $filesystemDisk]
        );
    } else {
        check(
            "Filesystem disk for MinIO",
            'warn',
            "FILESYSTEM_DISK=$filesystemDisk (should be 'minio' or 's3' for MinIO)",
            ['Disk' => $filesystemDisk, 'Recommended' => 'minio']
        );
    }
}

/**
 * Check directory permissions
 */
function checkPermissions() {
    $directories = [
        'storage' => 'Storage directory',
        'storage/app' => 'Storage/app directory',
        'storage/framework' => 'Storage/framework directory',
        'storage/logs' => 'Storage/logs directory',
        'bootstrap/cache' => 'Bootstrap cache directory'
    ];
    
    foreach ($directories as $dir => $name) {
        if (!is_dir($dir)) {
            check(
                "$name exists",
                'fail',
                "Directory not found: $dir",
                ['Directory' => $dir, 'Exists' => 'No']
            );
            continue;
        }
        
        $writable = is_writable($dir);
        check(
            "$name writable",
            $writable ? 'pass' : 'fail',
            $writable ? "Writable" : "Not writable - check permissions",
            ['Directory' => $dir, 'Writable' => $writable ? 'Yes' : 'No']
        );
    }
    
    // Check public directory
    if (is_dir('public')) {
        check(
            "Public directory exists",
            'pass',
            "Found",
            ['Directory' => 'public']
        );
    } else {
        check(
            "Public directory exists",
            'fail',
            "Directory not found",
            ['Directory' => 'public']
        );
    }
}

/**
 * Check database configuration
 */
function checkDatabase() {
    if (!file_exists('.env')) {
        check(
            "Database configuration",
            'warn',
            ".env file not found - cannot check database",
            ['Status' => 'Skipped']
        );
        return;
    }
    
    $envContent = file_get_contents('.env');
    preg_match('/DB_CONNECTION=(\w+)/', $envContent, $dbMatch);
    $dbConnection = $dbMatch[1] ?? 'sqlite';
    
    check(
        "Database connection type",
        'pass',
        "Using: $dbConnection",
        ['Connection' => $dbConnection]
    );
    
    // For SQLite, check if database file exists
    if ($dbConnection === 'sqlite') {
        $dbFile = 'database/database.sqlite';
        check(
            "SQLite database file",
            file_exists($dbFile) ? 'pass' : 'warn',
            file_exists($dbFile) ? "Found" : "Not found - will be created on migration",
            ['File' => $dbFile]
        );
    }
    
    // Check if migrations have been run
    if (file_exists('vendor/autoload.php')) {
        require_once 'vendor/autoload.php';
        
        try {
            // Try to bootstrap Laravel to check database
            $app = require_once 'bootstrap/app.php';
            $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
            
            // Check if we can connect to database
            $db = app('db');
            $connected = true;
            
            try {
                $db->connection()->getPdo();
            } catch (\Exception $e) {
                $connected = false;
                check(
                    "Database connection",
                    'fail',
                    "Cannot connect: " . $e->getMessage(),
                    ['Connected' => 'No']
                );
            }
            
            if ($connected) {
                check(
                    "Database connection",
                    'pass',
                    "Connected successfully",
                    ['Connected' => 'Yes', 'Driver' => $dbConnection]
                );
                
                // Check if migrations have been run
                try {
                    $hasMigrations = $db->connection()->getSchemaBuilder()->hasTable('migrations');
                    check(
                        "Database migrations",
                        $hasMigrations ? 'pass' : 'warn',
                        $hasMigrations ? "Migrations table exists" : "Migrations not run - run 'php artisan migrate'",
                        ['Migrations Run' => $hasMigrations ? 'Yes' : 'No']
                    );
                } catch (\Exception $e) {
                    check(
                        "Database migrations",
                        'warn',
                        "Could not check: " . $e->getMessage(),
                        ['Error' => $e->getMessage()]
                    );
                }
            }
        } catch (\Exception $e) {
            check(
                "Database bootstrap",
                'warn',
                "Could not bootstrap Laravel: " . $e->getMessage(),
                ['Error' => $e->getMessage()]
            );
        }
    }
}

/**
 * Check MinIO connectivity
 */
function checkMinIOConnectivity() {
    if (!file_exists('.env')) {
        check(
            "MinIO connectivity",
            'warn',
            ".env file not found - cannot check MinIO",
            ['Status' => 'Skipped']
        );
        return;
    }
    
    // Load environment variables
    $envContent = file_get_contents('.env');
    $envVars = [];
    
    foreach (explode("\n", $envContent) as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) continue;
        
        if (preg_match('/^([^=]+)=(.*)$/', $line, $matches)) {
            $envVars[trim($matches[1])] = trim($matches[2]);
        }
    }
    
    $filesystemDisk = $envVars['FILESYSTEM_DISK'] ?? '';
    
    if (!in_array($filesystemDisk, ['minio', 's3'])) {
        check(
            "MinIO connectivity",
            'warn',
            "FILESYSTEM_DISK is not set to 'minio' or 's3'",
            ['Disk' => $filesystemDisk, 'Status' => 'Skipped']
        );
        return;
    }
    
    // Check if S3 client can be instantiated
    if (file_exists('vendor/autoload.php')) {
        require_once 'vendor/autoload.php';
        
        try {
            $app = require_once 'bootstrap/app.php';
            $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
            
            $s3Client = Storage::disk($filesystemDisk)->getClient();
            
            check(
                "S3/MinIO client instantiation",
                'pass',
                "Client created successfully",
                [
                    'Disk' => $filesystemDisk,
                    'Endpoint' => $envVars['AWS_ENDPOINT'] ?? 'Not set',
                    'Bucket' => $envVars['AWS_BUCKET'] ?? 'Not set'
                ]
            );
            
            // Try to check if bucket exists (only if we have credentials)
            if (isset($envVars['AWS_ACCESS_KEY_ID']) && isset($envVars['AWS_SECRET_ACCESS_KEY'])) {
                try {
                    // Simple connectivity test
                    $endpoint = $envVars['AWS_ENDPOINT'] ?? '';
                    if (!empty($endpoint)) {
                        $ch = curl_init($endpoint);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                        
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                        
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $error = curl_error($ch);
                        curl_close($ch);
                        
                        if ($httpCode > 0) {
                            check(
                                "MinIO endpoint connectivity",
                                'pass',
                                "Endpoint reachable (HTTP $httpCode)",
                                [
                                    'Endpoint' => $endpoint,
                                    'HTTP Code' => $httpCode,
                                    'Response Time' => curl_getinfo($ch, CURLINFO_TOTAL_TIME) . 's'
                                ]
                            );
                        } else {
                            check(
                                "MinIO endpoint connectivity",
                                'fail',
                                "Cannot reach endpoint: $error",
                                [
                                    'Endpoint' => $endpoint,
                                    'Error' => $error
                                ]
                            );
                        }
                    }
                } catch (\Exception $e) {
                    check(
                        "MinIO endpoint connectivity",
                        'warn',
                        "Could not test: " . $e->getMessage(),
                        ['Error' => $e->getMessage()]
                    );
                }
            } else {
                check(
                    "MinIO credentials",
                    'warn',
                    "AWS_ACCESS_KEY_ID or AWS_SECRET_ACCESS_KEY not set",
                    ['Status' => 'Incomplete credentials']
                );
            }
        } catch (\Exception $e) {
            check(
                "S3/MinIO client",
                'fail',
                "Failed to create client: " . $e->getMessage(),
                ['Error' => $e->getMessage()]
            );
        }
    }
}

/**
 * Check Laravel application
 */
function checkLaravelApplication() {
    if (!file_exists('artisan')) {
        check(
            "Laravel application",
            'fail',
            "Artisan file not found - not a Laravel application",
            ['File' => 'artisan']
        );
        return;
    }
    
    check(
        "Artisan file exists",
        'pass',
        "Found",
        ['File' => 'artisan']
    );
    
    // Check if we can bootstrap Laravel
    if (file_exists('vendor/autoload.php')) {
        try {
            $app = require_once 'bootstrap/app.php';
            $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
            
            check(
                "Laravel bootstrap",
                'pass',
                "Application bootsrapped successfully",
                [
                    'Version' => app()->version(),
                    'Environment' => app()->environment(),
                    'Debug Mode' => app()->isLocal() ? 'true' : 'false'
                ]
            );
            
            // Check for required routes
            $routes = [
                'images.index' => 'Image listing route',
                'images.create' => 'Image upload route',
                'images.store' => 'Image store route',
                'images.show' => 'Image view route',
                'images.destroy' => 'Image delete route'
            ];
            
            foreach ($routes as $route => $name) {
                try {
                    $exists = route($route, [], false) !== null;
                    check(
                        "Route: $name",
                        $exists ? 'pass' : 'warn',
                        $exists ? "Route exists" : "Route not found",
                        ['Route' => $route]
                    );
                } catch (\Exception $e) {
                    check(
                        "Route: $name",
                        'warn',
                        "Route not defined: " . $e->getMessage(),
                        ['Route' => $route]
                    );
                }
            }
            
            // Check if Image model exists
            $modelPath = 'app/Models/Image.php';
            check(
                "Image model exists",
                file_exists($modelPath) ? 'pass' : 'fail',
                file_exists($modelPath) ? "Found" : "Model not found",
                ['File' => $modelPath]
            );
            
            // Check if ImageController exists
            $controllerPath = 'app/Http/Controllers/ImageController.php';
            check(
                "ImageController exists",
                file_exists($controllerPath) ? 'pass' : 'fail',
                file_exists($controllerPath) ? "Found" : "Controller not found",
                ['File' => $controllerPath]
            );
            
        } catch (\Exception $e) {
            check(
                "Laravel bootstrap",
                'fail',
                "Failed to bootstrap: " . $e->getMessage(),
                ['Error' => $e->getMessage()]
            );
        }
    } else {
        check(
            "Laravel application",
            'fail',
            "Vendor autoload not found - run 'composer install'",
            ['Status' => 'Dependencies missing']
        );
    }
}

/**
 * Check configuration files
 */
function checkConfiguration() {
    $configFiles = [
        'config/filesystems.php' => 'Filesystem configuration',
        'config/database.php' => 'Database configuration',
        'config/app.php' => 'Application configuration'
    ];
    
    foreach ($configFiles as $file => $name) {
        check(
            "$name exists",
            file_exists($file) ? 'pass' : 'fail',
            file_exists($file) ? "Found" : "Configuration file missing",
            ['File' => $file]
        );
    }
    
    // Check if filesystems.php has minio disk configured
    if (file_exists('config/filesystems.php')) {
        $content = file_get_contents('config/filesystems.php');
        $hasMinio = strpos($content, "'minio'") !== false;
        $hasS3 = strpos($content, "'s3'") !== false;
        
        if ($hasMinio || $hasS3) {
            check(
                "MinIO/S3 disk configured",
                'pass',
                $hasMinio ? "MinIO disk found" : "S3 disk found",
                [
                    'MinIO Disk' => $hasMinio ? 'Yes' : 'No',
                    'S3 Disk' => $hasS3 ? 'Yes' : 'No'
                ]
            );
        } else {
            check(
                "MinIO/S3 disk configured",
                'fail',
                "No MinIO or S3 disk configuration found in filesystems.php",
                ['Status' => 'Not configured']
            );
        }
    }
}

/**
 * Display summary
 */
function displaySummary() {
    global $results, $errors, $warnings, $colors, $jsonOutput;
    
    if ($jsonOutput) {
        $summary = [
            'total_checks' => count($results),
            'passed' => count(array_filter($results, fn($r) => $r['status'] === 'pass')),
            'failed' => $errors,
            'warnings' => $warnings,
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $results
        ];
        
        echo json_encode($summary, JSON_PRETTY_PRINT);
        return;
    }
    
    output('', 'reset');
    output(str_repeat('=', 60), 'cyan');
    output("  SUMMARY", 'cyan', true);
    output(str_repeat('=', 60), 'cyan');
    output('', 'reset');
    
    $passed = count($results) - $errors - $warnings;
    $total = count($results);
    
    output("  Total Checks:  $total", 'white');
    output("  Passed:        $passed", 'green');
    output("  Failed:        $errors", 'red');
    output("  Warnings:      $warnings", 'yellow');
    output('', 'reset');
    
    if ($errors === 0 && $warnings === 0) {
        output("  ✓ All checks passed! System is ready.", 'green', true);
    } elseif ($errors === 0) {
        output("  ⚠ All critical checks passed, but there are warnings.", 'yellow', true);
    } else {
        output("  ✗ Some critical checks failed. Please review above.", 'red', true);
    }
    
    output('', 'reset');
}

// Main execution
output('', 'reset');
output("╔══════════════════════════════════════════════════════════╗", 'cyan');
output("║       Laravel MinIO Upload - System Check Script      ║", 'cyan');
output("╚══════════════════════════════════════════════════════════╝", 'cyan');
output('', 'reset');
output("Running checks...", 'white');

// Run all checks
section("PHP Environment");
checkPhpVersion();
checkPhpExtensions();

section("Composer Dependencies");
checkComposerDependencies();

section("Environment Configuration");
checkEnvironment();

section("Directory Permissions");
checkPermissions();

section("Database Configuration");
checkDatabase();

section("MinIO Connectivity");
checkMinIOConnectivity();

section("Laravel Application");
checkLaravelApplication();

section("Configuration Files");
checkConfiguration();

// Display summary
displaySummary();

// Exit with appropriate code
exit($errors > 0 ? 1 : 0);