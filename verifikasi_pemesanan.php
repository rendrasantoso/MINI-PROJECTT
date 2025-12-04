<?php
session_start();
require_once __DIR__ . '/config/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Ambil data pemesanan yang pending
$stmt = $pdo->query("
    SELECT p.*, m.harga 
    FROM pesanan p 
    LEFT JOIN merchandise m ON p.produk = m.nama_produk 
    WHERE p.status_verifikasi = 'pending' 
    ORDER BY p.tanggal DESC
");
$pesanan_pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data pemesanan yang sudah diverifikasi
$stmt = $pdo->query("
    SELECT p.*, m.harga, a.nama_lengkap as admin_name
    FROM pesanan p 
    LEFT JOIN merchandise m ON p.produk = m.nama_produk 
    LEFT JOIN admin a ON p.verified_by = a.id
    WHERE p.status_verifikasi IN ('approved', 'rejected') 
    ORDER BY p.verified_at DESC
    LIMIT 50
");
$pesanan_verified = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Pemesanan - Admin Smekda Jersey</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: #667eea;
            margin-bottom: 5px;
        }

        .header p {
            color: #666;
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 15px;
            padding: 10px 20px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .back-btn:hover {
            background: #f0f0f0;
        }

        .tabs {
            background: white;
            padding: 0;
            border-radius: 10px 10px 0 0;
            margin-bottom: 0;
            display: flex;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .tab-btn {
            flex: 1;
            padding: 15px;
            border: none;
            background: white;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
        }

        .tab-btn.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-btn:first-child {
            border-radius: 10px 0 0 0;
        }

        .tab-btn:last-child {
            border-radius: 0 10px 0 0;
        }

        .tab-content {
            display: none;
            background: white;
            padding: 20px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .tab-content.active {
            display: block;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
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

        .card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-left: 5px solid #667eea;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .card-id {
            font-weight: bold;
            color: #667eea;
            font-size: 18px;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .card-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-group {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .kartu-preview {
            grid-column: 1 / -1;
            text-align: center;
        }

        .kartu-preview img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            cursor: pointer;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            grid-column: 1 / -1;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .btn-view {
            background: #667eea;
            color: white;
        }

        .btn-view:hover {
            background: #5568d3;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-family: inherit;
            resize: vertical;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ccc;
        }

        .image-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            justify-content: center;
            align-items: center;
        }

        .image-modal.active {
            display: flex;
        }

        .image-modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 10px;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 40px;
            color: white;
            font-size: 40px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .card-body {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-direction: column;
            }

            .tab-btn {
                border-bottom: 1px solid #e0e0e0;
            }

            .tab-btn.active {
                border-bottom-color: #667eea;
                border-left: 3px solid #667eea;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>

        <div class="header">
            <h1><i class="fas fa-check-circle"></i> Verifikasi Pemesanan</h1>
            <p>Kelola dan verifikasi pemesanan dengan kartu pelajar</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('pending')">
                <i class="fas fa-clock"></i> Menunggu Verifikasi (<?php echo count($pesanan_pending); ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('verified')">
                <i class="fas fa-history"></i> Riwayat Verifikasi
            </button>
        </div>

        <!-- Tab Pending -->
        <div id="pending-tab" class="tab-content active">
            <?php if (count($pesanan_pending) > 0): ?>
                <?php foreach ($pesanan_pending as $pesanan): ?>
                    <div class="card">
                        <div class="card-header">
                            <span class="card-id">Pesanan #<?php echo $pesanan['id']; ?></span>
                            <span class="status-badge status-pending">
                                <i class="fas fa-clock"></i> Pending
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="info-group">
                                <span class="info-label"><i class="fas fa-user"></i> Nama</span>
                                <span class="info-value"><?php echo htmlspecialchars($pesanan['nama']); ?></span>
                            </div>
                            <div class="info-group">
                                <span class="info-label"><i class="fas fa-phone"></i> No HP</span>
                                <span class="info-value"><?php echo htmlspecialchars($pesanan['no_hp']); ?></span>
                            </div>
                            <div class="info-group">
                                <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                                <span class="info-value"><?php echo htmlspecialchars($pesanan['email'] ?? '-'); ?></span>
                            </div>
                            <div class="info-group">
                                <span class="info-label"><i class="fas fa-calendar"></i> Tanggal Pesan</span>
                                <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($pesanan['tanggal'])); ?></span>
                            </div>
                            <div class="info-group">
                                <span class="info-label"><i class="fas fa-box"></i> Produk</span>
                                <span class="info-value"><?php echo htmlspecialchars($pesanan['produk']); ?></span>
                            </div>
                            <div class="info-group">
                                <span class="info-label"><i class="fas fa-shopping-cart"></i> Jumlah</span>
                                <span class="info-value"><?php echo $pesanan['jumlah']; ?> pcs</span>
                            </div>
                            <div class="info-group">
                                <span class="info-label"><i class="fas fa-money-bill"></i> Total</span>
                                <span class="info-value">Rp <?php echo number_format($pesanan['total'], 0, ',', '.'); ?></span>
                            </div>
                            <div class="info-group">
                                <span class="info-label"><i class="fas fa-map-marker-alt"></i> Alamat</span>
                                <span class="info-value"><?php echo htmlspecialchars($pesanan['alamat']); ?></span>
                            </div>
                            <div class="kartu-preview">
                                <span class="info-label"><i class="fas fa-id-card"></i> Foto Kartu Pelajar</span>
                                <img src="uploads/kartu_pelajar/<?php echo htmlspecialchars($pesanan['foto_kartu_pelajar']); ?>" 
                                     alt="Kartu Pelajar"
                                     onclick="viewImage('uploads/kartu_pelajar/<?php echo htmlspecialchars($pesanan['foto_kartu_pelajar']); ?>')">
                            </div>
                            <div class="card-actions">
                                <button class="btn btn-approve" onclick="openModal(<?php echo $pesanan['id']; ?>, 'approve')">
                                    <i class="fas fa-check"></i> Setujui
                                </button>
                                <button class="btn btn-reject" onclick="openModal(<?php echo $pesanan['id']; ?>, 'reject')">
                                    <i class="fas fa-times"></i> Tolak
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Tidak ada pemesanan yang menunggu verifikasi</h3>
                    <p>Semua pemesanan sudah diverifikasi</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab Verified -->
        <div id="verified-tab" class="tab-content">
            <?php if (count($pesanan_verified) > 0): ?>
                <?php foreach ($pesanan_verified as $pesanan): ?>
                    <div class="card">
                        <div class="card-header">
                            <span class="card-id">Pesanan #<?php echo $pesanan['id']; ?></span>
                            <span class="status-badge <?php echo $pesanan['status_verifikasi'] == 'approved' ? 'status-approved' : 'status-rejected'; ?>">
                                <i class="fas fa-<?php echo $pesanan['status_verifikasi'] == 'approved' ? 'check' : 'times'; ?>"></i> 
                                <?php echo $pesanan['status_verifikasi'] == 'approved' ? 'Disetujui' : 'Ditolak'; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="info-group">
                                <span class="info-label"><i class="fas fa-user"></i> Nama</span>
                                <span class="info-value"><?php echo htmlspecialchars($pesanan['nama']); ?></span>
                            </div>
                            <div class="info-group">
                                <span class="info-label"><i class="fas fa-box"></i> Produk</span>
                                <span class="info-value"><?php echo htmlspecialchars($pesanan['produk']); ?> (<?php echo $pesanan['jumlah']; ?> pcs)</span>
                            </div>
                            <div class="info-group">
                                <span class="info-label"><i class="fas fa-money-bill"></i> Total</span>
                                <span class="info-value">Rp <?php echo number_format($pesanan['total'], 0, ',', '.'); ?></span>
                            </div>
                            <div class="info-group">
                                <span class="info-label"><i class="fas fa-calendar"></i> Diverifikasi</span>
                                <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($pesanan['verified_at'])); ?></span>
                            </div>
                            <div class="info-group">
                                <span class="info-label"><i class="fas fa-user-shield"></i> Oleh Admin</span>
                                <span class="info-value"><?php echo htmlspecialchars($pesanan['admin_name'] ?? '-'); ?></span>
                            </div>
                            <?php if ($pesanan['keterangan_admin']): ?>
                            <div class="info-group" style="grid-column: 1 / -1;">
                                <span class="info-label"><i class="fas fa-comment"></i> Keterangan</span>
                                <span class="info-value"><?php echo htmlspecialchars($pesanan['keterangan_admin']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="card-actions">
                                <button class="btn btn-view" onclick="viewImage('uploads/kartu_pelajar/<?php echo htmlspecialchars($pesanan['foto_kartu_pelajar']); ?>')">
                                    <i class="fas fa-eye"></i> Lihat Kartu Pelajar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Belum ada riwayat verifikasi</h3>
                    <p>Riwayat verifikasi akan muncul di sini</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Verifikasi -->
    <div id="verificationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"></h2>
            </div>
            <form id="verificationForm" method="POST" action="proses_verifikasi.php">
                <input type="hidden" name="id" id="pesananId">
                <input type="hidden" name="action" id="actionType">
                
                <div class="form-group">
                    <label for="keterangan">Keterangan (opsional):</label>
                    <textarea name="keterangan" id="keterangan" rows="4" placeholder="Masukkan keterangan jika diperlukan..."></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn" id="submitBtn"></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Image Viewer -->
    <div id="imageModal" class="image-modal">
        <span class="close-modal" onclick="closeImageModal()">&times;</span>
        <img id="modalImage" src="" alt="Kartu Pelajar">
    </div>

    <script>
        function switchTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tab + '-tab').classList.add('active');
            event.target.classList.add('active');
        }

        function openModal(id, action) {
            const modal = document.getElementById('verificationModal');
            const title = document.getElementById('modalTitle');
            const submitBtn = document.getElementById('submitBtn');
            
            document.getElementById('pesananId').value = id;
            document.getElementById('actionType').value = action;
            
            if (action === 'approve') {
                title.innerHTML = '<i class="fas fa-check-circle"></i> Setujui Pesanan #' + id;
                submitBtn.className = 'btn btn-approve';
                submitBtn.innerHTML = '<i class="fas fa-check"></i> Setujui Pesanan';
            } else {
                title.innerHTML = '<i class="fas fa-times-circle"></i> Tolak Pesanan #' + id;
                submitBtn.className = 'btn btn-reject';
                submitBtn.innerHTML = '<i class="fas fa-times"></i> Tolak Pesanan';
            }
            
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('verificationModal').classList.remove('active');
            document.getElementById('keterangan').value = '';
        }

        function viewImage(imagePath) {
            const modal = document.getElementById('imageModal');
            const img = document.getElementById('modalImage');
            img.src = imagePath;
            modal.classList.add('active');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('active');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const verificationModal = document.getElementById('verificationModal');
            const imageModal = document.getElementById('imageModal');
            
            if (event.target == verificationModal) {
                closeModal();
            }
            if (event.target == imageModal) {
                closeImageModal();
            }
        }

        // Close image modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
                closeImageModal();
            }
        });
    </script>
</body>
</html>