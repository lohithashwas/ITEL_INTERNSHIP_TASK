<?php
header('Content-Type: application/json');

try {
    // --- Database Configuration (Railway Optimized) ---
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }

    // --- Extreme Master Connect (Railway/Docker Deep Audit) ---
    mysqli_report(MYSQLI_REPORT_OFF);

    $get_raw = function($key) { return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key); };

    // 1. Gather all possible credentials
    $raw_passes = array_filter([(string)$get_raw('MYSQL_PASSWORD'), (string)$get_raw('MYSQLPASSWORD')], 'strlen');
    $passes = [""];
    foreach($raw_passes as $rp) {
        $passes[] = $rp;
        $passes[] = trim($rp);
    }
    $passes = array_values(array_unique($passes));

    $users = array_values(array_unique(array_filter([$get_raw('MYSQL_USER'), $get_raw('MYSQLUSER'), 'root', 'railway', 'intern', 'mysql'])));
    $hosts = array_values(array_unique(array_filter([$get_raw('MYSQL_HOST'), $get_raw('MYSQLHOST'), 'mysql.railway.internal', 'mysql', 'db', 'database', '127.0.0.1'])));
    $ports = array_values(array_unique(array_filter([(int)$get_raw('MYSQL_PORT'), (int)$get_raw('MYSQLPORT'), 3306])));
    $dbs   = array_values(array_unique(array_filter([$get_raw('MYSQL_DATABASE'), $get_raw('MYSQLDATABASE'), 'railway', 'internship_db'])));

    $mysql = null;
    $history = [];
    $dns_info = [];
    foreach($hosts as $h) { $ip = gethostbyname($h); $dns_info[] = "$h=" . ($ip === $h ? "DNS_FAIL" : $ip); }

    foreach ($hosts as $h) {
        foreach ($users as $u) {
            foreach ($passes as $p) {
                foreach ($ports as $prt) {
                    foreach ($dbs as $db) {
                        $mysql = @new mysqli($h, $u, $p, $db, (int)$prt);
                        if (!$mysql->connect_error) break 5;
                        $history[] = "$h($u)->$db=" . $mysql->connect_error;
                    }
                    $mysql = @new mysqli($h, $u, $p, "", (int)$prt);
                    if (!$mysql->connect_error) break 4;
                    $history[] = "$h($u)=" . $mysql->connect_error;
                }
            }
        }
    }

    if (!$mysql || $mysql->connect_error) {
        $primary_p = $raw_passes[0] ?? "";
        $p_diag = (strlen($primary_p) > 0) ? ($primary_p[0] . "...(Len:".strlen($primary_p).")") : "EMPTY";
        $diag_msg = "DNS: " . implode(",", $dns_info) . " | P_Diag: $p_diag";
        throw new Exception("MySQL Reject. $diag_msg. Hist: " . implode(" | ", array_slice(array_unique($history), -4)));
    }

    $db_found = false;
    foreach ($env_dbs as $db) {
        if ($mysql->select_db($db)) {
            $db_found = true;
            break;
        }
    }
    if (!$db_found) {
        $target_db = $env_dbs[0] ?? 'railway';
        $mysql->query("CREATE DATABASE IF NOT EXISTS `$target_db`") or throw new Exception("DB Setup Fail");
        $mysql->select_db($target_db);
    }

    $mysql->query("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) UNIQUE NOT NULL, password VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

    $mongoCollection = null;
    try {
        $mongoUri = getenv('MONGODB_URI') ?: "mongodb://localhost:27017";
        $mongoClient = new MongoDB\Client($mongoUri);
        $mongoDbName = getenv('MONGODB_DB') ?: 'internship_data';
        $mongoCollection = $mongoClient->$mongoDbName->profiles;
    } catch (Exception $e) { }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $age = $_POST['age'] ?? '';
        $dob = $_POST['dob'] ?? '';
        $contact = $_POST['contact'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            exit;
        }

        $stmt = $mysql->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Email already registered']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $insert_stmt = $mysql->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
        $insert_stmt->bind_param("ss", $email, $hashed_password);

        if ($insert_stmt->execute()) {
            $user_id = $insert_stmt->insert_id;
            $insert_stmt->close();

            if ($mongoCollection) {
                $mongoCollection->insertOne([
                    'user_id' => $user_id,
                    'name' => $name,
                    'email' => $email,
                    'age' => $age,
                    'dob' => $dob,
                    'contact' => $contact,
                    'created_at' => new MongoDB\BSON\UTCDateTime()
                ]);
                echo json_encode(['status' => 'success', 'message' => 'Registration successful']);
            } else {
                throw new Exception("MongoDB service not available.");
            }
        } else {
            throw new Exception("MySQL Insert Error: " . $insert_stmt->error);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()]);
}
?>
