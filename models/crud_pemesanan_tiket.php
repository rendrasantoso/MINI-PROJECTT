<?php
// Tambahkan debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . '/../config/config.php'; 

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$action = $_GET['action'] ?? '';
error_log("CRUD Pemesanan Tiket - Action: " . $action);

try {
    switch ($action) {
        case 'update_status':
            error_log("CRUD Pemesanan Tiket - Update status action");
            error_log("POST data: " . print_r($_POST, true));
            
            $stmt = $pdo->prepare("UPDATE pemesanan_tiket SET status=? WHERE id=?");
            $stmt->execute([
                $_POST['status'],
                $_POST['id']
            ]);
            
            $_SESSION['success'] = 'Status pemesanan tiket berhasil diupdate!';
            error_log("CRUD Pemesanan Tiket - Status updated, rows affected: " . $stmt->rowCount());
            break;

        case 'delete':
            error_log("CRUD Pemesanan Tiket - Delete action, ID: " . $_GET['id']);
            
            // Ambil data tiket untuk mengembalikan stok
            $stmt = $pdo->prepare("SELECT tiket_id, jumlah_tiket FROM pemesanan_tiket WHERE id=?");
            $stmt->execute([$_GET['id']]);
            $data = $stmt->fetch();
            
            error_log("CRUD Pemesanan Tiket - Data to delete: " . print_r($data, true));
            
            if ($data) {
                // Kembalikan stok tiket
                $stmt = $pdo->prepare("UPDATE tiket SET terjual = terjual - ? WHERE id=?");
                $stmt->execute([$data['jumlah_tiket'], $data['tiket_id']]);
                error_log("CRUD Pemesanan Tiket - Stock returned: " . $data['jumlah_tiket']);
                
                // Hapus pemesanan
                $stmt = $pdo->prepare("DELETE FROM pemesanan_tiket WHERE id=?");
                $stmt->execute([$_GET['id']]);
                
                $_SESSION['success'] = 'Pemesanan tiket berhasil dihapus!';
                error_log("CRUD Pemesanan Tiket - Delete success");
            } else {
                $_SESSION['error'] = 'Data pemesanan tidak ditemukan!';
                error_log("CRUD Pemesanan Tiket - Data not found");
            }
            break;

        case 'get':
            error_log("CRUD Pemesanan Tiket - Get action for ID: " . $_GET['id']);
            
            $stmt = $pdo->prepare("
                SELECT pt.*, t.jenis_tiket, jm.pertandingan 
                FROM pemesanan_tiket pt
                LEFT JOIN tiket t ON pt.tiket_id = t.id
                LEFT JOIN jadwal_match jm ON t.match_id = jm.id
                WHERE pt.id=?
            ");
            $stmt->execute([$_GET['id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("CRUD Pemesanan Tiket - Data retrieved: " . print_r($data, true));
            
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;

        default:
            error_log("CRUD Pemesanan Tiket - Invalid action: " . $action);
            $_SESSION['error'] = 'Aksi tidak valid!';
    }
} catch (PDOException $e) {
    error_log("CRUD Pemesanan Tiket - Database error: " . $e->getMessage());
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

error_log("CRUD Pemesanan Tiket - Redirecting to dashboard");
header('Location: ../views/dashboard.php');
exit;
?>