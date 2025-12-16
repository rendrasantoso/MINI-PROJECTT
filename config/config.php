<?php
/**
 * CONFIG.PHP - Koneksi Database + Midtrans Manual
 */

// Konfigurasi database
$host = 'localhost';
$dbname = 'smekda_kece';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("❌ Koneksi database gagal: " . $e->getMessage());
}

// PERBAIKAN PATH: Gunakan __DIR__ untuk path absolut ke root
$midtransPath = dirname(__DIR__) . '/midtrans/';  // ← dari config/ ke root

// Include Midtrans files secara manual
if (file_exists($midtransPath . 'Midtrans.php')) {
    require_once $midtransPath . 'Midtrans.php';
    require_once $midtransPath . 'Config.php';
    require_once $midtransPath . 'Snap.php';
    require_once $midtransPath . 'ApiRequestor.php';
} else {
    // Debug info
    die("❌ Midtrans library tidak ditemukan. Path yang dicari: " . $midtransPath . 'Midtrans.php' . 
        "<br>Current dir: " . __DIR__ . 
        "<br>Parent dir: " . dirname(__DIR__));
}

// Konfigurasi Midtrans
define('MIDTRANS_SERVER_KEY', 'Mid-server-TFHBPHytAPEHAosjWnXUKle-');
define('MIDTRANS_CLIENT_KEY', 'Mid-client-zq0yfGTxNTS41XMP');
define('MIDTRANS_IS_PRODUCTION', false);

// Set Midtrans config
if (class_exists('Midtrans\Config')) {
    Midtrans\Config::$serverKey = MIDTRANS_SERVER_KEY;
    Midtrans\Config::$isProduction = MIDTRANS_IS_PRODUCTION;
    Midtrans\Config::$isSanitized = true;
    Midtrans\Config::$is3ds = true;
}

// Base URL
define('BASE_URL', 'http://localhost/smekda');

// Helper functions
function redirect($url) {
    header("Location: $url");
    exit;
}

function format_currency($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
}

// Auto start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>