<?php
// proses_pemesanan.php
session_start();
require_once 'config/config.php';

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug: Tampilkan semua data yang masuk
error_log("==========================================");
error_log("PROSES PEMESANAN DIMULAI - " . date('Y-m-d H:i:s'));
error_log("==========================================");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Metode request tidak valid!";
    header('Location: index.php');
    exit;
}

// ========== AMBIL DATA DARI FORM ==========
$nama = trim($_POST['nama'] ?? '');
$no_hp = trim($_POST['no_hp'] ?? '');
$produk_nama = trim($_POST['produk'] ?? '');
$product_id = intval($_POST['product_id'] ?? 0);
$jumlah = intval($_POST['jumlah'] ?? 0);
$catatan = trim($_POST['catatan'] ?? '');

error_log("Data Form:");
error_log("- Nama: $nama");
error_log("- No HP: $no_hp");
error_log("- Produk Nama: '$produk_nama'");
error_log("- Product ID: $product_id");
error_log("- Jumlah: $jumlah");
error_log("- Catatan: $catatan");

// ========== VALIDASI DATA ==========
$errors = [];

// Validasi nama
if (empty($nama) || strlen($nama) < 3) {
    $errors[] = "Nama lengkap minimal 3 karakter";
}

// Validasi no HP
if (empty($no_hp) || !preg_match('/^[0-9]{10,13}$/', $no_hp)) {
    $errors[] = "No HP harus 10-13 digit angka";
}

// Validasi produk
if (empty($produk_nama) && $product_id <= 0) {
    $errors[] = "Produk harus dipilih";
}

// Validasi jumlah
if ($jumlah <= 0 || $jumlah > 20) {
    $errors[] = "Jumlah harus antara 1-20";
}

// Validasi file upload
if (!isset($_FILES['kartu_pelajar']) || $_FILES['kartu_pelajar']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = "Foto kartu pelajar harus diupload";
} else {
    $file = $_FILES['kartu_pelajar'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        $errors[] = "Format file harus JPG, PNG, atau GIF";
    }
    
    if ($file['size'] > $max_size) {
        $errors[] = "Ukuran file maksimal 5MB";
    }
}

// Jika ada error, redirect kembali
if (!empty($errors)) {
    $_SESSION['error'] = implode(" | ", $errors);
    header('Location: index.php#form-pemesanan');
    exit;
}

// ========== CEK KONEKSI DATABASE ==========
try {
    // Test koneksi
    $pdo->query("SELECT 1");
    error_log("Koneksi database OK");
} catch (Exception $e) {
    error_log("Error koneksi database: " . $e->getMessage());
    $_SESSION['error'] = "Koneksi database gagal: " . $e->getMessage();
    header('Location: index.php#form-pemesanan');
    exit;
}

// ========== CARI PRODUK DI DATABASE ==========
error_log("Mencari produk...");

// PERTAMA: Cari berdasarkan product_id (paling akurat)
if ($product_id > 0) {
    $stmt = $pdo->prepare("
        SELECT id, nama_produk, harga, stok, terjual, 
               (stok - terjual) as stok_tersedia
        FROM merchandise 
        WHERE id = ? 
        LIMIT 1
    ");
    $stmt->execute([$product_id]);
    $produk = $stmt->fetch();
    
    if ($produk) {
        error_log("Produk ditemukan berdasarkan ID $product_id:");
        error_log("- Nama: '{$produk['nama_produk']}'");
        error_log("- Harga: Rp " . number_format($produk['harga'], 0, ',', '.'));
        error_log("- Stok total: {$produk['stok']}");
        error_log("- Terjual: {$produk['terjual']}");
        error_log("- Stok tersedia: {$produk['stok_tersedia']}");
    }
}

// KEDUA: Jika tidak ketemu dengan ID, cari dengan nama
if (!isset($produk) || !$produk) {
    error_log("Cari berdasarkan nama: '$produk_nama'");
    
    // Bersihkan nama produk
    $clean_name = trim($produk_nama);
    error_log("Nama setelah trim: '$clean_name'");
    
    // Coba berbagai metode pencarian
    $queries = [
        // Exact match
        ["SELECT * FROM merchandise WHERE nama_produk = ? LIMIT 1", [$clean_name]],
        // Case insensitive
        ["SELECT * FROM merchandise WHERE LOWER(nama_produk) = LOWER(?) LIMIT 1", [$clean_name]],
        // Like match
        ["SELECT * FROM merchandise WHERE nama_produk LIKE ? LIMIT 1", ["%$clean_name%"]],
        // Hapus spasi ganda
        ["SELECT * FROM merchandise WHERE REPLACE(nama_produk, '  ', ' ') = ? LIMIT 1", [preg_replace('/\s+/', ' ', $clean_name)]]
    ];
    
    foreach ($queries as $query) {
        $stmt = $pdo->prepare($query[0]);
        $stmt->execute($query[1]);
        $produk = $stmt->fetch();
        
        if ($produk) {
            error_log("Produk ditemukan dengan query: " . $query[0]);
            break;
        }
    }
}

// KETIGA: Jika masih tidak ketemu, tampilkan semua produk untuk debugging
if (!isset($produk) || !$produk) {
    error_log("PRODUK TIDAK DITEMUKAN! Menampilkan semua produk...");
    
    $all_stmt = $pdo->query("SELECT id, nama_produk FROM merchandise ORDER BY id");
    $all_products = $all_stmt->fetchAll();
    
    if (empty($all_products)) {
        error_log("Database merchandise KOSONG!");
        $_SESSION['error'] = "Database produk masih kosong. Silakan hubungi admin.";
    } else {
        error_log("Semua produk dalam database:");
        foreach ($all_products as $p) {
            error_log("- ID: {$p['id']}, Nama: '{$p['nama_produk']}'");
        }
        
        // Ambil produk pertama sebagai fallback (untuk testing)
        $produk = $all_products[0];
        error_log("Menggunakan produk pertama sebagai fallback: '{$produk['nama_produk']}'");
    }
    
    if (!isset($produk) || !$produk) {
        $_SESSION['error'] = "Produk tidak ditemukan dalam sistem. Silakan refresh halaman dan coba lagi.";
        header('Location: index.php#form-pemesanan');
        exit;
    }
}

// ========== CEK STOK PRODUK ==========
if (!isset($produk['stok_tersedia'])) {
    $produk['stok_tersedia'] = $produk['stok'] - $produk['terjual'];
}

error_log("Cek stok: Tersedia = {$produk['stok_tersedia']}, Dipesan = $jumlah");

if ($produk['stok_tersedia'] < $jumlah) {
    $_SESSION['error'] = "Stok produk '{$produk['nama_produk']}' tidak mencukupi! Stok tersedia: {$produk['stok_tersedia']} unit";
    header('Location: index.php#form-pemesanan');
    exit;
}

// ========== HITUNG TOTAL HARGA ==========
$harga = $produk['harga'];
$total_harga = $harga * $jumlah;

error_log("Harga satuan: Rp " . number_format($harga, 0, ',', '.'));
error_log("Total harga: Rp " . number_format($total_harga, 0, ',', '.'));

// ========== GENERATE KODE PESANAN ==========
$kode_pesanan = 'SMEKDA-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
error_log("Kode pesanan: $kode_pesanan");

// ========== UPLOAD FOTO KARTU PELAJAR ==========
error_log("Proses upload file...");

$upload_dir = __DIR__ . '/uploads/kartu_pelajar/';
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        error_log("Gagal membuat folder upload: $upload_dir");
        $_SESSION['error'] = "Gagal membuat folder upload. Periksa permission folder.";
        header('Location: index.php#form-pemesanan');
        exit;
    }
    error_log("Folder upload dibuat: $upload_dir");
}

