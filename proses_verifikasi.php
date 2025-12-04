<?php
session_start();
require_once __DIR__ . '/config/config.php';

// Cek apakah admin sudah login
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $id = $_POST['id'];
        $action = $_POST['action'];
        $keterangan = $_POST['keterangan'] ?? null;
        $admin_id = $_SESSION['admin_id'];
        
        // Tentukan status berdasarkan action
        $status = ($action == 'approve') ? 'approved' : 'rejected';
        
        // Update status verifikasi pesanan
        $stmt = $pdo->prepare("
            UPDATE pesanan 
            SET status_verifikasi = ?,
                verified_by = ?,
                verified_at = NOW(),
                keterangan_admin = ?
            WHERE id = ?
        ");
        
        $stmt->execute([$status, $admin_id, $keterangan, $id]);
        
        // Jika disetujui, update status pesanan menjadi 'proses'
        if ($action == 'approve') {
            $stmt = $pdo->prepare("UPDATE pesanan SET status = 'proses' WHERE id = ?");
            $stmt->execute([$id]);
        } else {
            // Jika ditolak, kembalikan stok merchandise
            $stmt = $pdo->prepare("SELECT produk, jumlah FROM pesanan WHERE id = ?");
            $stmt->execute([$id]);
            $pesanan = $stmt->fetch();
            
            if ($pesanan) {
                $stmt = $pdo->prepare("
                    UPDATE merchandise 
                    SET stok = stok + ?, 
                        terjual = terjual - ? 
                    WHERE nama_produk = ?
                ");
                $stmt->execute([$pesanan['jumlah'], $pesanan['jumlah'], $pesanan['produk']]);
            }
            
            // Update status pesanan menjadi 'dibatalkan'
            $stmt = $pdo->prepare("UPDATE pesanan SET status = 'dibatalkan' WHERE id = ?");
            $stmt->execute([$id]);
        }
        
        $_SESSION['success'] = 'Pesanan berhasil ' . ($action == 'approve' ? 'disetujui' : 'ditolak') . '!';
        header('Location: verifikasi_pemesanan.php');
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Terjadi kesalahan: ' . $e->getMessage();
        header('Location: verifikasi_pemesanan.php');
        exit;
    }
} else {
    header('Location: verifikasi_pemesanan.php');
    exit;
}
?>