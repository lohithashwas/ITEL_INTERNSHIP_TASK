<?php
header('Content-Type: application/json');
// --- Database Configuration (Merged for strict folder structure) ---
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

$mysql_url = getenv('MYSQL_URL');
if ($mysql_url) {
    $url = parse_url($mysql_url);
    $mysql_host = $url['host'];
    $mysql_user = $url['user'];
    $mysql_pass = $url['pass'];
    $mysql_db   = ltrim($url['path'], '/');
    $mysql_port = $url['port'] ?? 3306;
} else {
    $mysql_host = getenv('MYSQL_HOST') ?: 'localhost';
    $mysql_user = getenv('MYSQL_USER') ?: 'root';
    $mysql_pass = getenv('MYSQL_PASSWORD') ?: '';
    $mysql_db   = getenv('MYSQL_DATABASE') ?: 'internship_db';
    $mysql_port = getenv('MYSQL_PORT') ?: 3306;
}

try {
    $mysql = @new mysqli($mysql_host, $mysql_user, $mysql_pass, $mysql_db, (int)$mysql_port);
} catch (Exception $e) { $mysql = null; }

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

    // 1. Verify against MySQL
    $stmt = $mysql->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // 3. Store in Redis - STRICT REQUIREMENT
            // Key: session:token -> Value: user_id
            $token = bin2hex(random_bytes(32)); 
            
            if ($redis) {
                try {
                    $redis->setex('session:' . $token, 3600, $user['id']);
                    echo json_encode(['status' => 'success', 'token' => $token]);
                } catch (Exception $e) {
                    echo json_encode(['status' => 'error', 'message' => 'Redis Error: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Redis service not configured or reachable.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }
    $stmt->close();

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
}
?>
