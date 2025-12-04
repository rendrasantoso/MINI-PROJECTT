<?php
// models/crud_pesanan.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// PERBAIKAN: Boleh diakses tanpa login untuk CREATE (form pemesanan user)
if ($_SERVER['REQUEST_METHOD'] != 'POST' || ($_POST['action'] ?? '') != 'create') {
    // Untuk action selain CREATE dari user, wajib login admin
    if (!isset($_SESSION['admin_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

// PERBAIKAN: Cek path config
$config_path = __DIR__ . '/../config/config.php';
if (!file_exists($config_path)) {
    die("Config file not found at: " . $config_path);
}
require_once $config_path;

// Ambil action
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
error_log("CRUD Pesanan - Action: " . $action . " | Method: " . $_SERVER['REQUEST_METHOD']);

try {
    // ==================== ACTION CREATE (Dari Form User) ====================
    if ($action == 'create' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        error_log("Processing CREATE from user form...");
        
        // Ambil data dari form
        $nama = $_POST['nama'] ?? '';
        $no_hp = $_POST['no_hp'] ?? '';
        $produk = $_POST['produk'] ?? '';
        $jumlah = $_POST['jumlah'] ?? 1;
        $catatan = $_POST['catatan'] ?? '';
        
        // Validasi data wajib
        if (empty($nama) || empty($no_hp) || empty($produk) || $jumlah < 1) {
            $_SESSION['error'] = 'Data tidak lengkap! Silakan isi semua field yang wajib.';
            header('Location: ../index.php#form-pemesanan');
            exit;
        }
        
        // Hitung total harga (dari harga produk)
        // PERBAIKAN: Ambil harga dari database berdasarkan nama produk
        $stmt = $pdo->prepare("SELECT harga FROM merchandise WHERE nama_produk = ?");
        $stmt->execute([$produk]);
        $merch = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$merch) {
            $_SESSION['error'] = 'Produk tidak ditemukan!';
            header('Location: ../index.php#form-pemesanan');
            exit;
        }
        
        $harga = $merch['harga'];
        $total = $harga * $jumlah;
        
        // Handle file upload kartu pelajar
        $foto_kartu_pelajar = null;
        if (isset($_FILES['kartu_pelajar']) && $_FILES['kartu_pelajar']['error'] == 0) {
            $upload_dir = '../upload/kartu_pelajar/';
            
            // Buat folder jika belum ada
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['kartu_pelajar']['name']);
            $file_path = $upload_dir . $file_name;
            
            // Validasi file
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = $_FILES['kartu_pelajar']['type'];
            $file_size = $_FILES['kartu_pelajar']['size'];
            
            if (!in_array($file_type, $allowed_types)) {
                $_SESSION['error'] = 'Hanya file gambar (JPG, PNG, GIF) yang diperbolehkan!';
                header('Location: ../index.php#form-pemesanan');
                exit;
            }
            
            if ($file_size > 5 * 1024 * 1024) { // 5MB
                $_SESSION['error'] = 'Ukuran file terlalu besar! Maksimal 5MB.';
                header('Location: ../index.php#form-pemesanan');
                exit;
            }
            
            // Pindahkan file
            if (move_uploaded_file($_FILES['kartu_pelajar']['tmp_name'], $file_path)) {
                $foto_kartu_pelajar = 'upload/kartu_pelajar/' . $file_name;
                error_log("File uploaded to: " . $foto_kartu_pelajar);
            } else {
                $_SESSION['error'] = 'Gagal mengupload file!';
                header('Location: ../index.php#form-pemesanan');
                exit;
            }
        } else {
            $_SESSION['error'] = 'Foto kartu pelajar wajib diupload!';
            header('Location: ../index.php#form-pemesanan');
            exit;
        }
        
        // Insert ke database
        $sql = "INSERT INTO pesanan 
                (nama, no_hp, produk, jumlah, total, catatan, foto_kartu_pelajar, tanggal, status_verifikasi) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nama, $no_hp, $produk, $jumlah, $total, $catatan, $foto_kartu_pelajar]);
        
        $last_id = $pdo->lastInsertId();
        error_log("Pesanan created with ID: " . $last_id . " | Total: " . $total);
        
        // PERBARUI STOK MERCHANDISE
        $update_stok = $pdo->prepare("UPDATE merchandise SET terjual = terjual + ? WHERE nama_produk = ?");
        $update_stok->execute([$jumlah, $produk]);
        
        // Set session success
        $_SESSION['success'] = "Pesanan berhasil dibuat! ID Pesanan: #" . str_pad($last_id, 3, '0', STR_PAD_LEFT);
        $_SESSION['last_order_id'] = $last_id;
        
        // Redirect ke halaman sukses atau kembali ke form
        header('Location: ../index.php#form-pemesanan');
        exit;
        
    // ==================== ACTION UPDATE (Admin) ====================
    } elseif ($action == 'update_verification') {
        error_log("Processing update_verification...");
        
        $id = $_POST['id'] ?? 0;
        $status_verifikasi = $_POST['status_verifikasi'] ?? '';
        
        if (!$id || !$status_verifikasi) {
            $_SESSION['error'] = 'Data tidak lengkap!';
        } else {
            $stmt = $pdo->prepare("UPDATE pesanan SET status_verifikasi = ?, verified_at = NOW() WHERE id = ?");
            $stmt->execute([$status_verifikasi, $id]);
            
            $_SESSION['success'] = "Status verifikasi berhasil diupdate!";
            error_log("Status updated to: " . $status_verifikasi . ", rows: " . $stmt->rowCount());
        }
        header('Location: ../views/dashboard.php');
        exit;
        
    // ==================== ACTION GET (Ajax) ====================
    } elseif ($action == 'get') {
        $id = $_GET['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM pesanan WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
        
    // ==================== ACTION DELETE (Admin) ====================
    } elseif ($action == 'delete') {
        $id = $_GET['id'] ?? 0;
        
        // Ambil data pesanan dulu
        $stmt = $pdo->prepare("SELECT * FROM pesanan WHERE id = ?");
        $stmt->execute([$id]);
        $pesanan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pesanan) {
            // Hapus file foto jika ada
            if ($pesanan['foto_kartu_pelajar'] && file_exists("../" . $pesanan['foto_kartu_pelajar'])) {
                unlink("../" . $pesanan['foto_kartu_pelajar']);
            }
            
            // Kembalikan stok merchandise
            $update_stok = $pdo->prepare("UPDATE merchandise SET terjual = terjual - ? WHERE nama_produk = ?");
            $update_stok->execute([$pesanan['jumlah'], $pesanan['produk']]);
            
            // Hapus pesanan
            $stmt = $pdo->prepare("DELETE FROM pesanan WHERE id = ?");
            $stmt->execute([$id]);
            
            $_SESSION['success'] = "Pesanan berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Pesanan tidak ditemukan!";
        }
        
        header('Location: ../views/dashboard.php');
        exit;
        
    } else {
        $_SESSION['error'] = "Aksi tidak valid!";
        header('Location: ../views/dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error'] = "Terjadi kesalahan database: " . $e->getMessage();
    
    // Redirect ke halaman yang sesuai
    if ($action == 'create') {
        header('Location: ../index.php#form-pemesanan');
    } else {
        header('Location: ../views/dashboard.php');
    }
    exit;
}