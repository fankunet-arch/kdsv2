<?php
$uri = $_SERVER["REQUEST_URI"];
if (strpos($uri, "/kds/") === 0) {
    $_SERVER["SCRIPT_NAME"] = str_replace("/kds", "", $_SERVER["SCRIPT_NAME"]);
    $_SERVER["SCRIPT_FILENAME"] = __DIR__ . "/public" . $uri;
    if (is_dir($_SERVER["SCRIPT_FILENAME"])) {
        $_SERVER["SCRIPT_FILENAME"] .= "/index.php";
    }
    // Simple file serving
    if (file_exists($_SERVER["SCRIPT_FILENAME"]) && !is_dir($_SERVER["SCRIPT_FILENAME"])) {
        // Let PHP serve it if it is .php, otherwise false to let built-in serve static
        if (pathinfo($_SERVER["SCRIPT_FILENAME"], PATHINFO_EXTENSION) == "php") {
            require $_SERVER["SCRIPT_FILENAME"];
            return true;
        }
        return false;
    }
    // Fallback for KDS (try index.php) - mimicking nginx try_files
    $_SERVER["SCRIPT_FILENAME"] = __DIR__ . "/public/kds/index.php";
    require $_SERVER["SCRIPT_FILENAME"];
    return true;
}

if (strpos($uri, "/pos/") === 0) {
    $_SERVER["SCRIPT_NAME"] = str_replace("/pos", "", $_SERVER["SCRIPT_NAME"]);
    $_SERVER["SCRIPT_FILENAME"] = __DIR__ . "/public" . $uri;
    if (is_dir($_SERVER["SCRIPT_FILENAME"])) {
        $_SERVER["SCRIPT_FILENAME"] .= "/index.php";
    }
    if (file_exists($_SERVER["SCRIPT_FILENAME"]) && !is_dir($_SERVER["SCRIPT_FILENAME"])) {
        if (pathinfo($_SERVER["SCRIPT_FILENAME"], PATHINFO_EXTENSION) == "php") {
            require $_SERVER["SCRIPT_FILENAME"];
            return true;
        }
        return false;
    }
     // Fallback for POS
    $_SERVER["SCRIPT_FILENAME"] = __DIR__ . "/public/pos/index.php";
    require $_SERVER["SCRIPT_FILENAME"];
    return true;
}

// Default 404
http_response_code(404);
echo "Not Found";
