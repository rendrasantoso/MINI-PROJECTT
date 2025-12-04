<?php
session_start();
require_once __DIR__ . '/config/config.php';

$order_id = $_GET['order_id'] ?? '';

// Clear session payment data
$payment_data = $_SESSION['payment_data'] ?? null;
unset($_SESSION['payment_data']);

if ($order_id) {
    // Update status pesanan di database
    try {
        $stmt = $pdo->prepare("
            UPDATE pemesanan_merchandise 
            SET metode_pembayaran = 'midtrans',
                status_pembayaran = 'paid',
                status = 'confirmed',
                updated_at = NOW()
            WHERE kode_pesanan = ?
        ");
        $stmt->execute([$order_id]);
        
        // Ambil data pesanan
        $stmt = $pdo->prepare("SELECT * FROM pemesanan_merchandise WHERE kode_pesanan = ?");
        $stmt->execute([$order_id]);
        $pesanan = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error updating payment status: " . $e->getMessage());
    }
}

// Generate WhatsApp URL
if ($pesanan) {
    $wa_message = "Halo admin Ultras Smekda, saya sudah melakukan pembayaran untuk pesanan:\n\n";
    $wa_message .= "Kode Pesanan: " . $pesanan['kode_pesanan'] . "\n";
    $wa_message .= "Nama: " . $pesanan['nama'] . "\n";
    $wa_message .= "No. HP: " . $pesanan['no_hp'] . "\n";
    $wa_message .= "Produk: " . $pesanan['produk'] . "\n";
    $wa_message .= "Jumlah: " . $pesanan['jumlah'] . " unit\n";
    $wa_message .= "Total: Rp " . number_format($pesanan['total_harga'], 0, ',', '.') . "\n\n";
    $wa_message .= "Silakan verifikasi pesanan saya.";
} elseif ($payment_data) {
    $wa_message = "Halo admin Ultras Smekda, saya sudah melakukan pembayaran untuk pesanan:\n\n";
    $wa_message .= "Kode Pesanan: " . $payment_data['kode_pesanan'] . "\n";
    $wa_message .= "Nama: " . $payment_data['nama'] . "\n";
    $wa_message .= "No. HP: " . $payment_data['no_hp'] . "\n";
    $wa_message .= "Produk: " . $payment_data['produk'] . "\n";
    $wa_message .= "Jumlah: " . $payment_data['jumlah'] . " unit\n";
    $wa_message .= "Total: Rp " . number_format($payment_data['total_harga'], 0, ',', '.') . "\n\n";
    $wa_message .= "Silakan verifikasi pesanan saya.";
} else {
    $wa_message = "Halo admin Ultras Smekda, saya sudah melakukan pembayaran untuk merchandise.\n\nSilakan verifikasi pesanan saya.";
}

$wa_url = "https://wa.me/6281235033165?text=" . urlencode($wa_message);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ULTRAS SMEKDA - Pembayaran Berhasil</title>
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

        /* ========== SUCCESS CONTAINER ========== */
        .success-container {
            padding-top: 120px;
            padding-bottom: 80px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .success-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(30px);
            border-radius: 40px;
            padding: 4rem;
            width: 100%;
            max-width: 800px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .success-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #25d366, #128c7e, #25d366);
        }

        .success-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #25d366, #128c7e);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            animation: scaleIn 0.5s ease 0.3s both;
        }

        @keyframes scaleIn {
            from { transform: scale(0); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .success-icon i {
            color: white;
            font-size: 60px;
        }

        .success-header h1 {
            font-size: 3.2rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, #25d366, #128c7e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .success-header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.2rem;
            margin-bottom: 3rem;
        }

        /* ========== ORDER DETAILS ========== */
        .order-details {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 25px;
            padding: 2.5rem;
            margin-bottom: 3rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: left;
        }

        .details-title {
            font-size: 1.8rem;
            margin-bottom: 2rem;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 15px;
            text-align: center;
            justify-content: center;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .detail-label {
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
        }

        .detail-value {
            color: #fff;
            font-weight: 600;
        }

        .status-badge {
            background: rgba(37, 211, 102, 0.15);
            border: 2px solid rgba(37, 211, 102, 0.3);
            border-radius: 20px;
            padding: 1rem 2rem;
            text-align: center;
            margin-top: 2rem;
        }

        .status-text {
            font-size: 1.2rem;
            color: #25d366;
            font-weight: 700;
        }

        /* ========== INFO BOX ========== */
        .info-box {
            background: rgba(37, 211, 102, 0.1);
            border: 2px solid rgba(37, 211, 102, 0.3);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 3rem;
            text-align: left;
        }

        .info-title {
            font-size: 1.4rem;
            color: #25d366;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-content ul {
            padding-left: 1.5rem;
            margin-top: 1rem;
        }

        .info-content li {
            margin-bottom: 0.8rem;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.6;
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
        }

        .btn:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 60px rgba(102, 126, 234, 0.5);
        }

        .btn-success {
            background: linear-gradient(135deg, #25d366, #128c7e);
            box-shadow: 0 15px 40px rgba(37, 211, 102, 0.3);
        }

        .btn-success:hover {
            box-shadow: 0 25px 60px rgba(37, 211, 102, 0.5);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .btn-container {
            display: flex;
            gap: 15px;
            margin-top: 2rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .success-card {
                padding: 2.5rem 2rem;
            }
            
            .success-header h1 {
                font-size: 2.5rem;
            }
            
            .success-icon {
                width: 100px;
                height: 100px;
            }
            
            .success-icon i {
                font-size: 50px;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .btn-container {
                flex-direction: column;
            }
            
            .btn {
                padding: 1.2rem 2.5rem;
                font-size: 1.1rem;
            }
        }

        @media (max-width: 480px) {
            .success-card {
                padding: 2rem 1.5rem;
                border-radius: 30px;
            }
            
            .success-header h1 {
                font-size: 2rem;
            }
            
            .success-header p {
                font-size: 1rem;
            }
            
            .success-icon {
                width: 80px;
                height: 80px;
            }
            
            .success-icon i {
                font-size: 40px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>

<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">
                <i class="fas fa-fire"></i>
                SMEKDA
            </a>
        </div>
    </nav>

    <!-- Success Section -->
    <div class="success-container container">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <div class="success-header">
                <h1>Pembayaran Berhasil!</h1>
                <p>Terima kasih telah melakukan pembayaran</p>
            </div>
            
            <?php if (isset($pesanan) || isset($payment_data)): ?>
            <div class="order-details">
                <h3 class="details-title"><i class="fas fa-receipt"></i> Detail Pesanan</h3>
                
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Kode Pesanan:</span>
                        <span class="detail-value" style="color: #feca57;">
                            <?php echo htmlspecialchars($pesanan['kode_pesanan'] ?? $payment_data['kode_pesanan'] ?? ''); ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Nama:</span>
                        <span class="detail-value">
                            <?php echo htmlspecialchars($pesanan['nama'] ?? $payment_data['nama'] ?? ''); ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">No. HP:</span>
                        <span class="detail-value">
                            <?php echo htmlspecialchars($pesanan['no_hp'] ?? $payment_data['no_hp'] ?? ''); ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Produk:</span>
                        <span class="detail-value">
                            <?php echo htmlspecialchars($pesanan['produk'] ?? $payment_data['produk'] ?? ''); ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Jumlah:</span>
                        <span class="detail-value">
                            <?php echo $pesanan['jumlah'] ?? $payment_data['jumlah'] ?? ''; ?> unit
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Total:</span>
                        <span class="detail-value" style="color: #feca57;">
                            Rp <?php echo number_format($pesanan['total_harga'] ?? $payment_data['total_harga'] ?? 0, 0, ',', '.'); ?>
                        </span>
                    </div>
                </div>
                
                <div class="status-badge">
                    <div class="status-text">
                        <i class="fas fa-check-circle"></i> PEMBAYARAN LUNAS
                    </div>
                </div>
            </div>
            
            <div class="info-box">
                <h4 class="info-title"><i class="fas fa-info-circle"></i> Informasi Penting</h4>
                <div class="info-content">
                    <ul>
                        <li>Pesanan Anda sedang diproses</li>
                        <li>Admin akan menghubungi Anda untuk konfirmasi pengambilan merchandise</li>
                        <li>Simpan bukti pembayaran dari Midtrans sebagai referensi</li>
                        <li>Pengambilan merchandise di WARKOP SATAM dengan menunjukkan kartu pelajar</li>
                        <li>Hubungi admin jika ada pertanyaan: 0812-3503-3165</li>
                    </ul>
                </div>
            </div>
            
            <div class="btn-container">
                <a href="<?php echo $wa_url; ?>" class="btn btn-success" target="_blank">
                    <i class="fab fa-whatsapp"></i> Konfirmasi ke Admin
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Kembali ke Beranda
                </a>
            </div>
            <?php else: ?>
            <div class="info-box" style="background: rgba(254, 202, 87, 0.1); border-color: rgba(254, 202, 87, 0.3);">
                <h4 class="info-title" style="color: #feca57;">
                    <i class="fas fa-exclamation-triangle"></i> Informasi
                </h4>
                <div class="info-content">
                    <p>Data pesanan tidak ditemukan atau sudah dihapus dari session.</p>
                    <p>Silakan hubungi admin untuk konfirmasi pembayaran Anda.</p>
                </div>
            </div>
            
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Kembali ke Beranda
            </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto scroll ke atas
        window.scrollTo(0, 0);
        
        // Confetti effect untuk sukses
        function createConfetti() {
            const colors = ['#667eea', '#ff6b6b', '#feca57', '#25d366', '#764ba2'];
            const confettiCount = 100;
            
            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.style.position = 'fixed';
                confetti.style.width = '10px';
                confetti.style.height = '10px';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.borderRadius = '50%';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.top = '-20px';
                confetti.style.zIndex = '9998';
                confetti.style.pointerEvents = 'none';
                
                document.body.appendChild(confetti);
                
                // Animation
                const animation = confetti.animate([
                    { transform: 'translateY(0) rotate(0deg)', opacity: 1 },
                    { transform: `translateY(${window.innerHeight + 100}px) rotate(${Math.random() * 360}deg)`, opacity: 0 }
                ], {
                    duration: Math.random() * 3000 + 2000,
                    easing: 'cubic-bezier(0.215, 0.610, 0.355, 1)'
                });
                
                animation.onfinish = () => confetti.remove();
            }
        }
        
        // Jalankan confetti saat halaman dimuat
        window.addEventListener('load', () => {
            setTimeout(createConfetti, 500);
        });
    </script>
</body>
</html>