<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../config/config.php'; 

error_log("CRUD Jadwal - Session admin_id: " . (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'NOT SET'));

if (!isset($_SESSION['admin_id'])) {
    error_log("CRUD Jadwal - Redirect to login");
    header('Location: ../login.php');
    exit;
}

$action = $_GET['action'] ?? '';
error_log("CRUD Jadwal - Action: " . $action);

try {
    switch ($action) {
        case 'create':
            error_log("CRUD Jadwal - Create action");
            error_log("POST data: " . print_r($_POST, true));
            
            $stmt = $pdo->prepare("INSERT INTO jadwal_match (pertandingan, tanggal, waktu, lokasi, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['pertandingan'],
                $_POST['tanggal'],
                $_POST['waktu'],
                $_POST['lokasi'],
                $_POST['status']
            ]);
            
            $lastId = $pdo->lastInsertId();
            error_log("CRUD Jadwal - Inserted ID: " . $lastId);
            
            $_SESSION['success'] = 'Jadwal berhasil ditambahkan!';
            break;

        case 'update':
            error_log("CRUD Jadwal - Update action");
            error_log("POST data: " . print_r($_POST, true));
            
            $stmt = $pdo->prepare("UPDATE jadwal_match SET pertandingan=?, tanggal=?, waktu=?, lokasi=?, status=? WHERE id=?");
            $stmt->execute([
                $_POST['pertandingan'],
                $_POST['tanggal'],
                $_POST['waktu'],
                $_POST['lokasi'],
                $_POST['status'],
                $_POST['id']
            ]);
            
            error_log("CRUD Jadwal - Updated rows: " . $stmt->rowCount());
            $_SESSION['success'] = 'Jadwal berhasil diupdate!';
            break;

        case 'delete':
            error_log("CRUD Jadwal - Delete action");
            error_log("ID to delete: " . $_GET['id']);
            
            $stmt = $pdo->prepare("DELETE FROM jadwal_match WHERE id=?");
            $stmt->execute([$_GET['id']]);
            
            error_log("CRUD Jadwal - Deleted rows: " . $stmt->rowCount());
            $_SESSION['success'] = 'Jadwal berhasil dihapus!';
            break;

        case 'get':
            error_log("CRUD Jadwal - Get action for ID: " . $_GET['id']);
            
            $stmt = $pdo->prepare("SELECT * FROM jadwal_match WHERE id=?");
            $stmt->execute([$_GET['id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;

        default:
            error_log("CRUD Jadwal - Invalid action: " . $action);
            $_SESSION['error'] = 'Aksi tidak valid!';
    }
} catch (PDOException $e) {
    error_log("CRUD Jadwal - Database error: " . $e->getMessage());
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

error_log("CRUD Jadwal - Redirecting to dashboard");
header('Location: ../views/dashboard.php');
exit;
?>