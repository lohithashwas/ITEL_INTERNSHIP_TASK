<?php
// ONE-TIME PERMISSION FIX SCRIPT
// Visit this script once to grant root user proper permissions for IPv6 connections
// After running successfully, DELETE this file for security!

header('Content-Type: application/json');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

try {
    echo json_encode(['status' => 'info', 'message' => 'Attempting to fix MySQL permissions...']) . "\n";
    
    $get_any = function($key) {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return is_string($val) ? trim($val) : $val;
    };
    
    // Get MySQL connection details
    $host = $get_any('MYSQL_HOST') ?: $get_any('MYSQLHOST') ?: 'mysql.railway.internal';
    $user = $get_any('MYSQL_USER') ?: $get_any('MYSQLUSER') ?: 'root';
    $pass = $get_any('MYSQL_PASSWORD') ?: $get_any('MYSQLPASSWORD') ?: '';
    $port = $get_any('MYSQL_PORT') ?: $get_any('MYSQLPORT') ?: 3306;
    $db = $get_any('MYSQL_DATABASE') ?: $get_any('MYSQLDATABASE') ?: 'railway';

    // Validate identifiers to prevent SQL injection from misconfigured environment variables
    if (!preg_match('/^\w+$/', $user)) {
        throw new Exception("Invalid MySQL username format.");
    }
    if (!preg_match('/^\w+$/', $db)) {
        throw new Exception("Invalid MySQL database name format.");
    }
    
    echo json_encode(['status' => 'info', 'message' => "Connecting to MySQL at $host:$port as $user..."]) . "\n";
    
    // Connect to MySQL
    $mysql = new mysqli($host, $user, $pass, $db, (int)$port);
    
    if ($mysql->connect_error) {
        throw new Exception("Connection failed: " . $mysql->connect_error);
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Connected to MySQL successfully!']) . "\n";
    
    // Try to grant permissions (this might fail if user doesn't have GRANT privilege, but worth trying)
    $grant_queries = [
        "GRANT ALL PRIVILEGES ON $db.* TO '$user'@'%'",
        "FLUSH PRIVILEGES"
    ];
    
    foreach ($grant_queries as $query) {
        echo json_encode(['status' => 'info', 'message' => "Executing: $query"]) . "\n";
        
        if ($mysql->query($query)) {
            echo json_encode(['status' => 'success', 'message' => "✓ Executed successfully"]) . "\n";
        } else {
            echo json_encode(['status' => 'warning', 'message' => "⚠ Query failed (this is normal if you don't have GRANT privileges): " . $mysql->error]) . "\n";
        }
    }
    
    // Verify connection works
    $result = $mysql->query("SELECT USER(), DATABASE()");
    if ($result) {
        $row = $result->fetch_row();
        echo json_encode(['status' => 'success', 'message' => "Current user: {$row[0]}, Database: {$row[1]}"]) . "\n";
    }
    
    // Check if users table exists
    $result = $mysql->query("SHOW TABLES LIKE 'users'");
    if ($result && $result->num_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => '✓ users table exists']) . "\n";
    } else {
        echo json_encode(['status' => 'warning', 'message' => '⚠ users table not found']) . "\n";
    }
    
    $mysql->close();
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Permission fix attempted! Now try registering on your site.',
        'next_steps' => [
            '1. Try registering a user on your site',
            '2. If it works, DELETE this fix-permissions.php file immediately for security!',
            '3. If the issue persists, the root user might not have GRANT privileges in Railway'
        ]
    ]) . "\n";
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error: ' . $e->getMessage(),
        'note' => 'The actual issue is that Railway MySQL root user needs GRANT privileges. Contact Railway support or use Railway CLI to run the appropriate GRANT ALL PRIVILEGES statement and FLUSH PRIVILEGES.'
    ]) . "\n";
}
?>
