<?php
session_start();
require_once 'database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function isGuru() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'guru';
}

function checkAccess($requiredRole) {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
    
    if ($requiredRole == 'admin' && !isAdmin()) {
        header('Location: dashboard_guru.php');
        exit();
    }
}

function getCurrentUserMapel() {
    return $_SESSION['mata_pelajaran_id'] ?? null;
}
?>