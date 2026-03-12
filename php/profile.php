<?php
header('Content-Type: application/json');

try {
    // --- Database Configuration (Railway Optimized) ---
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }

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

    $mongoCollection = null;
    try {
        $mongoUri = getenv('MONGODB_URI') ?: "mongodb://localhost:27017";
        $mongoClient = new MongoDB\Client($mongoUri);
        $mongoDbName = getenv('MONGODB_DB') ?: 'internship_data';
        $mongoCollection = $mongoClient->$mongoDbName->profiles;
    } catch (Exception $e) { }


    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $token = $_POST['token'] ?? '';

        if (empty($token)) {
            echo json_encode(['status' => 'error', 'message' => 'No session token provided']);
            exit;
        }

        if (!$redis) throw new Exception("Redis service not available.");

        $user_id = $redis->get('session:' . $token);
        if (!$user_id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired session']);
            exit;
        }

        if (!$mongoCollection) throw new Exception("MongoDB service not available.");

        $profile = $mongoCollection->findOne(['user_id' => (int)$user_id]);
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
            echo json_encode(['status' => 'success', 'data' => $profileArray]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Profile details not found in MongoDB.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()]);
}
?>
