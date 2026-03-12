<?php
header('Content-Type: application/json');

try {
    // --- Database Configuration (Railway Optimized) ---
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }

    // --- Master Smart-Connect (Railway/Docker Exhaustive) ---
    mysqli_report(MYSQLI_REPORT_OFF);

    $env_keys = array_keys(array_merge($_ENV, getenv()));
    $mysql_keys = array_filter($env_keys, function($k) { return strpos($k, 'MYSQL') !== false; });
    $diag_keys = "Keys: " . implode(", ", $mysql_keys);

    $hosts = [getenv('MYSQLHOST'), getenv('MYSQL_HOST'), 'mysql.railway.internal', '127.0.0.1'];
    $users = [getenv('MYSQLUSER'), getenv('MYSQL_USER'), 'root'];
    $passes = [getenv('MYSQLPASSWORD'), getenv('MYSQL_PASSWORD'), ''];
    $ports = [getenv('MYSQLPORT'), getenv('MYSQL_PORT'), 3306];
    $dbs = [getenv('MYSQLDATABASE'), getenv('MYSQL_DATABASE'), 'railway', 'internship_db'];

    if ($url = getenv('MYSQL_URL')) {
        $parsed = parse_url($url);
        array_unshift($hosts, $parsed['host'] ?? null);
        array_unshift($users, $parsed['user'] ?? null);
        array_unshift($passes, $parsed['pass'] ?? null);
        array_unshift($ports, $parsed['port'] ?? null);
        array_unshift($dbs, ltrim($parsed['path'] ?? '', '/') ?: null);
    }

    $mysql = null;
    $last_error = "";
    
    $hosts = array_values(array_unique(array_filter($hosts)));
    $users = array_values(array_unique(array_filter($users)));
    $passes = array_values(array_unique($passes));
    $ports = array_values(array_unique(array_filter($ports)));
    $dbs = array_values(array_unique(array_filter($dbs)));

    foreach ($hosts as $h) {
        foreach ($users as $u) {
            foreach ($passes as $p) {
                foreach ($ports as $prt) {
                    foreach ([true, false] as $with_db) {
                        $db_to_use = $with_db ? ($dbs[0] ?? 'railway') : "";
                        $mysql = @new mysqli($h, $u, $p, $db_to_use, (int)$prt);
                        if (!$mysql->connect_error) break 5;
                        $last_error = $mysql->connect_error;
                    }
                }
            }
        }
    }

    if (!$mysql || $mysql->connect_error) throw new Exception("MySQL Fail. $diag_keys. Last Err: $last_error");

    $db_found = false;
    foreach ($dbs as $db) {
        if ($mysql->select_db($db)) {
            $db_found = true;
            break;
        }
    }
    if (!$db_found) {
        $target_db = $dbs[0] ?? 'railway';
        $mysql->query("CREATE DATABASE IF NOT EXISTS `$target_db`") or throw new Exception("DB Setup Fail");
        $mysql->select_db($target_db);
    }

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
