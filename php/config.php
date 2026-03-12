<?php
// Enable error reporting for debugging (but keep display_errors off for JSON)
ini_set('display_errors', 1);
ini_set('error_log', __DIR__ . '/../php_errors.log');
error_reporting(E_ALL);

header('Content-Type: application/json');

// --- Composer Autoload ---
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Composer dependencies not installed. Run "php composer.phar install"']);
    exit;
}

// --- MySQL Configuration ---
$mysql_host = getenv('MYSQL_HOST') ?: 'localhost';
$mysql_user = getenv('MYSQL_USER') ?: 'root';
$mysql_pass = getenv('MYSQL_PASSWORD') ?: '';
$mysql_db   = getenv('MYSQL_DATABASE') ?: 'internship_db';
$mysql_port = getenv('MYSQL_PORT') ?: 3306;

try {
    $mysql = new mysqli($mysql_host, $mysql_user, $mysql_pass, $mysql_db, (int)$mysql_port);
    if ($mysql->connect_error) {
        throw new Exception("MySQL Connection Failed: " . $mysql->connect_error);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
    exit;
}

// --- MongoDB Configuration ---
$mongoCollection = null;
try {
    $mongoUri = getenv('MONGODB_URI') ?: "mongodb://localhost:27017";
    $mongoClient = new MongoDB\Client($mongoUri);
    $mongoDbName = getenv('MONGODB_DB') ?: 'internship_data';
    $mongoDb = $mongoClient->$mongoDbName;
    $mongoCollection = $mongoDb->profiles;
} catch (Exception $e) {
    // Strict requirement: MongoDB must be used.
}

// --- Redis Configuration ---
$redis = null;
try {
    $redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
    $redisPort = getenv('REDIS_PORT') ?: 6379;
    $redisPassword = getenv('REDIS_PASSWORD') ?: null;
    $redisScheme = getenv('REDIS_SCHEME') ?: 'tcp';

    $params = [
        'scheme' => $redisScheme,
        'host'   => $redisHost,
        'port'   => (int)$redisPort,
    ];
    
    if ($redisPassword) {
        $params['password'] = $redisPassword;
    }

    $redis = new Predis\Client($params);
    $redis->connect();
} catch (Exception $e) {
    // Strict requirement: Redis is needed.
}
// Debug check
if (!$mysql) {
    error_log("CONFIG CHECK: MySQL variable is null!");
} else {
    error_log("CONFIG CHECK: MySQL variable is set. Host: " . $mysql->host_info);
}
?>
