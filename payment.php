<?php
// payment.php
session_start();
require_once __DIR__ . '/config/config.php';

// Cek apakah ada data pesanan di session
if (!isset($_SESSION['payment_data'])) {
    // Jika ada order_id dari GET, coba ambil dari database
    $order_id = $_GET['order'] ?? '';
    
    if (!empty($order_id)) {
        try {
            $stmt = $pdo->prepare("
                SELECT pm.* 
                FROM pemesanan_merchandise pm
                WHERE pm.kode_pesanan = ? 
                LIMIT 1
            ");
            $stmt->execute([$order_id]);
            $pemesanan = $stmt->fetch();
            
            if ($pemesanan) {
                $_SESSION['payment_data'] = [
                    'pemesanan_id' => $pemesanan['id'],
                    'kode_pesanan' => $pemesanan['kode_pesanan'],
                    'nama' => $pemesanan['nama'],
                    'no_hp' => $pemesanan['no_hp'],
                    'produk' => $pemesanan['produk'],
                    'jumlah' => $pemesanan['jumlah'],
                    'total_harga' => $pemesanan['total_harga'],
                    'catatan' => $pemesanan['catatan'],
                    'file_path' => $pemesanan['foto_kartu_pelajar'],
                    'waktu_pemesanan' => $pemesanan['tanggal_pemesanan']
                ];
            }
        } catch (Exception $e) {
            error_log("Error mengambil data pesanan: " . $e->getMessage());
        }
    }
    
    // Jika masih tidak ada data, redirect ke index
    if (empty($_SESSION['payment_data'])) {
        $_SESSION['error'] = 'Tidak ada data pesanan. Silakan isi form pemesanan terlebih dahulu.';
        header('Location: index.php#form-pemesanan');
        exit;
    }
}

$data = $_SESSION['payment_data'];

// Buat Snap Token untuk Midtrans
try {
    // Siapkan data untuk Midtrans
    $transaction_details = array(
        'order_id' => $data['kode_pesanan'],
        'gross_amount' => $data['total_harga']
    );
    
    $customer_details = array(
        'first_name' => $data['nama'],
        'phone' => $data['no_hp'],
        'email' => $data['no_hp'] . '@smekda.com'
    );
    
    $item_details = array(
        array(
            'id' => 'item-' . $data['kode_pesanan'],
            'price' => $data['total_harga'],
            'quantity' => 1,
            'name' => $data['produk'] . ' (x' . $data['jumlah'] . ')'
        )
    );
    
    $transaction_data = array(
        'transaction_details' => $transaction_details,
        'customer_details' => $customer_details,
        'item_details' => $item_details,
        'callbacks' => array(
            'finish' => BASE_URL . '/payment-success.php'
        )
    );
    
    // Generate Snap Token
    $snapToken = \Midtrans\Snap::getSnapToken($transaction_data);
    
    // Simpan ke session
    $_SESSION['snap_token'] = $snapToken;
    
} catch (Exception $e) {
    error_log("Error Midtrans: " . $e->getMessage());
    $snapToken = null;
    $_SESSION['error'] = 'Gagal membuat token pembayaran. Silakan coba lagi.';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ULTRAS SMEKDA - Pembayaran</title>
    
    <!-- Midtrans Snap JS -->
    <script type="text/javascript"
        src="https://app.sandbox.midtrans.com/snap/snap.js"
        data-client-key="<?php echo MIDTRANS_CLIENT_KEY; ?>"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        *::before, *::after { 
            display: none !important; 
            background: none !important;
            content: none !important;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 30%, #16213e 70%, #0a0a0a 100%);
            color: #fff;
            overflow-x: hidden;
            scroll-behavior: smooth;
            min-height: 100vh;
        }

        /* ========== CONTAINER FLUID ========== */
        .container {
            width: 92%;
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* ========== NAVBAR GLASS ========== */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 1000;
            padding: 1.2rem 0;
            border-bottom: 1px solid rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            padding: 0.8rem 0;
            background: rgba(10, 10, 10, 0.98);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 92%;
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .nav-logo {
            font-size: 1.8rem;
            font-weight: 900;
            background: linear-gradient(45deg, #667eea, #ff6b6b, #feca57);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .nav-logo i {
            font-size: 1.5rem;
        }

        /* ========== PAYMENT CONTAINER ========== */
        .payment-container {
            padding-top: 120px;
            padding-bottom: 80px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .payment-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(30px);
            border-radius: 40px;
            padding: 4rem;
            width: 100%;
            max-width: 900px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .payment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #ff6b6b, #feca57);
        }

        .payment-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .payment-header h1 {
            font-size: 3.2rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, #667eea, #ff6b6b, #feca57);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .payment-header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.2rem;
        }

        /* ========== ORDER SUMMARY ========== */
        .order-summary {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 25px;
            padding: 2.5rem;
            margin-bottom: 3rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .summary-title {
            font-size: 1.8rem;
            margin-bottom: 2rem;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .summary-label {
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
        }

        .summary-value {
            color: #fff;
            font-weight: 600;
        }

        .total-amount {
            background: rgba(254, 202, 87, 0.15);
            border: 2px solid rgba(254, 202, 87, 0.3);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            margin-top: 2rem;
        }

        .total-label {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0.5rem;
        }

        .total-price {
            font-size: 2.8rem;
            font-weight: 800;
            color: #feca57;
        }

        /* ========== PAYMENT INFO ========== */
        .payment-info {
            background: rgba(94, 114, 228, 0.15);
            border: 2px solid rgba(94, 114, 228, 0.3);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .payment-info h4 {
            color: #5e72e4;
            margin-bottom: 1rem;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .method-item {
            text-align: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .method-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-5px);
        }

        .method-icon {
            font-size: 2rem;
            color: #5e72e4;
            margin-bottom: 0.5rem;
        }

        .method-name {
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* ========== COUNTDOWN TIMER ========== */
        .countdown-timer {
            background: rgba(255, 107, 107, 0.15);
            border: 2px solid rgba(255, 107, 107, 0.3);
            border-radius: 20px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 2rem;
        }

        .timer-title {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0.5rem;
        }

        .timer-display {
            font-size: 2rem;
            font-weight: 700;
            color: #ff6b6b;
            font-family: 'Courier New', monospace;
        }

        /* ========== BUTTONS ========== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #ff6b6b 100%);
            color: white;
            padding: 1.4rem 3.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.3rem;
            border: none;
            cursor: pointer;
            transition: all 0.4s ease;
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 60px rgba(102, 126, 234, 0.5);
        }

        .btn-midtrans {
            background: linear-gradient(135deg, #5e72e4, #825ee4);
            box-shadow: 0 15px 40px rgba(94, 114, 228, 0.3);
        }

        .btn-midtrans:hover {
            box-shadow: 0 25px 60px rgba(94, 114, 228, 0.5);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* ========== INSTRUCTION BOX ========== */
        .instruction-box {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 20px;
            padding: 2rem;
            margin-top: 2rem;
        }

        .instruction-title {
            font-size: 1.4rem;
            color: #667eea;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .instruction-content ul {
            padding-left: 1.5rem;
            margin-top: 1rem;
        }

        .instruction-content li {
            margin-bottom: 0.8rem;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.6;
        }

        /* ========== LOADING ========== */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 10, 10, 0.95);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .loading.active {
            display: flex;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(102, 126, 234, 0.2);
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ========== ERROR MESSAGE ========== */
        .error-message {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid #ff6b6b;
            color: #ff6b6b;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .payment-card {
                padding: 2.5rem 2rem;
            }
            
            .payment-header h1 {
                font-size: 2.5rem;
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
            }
            
            .payment-methods {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .btn {
                padding: 1.2rem 2.5rem;
                font-size: 1.1rem;
            }
        }

        @media (max-width: 480px) {
            .payment-card {
                padding: 2rem 1.5rem;
                border-radius: 30px;
            }
            
            .payment-header h1 {
                font-size: 2rem;
            }
            
            .payment-header p {
                font-size: 1rem;
            }
            
            .total-price {
                font-size: 2rem;
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>

<body>
    <!-- Loading Animation -->
    <div class="loading">
        <div class="spinner"></div>
    </div>

    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">
                <i class="fas fa-fire"></i>
                SMEKDA
            </a>
        </div>
    </nav>

    <!-- Payment Section -->
    <div class="payment-container container">
        <div class="payment-card">
            <div class="payment-header">
                <h1><i class="fas fa-credit-card"></i> Pembayaran</h1>
                <p>Selesaikan pembayaran untuk menyelesaikan pesanan Anda</p>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>

            <!-- Countdown Timer -->
            <div class="countdown-timer">
                <div class="timer-title">
                    <i class="fas fa-clock"></i> Selesaikan pembayaran dalam:
                </div>
                <div class="timer-display" id="countdown">15:00</div>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <h3 class="summary-title"><i class="fas fa-receipt"></i> Ringkasan Pesanan</h3>
                
                <div class="summary-grid">
                    <div class="summary-item">
                        <span class="summary-label">Kode Pesanan:</span>
                        <span class="summary-value" style="color: #feca57;"><?php echo htmlspecialchars($data['kode_pesanan']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Nama:</span>
                        <span class="summary-value"><?php echo htmlspecialchars($data['nama']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">No. HP:</span>
                        <span class="summary-value"><?php echo htmlspecialchars($data['no_hp']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Produk:</span>
                        <span class="summary-value"><?php echo htmlspecialchars($data['produk']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Jumlah:</span>
                        <span class="summary-value"><?php echo $data['jumlah']; ?> unit</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Harga Satuan:</span>
                        <span class="summary-value">Rp <?php echo number_format($data['total_harga'] / $data['jumlah'], 0, ',', '.'); ?></span>
                    </div>
                </div>

                <div class="total-amount">
                    <div class="total-label">Total yang harus dibayar:</div>
                    <div class="total-price">
                        Rp <?php echo number_format($data['total_harga'], 0, ',', '.'); ?>
                    </div>
                </div>
            </div>

            <!-- Midtrans Payment Info -->
            <div class="payment-info">
                <h4><i class="fas fa-lock"></i> Pembayaran via Midtrans</h4>
                <p style="color: rgba(255, 255, 255, 0.8); line-height: 1.6;">
                    Klik tombol "BAYAR SEKARANG" untuk membuka halaman pembayaran Midtrans. 
                    Pilih metode pembayaran yang Anda inginkan.
                </p>
                
                <div class="payment-methods">
                    <div class="method-item">
                        <div class="method-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="method-name">Kartu Kredit/Debit</div>
                    </div>
                    <div class="method-item">
                        <div class="method-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="method-name">E-Wallet</div>
                    </div>
                    <div class="method-item">
                        <div class="method-icon">
                            <i class="fas fa-university"></i>
                        </div>
                        <div class="method-name">Bank Transfer</div>
                    </div>
                    <div class="method-item">
                        <div class="method-icon">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="method-name">Minimarket</div>
                    </div>
                </div>
            </div>

            <!-- Midtrans Payment Button -->
            <?php if ($snapToken): ?>
            <button class="btn btn-midtrans" id="pay-button">
                <i class="fas fa-credit-card"></i> BAYAR SEKARANG
            </button>
            <?php else: ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                Gagal membuat token pembayaran. Silakan coba lagi atau hubungi admin.
            </div>
            <?php endif; ?>

            <!-- Instruction Box -->
            <div class="instruction-box">
                <h4 class="instruction-title"><i class="fas fa-info-circle"></i> Cara Pembayaran</h4>
                <div class="instruction-content">
                    <ul>
                        <li>Klik tombol "BAYAR SEKARANG" di atas</li>
                        <li>Pilih metode pembayaran yang tersedia</li>
                        <li>Ikuti instruksi pada halaman pembayaran Midtrans</li>
                        <li>Setelah pembayaran berhasil, Anda akan diarahkan ke halaman konfirmasi</li>
                        <li>Simpan bukti pembayaran Anda</li>
                    </ul>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 15px; margin-top: 2rem;">
                <a href="index.php" class="btn btn-secondary" style="flex: 1;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                </a>
                <button onclick="cancelOrder()" class="btn btn-secondary" 
                   style="flex: 1; background: rgba(255, 107, 107, 0.1); border: 1px solid rgba(255, 107, 107, 0.3);">
                    <i class="fas fa-times"></i> Batalkan Pesanan
                </button>
            </div>
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
        document.getElementById('pay-button').addEventListener('click', function() {
            // Show loading
            document.querySelector('.loading').classList.add('active');
            
            // Midtrans Snap payment
            snap.pay('<?php echo $snapToken; ?>', {
                onSuccess: function(result){
                    console.log('Payment success:', result);
                    window.location.href = 'payment-success.php?order_id=' + result.order_id;
                },
                onPending: function(result){
                    console.log('Payment pending:', result);
                    window.location.href = 'payment-success.php?order_id=' + result.order_id + '&status=pending';
                },
                onError: function(result){
                    console.log('Payment error:', result);
                    document.querySelector('.loading').classList.remove('active');
                    showNotification('Pembayaran gagal: ' + (result.status_message || 'Silakan coba lagi'), 'error');
                },
                onClose: function(){
                    console.log('Payment popup closed');
                    document.querySelector('.loading').classList.remove('active');
                }
            });
        });

        // Cancel order function
        function cancelOrder() {
            if (confirm('Apakah Anda yakin ingin membatalkan pesanan?')) {
                document.querySelector('.loading').classList.add('active');
                
                // AJAX request to cancel order
                fetch('cancel_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'kode_pesanan=<?php echo urlencode($data['kode_pesanan']); ?>'
                })
                .then(response => response.json())
                .then(data => {
                    document.querySelector('.loading').classList.remove('active');
                    if (data.success) {
                        showNotification('Pesanan berhasil dibatalkan.', 'success');
                        setTimeout(() => {
                            window.location.href = 'index.php';
                        }, 2000);
                    } else {
                        showNotification('Gagal membatalkan pesanan: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    document.querySelector('.loading').classList.remove('active');
                    showNotification('Terjadi kesalahan. Silakan coba lagi.', 'error');
                });
            }
        }

        // Show notification
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
                <div style="position: fixed; top: 100px; right: 20px; background: ${type === 'success' ? 'rgba(37, 211, 102, 0.9)' : 'rgba(255, 107, 107, 0.9)'}; 
                           color: white; padding: 1rem 1.5rem; border-radius: 10px; 
                           backdrop-filter: blur(10px); z-index: 10000; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                           display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s ease;">
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

        // Add CSS for notification animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);

        // Navbar scroll effect
        const navbar = document.querySelector('.navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 100) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>