// Generate nama file unik
$file_extension = strtolower(pathinfo($_FILES['kartu_pelajar']['name'], PATHINFO_EXTENSION));
$file_name = 'kartu_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
$file_path = $upload_dir . $file_name;

error_log("Nama file: $file_name");
error_log("Path lengkap: $file_path");

// Upload file
if (!move_uploaded_file($_FILES['kartu_pelajar']['tmp_name'], $file_path)) {
    error_log("GAGAL UPLOAD FILE!");
    $_SESSION['error'] = "Gagal upload foto kartu pelajar. Error: " . $_FILES['kartu_pelajar']['error'];
    header('Location: index.php#form-pemesanan');
    exit;
}

error_log("File berhasil diupload: " . filesize($file_path) . " bytes");

// ========== SIMPAN KE DATABASE ==========
error_log("Menyimpan data ke database...");

try {
    $pdo->beginTransaction();
    
    // 1. Insert pesanan
    $stmt = $pdo->prepare("
        INSERT INTO pemesanan_merchandise (
            kode_pesanan, nama, no_hp, produk, jumlah, 
            total_harga, catatan, foto_kartu_pelajar, status, tanggal_pemesanan
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $insert_success = $stmt->execute([
        $kode_pesanan,
        $nama,
        $no_hp,
        $produk['nama_produk'],
        $jumlah,
        $total_harga,
        $catatan,
        'uploads/kartu_pelajar/' . $file_name
    ]);
    
    if (!$insert_success) {
        throw new Exception("Gagal insert data pesanan");
    }
    
    $pemesanan_id = $pdo->lastInsertId();
    error_log("Pesanan disimpan. ID: $pemesanan_id");
    
    // 2. Update stok merchandise (PERBAIKAN: Hapus updated_at)
    $stmt = $pdo->prepare("
        UPDATE merchandise 
        SET terjual = terjual + ?
        WHERE id = ?
    ");
    
    $update_success = $stmt->execute([$jumlah, $produk['id']]);
    
    if (!$update_success) {
        throw new Exception("Gagal update stok produk");
    }
    
    error_log("Stok produk diperbarui. ID Produk: {$produk['id']}, Jumlah: $jumlah");
    
    $pdo->commit();
    error_log("Transaction COMMIT berhasil");
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    // Hapus file yang sudah diupload jika gagal
    if (file_exists($file_path)) {
        unlink($file_path);
        error_log("File dihapus karena rollback");
    }
    
    error_log("ERROR DATABASE: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    
    $_SESSION['error'] = "Terjadi kesalahan database: " . $e->getMessage();
    header('Location: index.php#form-pemesanan');
    exit;
}

// ========== SIMPAN KE SESSION UNTUK PAYMENT ==========
$_SESSION['payment_data'] = [
    'pemesanan_id' => $pemesanan_id,
    'kode_pesanan' => $kode_pesanan,
    'nama' => $nama,
    'no_hp' => $no_hp,
    'produk' => $produk['nama_produk'],
    'produk_id' => $produk['id'],
    'jumlah' => $jumlah,
    'harga_satuan' => $harga,
    'total_harga' => $total_harga,
    'catatan' => $catatan,
    'file_path' => 'uploads/kartu_pelajar/' . $file_name,
    'waktu_pemesanan' => date('Y-m-d H:i:s')
];

error_log("Data session payment_data disimpan");
error_log("==========================================");
error_log("PROSES PEMESANAN SELESAI - Redirect ke payment.php");
error_log("==========================================");

// ========== REDIRECT KE HALAMAN PAYMENT ==========
header("Location: payment.php?order=" . urlencode($kode_pesanan) . "&success=1");
exit;
?>