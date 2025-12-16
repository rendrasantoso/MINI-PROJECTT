<?php
session_start();
require_once 'config/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOG FILE CUSTOM (selain error.log)
$debug_log = __DIR__ . '/debug_pemesanan.log';
function debug_log($message) {
    global $debug_log;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($debug_log, "[$timestamp] $message\n", FILE_APPEND);
    error_log($message);
}

debug_log("==========================================");
debug_log("PROSES PEMESANAN DIMULAI");
debug_log("==========================================");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Metode request tidak valid!";
    header('Location: index.php');
    exit;
}

// Ambil data form
$nama = trim($_POST['nama'] ?? '');
$no_hp = trim($_POST['no_hp'] ?? '');
$produk_nama = trim($_POST['produk'] ?? '');
$jumlah = intval($_POST['jumlah'] ?? 0);
$catatan = trim($_POST['catatan'] ?? '');

debug_log("Form Data: nama=$nama, hp=$no_hp, produk=$produk_nama, jumlah=$jumlah");

// Validasi singkat
$errors = [];
if (empty($nama) || strlen($nama) < 3) $errors[] = "Nama minimal 3 karakter";
if (empty($no_hp) || !preg_match('/^[0-9]{10,13}$/', $no_hp)) $errors[] = "No HP tidak valid";
if (empty($produk_nama)) $errors[] = "Produk harus dipilih";
if ($jumlah <= 0 || $jumlah > 20) $errors[] = "Jumlah harus 1-20";
if (!isset($_FILES['kartu_pelajar']) || $_FILES['kartu_pelajar']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = "Foto kartu pelajar wajib";
}

if (!empty($errors)) {
    debug_log("Validasi gagal: " . implode(", ", $errors));
    $_SESSION['error'] = implode(" | ", $errors);
    header('Location: index.php#form-pemesanan');
    exit;
}

debug_log("✅ Validasi OK");

