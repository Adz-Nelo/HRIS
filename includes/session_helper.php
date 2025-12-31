<?php
// HRIS/includes/session_helper.php

function isLoggedIn() {
    return isset($_SESSION['employee_id']);
}

function isAdmin() {
    // Check both possible session variable names
    if (isset($_SESSION['role_name'])) {
        return strtolower($_SESSION['role_name']) === 'admin';
    } elseif (isset($_SESSION['role'])) {
        return strtolower($_SESSION['role']) === 'admin';
    }
    return false;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../login.html");
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: ../login.html");
        exit();
    }
}

function getCurrentUserRole() {
    if (isset($_SESSION['role_name'])) {
        return $_SESSION['role_name'];
    } elseif (isset($_SESSION['role'])) {
        return $_SESSION['role'];
    }
    return 'Guest';
}

function getCurrentUserName() {
    if (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
        return $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
    }
    return 'Guest User';
}
?>