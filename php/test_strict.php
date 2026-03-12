<?php
require_once 'config.php';

echo "--- Connection Test ---\n";
echo "MySQL: " . ($mysql ? "Connected" : "Failed") . "\n";
echo "MongoDB Collection: " . ($mongoCollection ? "Connected" : "Failed") . "\n";
echo "Redis: " . ($redis ? "Connected" : "Failed") . "\n";

if ($redis) {
    echo "Redis Ping: ";
    try {
        $redis->set('test_ping', 'pong');
        echo $redis->get('test_ping') . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

if ($mongoCollection) {
    echo "MongoDB Ping: ";
    try {
        $count = $mongoCollection->countDocuments([]);
        echo "OK (Count: $count)\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