// Cari produk
debug_log("Mencari produk: '$produk_nama'");
$stmt = $pdo->prepare("
    SELECT id, nama_produk, harga, stok, terjual, 
           (stok - terjual) as stok_tersedia
    FROM merchandise 
    WHERE nama_produk = ? 
    LIMIT 1
");
$stmt->execute([$produk_nama]);
$produk = $stmt->fetch();

if (!$produk) {
    debug_log("❌ Produk tidak ditemukan!");
    $_SESSION['error'] = "Produk tidak ditemukan!";
    header('Location: index.php#form-pemesanan');
    exit;
}

debug_log("✅ Produk ditemukan:");
debug_log("  ID: {$produk['id']}");
debug_log("  Nama: {$produk['nama_produk']}");
debug_log("  Stok: {$produk['stok']}");
debug_log("  Terjual: {$produk['terjual']}");
debug_log("  Stok Tersedia: {$produk['stok_tersedia']}");

// Cek stok
if ($produk['stok_tersedia'] < $jumlah) {
    debug_log("❌ Stok tidak cukup!");
    $_SESSION['error'] = "Stok tidak mencukupi! Tersedia: {$produk['stok_tersedia']} unit";
    header('Location: index.php#form-pemesanan');
    exit;
}

$harga = $produk['harga'];
$total_harga = $harga * $jumlah;
$kode_pesanan = 'SMEKDA-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));

debug_log("Kode pesanan: $kode_pesanan");
debug_log("Total harga: Rp" . number_format($total_harga, 0, ',', '.'));

// Upload file
$upload_dir = __DIR__ . '/uploads/kartu_pelajar/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$file_extension = strtolower(pathinfo($_FILES['kartu_pelajar']['name'], PATHINFO_EXTENSION));
$file_name = 'kartu_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
$file_path = $upload_dir . $file_name;

if (!move_uploaded_file($_FILES['kartu_pelajar']['tmp_name'], $file_path)) {
    debug_log("❌ Gagal upload file!");
    $_SESSION['error'] = "Gagal upload foto";
    header('Location: index.php#form-pemesanan');
    exit;
}

debug_log("✅ File uploaded: $file_name");

// ========== BAGIAN PENTING: DATABASE TRANSACTION ==========
debug_log("======== START DATABASE TRANSACTION ========");

try {
    debug_log("🔄 BEGIN TRANSACTION");
    $pdo->beginTransaction();
    
    // 1. INSERT PESANAN
    debug_log("📝 INSERT pesanan...");
    $stmt_insert = $pdo->prepare("
        INSERT INTO pemesanan_merchandise (
            kode_pesanan, nama, no_hp, produk, jumlah, 
            total_harga, catatan, foto_kartu_pelajar, status, tanggal_pemesanan
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $insert_result = $stmt_insert->execute([
        $kode_pesanan, $nama, $no_hp, $produk['nama_produk'], $jumlah,
        $total_harga, $catatan, 'uploads/kartu_pelajar/' . $file_name
    ]);
    
    if (!$insert_result) {
        throw new Exception("INSERT gagal: " . implode(", ", $stmt_insert->errorInfo()));
    }
    
    $pemesanan_id = $pdo->lastInsertId();
    debug_log("✅ INSERT berhasil. Pemesanan ID: $pemesanan_id");
    
    // 2. UPDATE STOK (INI YANG PALING PENTING!)
    debug_log("======== UPDATE STOK ========");
    debug_log("Query: UPDATE merchandise SET terjual = terjual + $jumlah WHERE id = {$produk['id']}");
    debug_log("Terjual SEBELUM: {$produk['terjual']}");
    debug_log("Jumlah order: $jumlah");
    debug_log("Terjual SEHARUSNYA: " . ($produk['terjual'] + $jumlah));
    
    $stmt_update = $pdo->prepare("
        UPDATE merchandise 
        SET terjual = terjual + ?
        WHERE id = ?
    ");
    
    debug_log("Executing UPDATE...");
    $update_result = $stmt_update->execute([$jumlah, $produk['id']]);
    
    if (!$update_result) {
        $error_info = $stmt_update->errorInfo();
        debug_log("❌ UPDATE GAGAL!");
        debug_log("Error Info: " . print_r($error_info, true));
        throw new Exception("UPDATE gagal: " . implode(", ", $error_info));
    }
    
    $affected_rows = $stmt_update->rowCount();
    debug_log("UPDATE executed. Affected rows: $affected_rows");
    
    if ($affected_rows === 0) {
        debug_log("⚠️ WARNING: Affected rows = 0!");
        throw new Exception("UPDATE tidak mengubah data! Affected rows = 0");
    }
    
    debug_log("✅ UPDATE berhasil dieksekusi");
    
    // 3. VERIFIKASI UPDATE
    debug_log("======== VERIFIKASI UPDATE ========");
    $verify_stmt = $pdo->prepare("
        SELECT id, nama_produk, stok, terjual, (stok - terjual) as stok_tersedia 
        FROM merchandise 
        WHERE id = ?
    ");
    $verify_stmt->execute([$produk['id']]);
    $verify = $verify_stmt->fetch();
    
    debug_log("Data SETELAH UPDATE:");
    debug_log("  ID: {$verify['id']}");
    debug_log("  Nama: {$verify['nama_produk']}");
    debug_log("  Stok: {$verify['stok']}");
    debug_log("  Terjual: {$verify['terjual']} (sebelum: {$produk['terjual']})");
    debug_log("  Stok Tersedia: {$verify['stok_tersedia']}");
    
    // CEK PERUBAHAN
    $expected_terjual = $produk['terjual'] + $jumlah;
    $actual_terjual = $verify['terjual'];
    
    debug_log("PERBANDINGAN:");
    debug_log("  Expected terjual: $expected_terjual");
    debug_log("  Actual terjual: $actual_terjual");
    debug_log("  Match: " . ($expected_terjual == $actual_terjual ? 'YES ✅' : 'NO ❌'));
    
    if ($actual_terjual != $expected_terjual) {
        debug_log("❌❌❌ FATAL: Terjual tidak sesuai! ❌❌❌");
        throw new Exception("UPDATE GAGAL VERIFIKASI! Expected: $expected_terjual, Got: $actual_terjual");
    }
    
    debug_log("✅✅✅ VERIFIKASI SUKSES! ✅✅✅");
    
    // 4. COMMIT
    debug_log("🔒 COMMIT TRANSACTION...");
    $pdo->commit();
    debug_log("✅✅✅ TRANSACTION COMMITTED SUCCESSFULLY! ✅✅✅");
    debug_log("==========================================");
    
    // Session untuk payment
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
    
    debug_log("Redirect ke payment.php");
    header("Location: payment.php?order=" . urlencode($kode_pesanan) . "&success=1");
    exit;
    
} catch (Exception $e) {
    debug_log("======== EXCEPTION CAUGHT ========");
    debug_log("❌❌❌ ERROR: " . $e->getMessage());
    debug_log("Stack trace: " . $e->getTraceAsString());
    
    debug_log("🔙 ROLLBACK TRANSACTION");
    $pdo->rollBack();
    
    if (file_exists($file_path)) {
        unlink($file_path);
        debug_log("🗑️ File deleted");
    }
    
    debug_log("==========================================");
    
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    header('Location: index.php#form-pemesanan');
    exit;
}
?>