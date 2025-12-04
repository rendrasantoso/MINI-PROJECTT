<?php
/**
 * STATUS_PEMESANAN.PHP
 * Halaman tracking status pesanan untuk customer
 * Menggunakan PDO
 */

session_start();
require_once __DIR__ . '/config/config.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$pesanan_id = $_GET['id'];

try {
    $query = "SELECT p.*, a.nama_lengkap as admin_name 
              FROM pesanan p 
              LEFT JOIN admin a ON p.payment_verified_by = a.id 
              WHERE p.id = :id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $pesanan_id]);
    $pesanan = $stmt->fetch();
    
    if (!$pesanan) {
        header('Location: index.php');
        exit();
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Tentukan status dan progress
$status_info = [
    'pending' => [
        'title' => 'Menunggu Pembayaran',
        'icon' => 'fa-clock',
        'color' => '#ffa726',
        'progress' => 25,
        'message' => 'Silakan selesaikan pembayaran Anda'
    ],
    'proses' => [
        'title' => 'Sedang Diproses',
        'icon' => 'fa-box',
        'color' => '#42a5f5',
        'progress' => 75,
        'message' => 'Pesanan Anda sedang diproses'
    ],
    'selesai' => [
        'title' => 'Selesai',
        'icon' => 'fa-check-circle',
        'color' => '#66bb6a',
        'progress' => 100,
        'message' => 'Pesanan telah selesai'
    ],
    'dibatalkan' => [
        'title' => 'Dibatalkan',
        'icon' => 'fa-times-circle',
        'color' => '#ef5350',
        'progress' => 0,
        'message' => 'Pesanan dibatalkan'
    ]
];

$current_status = $status_info[$pesanan['status']];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pesanan - SMEKDA Jersey</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 800px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .order-number {
            font-size: 18px;
            opacity: 0.9;
        }

        .content {
            padding: 40px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 30px;
        }

        .progress-container {
            margin: 40px 0;
        }

        .progress-bar-bg {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            border-radius: 10px;
            transition: width 1s ease;
        }

        .timeline {
            margin: 40px 0;
        }

        .timeline-item {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            position: relative;
        }

        .timeline-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 19px;
            top: 40px;
            width: 2px;
            height: calc(100% + 10px);
            background: #e0e0e0;
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            z-index: 1;
        }

        .timeline-icon.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .timeline-icon.inactive {
            background: #f0f0f0;
            color: #999;
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .timeline-date {
            color: #999;
            font-size: 13px;
        }

        .order-details {
            background: #f8f9ff;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
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
        }

        .detail-value {
            font-weight: 600;
            color: #333;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .btn-container {
            margin-top: 30px;
            display: flex;
            gap: 15px;
        }

        .btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #f8f9ff;
        }

        @media (max-width: 768px) {
            .content {
                padding: 20px;
            }

            .btn-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-receipt"></i> Status Pesanan</h1>
            <div class="order-number">
                Order #<?php echo str_pad($pesanan['id'], 6, '0', STR_PAD_LEFT); ?>
            </div>
        </div>

        <div class="content">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($pesanan['status'] == 'dibatalkan' && $pesanan['keterangan_admin']): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-info-circle"></i>
                    <span><strong>Alasan Penolakan:</strong> <?php echo htmlspecialchars($pesanan['keterangan_admin']); ?></span>
                </div>
            <?php endif; ?>

            <!-- Status Badge -->
            <div style="text-align: center;">
                <div class="status-badge" style="background: <?php echo $current_status['color']; ?>; color: white;">
                    <i class="fas <?php echo $current_status['icon']; ?>"></i>
                    <?php echo $current_status['title']; ?>
                </div>
                <p style="color: #666; margin-top: 10px;">
                    <?php echo $current_status['message']; ?>
                </p>
            </div>

            <!-- Progress Bar -->
            <div class="progress-container">
                <div class="progress-bar-bg">
                    <div class="progress-bar" 
                         style="width: <?php echo $current_status['progress']; ?>%; 
                                background: <?php echo $current_status['color']; ?>;">
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-icon active">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">Pesanan Dibuat</div>
                        <div class="timeline-date">
                            <?php echo date('d M Y, H:i', strtotime($pesanan['tanggal'])); ?>
                        </div>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-icon <?php echo $pesanan['bukti_transfer'] ? 'active' : 'inactive'; ?>">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">Pembayaran</div>
                        <div class="timeline-date">
                            <?php 
                            if ($pesanan['bukti_transfer']) {
                                echo 'Bukti transfer sudah diupload';
                            } else {
                                echo 'Menunggu pembayaran';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-icon <?php echo $pesanan['status_pembayaran'] == 'lunas' ? 'active' : 'inactive'; ?>">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">Verifikasi</div>
                        <div class="timeline-date">
                            <?php 
                            if ($pesanan['payment_verified_at']) {
                                echo date('d M Y, H:i', strtotime($pesanan['payment_verified_at']));
                                if ($pesanan['admin_name']) {
                                    echo ' oleh ' . htmlspecialchars($pesanan['admin_name']);
                                }
                            } else {
                                echo 'Menunggu verifikasi admin';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-icon <?php echo $pesanan['status'] == 'selesai' ? 'active' : 'inactive'; ?>">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">Pesanan Selesai</div>
                        <div class="timeline-date">
                            <?php echo $pesanan['status'] == 'selesai' ? 'Pesanan selesai' : 'Menunggu...'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Details -->
            <div class="order-details">
                <h3 style="margin-bottom: 15px; color: #333;">
                    <i class="fas fa-info-circle"></i> Detail Pesanan
                </h3>
                <div class="detail-row">
                    <span class="detail-label">Nama</span>
                    <span class="detail-value"><?php echo htmlspecialchars($pesanan['nama']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">No. HP</span>
                    <span class="detail-value"><?php echo htmlspecialchars($pesanan['no_hp']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Produk</span>
                    <span class="detail-value"><?php echo htmlspecialchars($pesanan['produk']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Jumlah</span>
                    <span class="detail-value"><?php echo $pesanan['jumlah']; ?> pcs</span>
                </div>
                <?php if ($pesanan['bank_tujuan']): ?>
                <div class="detail-row">
                    <span class="detail-label">Bank Tujuan</span>
                    <span class="detail-value"><?php echo htmlspecialchars($pesanan['bank_tujuan']); ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row" style="margin-top: 10px; padding-top: 15px; border-top: 2px solid #667eea;">
                    <span class="detail-label" style="font-size: 18px;">Total</span>
                    <span class="detail-value" style="color: #667eea; font-size: 20px;">
                        Rp <?php echo number_format($pesanan['total'], 0, ',', '.'); ?>
                    </span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="btn-container">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Kembali ke Beranda
                </a>
                <?php if ($pesanan['status'] == 'pending' && !$pesanan['bukti_transfer']): ?>
                    <a href="payment.php?id=<?php echo $pesanan['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-credit-card"></i> Bayar Sekarang
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>