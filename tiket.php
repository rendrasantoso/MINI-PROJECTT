<?php
// HARUS DITARUH DI BARIS PALING ATAS
session_start();
require_once __DIR__ . '/config/config.php';  // Pastikan path ini benar

// Debug: cek apakah koneksi berhasil
if (!isset($pdo)) {
    error_log("ERROR: Koneksi database gagal di tiket.php");
    die("Koneksi database gagal. Silakan cek konfigurasi.");
}

// Ambil match_id dari URL
$match_id = $_GET['match_id'] ?? 0;

// Debug: cek match_id
error_log("Match ID dari URL: $match_id");

// Ambil data jadwal match
$stmt = $pdo->prepare("SELECT * FROM jadwal_match WHERE id = ?");
$stmt->execute([$match_id]);
$match = $stmt->fetch();

if (!$match) {
    error_log("Jadwal match tidak ditemukan untuk ID: $match_id");
    header('Location: index.php');
    exit;
}

error_log("Jadwal ditemukan: " . $match['pertandingan']);

// PERBAIKAN QUERY: Ambil tiket yang tersedia untuk match ini
$stmt = $pdo->prepare("
    SELECT t.*, 
           (t.stok - t.terjual) as stok_tersisa,
           jm.pertandingan, jm.tanggal, jm.waktu, jm.lokasi
    FROM tiket t 
    LEFT JOIN jadwal_match jm ON t.match_id = jm.id 
    WHERE t.match_id = ? 
    AND (t.stok - t.terjual) > 0
    ORDER BY t.harga ASC
");
$stmt->execute([$match_id]);
$tiket_list = $stmt->fetchAll();

error_log("Jumlah tiket ditemukan: " . count($tiket_list));

// Cek apakah ada error di session
if (isset($_SESSION['error'])) {
    error_log("Ada error di session: " . $_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Tiket - <?php echo htmlspecialchars($match['pertandingan']); ?></title>
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
            max-width: 1000px;
            margin: 0 auto;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.8rem 1.5rem;
            border-radius: 10px;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .back-btn:hover {
            background: rgba(102, 126, 234, 0.3);
            transform: translateX(-5px);
        }

        .match-header {
            background: linear-gradient(135deg, #1e1e2e 0%, #2a2a3e 100%);
            padding: 2.5rem;
            border-radius: 20px;
            margin-bottom: 3rem;
            border: 2px solid rgba(255, 107, 107, 0.3);
        }

        .match-header h1 {
            font-size: 2.5rem;
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
        }

        .match-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .detail-item i {
            font-size: 1.5rem;
            color: #ff6b6b;
        }

        .detail-item .text {
            display: flex;
            flex-direction: column;
        }

        .detail-item .label {
            font-size: 0.85rem;
            color: #aaa;
        }

        .detail-item .value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
        }

        .section-title {
            font-size: 2rem;
            margin-bottom: 2rem;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .ticket-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .ticket-card {
            background: linear-gradient(135deg, #1e1e2e 0%, #2a2a3e 100%);
            padding: 2rem;
            border-radius: 20px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .ticket-card::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.2), transparent);
            border-radius: 50%;
        }

        .ticket-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
        }

        .ticket-type {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #fff;
        }

        .ticket-price {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
        }

        .ticket-stock {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem;
            background: rgba(37, 211, 102, 0.1);
            border: 1px solid #25d366;
            border-radius: 10px;
            color: #25d366;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .btn-order {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-order:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
        }

        /* Form Pemesanan */
        .form-container {
            background: linear-gradient(135deg, #1e1e2e 0%, #2a2a3e 100%);
            padding: 3rem;
            border-radius: 20px;
            border: 2px solid rgba(102, 126, 234, 0.3);
            display: none;
            margin-top: 2rem;
        }

        .form-container.active {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #ddd;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.08);
        }

        .total-display {
            background: rgba(102, 126, 234, 0.1);
            padding: 1.5rem;
            border-radius: 10px;
            border: 2px solid #667eea;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .total-display .label {
            color: #aaa;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .total-display .amount {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .match-header h1 {
                font-size: 1.8rem;
            }

            .ticket-grid {
                grid-template-columns: 1fr;
            }

            .form-container {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php#jadwal-match" class="back-btn">
            <i class="fas fa-arrow-left"></i> Kembali ke Beranda
        </a>

        <!-- Tampilkan error jika ada -->
        <?php if (isset($_SESSION['error'])): ?>
        <div style="background: rgba(255, 107, 107, 0.1); border: 2px solid #ff6b6b; color: #ff6b6b; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem;">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; ?>
            <?php unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>

        <!-- Tampilkan success jika ada -->
        <?php if (isset($_SESSION['success'])): ?>
        <div style="background: rgba(37, 211, 102, 0.1); border: 2px solid #25d366; color: #25d366; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem;">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; ?>
            <?php unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>

        <div class="match-header">
            <h1><i class="fas fa-futbol"></i> <?php echo htmlspecialchars($match['pertandingan']); ?></h1>
            <div class="match-details">
                <div class="detail-item">
                    <i class="fas fa-calendar-alt"></i>
                    <div class="text">
                        <span class="label">Tanggal</span>
                        <span class="value"><?php echo date('d F Y', strtotime($match['tanggal'])); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <i class="fas fa-clock"></i>
                    <div class="text">
                        <span class="label">Waktu</span>
                        <span class="value"><?php echo date('H:i', strtotime($match['waktu'])); ?> WIB</span>
                    </div>
                </div>
                <div class="detail-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div class="text">
                        <span class="label">Lokasi</span>
                        <span class="value"><?php echo htmlspecialchars($match['lokasi']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <h2 class="section-title"><i class="fas fa-ticket-alt"></i> Pilih Tiket</h2>

        <?php if (count($tiket_list) > 0): ?>
        <div class="ticket-grid">
            <?php foreach ($tiket_list as $tiket): ?>
            <div class="ticket-card">
                <h3 class="ticket-type"><?php echo htmlspecialchars($tiket['jenis_tiket']); ?></h3>
                <div class="ticket-price">Rp<?php echo number_format($tiket['harga'], 0, ',', '.'); ?></div>
                <div class="ticket-stock">
                    <i class="fas fa-users"></i>
                    <span>Tersisa <?php echo $tiket['stok_tersisa']; ?> tiket</span>
                </div>
                <button class="btn-order" onclick="openOrderForm(<?php echo $tiket['id']; ?>, '<?php echo htmlspecialchars($tiket['jenis_tiket']); ?>', <?php echo $tiket['harga']; ?>, <?php echo $tiket['stok_tersisa']; ?>)">
                    <i class="fas fa-shopping-cart"></i> Pesan Tiket Ini
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Form Pemesanan -->
        <div class="form-container" id="orderForm">
            <h2 class="section-title"><i class="fas fa-file-invoice"></i> Form Pemesanan Tiket</h2>
            <form action="proses_tiket.php" method="POST">
                <input type="hidden" name="tiket_id" id="tiket_id">
                <input type="hidden" name="match_id" value="<?php echo $match_id; ?>">
                <input type="hidden" name="harga" id="harga">

                <div class="form-group">
                    <label><i class="fas fa-ticket-alt"></i> Jenis Tiket</label>
                    <input type="text" id="jenis_tiket_display" readonly style="background: rgba(255, 255, 255, 0.1); cursor: not-allowed;">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nama Lengkap *</label>
                    <input type="text" name="nama" placeholder="Masukkan nama lengkap" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-phone"></i> No HP / WhatsApp *</label>
                    <input type="tel" name="no_hp" placeholder="081234567890" pattern="[0-9]{10,13}" required>
                    <small style="color: #aaa; font-size: 0.8rem; display: block; margin-top: 0.3rem;">Contoh: 081234567890 (10-13 digit)</small>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email (Opsional)</label>
                    <input type="email" name="email" placeholder="email@example.com">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> Jumlah Tiket *</label>
                    <input type="number" name="jumlah_tiket" id="jumlah_tiket" min="1" value="1" oninput="calculateTotal(); validateJumlah()" required>
                    <small style="color: #aaa; margin-top: 0.5rem; display: block;">
                        Maksimal <span id="max_tiket">10</span> tiket per pemesanan
                        <span id="stok_info" style="color: #ff6b6b; margin-left: 10px;"></span>
                    </small>
                </div>

                <div class="total-display">
                    <div class="label">Total Pembayaran</div>
                    <div class="amount" id="total_display">Rp0</div>
                </div>

                <!-- Error display -->
                <div id="form_error" style="display: none; background: rgba(255, 107, 107, 0.1); border: 1px solid #ff6b6b; color: #ff6b6b; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                    <i class="fas fa-exclamation-circle"></i> <span id="error_text"></span>
                </div>

                <button type="submit" class="btn-order" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Konfirmasi Pesanan
                </button>
            </form>
        </div>

        <?php else: ?>
        <div style="text-align: center; padding: 4rem 2rem; background: rgba(255, 255, 255, 0.05); border-radius: 20px;">
            <i class="fas fa-times-circle" style="font-size: 5rem; color: #ff6b6b; margin-bottom: 1.5rem;"></i>
            <h3 style="font-size: 1.8rem; margin-bottom: 1rem;">Tiket Belum Tersedia</h3>
            <p style="color: #aaa; font-size: 1.1rem;">Maaf, tiket untuk pertandingan ini belum tersedia atau sudah habis. Silakan cek kembali nanti.</p>
            
            <!-- Tampilkan info jika stok 0 -->
            <?php 
            $stmt = $pdo->prepare("SELECT t.*, (t.stok - t.terjual) as stok_tersisa FROM tiket t WHERE t.match_id = ?");
            $stmt->execute([$match_id]);
            $all_tiket = $stmt->fetchAll();
            
            if (count($all_tiket) > 0): 
            ?>
            <div style="margin-top: 2rem; background: rgba(255, 107, 107, 0.05); padding: 1.5rem; border-radius: 10px;">
                <h4 style="color: #ff6b6b; margin-bottom: 1rem;">Status Tiket:</h4>
                <?php foreach ($all_tiket as $t): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                    <span><?php echo htmlspecialchars($t['jenis_tiket']); ?></span>
                    <span style="color: <?php echo ($t['stok_tersisa'] > 0) ? '#25d366' : '#ff6b6b'; ?>; font-weight: 600;">
                        <?php echo ($t['stok_tersisa'] > 0) ? $t['stok_tersisa'] . ' tersedia' : 'Habis'; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <a href="index.php#jadwal-match" class="btn-order" style="max-width: 300px; margin: 2rem auto 0; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script>
        let currentTicketData = {
            id: null,
            harga: 0,
            stokTersisa: 0,
            jenisTiket: ''
        };

        function openOrderForm(tiketId, jenistiket, harga, stokTersisa) {
            // Simpan data tiket yang dipilih
            currentTicketData = {
                id: tiketId,
                harga: harga,
                stokTersisa: stokTersisa,
                jenisTiket: jenistiket
            };
            
            // Update form
            document.getElementById('orderForm').classList.add('active');
            document.getElementById('tiket_id').value = tiketId;
            document.getElementById('jenis_tiket_display').value = jenistiket;
            document.getElementById('harga').value = harga;
            
            // Set max dan reset jumlah
            const jumlahInput = document.getElementById('jumlah_tiket');
            const maxTickets = Math.min(stokTersisa, 10);
            jumlahInput.max = maxTickets;
            jumlahInput.value = 1;
            document.getElementById('max_tiket').textContent = maxTickets;
            
            // Update info stok
            document.getElementById('stok_info').textContent = `(Stok tersedia: ${stokTersisa})`;
            document.getElementById('stok_info').style.color = stokTersisa > 0 ? '#25d366' : '#ff6b6b';
            
            // Hitung total awal
            calculateTotal();
            
            // Reset error
            hideError();
            
            // Scroll to form
            document.getElementById('orderForm').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }

        function calculateTotal() {
            const harga = parseInt(document.getElementById('harga').value) || 0;
            const jumlah = parseInt(document.getElementById('jumlah_tiket').value) || 0;
            const total = harga * jumlah;
            
            document.getElementById('total_display').textContent = 'Rp' + total.toLocaleString('id-ID');
        }

        function validateJumlah() {
            const jumlah = parseInt(document.getElementById('jumlah_tiket').value) || 0;
            const maxTickets = parseInt(document.getElementById('jumlah_tiket').max) || 0;
            
            if (jumlah > maxTickets) {
                showError(`Maksimal ${maxTickets} tiket tersedia!`);
                document.getElementById('jumlah_tiket').value = maxTickets;
                calculateTotal();
            } else {
                hideError();
            }
        }

        function showError(message) {
            document.getElementById('form_error').style.display = 'block';
            document.getElementById('error_text').textContent = message;
        }

        function hideError() {
            document.getElementById('form_error').style.display = 'none';
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            // Validasi nomor HP
            const noHp = document.querySelector('input[name="no_hp"]').value;
            const phoneRegex = /^[0-9]{10,13}$/;
            
            if (!phoneRegex.test(noHp)) {
                e.preventDefault();
                showError('Nomor HP harus 10-13 digit angka!');
                return false;
            }
            
            // Validasi jumlah tiket
            const jumlah = parseInt(document.getElementById('jumlah_tiket').value) || 0;
            if (jumlah < 1) {
                e.preventDefault();
                showError('Jumlah tiket minimal 1!');
                return false;
            }
            
            if (currentTicketData.stokTersisa < 1) {
                e.preventDefault();
                showError('Stok tiket habis!');
                return false;
            }
            
            if (jumlah > currentTicketData.stokTersisa) {
                e.preventDefault();
                showError(`Stok hanya tersedia ${currentTicketData.stokTersisa} tiket!`);
                return false;
            }
            
            // Show loading
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            submitBtn.disabled = true;
            
            return true;
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Cek jika ada error dari session
            <?php if (isset($_SESSION['error'])): ?>
                showError('<?php echo addslashes($_SESSION['error']); ?>');
            <?php endif; ?>
        });
    </script>
</body>
</html>