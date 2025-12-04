<?php
// tiket_success.php - FINAL FIXED VERSION
session_start();
require_once 'config/config.php';

// Cek apakah ada parameter ID di URL
$pemesanan_id = $_GET['id'] ?? null;

if ($pemesanan_id) {
    // Ambil data dari database berdasarkan ID
    // PERBAIKAN: Hapus kolom yang tidak ada (team_home, team_away, image)
    $stmt = $pdo->prepare("
        SELECT pt.*, t.jenis_tiket, t.harga, 
               jm.pertandingan, jm.tanggal, jm.waktu, jm.lokasi, jm.status
        FROM pemesanan_tiket pt
        LEFT JOIN tiket t ON pt.tiket_id = t.id
        LEFT JOIN jadwal_match jm ON pt.match_id = jm.id
        WHERE pt.id = ?
    ");
    $stmt->execute([$pemesanan_id]);
    $pemesanan = $stmt->fetch();
    
    if (!$pemesanan) {
        $_SESSION['error'] = "Pemesanan tidak ditemukan!";
        header('Location: tiket.php');
        exit;
    }
    
    // Generate kode booking jika tidak ada
    if (empty($pemesanan['kode_booking'])) {
        $pemesanan['kode_booking'] = 'TIKET-' . str_pad($pemesanan['id'], 6, '0', STR_PAD_LEFT);
    }
} 
// Cek apakah ada data di session (untuk kasus redirect langsung)
elseif (isset($_SESSION['last_tiket_pemesanan_id'])) {
    $pemesanan_id = $_SESSION['last_tiket_pemesanan_id'];
    
    $stmt = $pdo->prepare("
        SELECT pt.*, t.jenis_tiket, t.harga, 
               jm.pertandingan, jm.tanggal, jm.waktu, jm.lokasi, jm.status
        FROM pemesanan_tiket pt
        LEFT JOIN tiket t ON pt.tiket_id = t.id
        LEFT JOIN jadwal_match jm ON pt.match_id = jm.id
        WHERE pt.id = ?
    ");
    $stmt->execute([$pemesanan_id]);
    $pemesanan = $stmt->fetch();
    
    if (!$pemesanan) {
        $_SESSION['error'] = "Pemesanan tidak ditemukan!";
        header('Location: tiket.php');
        exit;
    }
    
    // Generate kode booking jika tidak ada
    if (empty($pemesanan['kode_booking'])) {
        $pemesanan['kode_booking'] = 'TIKET-' . str_pad($pemesanan['id'], 6, '0', STR_PAD_LEFT);
    }
    
    // Clear session
    unset($_SESSION['last_tiket_pemesanan_id']);
}
// Jika tidak ada data sama sekali
else {
    echo "<script>
        alert('Tidak ada data pemesanan! Silakan pesan tiket terlebih dahulu.');
        window.location.href = 'tiket.php';
    </script>";
    exit;
}

// Format tanggal dan waktu
$tanggal = date('d F Y', strtotime($pemesanan['tanggal']));
$waktu = date('H:i', strtotime($pemesanan['waktu']));
$tanggal_pesan = date('d F Y H:i', strtotime($pemesanan['tanggal_pesan']));

// Ekstrak nama tim dari field pertandingan
$pertandingan = $pemesanan['pertandingan'];
// Contoh format pertandingan: "smekda vs stemda"
$teams = explode(' vs ', $pertandingan);
$team_home = isset($teams[0]) ? trim($teams[0]) : 'Tim Home';
$team_away = isset($teams[1]) ? trim($teams[1]) : 'Tim Away';

// Jika tidak ada "vs", gunakan format lain
if (count($teams) < 2) {
    $team_home = $pertandingan;
    $team_away = 'Lawan';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemesanan Berhasil - Smekda Jersey</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* CSS tetap sama seperti sebelumnya, tidak perlu perubahan */
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            overflow-x: hidden;
        }

        /* Confetti Background */
        .confetti {
            position: fixed;
            width: 15px;
            height: 15px;
            background: var(--color);
            top: -20px;
            opacity: 0;
            animation: confettiFall 3s linear infinite;
            z-index: 1;
        }

        @keyframes confettiFall {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }

        /* Main Container */
        .success-container {
            max-width: 900px;
            width: 100%;
            background: linear-gradient(135deg, #1e1e2e 0%, #2a2a3e 100%);
            border-radius: 30px;
            padding: 4rem;
            border: 3px solid rgba(37, 211, 102, 0.4);
            box-shadow: 
                0 25px 60px rgba(37, 211, 102, 0.3),
                0 0 100px rgba(37, 211, 102, 0.1);
            position: relative;
            z-index: 2;
            animation: containerPop 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            overflow: hidden;
        }

        @keyframes containerPop {
            0% {
                transform: scale(0.8);
                opacity: 0;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .success-container::before {
            content: "";
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, #25d366, #48dbfb, #25d366, #48dbfb);
            border-radius: 32px;
            z-index: -1;
            animation: borderGlow 3s linear infinite;
            background-size: 400% 400%;
        }

        @keyframes borderGlow {
            0%, 100% {
                background-position: 0% 50%;
                opacity: 0.5;
            }
            50% {
                background-position: 100% 50%;
                opacity: 1;
            }
        }

        /* Success Icon */
        .success-icon {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #25d366, #128c7e);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 3rem;
            font-size: 5rem;
            animation: 
                iconPulse 2s ease infinite,
                iconFloat 3s ease-in-out infinite;
            box-shadow: 
                0 20px 50px rgba(37, 211, 102, 0.5),
                inset 0 10px 30px rgba(255, 255, 255, 0.2);
            position: relative;
        }

        @keyframes iconPulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        @keyframes iconFloat {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        .success-icon::after {
            content: "";
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            border: 5px solid rgba(37, 211, 102, 0.3);
            border-radius: 50%;
            animation: iconRing 2s ease-out infinite;
        }

        @keyframes iconRing {
            0% {
                transform: scale(0.8);
                opacity: 1;
            }
            100% {
                transform: scale(1.5);
                opacity: 0;
            }
        }

        /* Title */
        h1 {
            font-size: 3.5rem;
            text-align: center;
            background: linear-gradient(45deg, #25d366, #48dbfb, #25d366);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
            animation: titleGradient 3s ease infinite;
            background-size: 200% 200%;
        }

        @keyframes titleGradient {
            0%, 100% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
        }

        /* Subtitle */
        .subtitle {
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.3rem;
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Booking Code */
        .booking-code {
            font-size: 4rem;
            font-weight: 900;
            text-align: center;
            letter-spacing: 3px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 2rem 0;
            text-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
            font-family: 'Courier New', monospace;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            border: 2px solid rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .booking-code::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.2), transparent);
            animation: codeShine 2s linear infinite;
        }

        @keyframes codeShine {
            100% {
                left: 100%;
            }
        }

        .booking-label {
            font-size: 1.2rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        /* Message */
        .success-message {
            text-align: center;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.3rem;
            line-height: 1.6;
            margin-bottom: 3rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 15px;
            border-left: 5px solid #25d366;
        }

        /* Match Info */
        .match-info {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 20px;
            padding: 2.5rem;
            margin: 2rem 0;
            border: 2px solid rgba(102, 126, 234, 0.3);
            backdrop-filter: blur(10px);
        }

        .match-teams {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3rem;
            margin-bottom: 2rem;
        }

        .team {
            text-align: center;
            flex: 1;
        }

        .team-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
            margin-top: 1rem;
        }

        .vs {
            font-size: 2.5rem;
            font-weight: 900;
            color: #feca57;
            background: rgba(254, 202, 87, 0.1);
            padding: 0.5rem 2rem;
            border-radius: 50px;
        }

        .match-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            text-align: center;
        }

        .detail-item {
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border-left: 4px solid #25d366;
        }

        .detail-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .detail-value {
            font-size: 1.3rem;
            font-weight: 600;
            color: #fff;
        }

        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02));
            border-radius: 20px;
            padding: 2.5rem;
            margin: 2rem 0;
            border: 2px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .info-item:hover {
            background: rgba(255, 255, 255, 0.06);
            transform: translateY(-3px);
            border-color: rgba(102, 126, 234, 0.3);
        }

        .info-label {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
            margin-bottom: 0.8rem;
        }

        .info-label i {
            width: 20px;
            color: #667eea;
        }

        .info-value {
            color: #fff;
            font-size: 1.3rem;
            font-weight: 600;
            line-height: 1.4;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-left: 1rem;
        }

        .status-pending {
            background: rgba(254, 202, 87, 0.2);
            color: #feca57;
            border: 1px solid #feca57;
        }

        .status-confirmed {
            background: rgba(37, 211, 102, 0.2);
            color: #25d366;
            border: 1px solid #25d366;
        }

        /* Total Price */
        .total-price {
            background: linear-gradient(135deg, rgba(37, 211, 102, 0.15), rgba(72, 219, 251, 0.15));
            padding: 2.5rem;
            border-radius: 20px;
            text-align: center;
            border: 3px solid #25d366;
            margin: 3rem 0;
            position: relative;
            overflow: hidden;
        }

        .total-price::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #25d366, #48dbfb, #25d366);
            animation: slide 2s linear infinite;
        }

        @keyframes slide {
            0% { background-position: -200px 0; }
            100% { background-position: 200px 0; }
        }

        .total-label {
            font-size: 1.4rem;
            color: #25d366;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .total-amount {
            font-size: 4rem;
            font-weight: 900;
            background: linear-gradient(45deg, #25d366, #48dbfb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 5px 15px rgba(37, 211, 102, 0.3);
        }

        /* Instructions */
        .instructions {
            background: linear-gradient(135deg, rgba(254, 202, 87, 0.1), rgba(255, 159, 243, 0.1));
            border: 2px solid #feca57;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 3rem;
        }

        .instructions h3 {
            color: #feca57;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .steps {
            list-style: none;
            padding-left: 0;
        }

        .steps li {
            margin-bottom: 1.5rem;
            padding-left: 3rem;
            position: relative;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
        }

        .steps li::before {
            content: counter(step);
            counter-increment: step;
            position: absolute;
            left: 0;
            top: 0;
            width: 2rem;
            height: 2rem;
            background: #feca57;
            color: #000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .steps {
            counter-reset: step;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 2rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 3rem;
        }

        .btn {
            padding: 1.2rem 3rem;
            border: none;
            border-radius: 15px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s ease;
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            min-width: 250px;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #25d366, #128c7e);
            color: #fff;
            box-shadow: 0 10px 30px rgba(37, 211, 102, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 20px 50px rgba(37, 211, 102, 0.6);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 20px 50px rgba(102, 126, 234, 0.6);
        }

        .btn-print {
            background: linear-gradient(135deg, #feca57, #ff9ff3);
            color: #000;
            box-shadow: 0 10px 30px rgba(254, 202, 87, 0.4);
        }

        .btn-print:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 20px 50px rgba(254, 202, 87, 0.6);
        }

        /* QR Code */
        .qr-section {
            text-align: center;
            margin: 2rem 0;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
        }

        .qr-code {
            width: 200px;
            height: 200px;
            margin: 1rem auto;
            background: #fff;
            padding: 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            color: #000;
            font-size: 1.2rem;
            word-break: break-all;
            text-align: center;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .success-container {
                padding: 2rem;
                border-radius: 20px;
            }

            h1 {
                font-size: 2.2rem;
            }

            .booking-code {
                font-size: 2.2rem;
                letter-spacing: 2px;
            }

            .total-amount {
                font-size: 2.8rem;
            }

            .match-teams {
                flex-direction: column;
                gap: 1rem;
            }

            .vs {
                padding: 0.5rem 1rem;
                font-size: 1.8rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .btn {
                min-width: 100%;
                padding: 1rem 2rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 1.8rem;
            }

            .booking-code {
                font-size: 1.8rem;
            }

            .total-amount {
                font-size: 2.2rem;
            }

            .match-info, .info-box, .instructions {
                padding: 1.5rem;
            }

            .success-icon {
                width: 100px;
                height: 100px;
                font-size: 3.5rem;
                margin-bottom: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <!-- Success Icon -->
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>

        <!-- Title -->
        <h1>Pemesanan Berhasil!</h1>
        <p class="subtitle">Tiket pertandingan Anda telah berhasil dipesan. Simpan kode booking untuk pengambilan tiket.</p>

        <!-- Booking Code -->
        <div class="booking-label">
            <i class="fas fa-ticket-alt"></i> KODE BOOKING ANDA
        </div>
        <div class="booking-code"><?php echo htmlspecialchars($pemesanan['kode_booking']); ?></div>

        <!-- Status -->
        <div style="text-align: center; margin-bottom: 2rem;">
            <span class="status-badge status-<?php echo $pemesanan['status']; ?>">
                <i class="fas fa-circle"></i> 
                <?php 
                $statusText = [
                    'pending' => 'Menunggu Konfirmasi',
                    'confirmed' => 'Terkonfirmasi',
                    'paid' => 'Sudah Dibayar',
                    'cancelled' => 'Dibatalkan'
                ];
                echo $statusText[$pemesanan['status']] ?? $pemesanan['status'];
                ?>
            </span>
            <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.9rem; margin-top: 0.5rem;">
                <i class="far fa-clock"></i> Dipesan pada: <?php echo $tanggal_pesan; ?>
            </div>
        </div>

        <!-- Success Message -->
        <div class="success-message">
            <i class="fas fa-check-circle"></i> Konfirmasi pemesanan telah dikirim ke WhatsApp/Email Anda. 
            Silakan tunjukkan kode booking di atas saat pengambilan tiket di lokasi.
        </div>

        <!-- Match Info -->
        <div class="match-info">
            <div class="match-teams">
                <div class="team">
                    <div class="team-name"><?php echo htmlspecialchars($team_home); ?></div>
                </div>
                <div class="vs">VS</div>
                <div class="team">
                    <div class="team-name"><?php echo htmlspecialchars($team_away); ?></div>
                </div>
            </div>
            
            <div class="match-details">
                <div class="detail-item">
                    <div class="detail-label"><i class="fas fa-calendar"></i> Tanggal</div>
                    <div class="detail-value"><?php echo $tanggal; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><i class="fas fa-clock"></i> Waktu</div>
                    <div class="detail-value"><?php echo $waktu; ?> WIB</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><i class="fas fa-map-marker-alt"></i> Lokasi</div>
                    <div class="detail-value"><?php echo htmlspecialchars($pemesanan['lokasi']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><i class="fas fa-info-circle"></i> Status Pertandingan</div>
                    <div class="detail-value">
                        <?php 
                        $statusMatch = [
                            'upcoming' => 'Akan Datang',
                            'ongoing' => 'Sedang Berlangsung',
                            'finished' => 'Selesai',
                            'cancelled' => 'Dibatalkan'
                        ];
                        echo $statusMatch[$pemesanan['status']] ?? $pemesanan['status'];
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booking Details -->
        <div class="info-box">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-user"></i> Nama Pemesan</div>
                    <div class="info-value"><?php echo htmlspecialchars($pemesanan['nama']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-phone"></i> WhatsApp</div>
                    <div class="info-value"><?php echo htmlspecialchars($pemesanan['no_hp']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                    <div class="info-value">
                        <?php echo !empty($pemesanan['email']) ? htmlspecialchars($pemesanan['email']) : '<span style="color:#aaa">Tidak diisi</span>'; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-ticket-alt"></i> Jenis Tiket</div>
                    <div class="info-value"><?php echo htmlspecialchars($pemesanan['jenis_tiket']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-money-bill-wave"></i> Harga per Tiket</div>
                    <div class="info-value">Rp <?php echo number_format($pemesanan['harga'], 0, ',', '.'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-users"></i> Jumlah Tiket</div>
                    <div class="info-value"><?php echo $pemesanan['jumlah_tiket']; ?> Tiket</div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-hashtag"></i> ID Pemesanan</div>
                    <div class="info-value">#<?php echo str_pad($pemesanan['id'], 5, '0', STR_PAD_LEFT); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-barcode"></i> ID Tiket</div>
                    <div class="info-value">T<?php echo str_pad($pemesanan['tiket_id'], 3, '0', STR_PAD_LEFT); ?></div>
                </div>
            </div>
        </div>

        <!-- Total Price -->
        <div class="total-price">
            <div class="total-label">TOTAL PEMBAYARAN</div>
            <div class="total-amount">Rp <?php echo number_format($pemesanan['total_harga'], 0, ',', '.'); ?></div>
            <p style="margin-top: 1rem; color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">
                <i class="fas fa-info-circle"></i> Pembayaran dilakukan di lokasi saat pengambilan tiket
            </p>
        </div>

        <!-- QR Code Placeholder -->
        <div class="qr-section">
            <h3><i class="fas fa-qrcode"></i> QR Code Booking</h3>
            <div class="qr-code">
                <div>
                    <i class="fas fa-qrcode fa-3x" style="color: #333; margin-bottom: 1rem;"></i>
                    <div style="font-size: 0.9rem; color: #666;">
                        <?php echo substr($pemesanan['kode_booking'], 0, 8) . '...'; ?>
                    </div>
                </div>
            </div>
            <p style="color: rgba(255, 255, 255, 0.7); margin-top: 1rem;">
                Scan QR code ini untuk verifikasi cepat
            </p>
        </div>

        <!-- Instructions -->
        <div class="instructions">
            <h3><i class="fas fa-clipboard-list"></i> Langkah Selanjutnya</h3>
            <ol class="steps">
                <li>Tunjukkan kode booking atau QR code di atas saat pengambilan tiket</li>
                <li>Datang minimal 30 menit sebelum pertandingan dimulai</li>
                <li>Bawa kartu identitas asli untuk verifikasi</li>
                <li>Lakukan pembayaran di lokasi dengan nominal di atas</li>
                <li>Tiket yang sudah dibeli tidak dapat dikembalikan atau direfund</li>
                <li>Hubungi kami jika ada kendala: 0821-XXXX-XXXX</li>
            </ol>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="tiket.php" class="btn btn-secondary">
                <i class="fas fa-ticket-alt"></i> Pesan Tiket Lagi
            </a>
            <a href="javascript:window.print()" class="btn btn-print">
                <i class="fas fa-print"></i> Cetak Konfirmasi
            </a>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home"></i> Kembali ke Beranda
            </a>
        </div>
    </div>

    <script>
        // Create confetti effect
        function createConfetti() {
            const colors = ['#25d366', '#48dbfb', '#667eea', '#764ba2', '#feca57', '#ff6b6b'];
            
            for (let i = 0; i < 100; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.className = 'confetti';
                    confetti.style.setProperty('--color', colors[Math.floor(Math.random() * colors.length)]);
                    confetti.style.left = Math.random() * 100 + 'vw';
                    confetti.style.animationDuration = (2 + Math.random() * 3) + 's';
                    confetti.style.animationDelay = Math.random() * 2 + 's';
                    confetti.style.width = (10 + Math.random() * 20) + 'px';
                    confetti.style.height = confetti.style.width;
                    confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
                    
                    document.body.appendChild(confetti);
                    
                    // Remove after animation
                    setTimeout(() => {
                        confetti.remove();
                    }, 5000);
                }, i * 50);
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Create confetti
            createConfetti();
            
            // Auto-scroll to top
            window.scrollTo(0, 0);
            
            // Add copy to clipboard for booking code
            const bookingCode = document.querySelector('.booking-code');
            if (bookingCode) {
                bookingCode.addEventListener('click', function() {
                    const text = this.textContent.trim();
                    navigator.clipboard.writeText(text).then(() => {
                        const original = this.innerHTML;
                        this.innerHTML = '<i class="fas fa-check"></i> Kode Disalin!';
                        setTimeout(() => {
                            this.innerHTML = original;
                        }, 2000);
                    });
                });
                
                bookingCode.style.cursor = 'pointer';
                bookingCode.title = 'Klik untuk menyalin kode';
            }
        });

        // Print functionality
        window.addEventListener('beforeprint', function() {
            document.body.style.background = '#fff';
            document.querySelector('.success-container').style.boxShadow = 'none';
        });
        
        window.addEventListener('afterprint', function() {
            document.body.style.background = '';
            document.querySelector('.success-container').style.boxShadow = '';
        });
    </script>
</body>
</html>