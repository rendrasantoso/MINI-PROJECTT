<?php
// logout.php
// File untuk proses logout admin

session_start();

// Hapus semua session variables
$_SESSION = array();

// Hapus cookie session jika ada
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Hapus remember me cookie jika ada
if (isset($_COOKIE['admin_remember'])) {
    setcookie('admin_remember', '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Set pesan sukses (opsional)
session_start();
$_SESSION['success'] = 'Anda telah berhasil logout!';

// Redirect ke halaman login
header('Location: login.php');
exit;
?>