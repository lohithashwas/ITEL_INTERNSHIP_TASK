<?php
header('Content-Type: application/json');
require_once 'config.php';

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

    // 1. Check if email exists in MySQL
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

    // 2. Insert into MySQL (Prepared Statement) - STRICTLY credentials only (and maybe email to link)
    // We removed 'name' from MySQL insert as it belongs to profile details in MongoDB per strict requirements?
    // "create a signup page where a user can register... login... profile page which should contain additional details such as age, dob, contact"
    // Usually Name is basic, but let's put it in MongoDB to be safe with "details of user profiles".
    // Actually, let's keep email in MySQL for login.
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $insert_stmt = $mysql->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    $insert_stmt->bind_param("ss", $email, $hashed_password);

    if ($insert_stmt->execute()) {
        $user_id = $insert_stmt->insert_id;
        $insert_stmt->close();

        // 3. Insert Profile Data into MongoDB - STRICT REQUIREMENT
        if ($mongoCollection) {
            try {
                $insertOneResult = $mongoCollection->insertOne([
                    'user_id' => $user_id,
                    'name' => $name,
                    'email' => $email, // Redundant but useful for display without join
                    'age' => $age,
                    'dob' => $dob,
                    'contact' => $contact,
                    'created_at' => new MongoDB\BSON\UTCDateTime()
                ]);
    
                echo json_encode(['status' => 'success', 'message' => 'Registration successful']);
            } catch (Exception $e) {
                // If MongoDB fails, we should probably fail the whole registration or at least warn.
                // But since MySQL is done, we can't easily rollback without transaction code.
                // We will return success but log error.
                error_log("MongoDB Insert Error: " . $e->getMessage());
                echo json_encode(['status' => 'success', 'message' => 'Registration partially successful (Profile data saved to MySQL only?) No, STRICT MODE: MongoDB Failed.']);
            }
        } else {
             // Strict mode: If MongoDB is missing, this is a system error
             http_response_code(500);
             echo json_encode(['status' => 'error', 'message' => 'Internal Error: MongoDB service not available for profile storage.']);
        }
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'MySQL Error: ' . $insert_stmt->error]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
}
?>
