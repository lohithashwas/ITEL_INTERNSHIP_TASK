<?php
// Forward Vercel requests to the appropriate PHP file
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve static files directly? No, Vercel handles that, but for PHP files:
$file = __DIR__ . '/..' . $uri;

if (is_file($file)) {
    // If it's a PHP file, include it
    if (str_ends_with($file, '.php')) {
        require $file;
    } else {
        // Static file
        return false; 
    }
} else {
    // Default to index.html or handle 404
    if ($uri === '/' || $uri === '/index.html') {
        readfile(__DIR__ . '/../login.html'); // Serve login as home?
    } else {
        http_response_code(404);
        echo "Not Found";
    }
}
?>
