<?php
// models/crud_pemesanan_merchandise.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/config.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
error_log("CRUD Pemesanan Merchandise - Action: " . $action);

try {
    // UPDATE STATUS PESANAN
    if ($action == 'update_status') {
        $id = $_POST['id'] ?? 0;
        $status = $_POST['status'] ?? '';
        
        error_log("Update status - ID: $id, Status: $status");
        
        if (!$id || !$status) {
            $_SESSION['error'] = 'Data tidak lengkap!';
        } else {
            $stmt = $pdo->prepare("UPDATE pemesanan_merchandise SET status = ? WHERE id = ?");
            $success = $stmt->execute([$status, $id]);
            
            if ($success && $stmt->rowCount() > 0) {
                $_SESSION['success'] = "Status pesanan berhasil diupdate ke: " . ucfirst($status);
                error_log("✅ Status updated successfully");
            } else {
                $_SESSION['error'] = "Gagal update status atau data tidak ditemukan";
                error_log("❌ Failed to update status");
            }
        }
        
        header('Location: ../views/dashboard.php');
        exit;
        
    // GET DETAIL PESANAN
    } elseif ($action == 'get') {
        $id = $_GET['id'] ?? 0;
        error_log("Get order detail - ID: $id");
        
        $stmt = $pdo->prepare("SELECT * FROM pemesanan_merchandise WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            // Fix path jika perlu
            if (isset($data['foto_kartu_pelajar'])) {
                // Normalize path
                $data['foto_kartu_pelajar'] = ltrim($data['foto_kartu_pelajar'], './');
            }
            $data['tanggal_formatted'] = date('d M Y H:i', strtotime($data['tanggal_pemesanan']));
        }
        
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
        
    // DELETE PESANAN
    } elseif ($action == 'delete') {
        $id = $_GET['id'] ?? 0;
        error_log("Delete order - ID: $id");
        
        // Ambil data untuk menghapus file
        $stmt = $pdo->prepare("SELECT foto_kartu_pelajar FROM pemesanan_merchandise WHERE id = ?");
        $stmt->execute([$id]);
        $pesanan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pesanan && !empty($pesanan['foto_kartu_pelajar'])) {
            $file_path = '../' . $pesanan['foto_kartu_pelajar'];
            if (file_exists($file_path)) {
                unlink($file_path);
                error_log("Deleted file: $file_path");
            }
        }
        
        // Hapus dari database
        $stmt = $pdo->prepare("DELETE FROM pemesanan_merchandise WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['success'] = "Pesanan berhasil dihapus!";
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
    header('Location: ../views/dashboard.php');
    exit;
}