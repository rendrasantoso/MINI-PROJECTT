<?php
// Tambahkan debugging di atas
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . '/../config/config.php'; 

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$action = $_GET['action'] ?? '';
error_log("CRUD Tiket - Action: " . $action);

try {
    switch ($action) {
        case 'create':
            error_log("CRUD Tiket - Create action");
            error_log("POST data: " . print_r($_POST, true));
            
            $stmt = $pdo->prepare("INSERT INTO tiket (jenis_tiket, match_id, harga, stok, terjual) VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([
                $_POST['jenis_tiket'],
                $_POST['match_id'],
                $_POST['harga'],
                $_POST['stok']
            ]);
            
            $_SESSION['success'] = 'Tiket berhasil ditambahkan!';
            error_log("CRUD Tiket - Insert success");
            break;

        case 'update':
            error_log("CRUD Tiket - Update action");
            error_log("POST data: " . print_r($_POST, true));
            
            $stmt = $pdo->prepare("UPDATE tiket SET jenis_tiket=?, match_id=?, harga=?, stok=? WHERE id=?");
            $stmt->execute([
                $_POST['jenis_tiket'],
                $_POST['match_id'],
                $_POST['harga'],
                $_POST['stok'],
                $_POST['id']
            ]);
            
            $_SESSION['success'] = 'Tiket berhasil diupdate!';
            error_log("CRUD Tiket - Update success, rows affected: " . $stmt->rowCount());
            break;

        case 'delete':
            error_log("CRUD Tiket - Delete action, ID: " . $_GET['id']);
            
            $stmt = $pdo->prepare("DELETE FROM tiket WHERE id=?");
            $stmt->execute([$_GET['id']]);
            
            $_SESSION['success'] = 'Tiket berhasil dihapus!';
            error_log("CRUD Tiket - Delete success, rows affected: " . $stmt->rowCount());
            break;

        case 'get':
            error_log("CRUD Tiket - Get action for ID: " . $_GET['id']);
            
            $stmt = $pdo->prepare("SELECT * FROM tiket WHERE id=?");
            $stmt->execute([$_GET['id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                error_log("CRUD Tiket - No data found for ID: " . $_GET['id']);
            }
            
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;

        default:
            error_log("CRUD Tiket - Invalid action: " . $action);
            $_SESSION['error'] = 'Aksi tidak valid!';
    }
} catch (PDOException $e) {
    error_log("CRUD Tiket - Database error: " . $e->getMessage());
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("CRUD Tiket - General error: " . $e->getMessage());
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

error_log("CRUD Tiket - Redirecting to dashboard");
header('Location: ../views/dashboard.php');
exit;
?>