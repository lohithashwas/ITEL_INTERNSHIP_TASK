<?php
header('Content-Type: application/json');

try {
    // --- Database Configuration (Railway Optimized) ---
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }

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

    $mysql = @new mysqli($mysql_host, $mysql_user, $mysql_pass, $mysql_db, (int)$mysql_port);
    if ($mysql->connect_error) {
        throw new Exception("Database Connection Failed: " . $mysql->connect_error);
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
