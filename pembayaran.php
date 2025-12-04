<?php
session_start();

// Cek apakah ada data pesanan
if (!isset($_SESSION['snap_token'])) {
    header('Location: index.php');
    exit;
}

$pesanan_id = $_SESSION['pesanan_id'];
$snap_token = $_SESSION['snap_token'];
$total_harga = $_SESSION['total_harga'];
$pesanan_data = $_SESSION['pesanan_data'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - Ultras Smekda</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script type="text/javascript"
        src="https://app.sandbox.midtrans.com/snap/snap.js"
        data-client-key="SB-Mid-client-your-client-key-here"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 800px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
            font-size: 1.1rem;
        }
        .content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .order-summary {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
        }
        .order-summary h3 {
            color: #667eea;
            margin-bottom: 20px;
            text-align: center;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #666;
            font-weight: 600;
        }
        .detail-value {
            color: #333;
            font-weight: 700;
        }
        .total-price {
            background: #667eea;
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin-top: 20px;
        }
        .payment-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
        }
        .payment-section h3 {
            color: #667eea;
            margin-bottom: 20px;
            text-align: center;
        }
        .payment-instruction {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .payment-instruction p {
            color: #856404;
            line-height: 1.6;
            margin: 5px 0;
        }
        .btn-container {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        .btn {
            flex: 1;
            padding: 15px 25px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-pay {
            background: #667eea;
            color: white;
        }
        .btn-pay:hover {
            background: #5568d3;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(108, 117, 125, 0.3);
        }
        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
            }
            .container {
                padding: 30px 20px;
            }
            .header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-credit-card"></i> Pembayaran</h1>
            <p>Selesaikan pembayaran untuk pesanan Anda</p>
        </div>
        
        <div class="content">
            <div class="order-summary">
                <h3><i class="fas fa-receipt"></i> Ringkasan Pesanan</h3>
                <div class="detail-row">
                    <span class="detail-label">ID Pesanan:</span>
                    <span class="detail-value">#<?php echo str_pad($pesanan_id, 3, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Nama:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($pesanan_data['nama']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Produk:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($pesanan_data['produk']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Jumlah:</span>
                    <span class="detail-value"><?php echo $pesanan_data['jumlah']; ?> pcs</span>
                </div>
                <div class="total-price">
                    Total: Rp <?php echo number_format($total_harga, 0, ',', '.'); ?>
                </div>
            </div>
            
            <div class="payment-section">
                <h3><i class="fas fa-wallet"></i> Metode Pembayaran</h3>
                
                <div class="payment-instruction">
                    <p><i class="fas fa-info-circle"></i> <strong>Pembayaran via Midtrans</strong></p>
                    <p>Pilih metode pembayaran yang tersedia:</p>
                    <p>• Credit/Debit Card</p>
                    <p>• Bank Transfer</p>
                    <p>• E-wallet (Gopay, ShopeePay, dll)</p>
                </div>
                
                <button id="pay-button" class="btn btn-pay">
                    <i class="fas fa-credit-card"></i> Bayar Sekarang
                </button>
                
                <div class="btn-container">
                    <a href="index.php" class="btn btn-cancel">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        document.getElementById('pay-button').onclick = function(){
            snap.pay('<?php echo $snap_token; ?>', {
                onSuccess: function(result){
                    window.location.href = 'payment-success.php?order_id=<?php echo $_SESSION['order_id_midtrans']; ?>';
                },
                onPending: function(result){
                    window.location.href = 'payment-pending.php?order_id=<?php echo $_SESSION['order_id_midtrans']; ?>';
                },
                onError: function(result){
                    window.location.href = 'payment-error.php?order_id=<?php echo $_SESSION['order_id_midtrans']; ?>';
                }
            });
        };
    </script>
</body>
</html>