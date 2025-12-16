<?php
// config/email_config.php

// ============================
// KONFIGURASI UNTUK GMAIL
// ============================

// Server Settings
define('EMAIL_HOST', 'smtp.gmail.com');
define('EMAIL_PORT', 587);
define('EMAIL_SMTP_SECURE', 'tls'); // 'tls' atau 'ssl'

// Authentication - ISI DENGAN DATA ANDA!
define('EMAIL_USERNAME', 'email.anda@gmail.com'); // GANTI INI
define('EMAIL_PASSWORD', 'app_password_anda'); // GANTI INI (App Password)

// Sender Information
define('EMAIL_FROM', 'email.anda@gmail.com'); // GANTI INI
define('EMAIL_FROM_NAME', 'Toko Online Saya');

// Admin email untuk notifikasi
define('EMAIL_ADMIN', 'admin@tokosaya.com');
define('EMAIL_ADMIN_NAME', 'Admin Toko');

// Debug mode (0 = off, 2 = full debug)
define('EMAIL_DEBUG', 0);

// Timeout
define('EMAIL_TIMEOUT', 30);

// Website Info
define('WEBSITE_NAME', 'Ultras Smekda');
define('WEBSITE_URL', 'http://localhost/smekda/index.php');
?>