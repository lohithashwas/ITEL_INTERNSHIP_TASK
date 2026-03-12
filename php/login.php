<?php
header('Content-Type: application/json');

try {
    // --- Database Configuration (Railway Optimized) ---
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }

    // --- Improved Database Configuration (Railway Optimized) ---
    $mysql_host = getenv('MYSQLHOST') ?: getenv('MYSQL_HOST') ?: 'localhost';
    $mysql_user = getenv('MYSQLUSER') ?: getenv('MYSQL_USER') ?: 'root';
    $mysql_pass = getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '';
    $mysql_db   = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: 'railway';
    $mysql_port = getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: 3306;

    if ($url = getenv('MYSQL_URL')) {
        $parsed = parse_url($url);
        $mysql_host = $parsed['host'] ?? $mysql_host;
        $mysql_user = $parsed['user'] ?? $mysql_user;
        $mysql_pass = $parsed['pass'] ?? $mysql_pass;
        $mysql_db   = ltrim($parsed['path'] ?? '', '/') ?: $mysql_db;
        $mysql_port = $parsed['port'] ?? $mysql_port;
    }

    // 1. Connect to MySQL
    $mysql = @new mysqli($mysql_host, $mysql_user, $mysql_pass, "", (int)$mysql_port);
    if ($mysql->connect_error) {
        $debugInfo = "Host: $mysql_host, User: $mysql_user, Port: $mysql_port";
        throw new Exception("Connection Failed. Check your Railway 'Variables'. Details: " . $mysql->connect_error . " [$debugInfo]");
    }

    // 2. Select DB
    if (!$mysql->select_db($mysql_db)) {
        $mysql->query("CREATE DATABASE IF NOT EXISTS `$mysql_db`") or throw new Exception("Could not create DB '$mysql_db': " . $mysql->error);
        $mysql->select_db($mysql_db);
    }
    
    // 3. Auto-setup table
    $mysql->query("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) UNIQUE NOT NULL, password VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

    $redis = null;
    try {
        $redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
        $redisPort = getenv('REDIS_PORT') ?: 6379;
        $redisPassword = getenv('REDIS_PASSWORD') ?: null;
        $params = ['scheme' => 'tcp', 'host' => $redisHost, 'port' => (int)$redisPort];
        if ($redisPassword) $params['password'] = $redisPassword;
        $redis = new Predis\Client($params);
        $redis->connect();
    } catch (Exception $e) { }


    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing credentials']);
            exit;
        }

        $stmt = $mysql->prepare("SELECT id, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $token = bin2hex(random_bytes(32)); 
                
                if ($redis) {
                    $redis->setex('session:' . $token, 3600, $user['id']);
                    echo json_encode(['status' => 'success', 'token' => $token]);
                } else {
                    throw new Exception("Redis service not available.");
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'User not found']);
        }
        if(isset($stmt)) $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()]);
}
?>
