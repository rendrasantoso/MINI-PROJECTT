<?php
session_start();
include 'config.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    
    // Ambil data user
    $stmt = $conn->prepare("SELECT nama, email FROM admin WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    
    // Validasi file upload
    if (isset($_FILES['kartu_pelajar']) && $_FILES['kartu_pelajar']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['kartu_pelajar']['name'];
        $filesize = $_FILES['kartu_pelajar']['size'];
        
        // Dapatkan ekstensi file
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Validasi ekstensi
        if (!in_array($ext, $allowed)) {
            $error = 'Format file tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.';
        }
        // Validasi ukuran (max 5MB)
        elseif ($filesize > 5 * 1024 * 1024) {
            $error = 'Ukuran file terlalu besar. Maksimal 5MB.';
        }
        else {
            // Buat nama file unik
            $new_filename = 'kartu_' . $user_id . '_' . time() . '.' . $ext;
            $upload_path = 'uploads/kartu_pelajar/' . $new_filename;
            
            // Buat folder jika belum ada
            if (!file_exists('uploads/kartu_pelajar')) {
                mkdir('uploads/kartu_pelajar', 0777, true);
            }
            
            // Upload file
            if (move_uploaded_file($_FILES['kartu_pelajar']['tmp_name'], $upload_path)) {
                // Simpan ke database
                $stmt = $conn->prepare("UPDATE admin SET kartu_pelajar = ?, status_verifikasi = 'pending' WHERE id = ?");
                $stmt->bind_param("si", $new_filename, $user_id);
                $stmt->execute();
                $stmt->close();
                
                // KIRIM KE WHATSAPP
                // Ganti nomor ini dengan nomor admin Anda (format: 62xxx tanpa +)
                $admin_phone = "6285708953138"; // GANTI DENGAN NOMOR ADMIN ANDA
                
                // URL foto (full URL)
                $site_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
                $foto_url = $site_url . "/" . $upload_path;
                
                // Pesan WhatsApp
                $pesan = "🆕 *UPLOAD KARTU PELAJAR BARU*\n\n";
                $pesan .= "👤 Nama: " . $user_data['nama'] . "\n";
                $pesan .= "📧 Email: " . $user_data['email'] . "\n";
                $pesan .= "🆔 ID User: " . $user_id . "\n";
                $pesan .= "📸 Foto Kartu: " . $foto_url . "\n\n";
                $pesan .= "Silakan cek dan verifikasi kartu pelajar ini.";
                
                // Encode pesan untuk URL
                $pesan_encoded = urlencode($pesan);
                
                // URL WhatsApp API
                $wa_url = "https://api.whatsapp.com/send?phone=" . $admin_phone . "&text=" . $pesan_encoded;
                
                // Auto redirect ke WhatsApp (opsional, bisa dimatikan)
                // echo "<script>window.open('" . $wa_url . "', '_blank');</script>";
                
                $message = 'Kartu pelajar berhasil diupload! Admin akan segera memverifikasi.';
                $message .= '<br><a href="' . $wa_url . '" target="_blank" style="color: #25D366; font-weight: bold;">📱 Klik di sini untuk notifikasi admin via WhatsApp</a>';
                
            } else {
                $error = 'Gagal mengupload file.';
            }
        }
    } else {
        $error = 'Silakan pilih file untuk diupload.';
    }
}

// Ambil data kartu pelajar yang sudah diupload
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT kartu_pelajar, status_verifikasi FROM admin WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_card = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Kartu Pelajar - SMEKDA</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 50px auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        h1 {
            color: #2a5298;
            text-align: center;
            margin-bottom: 10px;
            font-size: 2em;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .info-box h4 {
            color: #1976D2;
            margin-bottom: 10px;
        }

        .upload-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: block;
            padding: 15px;
            background: white;
            border: 2px dashed #2a5298;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-input-label:hover {
            background: #f0f4ff;
            border-color: #1e3c72;
        }

        .file-input-label i {
            font-size: 2em;
            color: #2a5298;
            display: block;
            margin-bottom: 10px;
        }

        .help-text {
            font-size: 0.9em;
            color: #666;
            margin-top: 8px;
        }

        .btn {
            width: 100%;
            padding: 15px;
            font-size: 1.1em;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-whatsapp {
            background: #25D366;
            color: white;
            text-decoration: none;
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            margin-top: 10px;
            font-weight: 600;
        }

        .btn-whatsapp:hover {
            background: #20BA5A;
        }

        .current-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-top: 30px;
        }

        .current-card h3 {
            color: #2a5298;
            margin-bottom: 15px;
        }

        .card-preview {
            max-width: 100%;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
            margin-top: 10px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-verified {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #2a5298;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📸 Upload Kartu Pelajar</h1>
        <p class="subtitle">Upload foto kartu pelajar Anda untuk verifikasi</p>

        <div class="info-box">
            <h4>📱 Cara Kerja Sistem Ini:</h4>
            <p>1. Upload foto kartu pelajar Anda<br>
            2. Foto akan tersimpan di sistem<br>
            3. Anda akan mendapat link WhatsApp untuk notifikasi admin<br>
            4. Admin akan verifikasi dan konfirmasi via WhatsApp</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="upload-section">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>📋 Foto Kartu Pelajar *</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="kartu_pelajar" id="kartu_pelajar" accept="image/*" required>
                        <label for="kartu_pelajar" class="file-input-label">
                            <i>📁</i>
                            <span id="file-name">Klik untuk pilih file atau drag & drop</span>
                        </label>
                    </div>
                    <p class="help-text">
                        ℹ️ Upload foto kartu pelajar yang masih berlaku (Max 5MB, Format: JPG/PNG/GIF)
                    </p>
                </div>

                <button type="submit" class="btn btn-primary">
                    🚀 Upload & Kirim ke Admin
                </button>
            </form>
        </div>

        <?php if ($current_card && $current_card['kartu_pelajar']): ?>
            <div class="current-card">
                <h3>Kartu Pelajar Saat Ini</h3>
                <img src="uploads/kartu_pelajar/<?php echo htmlspecialchars($current_card['kartu_pelajar']); ?>" 
                     alt="Kartu Pelajar" class="card-preview">
                
                <?php
                $status = $current_card['status_verifikasi'];
                $status_class = 'status-pending';
                $status_text = '⏳ Menunggu Verifikasi';
                
                if ($status == 'verified') {
                    $status_class = 'status-verified';
                    $status_text = '✅ Terverifikasi';
                } elseif ($status == 'rejected') {
                    $status_class = 'status-rejected';
                    $status_text = '❌ Ditolak - Upload ulang';
                }
                ?>
                
                <span class="status-badge <?php echo $status_class; ?>">
                    <?php echo $status_text; ?>
                </span>
            </div>
        <?php endif; ?>

        <a href="dashboard.php" class="back-link">← Kembali ke Dashboard</a>
    </div>

    <script>
        // Preview nama file yang dipilih
        document.getElementById('kartu_pelajar').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'Klik untuk pilih file atau drag & drop';
            document.getElementById('file-name').textContent = fileName;
        });
    </script>
</body>
</html>