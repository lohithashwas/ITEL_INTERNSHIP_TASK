<?php
header('Content-Type: application/json');

try {
    // --- Database Configuration (Railway Optimized) ---
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }

    // --- Definitive Master Connect (Railway/Docker) ---
    mysqli_report(MYSQLI_REPORT_OFF);

    $get_any = function($key) {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return is_string($val) ? trim($val) : $val;
    };

    $env_hosts = array_filter([$get_any('MYSQL_HOST'), $get_any('MYSQLHOST'), 'mysql.railway.internal', 'mysql', '127.0.0.1']);
    $env_users = array_filter([$get_any('MYSQL_USER'), $get_any('MYSQLUSER'), 'root']);
    $env_passes = [(string)$get_any('MYSQL_PASSWORD'), (string)$get_any('MYSQLPASSWORD'), ""];
    $env_ports = array_filter([$get_any('MYSQL_PORT'), $get_any('MYSQLPORT'), 3306]);
    $env_dbs   = array_filter([$get_any('MYSQL_DATABASE'), $get_any('MYSQLDATABASE'), 'railway', 'internship_db']);

    $mysql = null;
    $history = [];
    
    $hosts = array_values(array_unique(array_filter($env_hosts)));
    $users = array_values(array_unique(array_filter($env_users)));
    $passes = array_values(array_unique($env_passes));
    $ports = array_values(array_unique(array_filter($env_ports)));

    // DNS Check
    $dns_info = [];
    foreach($hosts as $h) {
        $ip = gethostbyname($h);
        $dns_info[] = "$h=" . ($ip === $h ? "DNS_FAIL" : $ip);
    }

    foreach ($hosts as $h) {
        foreach ($users as $u) {
            foreach ($passes as $p) {
                foreach ($ports as $prt) {
                    foreach ($dbs as $db) {
                        // TRY 1: Connect with DB name (Essential for Railway permissions)
                        $mysql = @new mysqli($h, $u, $p, $db, (int)$prt);
                        if (!$mysql->connect_error) break 5;
                        $history[] = "$h:$prt($u)->$db=" . $mysql->connect_error;
                    }
                    // TRY 2: Connect without DB name (Backup)
                    $mysql = @new mysqli($h, $u, $p, "", (int)$prt);
                    if (!$mysql->connect_error) break 4;
                    $history[] = "$h:$prt($u)=" . $mysql->connect_error;
                }
            }
        }
    }

    if (!$mysql || $mysql->connect_error) {
        $masked_p = (strlen($env_passes[0]) > 0) ? ($env_passes[0][0] . "***") : "EMPTY";
        $diag = "DNS: " . implode(",", $dns_info) . " | P_Hint: $masked_p";
        throw new Exception("MySQL Fail. $diag. Hist: " . implode(" | ", array_unique($history)));
    }

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
