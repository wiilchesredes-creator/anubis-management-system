<?php
function conectar() {
    $host = 'localhost';
    $dbname = 'anubisbox';
    $username = 'root';
    $password = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("❌ Error de conexión: " . $e->getMessage());
    }
}


?>