<?php
session_start();
require_once __DIR__ . '/config/config.php';

$order_id = $_GET['order_id'] ?? '';

// Clear session
unset($_SESSION['pesanan_id']);
unset($_SESSION['order_id_midtrans']);
unset($_SESSION['snap_token']);
unset($_SESSION['total_harga']);
unset($_SESSION['pesanan_data']);

if ($order_id) {
    // Update status pesanan di database
    $stmt = $pdo->prepare("
        UPDATE pesanan 
        SET status = 'failed', 
            transaction_status = 'error'
        WHERE order_id_midtrans = ?
    ");
    $stmt->execute([$order_id]);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Gagal - Ultras Smekda</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; border-radius: 20px; padding: 40px; max-width: 600px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); text-align: center; }
        .error-icon { width: 100px; height: 100px; background: linear-gradient(135deg, #ff6b6b, #ee5a6f); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 30px; }
        .error-icon i { color: white; font-size: 50px; }
        h1 { color: #333; font-size: 2rem; margin-bottom: 15px; }
        .info-box { background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 10px; padding: 20px; margin: 20px 0; text-align: left; }
        .btn { display: inline-block; background: #667eea; color: white; padding: 15px 30px; border-radius: 10px; text-decoration: none; margin: 10px; }
        .btn-retry { background: #ff6b6b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">
            <i class="fas fa-times"></i>
        </div>
        
        <h1>Pembayaran Gagal</h1>
        <p>Terjadi kesalahan dalam proses pembayaran.</p>
        
        <div class="info-box">
            <p><strong>Status:</strong> Pembayaran gagal</p>
            <p><strong>Order ID:</strong> <?php echo htmlspecialchars($order_id); ?></p>
            <p>Silakan coba lagi atau gunakan metode pembayaran lainnya.</p>
        </div>
        
        <a href="index.php" class="btn">
            <i class="fas fa-home"></i> Kembali ke Beranda
        </a>
        <a href="reset_session.php" class="btn btn-retry">
            <i class="fas fa-redo"></i> Coba Lagi
        </a>
    </div>
</body>
</html>