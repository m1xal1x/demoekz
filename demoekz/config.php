<?php
session_start();

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=cleaning_portal;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch(PDOException $e) {
    die("Ошибка подключения к базе данных");
}

function validate($data, $type) {
    $patterns = [
        'login' => '/^[a-zA-Z0-9_]{8,}$/',
        'password' => '/^.{8,}$/',
        'phone' => '/^\+7\(\d{3}\)-\d{3}-\d{2}-\d{2}$/',
        'name' => '/^[а-яА-ЯёЁ\s\-]{5,}$/u',
        'email' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'
    ];
    return isset($patterns[$type]) ? preg_match($patterns[$type], $data) : false;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isUser() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'user';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}
?>