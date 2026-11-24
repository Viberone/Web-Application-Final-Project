<?php
// Shared database connection. Every page that needs the database will require this file.

$host = 'mysql-server'; // service name from docker-compose.yml
$user = 'root';
$pass = 'root';
$db   = 'note_management';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Cannot connect to the database.');
}