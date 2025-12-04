<?php
session_start();

// Koneksi database
$host = "localhost";
$user = "root";
$pass = "";
$db = "smekda_jersey";

// Buat koneksi
$conn = new mysqli($host, $user, $pass, $db);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Cek apakah form di-submit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Ambil data dari form
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    
    // Query untuk cek user
    $query = "SELECT * FROM admin WHERE username = '$username' LIMIT 1";
    $result = $conn->query($query);
    
    if ($result->num_rows == 1) {
        $admin = $result->fetch_assoc();
        
        // Verifikasi password
        $password_db = trim($admin['password']);
        $password_input = trim($password);
        
        // Cek password (plain text atau hash)
        if ($password_input === $password_db || password_verify($password_input, $password_db)) {
            // Login berhasil
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_nama'] = isset($admin['nama_lengkap']) ? $admin['nama_lengkap'] : $admin['nama'];
            
            // Redirect ke dashboard
            header('Location: views/dashboard.php');
            exit;
        } else {
            // Password salah
            $_SESSION['error'] = "Password salah!";
            header('Location: login.php');
            exit;
        }
    } else {
        // Username tidak ditemukan
        $_SESSION['error'] = "Username tidak ditemukan!";
        header('Location: login.php');
        exit;
    }
    
} else {
    // Jika diakses langsung tanpa POST
    header('Location: login.php');
    exit;
}

$conn->close();
?>