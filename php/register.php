<?php
header('Content-Type: application/json');

try {
    // --- Database Configuration (Railway Optimized) ---
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }

    // --- Master Smart-Connect (Railway/Docker Exhaustive) ---
    mysqli_report(MYSQL_REPORT_OFF);

    $hosts = [getenv('MYSQLHOST'), getenv('MYSQL_HOST'), 'mysql.railway.internal', 'localhost'];
    $users = [getenv('MYSQLUSER'), getenv('MYSQL_USER'), 'root'];
    $passes = [getenv('MYSQLPASSWORD'), getenv('MYSQL_PASSWORD'), ''];
    $ports = [getenv('MYSQLPORT'), getenv('MYSQL_PORT'), 3306];
    $dbs = [getenv('MYSQLDATABASE'), getenv('MYSQL_DATABASE'), 'railway', 'internship_db'];

    // Also parse MYSQL_URL as a top priority if it exists
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
    
    // Clean arrays (remove nulls/false)
    $hosts = array_values(array_filter($hosts));
    $users = array_values(array_filter($users));
    $passes = array_values($passes); // Keep empty strings
    $ports = array_values(array_filter($ports));
    $dbs = array_values(array_filter($dbs));

    foreach ($hosts as $h) {
        foreach ($users as $u) {
            foreach ($passes as $p) {
                foreach ($ports as $prt) {
                    $mysql = @new mysqli($h, $u, $p, "", (int)$prt);
                    if (!$mysql->connect_error) {
                        break 4; // Absolute success!
                    }
                    $last_error = $mysql->connect_error;
                }
            }
        }
    }

    if (!$mysql || $mysql->connect_error) {
        throw new Exception("Connection failed after trying all variables. Last Error: $last_error [H:".($hosts[0]??'')." U:".($users[0]??'')."]");
    }

    // Select DB from our list
    $db_found = false;
    foreach ($dbs as $db) {
        if ($mysql->select_db($db)) {
            $db_found = true;
            break;
        }
    }

    if (!$db_found) {
        $target_db = $dbs[0] ?? 'railway';
        $mysql->query("CREATE DATABASE IF NOT EXISTS `$target_db`") or throw new Exception("DB Setup Fail: " . $mysql->error);
        $mysql->select_db($target_db);
    }

    // Auto-setup table
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
