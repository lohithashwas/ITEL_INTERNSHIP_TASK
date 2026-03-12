<?php
header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_POST['token'] ?? '';

    if (empty($token)) {
        echo json_encode(['status' => 'error', 'message' => 'No session token provided']);
        exit;
    }

    try {
        // 1. Validate Session in Redis - STRICT
        $user_id = null;

        if ($redis) {
            $user_id = $redis->get('session:' . $token);
        } else {
             throw new Exception("Redis not available");
        }
        
        if (!$user_id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired session']);
            exit;
        }

        // 2. Fetch Profile from MongoDB using user_id - STRICT
        $profileArray = [];
        
        if ($mongoCollection) {
            $profile = $mongoCollection->findOne(['user_id' => (int)$user_id]);
            // Try string casting just in case
            if (!$profile) {
                 $profile = $mongoCollection->findOne(['user_id' => (string)$user_id]);
            }
            
            if ($profile) {
                $profileArray = (array)$profile;
                unset($profileArray['_id']); 
                unset($profileArray['user_id']); 
                if(isset($profileArray['created_at']) && $profileArray['created_at'] instanceof MongoDB\BSON\UTCDateTime){
                     $profileArray['created_at'] = $profileArray['created_at']->toDateTime()->format('Y-m-d H:i:s');
                }
            } else {
                $profileArray['error'] = "Profile details not found in MongoDB.";
                // We might still want to return name/email from MySQL if Mongo is partial?
                // But strict requirement says Mongo for details. 
                // Let's at least show what we have.
            }
        } else {
             throw new Exception("MongoDB connection missing");
        }

        // If simple success
        echo json_encode(['status' => 'success', 'data' => $profileArray]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
}
?>
