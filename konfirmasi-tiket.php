<?php
session_start();
require_once 'config/config.php';

// Include Midtrans config dan library
require_once __DIR__ . '/vendor/autoload.php';

use Midtrans\Config;
use Midtrans\Snap;

// Konfigurasi Midtrans - sesuaikan dengan konfigurasi Anda
// Pastikan Anda sudah memiliki file config/midtrans.php atau setting di config.php
Config::$serverKey = MIDTRANS_SERVER_KEY; // Define di config.php
Config::$clientKey = MIDTRANS_CLIENT_KEY; // Define di config.php
Config::$isProduction = false; // true untuk production
Config::$isSanitized = true;
Config::$is3ds = true;

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

// Generate Snap Token untuk Midtrans
try {
    // Siapkan parameter untuk Midtrans
    $transaction_details = [
        'order_id' => $data['kode_booking'],
        'gross_amount' => (int)$data['total_harga'],
    ];

    // Customer details - Anda bisa ambil dari database atau session
    $customer_details = [
        'first_name' => isset($_SESSION['user_nama']) ? $_SESSION['user_nama'] : 'Customer',
        'email' => isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'customer@example.com',
        'phone' => isset($_SESSION['user_telepon']) ? $_SESSION['user_telepon'] : '081234567890',
    ];

    // Item details
    $item_details = [
        [
            'id' => 'tiket-' . $tiket['id'],
            'price' => (int)$tiket['harga'],
            'quantity' => (int)$data['jumlah_tiket'],
            'name' => 'Tiket: ' . $tiket['pertandingan'],
        ]
    ];

    // Enable payments
    $enable_payments = [
        'credit_card',
        'gopay',
        'shopeepay',
        'bank_transfer',
        'echannel',
        'qris'
    ];

    $params = [
        'transaction_details' => $transaction_details,
        'customer_details' => $customer_details,
        'item_details' => $item_details,
        'enabled_payments' => $enable_payments,
        'callbacks' => [
            'finish' => BASE_URL . '/payment-success.php?order_id=' . $data['kode_booking']
        ]
    ];

    // Get Snap Token
    $snapToken = Snap::getSnapToken($params);
    
} catch (Exception $e) {
    $snapToken = null;
    $midtrans_error = $e->getMessage();
    error_log("Midtrans Error: " . $midtrans_error);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pemesanan Tiket - Smekda Jersey</title>
    
    <!-- Midtrans Snap JS -->
    <script type="text/javascript" 
        src="https://app.sandbox.midtrans.com/snap/snap.js" 
        data-client-key="<?php echo Config::$clientKey; ?>"></script>
    
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

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            flex-direction: column;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Countdown Timer */
        .countdown-timer {
            background: rgba(255, 107, 107, 0.1);
            border: 2px solid rgba(255, 107, 107, 0.3);
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .timer-title {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0.5rem;
        }
        
        .timer-display {
            font-size: 1.8rem;
            font-weight: 700;
            color: #ff6b6b;
            font-family: 'Courier New', monospace;
        }

        /* Error Message */
        .error-message {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid #ff6b6b;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
            color: #ff6b6b;
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
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <p style="color: #fff; font-size: 1.2rem;">Mengarahkan ke halaman pembayaran...</p>
    </div>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-ticket-alt"></i> Konfirmasi Pemesanan</h1>
            <p>Review detail pesanan Anda sebelum melanjutkan ke pembayaran</p>
        </div>

        <!-- Error Message -->
        <?php if (isset($midtrans_error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i> 
            Terjadi kesalahan: <?php echo htmlspecialchars($midtrans_error); ?>
        </div>
        <?php endif; ?>

        <!-- Countdown Timer -->
        <div class="countdown-timer">
            <div class="timer-title">
                <i class="fas fa-clock"></i> Selesaikan dalam:
            </div>
            <div class="timer-display" id="countdown">15:00</div>
        </div>

        <!-- Kode Booking -->
        <div class="kode-booking">
            <div class="label">KODE BOOKING</div>
            <div class="kode"><?php echo $data['kode_booking']; ?></div>
        </div>

        <!-- Informasi Tiket -->
        <div class="info-section">
            <h3 class="section-title"><i class="fas fa-info-circle"></i> Detail Pertandingan</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Pertandingan</div>
                    <div class="value"><?php echo htmlspecialchars($tiket['pertandingan']); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Tanggal & Waktu</div>
                    <div class="value"><?php echo date('d F Y', strtotime($tiket['tanggal'])); ?>, <?php echo $tiket['waktu']; ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Lokasi</div>
                    <div class="value"><?php echo htmlspecialchars($tiket['lokasi']); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Harga Tiket</div>
                    <div class="value">Rp <?php echo number_format($tiket['harga'], 0, ',', '.'); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Jumlah Tiket</div>
                    <div class="value"><?php echo $data['jumlah_tiket']; ?> Tiket</div>
                </div>
                <div class="info-item">
                    <div class="label">Stok Tersisa</div>
                    <div class="value"><?php echo $stok_tersisa; ?> Tiket</div>
                </div>
            </div>
        </div>

        <!-- Total Pembayaran -->
        <div class="total-section">
            <div class="label">TOTAL PEMBAYARAN</div>
            <div class="amount">Rp <?php echo number_format($data['total_harga'], 0, ',', '.'); ?></div>
        </div>

        <!-- Informasi Pembayaran Midtrans -->
        <div class="warning-box">
            <h3><i class="fas fa-credit-card"></i> Pembayaran via Midtrans</h3>
            <p>Kami mendukung berbagai metode pembayaran melalui Midtrans:</p>
            <ul>
                <li>Kartu Kredit/Debit (Visa, Mastercard)</li>
                <li>E-Wallet (GoPay, ShopeePay, OVO, Dana)</li>
                <li>Transfer Bank (BCA, Mandiri, BNI, BRI, dll)</li>
                <li>QRIS</li>
                <li>Alfamart/Indomaret</li>
            </ul>
            <p style="color: #ff6b6b; font-weight: 600;">
                <i class="fas fa-exclamation-triangle"></i> Pesanan akan otomatis dibatalkan setelah 15 menit!
            </p>
        </div>

        <!-- Tombol Aksi -->
        <div class="action-buttons">
            <a href="tiket.php?match_id=<?php echo $data['match_id']; ?>" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Kembali & Edit
            </a>
            
            <?php if ($snapToken): ?>
                <!-- Tombol Midtrans Payment -->
                <button class="btn btn-payment" id="pay-button">
                    <i class="fas fa-credit-card"></i> Lanjutkan Pembayaran
                </button>
            <?php else: ?>
                <button class="btn btn-payment" disabled style="opacity: 0.7;">
                    <i class="fas fa-exclamation-triangle"></i> Sistem Pembayaran Sedang Gangguan
                </button>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Countdown timer (15 menit)
        let countdownTime = 15 * 60; // 15 menit dalam detik
        const countdownElement = document.getElementById('countdown');

        function updateCountdown() {
            const minutes = Math.floor(countdownTime / 60);
            const seconds = countdownTime % 60;
            
            countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (countdownTime > 0) {
                countdownTime--;
                setTimeout(updateCountdown, 1000);
            } else {
                countdownElement.innerHTML = '<span style="color: #ff6b6b;">Waktu habis!</span>';
                showNotification('Waktu pembayaran telah habis. Silakan buat pesanan baru.', 'error');
                document.getElementById('pay-button').disabled = true;
            }
        }

        // Start countdown
        updateCountdown();

        // Midtrans payment button handler
        <?php if ($snapToken): ?>
        document.getElementById('pay-button').addEventListener('click', function() {
            // Show loading
            document.getElementById('loadingOverlay').style.display = 'flex';
            
            // Midtrans Snap payment
            snap.pay('<?php echo $snapToken; ?>', {
                onSuccess: function(result){
                    console.log('Payment success:', result);
                    window.location.href = 'payment-success.php?order_id=' + result.order_id + '&status=success';
                },
                onPending: function(result){
                    console.log('Payment pending:', result);
                    window.location.href = 'payment-success.php?order_id=' + result.order_id + '&status=pending';
                },
                onError: function(result){
                    console.log('Payment error:', result);
                    document.getElementById('loadingOverlay').style.display = 'none';
                    showNotification('Pembayaran gagal: ' + (result.status_message || 'Silakan coba lagi'), 'error');
                },
                onClose: function(){
                    console.log('Payment popup closed');
                    document.getElementById('loadingOverlay').style.display = 'none';
                }
            });
        });
        <?php endif; ?>

        // Show notification function
        function showNotification(message, type) {
            // Remove existing notification
            const existingNotification = document.querySelector('.notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div style="position: fixed; top: 100px; right: 20px; 
                           background: ${type === 'success' ? 'rgba(37, 211, 102, 0.9)' : 'rgba(255, 107, 107, 0.9)'}; 
                           color: white; padding: 1rem 1.5rem; border-radius: 10px; 
                           backdrop-filter: blur(10px); z-index: 10000; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                           display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Auto scroll to top
        window.scrollTo(0, 0);
    </script>
</body>
</html>