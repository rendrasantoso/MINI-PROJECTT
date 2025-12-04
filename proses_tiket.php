<?php
// proses_tiket.php
session_start();
require_once __DIR__ . '/config/config.php';

error_log("=== PROSES TIKET DIMULAI ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST Data: " . print_r($_POST, true));
error_log("SESSION: " . print_r($_SESSION, true));

// Jika datang dari konfirmasi-tiket.php (action=confirm)
if (isset($_POST['action']) && $_POST['action'] === 'confirm') {
    error_log("Processing confirm action...");
    
    // Cek apakah ada data di session
    if (!isset($_SESSION['tiket_pemesanan'])) {
        $_SESSION['error'] = "Data pemesanan tidak ditemukan! Silakan ulangi proses pemesanan.";
        header('Location: tiket.php');
        exit;
    }
    
    $data = $_SESSION['tiket_pemesanan'];
    
    $tiket_id = $data['tiket_id'];
    $match_id = $data['match_id'];
    $nama = $data['nama'];
    $no_hp = $data['no_hp'];
    $email = $data['email'];
    $jumlah_tiket = $data['jumlah_tiket'];
    $total_harga = $data['total_harga'];
    $kode_booking = $data['kode_booking'];
    
    error_log("Data dari session: tiket_id=$tiket_id, jumlah=$jumlah_tiket, total=$total_harga");
    
    try {
        // MULAI TRANSACTION
        $pdo->beginTransaction();
        
        try {
            // 1. Ambil data tiket dengan LOCK
            $stmt = $pdo->prepare("SELECT * FROM tiket WHERE id = ? FOR UPDATE");
            $stmt->execute([$tiket_id]);
            $tiket = $stmt->fetch();
            
            if (!$tiket) {
                throw new Exception("Tiket tidak ditemukan!");
            }
            
            // 2. Cek stok lagi (double check)
            $stok_tersisa = $tiket['stok'] - $tiket['terjual'];
            error_log("Stok tersisa: $stok_tersisa, Pesanan: $jumlah_tiket");
            
            if ($jumlah_tiket > $stok_tersisa) {
                throw new Exception("Stok tidak mencukupi! Stok tersisa: $stok_tersisa tiket");
            }
            
            // 3. Simpan ke database pemesanan_tiket
            $stmt = $pdo->prepare("
                INSERT INTO pemesanan_tiket 
                (nama, no_hp, email, tiket_id, match_id, jumlah_tiket, total_harga, status, tanggal_pesan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $success = $stmt->execute([
                $nama, $no_hp, $email, $tiket_id, $match_id, 
                $jumlah_tiket, $total_harga
            ]);
            
            if (!$success) {
                throw new Exception("Gagal menyimpan pemesanan tiket!");
            }
            
            $pemesanan_id = $pdo->lastInsertId();
            error_log("Pemesanan ID: $pemesanan_id");
            
            // 4. PERBAIKAN: Update terjual di tabel tiket
            $stmt = $pdo->prepare("UPDATE tiket SET terjual = terjual + ? WHERE id = ?");
            $stmt->execute([$jumlah_tiket, $tiket_id]);
            
            error_log("Update terjual berhasil: +$jumlah_tiket untuk tiket ID $tiket_id");
            
            // 5. Cek update
            $stmt = $pdo->prepare("SELECT terjual FROM tiket WHERE id = ?");
            $stmt->execute([$tiket_id]);
            $updated = $stmt->fetch();
            error_log("Terjual setelah update: {$updated['terjual']}");
            
            // 6. Ambil detail untuk success page
            $stmt = $pdo->prepare("
                SELECT pt.*, t.jenis_tiket, jm.pertandingan, jm.tanggal, jm.waktu, jm.lokasi
                FROM pemesanan_tiket pt
                JOIN tiket t ON pt.tiket_id = t.id
                JOIN jadwal_match jm ON t.match_id = jm.id
                WHERE pt.id = ?
            ");
            $stmt->execute([$pemesanan_id]);
            $detail = $stmt->fetch();
            
            if (!$detail) {
                throw new Exception("Gagal mengambil detail pemesanan!");
            }
            
            // 7. COMMIT transaction
            $pdo->commit();
            
            // 8. Clear session pemesanan
            unset($_SESSION['tiket_pemesanan']);
            
            // 9. Set session success dengan detail lengkap
            $_SESSION['tiket_success'] = [
                'id' => $pemesanan_id,
                'kode_booking' => $kode_booking,
                'nama' => $nama,
                'no_hp' => $no_hp,
                'jumlah_tiket' => $jumlah_tiket,
                'total_harga' => $total_harga,
                'detail' => $detail
            ];
            
            error_log("=== PROSES TIKET SELESAI ===");
            error_log("Redirect ke tiket_success.php?id=$pemesanan_id");
            
            // 10. Redirect ke success page
            header('Location: tiket_success.php?id=' . $pemesanan_id);
            exit;
            
        } catch (Exception $e) {
            // ROLLBACK jika ada error
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        $_SESSION['error'] = 'Terjadi kesalahan database: ' . $e->getMessage();
        header('Location: konfirmasi-tiket.php');
        exit;
    } catch (Exception $e) {
        error_log("General Error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header('Location: konfirmasi-tiket.php');
        exit;
    }
}

// Jika datang langsung dari tiket.php (tanpa konfirmasi)
else {
    error_log("Processing direct order from tiket.php");
    
    // Validasi method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['error'] = 'Metode tidak valid!';
        header('Location: index.php');
        exit;
    }
    
    try {
        // Validasi input wajib
        $required_fields = ['tiket_id', 'nama', 'no_hp', 'jumlah_tiket', 'match_id', 'harga'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $_SESSION['error'] = "Field $field harus diisi!";
                header('Location: tiket.php?match_id=' . ($_POST['match_id'] ?? ''));
                exit;
            }
        }
        
        // Ambil data
        $tiket_id = (int)$_POST['tiket_id'];
        $match_id = (int)$_POST['match_id'];
        $nama = trim($_POST['nama']);
        $no_hp = trim($_POST['no_hp']);
        $email = trim($_POST['email'] ?? '');
        $jumlah_tiket = (int)$_POST['jumlah_tiket'];
        $harga = (float)$_POST['harga'];
        
        // Validasi
        if ($jumlah_tiket < 1 || $jumlah_tiket > 10) {
            $_SESSION['error'] = 'Jumlah tiket harus 1-10!';
            header('Location: tiket.php?match_id=' . $match_id);
            exit;
        }
        
        // Simpan di session untuk konfirmasi
        $_SESSION['tiket_pemesanan'] = [
            'tiket_id' => $tiket_id,
            'match_id' => $match_id,
            'nama' => $nama,
            'no_hp' => $no_hp,
            'email' => $email,
            'jumlah_tiket' => $jumlah_tiket,
            'total_harga' => $harga * $jumlah_tiket,
            'kode_booking' => 'TIK-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6)),
            'tanggal_pesan' => date('Y-m-d H:i:s')
        ];
        
        // Redirect ke halaman konfirmasi
        header('Location: konfirmasi-tiket.php');
        exit;
        
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header('Location: tiket.php?match_id=' . ($_POST['match_id'] ?? ''));
        exit;
    }
}
?>