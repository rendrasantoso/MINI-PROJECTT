<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . '/../config/config.php'; 

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$action = $_GET['action'] ?? '';
error_log("CRUD Merchandise - Action: " . $action);

try {
    switch ($action) {
        case 'create':
            error_log("CRUD Merchandise - Create action");
            error_log("POST data: " . print_r($_POST, true));
            error_log("FILES data: " . print_r($_FILES, true));
            
            $gambar = '';
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
                // PERBAIKI PATH: upload/merchandise/ di root
                $target_dir = "../upload/merchandise/";
                
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                    error_log("Created directory: " . $target_dir);
                }
                
                $filename = time() . '_' . basename($_FILES['gambar']['name']);
                $targetPath = $target_dir . $filename;
                
                error_log("Uploading to: " . $targetPath);
                
                if (move_uploaded_file($_FILES['gambar']['tmp_name'], $targetPath)) {
                    $gambar = 'upload/merchandise/' . $filename;
                    error_log("File uploaded: " . $gambar);
                } else {
                    error_log("File upload failed");
                }
            }
            
            $stmt = $pdo->prepare("INSERT INTO merchandise (nama_produk, kategori, harga, stok, status, gambar, terjual) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $stmt->execute([
                $_POST['nama_produk'],
                $_POST['kategori'],
                $_POST['harga'],
                $_POST['stok'],
                $_POST['status'],
                $gambar
            ]);
            
            $_SESSION['success'] = 'Merchandise berhasil ditambahkan!';
            error_log("CRUD Merchandise - Insert success, ID: " . $pdo->lastInsertId());
            break;

        case 'update':
            error_log("CRUD Merchandise - Update action");
            error_log("POST data: " . print_r($_POST, true));
            error_log("FILES data: " . print_r($_FILES, true));
            
            $gambar = $_POST['gambar_lama'] ?? '';
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
                $target_dir = "../upload/merchandise/";
                
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                // Hapus gambar lama
                if ($gambar && file_exists("../" . $gambar)) {
                    unlink("../" . $gambar);
                    error_log("Deleted old image: ../" . $gambar);
                }
                
                $filename = time() . '_' . basename($_FILES['gambar']['name']);
                $targetPath = $target_dir . $filename;
                
                if (move_uploaded_file($_FILES['gambar']['tmp_name'], $targetPath)) {
                    $gambar = 'upload/merchandise/' . $filename;
                    error_log("New image uploaded: " . $gambar);
                }
            }
            
            $stmt = $pdo->prepare("UPDATE merchandise SET nama_produk=?, kategori=?, harga=?, stok=?, status=?, gambar=? WHERE id=?");
            $stmt->execute([
                $_POST['nama_produk'],
                $_POST['kategori'],
                $_POST['harga'],
                $_POST['stok'],
                $_POST['status'],
                $gambar,
                $_POST['id']
            ]);
            
            $_SESSION['success'] = 'Merchandise berhasil diupdate!';
            error_log("CRUD Merchandise - Update success, rows affected: " . $stmt->rowCount());
            break;

        case 'delete':
            error_log("CRUD Merchandise - Delete action, ID: " . $_GET['id']);
            
            // Hapus gambar
            $stmt = $pdo->prepare("SELECT gambar FROM merchandise WHERE id=?");
            $stmt->execute([$_GET['id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data && $data['gambar'] && file_exists("../" . $data['gambar'])) {
                unlink("../" . $data['gambar']);
                error_log("Deleted image file: ../" . $data['gambar']);
            }
            
            $stmt = $pdo->prepare("DELETE FROM merchandise WHERE id=?");
            $stmt->execute([$_GET['id']]);
            
            $_SESSION['success'] = 'Merchandise berhasil dihapus!';
            error_log("CRUD Merchandise - Delete success, rows affected: " . $stmt->rowCount());
            break;

        case 'get':
            error_log("CRUD Merchandise - Get action for ID: " . $_GET['id']);
            
            $stmt = $pdo->prepare("SELECT * FROM merchandise WHERE id=?");
            $stmt->execute([$_GET['id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;

        default:
            error_log("CRUD Merchandise - Invalid action: " . $action);
            $_SESSION['error'] = 'Aksi tidak valid!';
    }
} catch (PDOException $e) {
    error_log("CRUD Merchandise - Database error: " . $e->getMessage());
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

error_log("CRUD Merchandise - Redirecting to dashboard");
header('Location: ../views/dashboard.php');
exit;
?>