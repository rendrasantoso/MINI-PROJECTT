<?php
session_start();
require_once 'config/config.php';

// Jika tidak ada data di session, redirect ke tiket
if (!isset($_SESSION['tiket_pemesanan'])) {
    $_SESSION['error'] = "Tidak ada data pemesanan!";
    header('Location: tiket.php');
    exit;
}

$data = $_SESSION['tiket_pemesanan'];

// Ambil detail tiket dari database untuk ditampilkan
$stmt = $pdo->prepare("
    SELECT t.*, jm.pertandingan, jm.tanggal, jm.waktu, jm.lokasi 
    FROM tiket t 
    LEFT JOIN jadwal_match jm ON t.match_id = jm.id 
    WHERE t.id = ?
");
$stmt->execute([$data['tiket_id']]);
$tiket = $stmt->fetch();

if (!$tiket) {
    $_SESSION['error'] = "Tiket tidak ditemukan!";
    unset($_SESSION['tiket_pemesanan']);
    header('Location: tiket.php');
    exit;
}

// Hitung stok tersisa
$stok_tersisa = $tiket['stok'] - $tiket['terjual'];

// Jika stok sudah habis, beri error
if ($data['jumlah_tiket'] > $stok_tersisa) {
    $_SESSION['error'] = "Stok tidak mencukupi! Stok tersisa: " . $stok_tersisa . " tiket";
    unset($_SESSION['tiket_pemesanan']);
    header('Location: tiket.php?match_id=' . $data['match_id']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pemesanan Tiket - Smekda Jersey</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 100%);
            color: #fff;
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: linear-gradient(135deg, #1e1e2e 0%, #2a2a3e 100%);
            border-radius: 20px;
            padding: 3rem;
            border: 2px solid rgba(102, 126, 234, 0.3);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .header h1 {
            font-size: 2.8rem;
            background: linear-gradient(45deg, #25d366, #48dbfb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .header p {
            color: #aaa;
            font-size: 1.1rem;
        }

        .kode-booking {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 2rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .kode-booking .label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0.5rem;
        }

        .kode-booking .kode {
            font-size: 2.5rem;
            font-weight: 900;
            letter-spacing: 3px;
            color: #fff;
        }

        .info-section {
            background: rgba(255, 255, 255, 0.05);
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.4rem;
            color: #667eea;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .section-title i {
            font-size: 1.2rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .info-item .label {
            font-size: 0.85rem;
            color: #aaa;
            margin-bottom: 0.3rem;
        }

        .info-item .value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
        }

        .total-section {
            background: linear-gradient(135deg, rgba(37, 211, 102, 0.1), rgba(72, 219, 251, 0.1));
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            border: 2px solid #25d366;
            margin-bottom: 3rem;
        }

        .total-section .label {
            font-size: 1.1rem;
            color: #25d366;
            margin-bottom: 0.5rem;
        }

        .total-section .amount {
            font-size: 3rem;
            font-weight: 900;
            background: linear-gradient(45deg, #25d366, #48dbfb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .action-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            min-width: 200px;
            justify-content: center;
        }

        .btn-payment {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
        }

        .btn-payment:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }

        .btn-edit {
            background: linear-gradient(135deg, #feca57, #ff9ff3);
            color: #fff;
        }

        .btn-edit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(254, 202, 87, 0.4);
        }

        .btn-back {
            background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
            color: #fff;
        }

        .btn-back:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(255, 107, 107, 0.4);
        }

        .warning-box {
            background: rgba(254, 202, 87, 0.1);
            border: 2px solid #feca57;
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .warning-box h3 {
            color: #feca57;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 0.8rem;
        }

        .warning-box ul {
            color: #ddd;
            padding-left: 1.5rem;
            margin-bottom: 0.8rem;
        }

        .warning-box li {
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .container {
                padding: 2rem 1.5rem;
            }

            .header h1 {
                font-size: 2rem;
            }

            .kode-booking .kode {
                font-size: 1.8rem;
            }

            .total-section .amount {
                font-size: 2.2rem;
            }

            .btn {
                min-width: 100%;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ... konten HTML tetap sama ... -->
        
        <div class="action-buttons">
            <a href="tiket.php?match_id=<?php echo $data['match_id']; ?>" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Kembali & Edit
            </a>
            
            <!-- PERBAIKAN: Kirim semua data yang diperlukan -->
            <form action="proses_tiket.php" method="POST" style="display: inline;" id="confirmForm">
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="kode_booking" value="<?php echo $data['kode_booking']; ?>">
                <input type="hidden" name="total_harga" value="<?php echo $data['total_harga']; ?>">
                
                <!-- Tambahkan loading state -->
                <button type="submit" class="btn btn-payment" id="confirmBtn">
                    <i class="fas fa-credit-card"></i> Lanjutkan Pembayaran
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Tambahkan loading state saat form submit
        document.getElementById('confirmForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('confirmBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            btn.disabled = true;
            
            // Tambahkan delay kecil agar loading terlihat
            setTimeout(() => {
                // Form akan submit secara normal
            }, 100);
        });
        
        // Auto scroll to top
        window.scrollTo(0, 0);
    </script>
</body>
</html>