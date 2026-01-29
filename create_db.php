<?php
$host = '127.0.0.1';
$user = 'root';
$pass = ''; // Default XAMPP
$dbName = 'berita_acara_ujian';
$ports = [3306, 3307, 3308, 3309];

foreach ($ports as $port) {
    echo "Trying port $port...\n";
    try {
        $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbName");
        echo "SUCCESS: Database '$dbName' created/exists on port $port.\n";
        exit(0);
    } catch (PDOException $e) {
        // If it's a connection refused, continue. Use specific error codes if possible, strictly 2002 often.
        if (strpos($e->getMessage(), 'actively refused') !== false || $e->getCode() == 2002) {
            echo "Port $port refused.\n";
            continue;
        }
        // If access denied (meaning port is open but pass is wrong), we found the port!
        if ($e->getCode() == 1045) { // Access denied
            echo "SUCCESS-BUT-AUTH-ERROR: Found MySQL on port $port, but password denied.\n";
            exit(2);
        }
        echo "Error on port $port: " . $e->getMessage() . "\n";
    }
}

echo "FAILED: Could not connect to MySQL on any common ports.\n";
exit(1);
?>