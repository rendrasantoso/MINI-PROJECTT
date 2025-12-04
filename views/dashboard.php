<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/config.php';

// ==================== STATISTIK ====================
// Total pesanan dari pemesanan_merchandise
$total_pesanan = $pdo->query("SELECT COUNT(*) FROM pemesanan_merchandise")->fetchColumn();

// Total merchandise terjual (dari kolom terjual di tabel merchandise)
$total_merchandise = $pdo->query("SELECT SUM(terjual) FROM merchandise")->fetchColumn() ?: 0;

// Total pendapatan dari pemesanan_merchandise yang sudah dibayar/selesai
$total_pendapatan = $pdo->query("SELECT SUM(total_harga) FROM pemesanan_merchandise WHERE status IN ('paid', 'completed')")->fetchColumn() ?: 0;

// Pesanan terbaru dari pemesanan_merchandise
$pesanan_terbaru = $pdo->query("
    SELECT 
        id, kode_pesanan, nama, no_hp, produk, jumlah, 
        total_harga, status, 
        DATE(tanggal_pemesanan) as tanggal
    FROM pemesanan_merchandise 
    ORDER BY tanggal_pemesanan DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Pending verifikasi (status pending)
$pending_verifikasi = $pdo->query("SELECT COUNT(*) FROM pemesanan_merchandise WHERE status = 'pending'")->fetchColumn();

// ==================== SEMUA DATA ====================
// Jadwal match
$all_jadwal = $pdo->query("SELECT * FROM jadwal_match ORDER BY tanggal DESC")->fetchAll(PDO::FETCH_ASSOC);

// Tiket dengan info pertandingan
$all_tiket = $pdo->query("SELECT t.*, jm.pertandingan FROM tiket t LEFT JOIN jadwal_match jm ON t.match_id = jm.id ORDER BY t.id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Merchandise
$all_merchandise = $pdo->query("SELECT * FROM merchandise ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Semua pesanan dari pemesanan_merchandise
$all_pesanan = $pdo->query("
    SELECT *,
           CASE 
               WHEN foto_kartu_pelajar IS NOT NULL AND foto_kartu_pelajar != '' THEN 'uploaded'
               ELSE 'not_uploaded' 
           END as status_kartu
    FROM pemesanan_merchandise 
    ORDER BY tanggal_pemesanan DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Pemesanan tiket
$pemesanan_tiket = $pdo->query("
    SELECT pt.*, t.jenis_tiket, jm.pertandingan 
    FROM pemesanan_tiket pt
    LEFT JOIN tiket t ON pt.tiket_id = t.id
    LEFT JOIN jadwal_match jm ON t.match_id = jm.id
    ORDER BY pt.tanggal_pesan DESC
")->fetchAll(PDO::FETCH_ASSOC);

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Smekda Jersey</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;900&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #0f0f1e; color: #fff; overflow-x: hidden; }

        /* Notification */
        .notification {
            position: fixed; top: 20px; right: 20px; padding: 1rem 1.5rem;
            border-radius: 10px; z-index: 9999; animation: slideIn 0.3s ease;
        }
        .notification.success { background: rgba(37, 211, 102, 0.2); border: 1px solid #25d366; color: #25d366; }
        .notification.error { background: rgba(255, 107, 107, 0.2); border: 1px solid #ff6b6b; color: #ff6b6b; }
        @keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.8); z-index: 9998; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content {
            background: linear-gradient(135deg, #1e1e2e 0%, #2a2a3e 100%);
            padding: 2rem; border-radius: 15px; width: 90%; max-width: 600px;
            max-height: 90vh; overflow-y: auto; border: 1px solid rgba(102, 126, 234, 0.2);
            animation: modalZoom 0.3s ease;
        }
        @keyframes modalZoom { from { transform: scale(0.7); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .modal-header h3 {
            font-size: 1.5rem; background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .close-modal {
            background: rgba(255, 107, 107, 0.2); border: none; color: #ff6b6b;
            width: 35px; height: 35px; border-radius: 50%; cursor: pointer;
            font-size: 1.2rem; transition: all 0.3s ease;
        }
        .close-modal:hover { background: rgba(255, 107, 107, 0.4); transform: rotate(90deg); }

        /* Sidebar */
        .sidebar {
            position: fixed; left: 0; top: 0; width: 280px; height: 100vh;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            padding: 0; z-index: 1000; border-right: 1px solid rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease; overflow-y: auto;
        }
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); }
        .sidebar::-webkit-scrollbar-thumb { background: #667eea; border-radius: 10px; }
        .sidebar.collapsed { width: 80px; }
        .logo-section {
            padding: 2rem 1.5rem; text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(102, 126, 234, 0.05);
        }
        .logo-section h2 {
            font-size: 1.8rem; background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            margin-bottom: 0.3rem; font-weight: 900;
        }
        .logo-section p { color: #aaa; font-size: 0.8rem; }
        .sidebar.collapsed .logo-section h2, .sidebar.collapsed .logo-section p { display: none; }

        .menu { list-style: none; padding: 1rem 0; }
        .menu-item { margin-bottom: 0.3rem; }
        .menu-link {
            display: flex; align-items: center; gap: 1rem; padding: 1rem 1.5rem;
            color: #aaa; text-decoration: none; transition: all 0.3s ease;
            cursor: pointer; position: relative;
        }
        .menu-link::before {
            content: ''; position: absolute; left: 0; top: 0; height: 100%;
            width: 4px; background: #667eea; transform: scaleY(0); transition: transform 0.3s ease;
        }
        .menu-link:hover, .menu-link.active {
            background: rgba(102, 126, 234, 0.1); color: #667eea;
        }
        .menu-link:hover::before, .menu-link.active::before { transform: scaleY(1); }
        .menu-link i { font-size: 1.2rem; min-width: 24px; text-align: center; }
        .sidebar.collapsed .menu-link span { display: none; }
        .sidebar.collapsed .menu-link { justify-content: center; padding: 1rem; }

        /* Main Content */
        .main-content { margin-left: 280px; padding: 2rem; transition: all 0.3s ease; min-height: 100vh; }
        .main-content.expanded { margin-left: 80px; }

        /* Header */
        .header {
            background: linear-gradient(135deg, #1e1e2e 0%, #2a2a3e 100%);
            padding: 1.5rem 2rem; border-radius: 15px; margin-bottom: 2rem;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3); flex-wrap: wrap; gap: 1rem;
        }
        .header-left { display: flex; align-items: center; gap: 1rem; }
        .toggle-sidebar {
            background: linear-gradient(135deg, #667eea, #764ba2); border: none;
            color: #fff; width: 45px; height: 45px; border-radius: 10px;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; transition: all 0.3s ease;
        }
        .toggle-sidebar:hover { transform: rotate(180deg) scale(1.1); }
        .header-title h1 {
            font-size: 1.8rem; background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.3rem;
        }
        .header-title p { color: #aaa; font-size: 0.9rem; }
        .header-right { display: flex; align-items: center; gap: 1rem; }
        .user-profile {
            display: flex; align-items: center; gap: 1rem;
            background: rgba(255, 255, 255, 0.05); padding: 0.6rem 1.2rem;
            border-radius: 50px; transition: all 0.3s ease;
        }
        .user-profile:hover { background: rgba(102, 126, 234, 0.1); }
        .user-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
        }
        .user-info strong { display: block; font-size: 0.95rem; }
        .user-info span { font-size: 0.75rem; color: #aaa; }
        .logout-btn {
            background: linear-gradient(135deg, #ff6b6b, #ee5a6f); border: none;
            color: #fff; padding: 0.7rem 1.5rem; border-radius: 10px;
            cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-size: 0.9rem;
        }
        .logout-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(255, 107, 107, 0.4); }

        /* Stats Cards */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem; margin-bottom: 2rem;
        }
        .stat-card {
            background: linear-gradient(135deg, #1e1e2e 0%, #2a2a3e 100%);
            padding: 1.8rem; border-radius: 15px; border: 1px solid rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease; position: relative; overflow: hidden;
        }
        .stat-card::before {
            content: ''; position: absolute; top: 0; right: 0; width: 100px; height: 100px;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1), transparent);
            border-radius: 50%; transform: translate(30%, -30%);
        }
        .stat-card:hover {
            transform: translateY(-8px); box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
            border-color: #667eea;
        }
        .stat-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
        .stat-card h3 {
            color: #aaa; font-size: 0.85rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .stat-icon {
            width: 50px; height: 50px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
        }
        .stat-value {
            font-size: 2.2rem; font-weight: 700;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.5rem;
        }
        .stat-change { font-size: 0.85rem; color: #25d366; }

        /* Content Sections */
        .content-section { display: none; }
        .content-section.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .section-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;
        }
        .section-header h2 {
            font-size: 1.8rem; background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .btn-add {
            background: linear-gradient(135deg, #667eea, #764ba2); border: none;
            color: #fff; padding: 0.8rem 1.8rem; border-radius: 10px;
            cursor: pointer; font-weight: 600; display: inline-flex;
            align-items: center; gap: 0.5rem; transition: all 0.3s ease; font-size: 0.95rem;
        }
        .btn-add:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4); }

        /* Table */
        .table-container {
            background: linear-gradient(135deg, #1e1e2e 0%, #2a2a3e 100%);
            border-radius: 15px; padding: 1.5rem; overflow-x: auto;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }
        .table-container h3 { margin-bottom: 1.5rem; color: #fff; font-size: 1.2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 1rem 0.8rem; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        th {
            color: #667eea; font-weight: 600; font-size: 0.85rem;
            text-transform: uppercase; letter-spacing: 0.5px; background: rgba(102, 126, 234, 0.05);
        }
        td { color: #ddd; font-size: 0.9rem; }
        tr:hover { background: rgba(102, 126, 234, 0.03); }

        .action-buttons { display: flex; gap: 0.5rem; }
        .btn-edit, .btn-delete, .btn-view {
            padding: 0.5rem 0.8rem; border: none; border-radius: 8px;
            cursor: pointer; font-size: 0.85rem; transition: all 0.3s ease;
            display: inline-flex; align-items: center; gap: 0.3rem;
            text-decoration: none;
        }
        .btn-edit { background: linear-gradient(135deg, #48dbfb, #0abde3); color: #fff; }
        .btn-delete { background: linear-gradient(135deg, #ff6b6b, #ee5a6f); color: #fff; }
        .btn-view { background: linear-gradient(135deg, #feca57, #ff9ff3); color: #fff; }
        .btn-edit:hover, .btn-delete:hover, .btn-view:hover {
            transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .badge {
            display: inline-block; padding: 0.4rem 0.9rem; border-radius: 20px;
            font-size: 0.8rem; font-weight: 600;
        }
        .badge-success { background: rgba(37, 211, 102, 0.2); color: #25d366; border: 1px solid #25d366; }
        .badge-pending { background: rgba(254, 202, 87, 0.2); color: #feca57; border: 1px solid #feca57; }
        .badge-cancelled { background: rgba(255, 107, 107, 0.2); color: #ff6b6b; border: 1px solid #ff6b6b; }
        .badge-info { background: rgba(72, 219, 251, 0.2); color: #48dbfb; border: 1px solid #48dbfb; }

        /* Form */
        .form-group { margin-bottom: 1.5rem; }
        .form-group label {
            display: block; color: #aaa; font-size: 0.9rem;
            margin-bottom: 0.5rem; font-weight: 600;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 0.8rem; background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px;
            color: #fff; font-size: 0.95rem; transition: all 0.3s ease;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; border-color: #667eea; background: rgba(255, 255, 255, 0.08);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
        @media (max-width: 768px) {
            .header { padding: 1rem; }
            .user-info { display: none; }
            .main-content { padding: 1rem; }
            .action-buttons { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
    <?php if($success): ?>
    <div class="notification success" id="notification">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="notification error" id="notification">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo-section">
            <h2>SMEKDA</h2>
            <p>Admin Dashboard</p>
        </div>
        <ul class="menu">
            <li class="menu-item">
                <a class="menu-link active" onclick="showSection('dashboard')">
                    <i class="fas fa-home"></i><span>Dashboard</span>
                </a>
            </li>
            <li class="menu-item">
                <a class="menu-link" onclick="showSection('jadwal')">
                    <i class="fas fa-calendar-alt"></i><span>Jadwal Match</span>
                </a>
            </li>
            <li class="menu-item">
                <a class="menu-link" onclick="showSection('tiket')">
                    <i class="fas fa-ticket-alt"></i><span>Tiket</span>
                </a>
            </li>
            <li class="menu-item">
                <a class="menu-link" onclick="showSection('merchandise')">
                    <i class="fas fa-tshirt"></i><span>Merchandise</span>
                </a>
            </li>
            <li class="menu-item">
                <a class="menu-link" onclick="showSection('pesanan')">
                    <i class="fas fa-shopping-cart"></i><span>Pesanan</span>
                </a>
            </li>
            <li class="menu-item">
                <a class="menu-link" onclick="showSection('pemesanan-tiket')">
                    <i class="fas fa-receipt"></i><span>Pemesanan Tiket</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <header class="header">
            <div class="header-left">
                <button class="toggle-sidebar" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="header-title">
                    <h1>Dashboard</h1>
                    <p><?php echo date('l, d F Y'); ?></p>
                </div>
            </div>
            <div class="header-right">
                <div class="user-profile">
                    <div class="user-avatar"><i class="fas fa-user"></i></div>
                    <div class="user-info">
                        <strong><?php echo $_SESSION['admin_username']; ?></strong>
                        <span>Administrator</span>
                    </div>
                </div>
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </header>

        <!-- Dashboard Section -->
        <section class="content-section active" id="dashboard">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Total Pesanan</h3>
                        <div class="stat-icon" style="background: rgba(102, 126, 234, 0.2);">
                            <i class="fas fa-shopping-bag" style="color: #667eea;"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_pesanan; ?></div>
                    <p class="stat-change"><i class="fas fa-arrow-up"></i> +12% dari bulan lalu</p>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Merchandise Terjual</h3>
                        <div class="stat-icon" style="background: rgba(72, 219, 251, 0.2);">
                            <i class="fas fa-tshirt" style="color: #48dbfb;"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_merchandise; ?></div>
                    <p class="stat-change"><i class="fas fa-arrow-up"></i> +8% dari bulan lalu</p>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Total Pendapatan</h3>
                        <div class="stat-icon" style="background: rgba(37, 211, 102, 0.2);">
                            <i class="fas fa-dollar-sign" style="color: #25d366;"></i>
                        </div>
                    </div>
                    <div class="stat-value">Rp<?php echo number_format($total_pendapatan, 0, ',', '.'); ?></div>
                    <p class="stat-change"><i class="fas fa-arrow-up"></i> +18% dari bulan lalu</p>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Pending Verifikasi</h3>
                        <div class="stat-icon" style="background: rgba(254, 202, 87, 0.2);">
                            <i class="fas fa-clock" style="color: #feca57;"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $pending_verifikasi; ?></div>
                    <p class="stat-change"><i class="fas fa-exclamation-circle"></i> Perlu tindakan</p>
                </div>
            </div>

            <div class="table-container">
                <h3><i class="fas fa-list"></i> Pesanan Terbaru</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Kode Pesanan</th><th>Nama</th><th>Produk</th><th>Jumlah</th>
                            <th>Total</th><th>Status</th><th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pesanan_terbaru as $p): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($p['kode_pesanan'] ?? '-'); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['nama']); ?></td>
                            <td><?php echo htmlspecialchars($p['produk']); ?></td>
                            <td><?php echo $p['jumlah']; ?></td>
                            <td>Rp<?php echo number_format($p['total_harga'] ?? 0, 0, ',', '.'); ?></td>
                            <td>
                                <span class="badge badge-<?php 
                                    $status = $p['status'] ?? 'pending';
                                    echo $status == 'completed' || $status == 'paid' ? 'success' : 
                                        ($status == 'pending' ? 'pending' : 'cancelled'); 
                                ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($p['tanggal'] ?? 'now')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Jadwal Match Section -->
        <section class="content-section" id="jadwal">
            <div class="section-header">
                <h2><i class="fas fa-calendar-alt"></i> Jadwal Match</h2>
                <button class="btn-add" onclick="openModal('jadwalModal')">
                    <i class="fas fa-plus"></i> Tambah Jadwal
                </button>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>Pertandingan</th><th>Tanggal</th><th>Waktu</th>
                            <th>Lokasi</th><th>Status</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_jadwal as $j): ?>
                        <tr>
                            <td>#M<?php echo str_pad($j['id'], 3, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($j['pertandingan']); ?></td>
                            <td><?php echo date('d M Y', strtotime($j['tanggal'])); ?></td>
                            <td><?php echo date('H:i', strtotime($j['waktu'])); ?> WIB</td>
                            <td><?php echo htmlspecialchars($j['lokasi']); ?></td>
                            <td><span class="badge badge-<?php echo $j['status'] == 'upcoming' ? 'success' : ($j['status'] == 'finished' ? 'info' : 'pending'); ?>"><?php echo ucfirst($j['status']); ?></span></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-edit" onclick="editJadwal(<?php echo $j['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-delete" onclick="deleteData('jadwal', <?php echo $j['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Tiket Section -->
        <section class="content-section" id="tiket">
            <div class="section-header">
                <h2><i class="fas fa-ticket-alt"></i> Manajemen Tiket</h2>
                <button class="btn-add" onclick="openModal('tiketModal')">
                    <i class="fas fa-plus"></i> Tambah Tiket
                </button>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>Jenis Tiket</th><th>Pertandingan</th>
                            <th>Harga</th><th>Stok</th><th>Terjual</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_tiket as $t): ?>
                        <tr>
                            <td>#T<?php echo str_pad($t['id'], 3, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($t['jenis_tiket']); ?></td>
                            <td><?php echo htmlspecialchars($t['pertandingan'] ?? '-'); ?></td>
                            <td>Rp<?php echo number_format($t['harga'], 0, ',', '.'); ?></td>
                            <td><?php echo $t['stok']; ?></td>
                            <td><?php echo $t['terjual']; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-edit" onclick="editTiket(<?php echo $t['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-delete" onclick="deleteData('tiket', <?php echo $t['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Merchandise Section -->
        <section class="content-section" id="merchandise">
            <div class="section-header">
                <h2><i class="fas fa-tshirt"></i> Manajemen Merchandise</h2>
                <button class="btn-add" onclick="openModal('merchandiseModal')">
                    <i class="fas fa-plus"></i> Tambah Merchandise
                </button>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>Produk</th><th>Kategori</th><th>Harga</th>
                            <th>Stok</th><th>Terjual</th><th>Status</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_merchandise as $m): ?>
                        <tr>
                            <td>#P<?php echo str_pad($m['id'], 3, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($m['nama_produk']); ?></td>
                            <td><?php echo htmlspecialchars($m['kategori']); ?></td>
                            <td>Rp<?php echo number_format($m['harga'], 0, ',', '.'); ?></td>
                            <td><?php echo $m['stok']; ?></td>
                            <td><?php echo $m['terjual']; ?></td>
                            <td><span class="badge badge-<?php echo $m['stok']>0?'success':'cancelled'; ?>"><?php echo ucfirst($m['status']); ?></span></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-edit" onclick="editMerchandise(<?php echo $m['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-delete" onclick="deleteData('merchandise', <?php echo $m['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Pesanan Section -->
        <section class="content-section" id="pesanan">
            <div class="section-header">
                <h2><i class="fas fa-shopping-cart"></i> Daftar Pesanan</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Kode Pesanan</th><th>Nama</th><th>No HP</th><th>Produk</th>
                            <th>Jumlah</th><th>Total</th><th>Status</th><th>Kartu Pelajar</th><th>Tanggal</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_pesanan as $p): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($p['kode_pesanan']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['nama']); ?></td>
                            <td><?php echo htmlspecialchars($p['no_hp']); ?></td>
                            <td><?php echo htmlspecialchars($p['produk']); ?></td>
                            <td><?php echo $p['jumlah']; ?></td>
                            <td>Rp<?php echo number_format($p['total_harga'] ?? 0, 0, ',', '.'); ?></td>
                            <td>
                                <span class="badge badge-<?php 
                                    $status = $p['status'] ?? 'pending';
                                    echo $status == 'completed' || $status == 'paid' ? 'success' : 
                                        ($status == 'pending' ? 'pending' : 'cancelled'); 
                                ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($p['foto_kartu_pelajar'])): ?>
                                    <button class="btn-view" onclick="viewKartuPelajar('<?php echo $p['foto_kartu_pelajar']; ?>')">
                                        <i class="fas fa-id-card"></i> Lihat
                                    </button>
                                <?php else: ?>
                                    <span class="badge badge-cancelled">Belum Upload</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d M Y H:i', strtotime($p['tanggal_pemesanan'] ?? 'now')); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-view" onclick="viewPesanan(<?php echo $p['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-edit" onclick="editStatusPesanan(<?php echo $p['id']; ?>, '<?php echo $p['status'] ?? 'pending'; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-delete" onclick="deleteData('pemesanan_merchandise', <?php echo $p['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Pemesanan Tiket Section -->
        <section class="content-section" id="pemesanan-tiket">
            <div class="section-header">
                <h2><i class="fas fa-receipt"></i> Pemesanan Tiket</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Kode Booking</th><th>Nama</th><th>No HP</th>
                            <th>Pertandingan</th><th>Jenis Tiket</th><th>Jumlah</th>
                            <th>Total</th><th>Status</th><th>Tanggal</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pemesanan_tiket as $pt): ?>
                        <tr>
                            <td><strong>TIK-<?php echo str_pad($pt['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars($pt['nama']); ?></td>
                            <td><?php echo htmlspecialchars($pt['no_hp']); ?></td>
                            <td><?php echo htmlspecialchars($pt['pertandingan'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($pt['jenis_tiket'] ?? '-'); ?></td>
                            <td><?php echo $pt['jumlah_tiket']; ?> tiket</td>
                            <td>Rp<?php echo number_format($pt['total_harga'], 0, ',', '.'); ?></td>
                            <td>
                                <span class="badge badge-<?php 
                                    $status = $pt['status'] ?? 'pending';
                                    echo $status=='paid' ? 'success' : 
                                        ($status=='confirmed' ? 'info' : 
                                            ($status=='cancelled' ? 'cancelled' : 'pending')); 
                                ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y H:i', strtotime($pt['tanggal_pesan'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-edit" onclick="editStatusTiket(<?php echo $pt['id']; ?>, '<?php echo $pt['status'] ?? 'pending'; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-view" onclick="viewPemesananTiket(<?php echo $pt['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-delete" onclick="deleteData('pemesanan_tiket', <?php echo $pt['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Modal Jadwal -->
    <div class="modal" id="jadwalModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-alt"></i> <span id="jadwalModalTitle">Tambah Jadwal</span></h3>
                <button class="close-modal" onclick="closeModal('jadwalModal')">×</button>
            </div>
            <form id="jadwalForm" method="POST" action="../models/crud_jadwal.php?action=create">
                <input type="hidden" name="id" id="jadwal_id">
                <div class="form-group">
                    <label>Pertandingan</label>
                    <input type="text" name="pertandingan" id="jadwal_pertandingan" required>
                </div>
                <div class="form-group">
                    <label>Tanggal</label>
                    <input type="date" name="tanggal" id="jadwal_tanggal" required>
                </div>
                <div class="form-group">
                    <label>Waktu</label>
                    <input type="time" name="waktu" id="jadwal_waktu" required>
                </div>
                <div class="form-group">
                    <label>Lokasi</label>
                    <input type="text" name="lokasi" id="jadwal_lokasi" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="jadwal_status" required>
                        <option value="upcoming">Upcoming</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="finished">Finished</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <button type="submit" class="btn-add">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Tiket -->
    <div class="modal" id="tiketModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-ticket-alt"></i> <span id="tiketModalTitle">Tambah Tiket</span></h3>
                <button class="close-modal" onclick="closeModal('tiketModal')">×</button>
            </div>
            <form id="tiketForm" method="POST" action="../models/crud_tiket.php?action=create">
                <input type="hidden" name="id" id="tiket_id">
                <div class="form-group">
                    <label>Jenis Tiket</label>
                    <input type="text" name="jenis_tiket" id="tiket_jenis" required>
                </div>
                <div class="form-group">
                    <label>Pertandingan</label>
                    <select name="match_id" id="tiket_match_id" required>
                        <option value="">Pilih Pertandingan</option>
                        <?php foreach ($all_jadwal as $j): ?>
                        <option value="<?php echo $j['id']; ?>"><?php echo htmlspecialchars($j['pertandingan']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Harga</label>
                    <input type="number" name="harga" id="tiket_harga" required>
                </div>
                <div class="form-group">
                    <label>Stok</label>
                    <input type="number" name="stok" id="tiket_stok" required>
                </div>
                <button type="submit" class="btn-add">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Merchandise -->
    <div class="modal" id="merchandiseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-tshirt"></i> <span id="merchandiseModalTitle">Tambah Merchandise</span></h3>
                <button class="close-modal" onclick="closeModal('merchandiseModal')">×</button>
            </div>
            <form id="merchandiseForm" method="POST" action="../models/crud_merchandise.php?action=create" enctype="multipart/form-data">
                <input type="hidden" name="id" id="merchandise_id">
                <input type="hidden" name="gambar_lama" id="merchandise_gambar_lama">
                <div class="form-group">
                    <label>Nama Produk</label>
                    <input type="text" name="nama_produk" id="merchandise_nama" required>
                </div>
                <div class="form-group">
                    <label>Kategori</label>
                    <input type="text" name="kategori" id="merchandise_kategori" required>
                </div>
                <div class="form-group">
                    <label>Harga</label>
                    <input type="number" name="harga" id="merchandise_harga" required>
                </div>
                <div class="form-group">
                    <label>Stok</label>
                    <input type="number" name="stok" id="merchandise_stok" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="merchandise_status" required>
                        <option value="tersedia">Tersedia</option>
                        <option value="habis">Habis</option>
                        <option value="pre-order">Pre-Order</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Gambar</label>
                    <input type="file" name="gambar" id="merchandise_gambar" accept="image/*">
                </div>
                <button type="submit" class="btn-add">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Status Pesanan -->
    <div class="modal" id="statusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Update Status Pesanan</h3>
                <button class="close-modal" onclick="closeModal('statusModal')">×</button>
            </div>
            <form method="POST" action="../models/crud_pemesanan_merchandise.php">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" id="status_id">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="status_value" required>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="paid">Paid</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <button type="submit" class="btn-add">
                    <i class="fas fa-save"></i> Update Status
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Status Tiket -->
    <div class="modal" id="statusTiketModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Update Status Tiket</h3>
                <button class="close-modal" onclick="closeModal('statusTiketModal')">×</button>
            </div>
            <form method="POST" action="../models/crud_pemesanan_tiket.php">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" id="status_tiket_id">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="status_tiket_value" required>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="paid">Paid</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <button type="submit" class="btn-add">
                    <i class="fas fa-save"></i> Update Status
                </button>
            </form>
        </div>
    </div>

    <!-- Modal View Pesanan -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Detail Pesanan</h3>
                <button class="close-modal" onclick="closeModal('viewModal')">×</button>
            </div>
            <div id="viewContent"></div>
        </div>
    </div>

    <!-- Modal View Kartu Pelajar -->
    <div class="modal" id="kartuModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-id-card"></i> Foto Kartu Pelajar</h3>
                <button class="close-modal" onclick="closeModal('kartuModal')">×</button>
            </div>
            <div id="kartuContent" style="text-align: center; padding: 1rem;">
                <img id="kartuImage" src="" alt="Kartu Pelajar" style="max-width: 100%; max-height: 500px; border-radius: 10px;">
                <div style="margin-top: 1rem;">
                    <button class="btn-download" onclick="downloadKartuPelajar()" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 0.5rem 1rem; border: none; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Auto hide notification
    setTimeout(() => {
        const notif = document.getElementById('notification');
        if (notif) notif.style.display = 'none';
    }, 5000);

    // Toggle Sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        if (window.innerWidth > 1024) {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        } else {
            sidebar.classList.toggle('active');
        }
    }

    // Show Section
    function showSection(sectionId) {
        document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
        document.getElementById(sectionId).classList.add('active');
        event.target.closest('.menu-link').classList.add('active');
        
        const titles = {
            'dashboard': 'Dashboard',
            'jadwal': 'Jadwal Match',
            'tiket': 'Manajemen Tiket',
            'merchandise': 'Manajemen Merchandise',
            'pesanan': 'Daftar Pesanan',
            'pemesanan-tiket': 'Pemesanan Tiket'
        };
        document.querySelector('.header-title h1').textContent = titles[sectionId];
        
        if (window.innerWidth <= 1024) {
            document.getElementById('sidebar').classList.remove('active');
        }
    }

    // Modal Functions
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('active');
        // Reset form
        const form = document.querySelector(`#${modalId} form`);
        if (form) form.reset();
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }

    // CRUD Functions - Jadwal
    function editJadwal(id) {
        console.log("🚀 editJadwal called with ID:", id);
        console.log("📡 Fetch URL:", `../models/crud_jadwal.php?action=get&id=${id}`);
        
        fetch(`../models/crud_jadwal.php?action=get&id=${id}`)
            .then(res => {
                console.log("📥 Response status:", res.status);
                return res.json();
            })
            .then(data => {
                console.log("✅ Data received:", data);
                
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                document.getElementById('jadwal_id').value = data.id;
                document.getElementById('jadwal_pertandingan').value = data.pertandingan;
                document.getElementById('jadwal_tanggal').value = data.tanggal;
                document.getElementById('jadwal_waktu').value = data.waktu;
                document.getElementById('jadwal_lokasi').value = data.lokasi;
                document.getElementById('jadwal_status').value = data.status;
                
                document.getElementById('jadwalForm').action = '../models/crud_jadwal.php?action=update';
                
                document.getElementById('jadwalModalTitle').textContent = 'Edit Jadwal';
                openModal('jadwalModal');
            })
            .catch(error => {
                console.error("❌ Fetch error:", error);
                alert('Gagal mengambil data jadwal: ' + error.message);
            });
    }

    // CRUD Functions - Tiket
    function editTiket(id) {
        console.log("🚀 editTiket called with ID:", id);
        console.log("📡 Fetch URL:", `../models/crud_tiket.php?action=get&id=${id}`);
        
        fetch(`../models/crud_tiket.php?action=get&id=${id}`)
            .then(res => {
                console.log("📥 Response status:", res.status);
                return res.json();
            })
            .then(data => {
                console.log("✅ Data received:", data);
                
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                document.getElementById('tiket_id').value = data.id;
                document.getElementById('tiket_jenis').value = data.jenis_tiket;
                document.getElementById('tiket_match_id').value = data.match_id;
                document.getElementById('tiket_harga').value = data.harga;
                document.getElementById('tiket_stok').value = data.stok;
                
                document.getElementById('tiketForm').action = '../models/crud_tiket.php?action=update';
                
                document.getElementById('tiketModalTitle').textContent = 'Edit Tiket';
                openModal('tiketModal');
            })
            .catch(error => {
                console.error("❌ Fetch error:", error);
                alert('Gagal mengambil data tiket: ' + error.message);
            });
    }

    // CRUD Functions - Merchandise
    function editMerchandise(id) {
        console.log("🚀 editMerchandise called with ID:", id);
        console.log("📡 Fetch URL:", `../models/crud_merchandise.php?action=get&id=${id}`);
        
        fetch(`../models/crud_merchandise.php?action=get&id=${id}`)
            .then(res => {
                console.log("📥 Response status:", res.status);
                return res.json();
            })
            .then(data => {
                console.log("✅ Data received:", data);
                
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                document.getElementById('merchandise_id').value = data.id;
                document.getElementById('merchandise_nama').value = data.nama_produk;
                document.getElementById('merchandise_kategori').value = data.kategori;
                document.getElementById('merchandise_harga').value = data.harga;
                document.getElementById('merchandise_stok').value = data.stok;
                document.getElementById('merchandise_status').value = data.status;
                document.getElementById('merchandise_gambar_lama').value = data.gambar || '';
                
                document.getElementById('merchandiseForm').action = '../models/crud_merchandise.php?action=update';
                
                document.getElementById('merchandiseModalTitle').textContent = 'Edit Merchandise';
                openModal('merchandiseModal');
            })
            .catch(error => {
                console.error("❌ Fetch error:", error);
                alert('Gagal mengambil data merchandise: ' + error.message);
            });
    }

    // Edit Status Pesanan
    function editStatusPesanan(id, currentStatus) {
        console.log("Edit status ID:", id, "Current:", currentStatus);
        
        document.getElementById('status_id').value = id;
        document.getElementById('status_value').value = currentStatus;
        
        openModal('statusModal');
    }

    // Edit Status Tiket
    function editStatusTiket(id, currentStatus) {
        console.log("🚀 editStatusTiket called - ID:", id, "Current Status:", currentStatus);
        
        document.getElementById('status_tiket_id').value = id;
        document.getElementById('status_tiket_value').value = currentStatus;
        
        openModal('statusTiketModal');
    }

    // View Kartu Pelajar
    function viewKartuPelajar(imagePath) {
        console.log("🔍 Path dari database:", imagePath);
        
        let fullPath = '../' + imagePath;
        
        console.log("🔍 Full path yang digunakan:", fullPath);
        
        const img = new Image();
        img.onload = function() {
            console.log("✅ Gambar berhasil dimuat");
            document.getElementById('kartuImage').src = fullPath;
            document.getElementById('kartuImage').setAttribute('data-path', fullPath);
            openModal('kartuModal');
        };
        img.onerror = function() {
            console.log("❌ Gagal memuat gambar:", fullPath);
            
            const altPath = imagePath.replace('../', '');
            console.log("🔄 Mencoba path alternatif:", altPath);
            
            const img2 = new Image();
            img2.onload = function() {
                document.getElementById('kartuImage').src = altPath;
                document.getElementById('kartuImage').setAttribute('data-path', altPath);
                openModal('kartuModal');
            };
            img2.onerror = function() {
                const rootPath = '/' + imagePath.replace('../', '');
                console.log("🔄 Mencoba path root:", rootPath);
                
                const img3 = new Image();
                img3.onload = function() {
                    document.getElementById('kartuImage').src = rootPath;
                    document.getElementById('kartuImage').setAttribute('data-path', rootPath);
                    openModal('kartuModal');
                };
                img3.onerror = function() {
                    alert('Gagal memuat foto kartu pelajar. File mungkin tidak ditemukan di: ' + fullPath);
                };
                img3.src = rootPath;
            };
            img2.src = altPath;
        };
        img.src = fullPath;
    }

    // Download Kartu Pelajar
    function downloadKartuPelajar() {
        const imagePath = document.getElementById('kartuImage').getAttribute('data-path');
        const link = document.createElement('a');
        link.href = imagePath;
        link.download = 'kartu_pelajar_' + new Date().getTime() + '.jpg';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // View Pesanan Detail
    function viewPesanan(id) {
        fetch(`../models/crud_pemesanan_merchandise.php?action=get&id=${id}`)
            .then(res => res.json())
            .then(data => {
                const kartuPelajarBtn = data.foto_kartu_pelajar ? 
                    `<button class="btn-view" onclick="viewKartuPelajar('${data.foto_kartu_pelajar}')" style="margin-top: 10px;">
                        <i class="fas fa-id-card"></i> Lihat Kartu Pelajar
                    </button>` : 
                    '<span class="badge badge-cancelled">Belum Upload Kartu</span>';
                
                const content = `
                    <div style="padding: 1rem;">
                        <p><strong>Kode Pesanan:</strong> ${data.kode_pesanan}</p>
                        <p><strong>Nama:</strong> ${data.nama}</p>
                        <p><strong>No HP:</strong> ${data.no_hp}</p>
                        <p><strong>Produk:</strong> ${data.produk}</p>
                        <p><strong>Jumlah:</strong> ${data.jumlah}</p>
                        <p><strong>Total:</strong> Rp${new Intl.NumberFormat('id-ID').format(data.total_harga)}</p>
                        <p><strong>Status:</strong> ${data.status}</p>
                        <p><strong>Tanggal Pesan:</strong> ${new Date(data.tanggal_pemesanan).toLocaleString('id-ID')}</p>
                        <p><strong>Kartu Pelajar:</strong> ${kartuPelajarBtn}</p>
                        ${data.catatan ? `<p><strong>Catatan:</strong> ${data.catatan}</p>` : ''}
                    </div>
                `;
                document.getElementById('viewContent').innerHTML = content;
                openModal('viewModal');
            })
            .catch(error => {
                console.error("Error fetching pesanan:", error);
                alert('Gagal mengambil detail pesanan');
            });
    }

    // View Detail Pemesanan Tiket
    function viewPemesananTiket(id) {
        fetch(`../models/crud_pemesanan_tiket.php?action=get&id=${id}`)
            .then(res => res.json())
            .then(data => {
                const content = `
                    <div style="padding: 1rem;">
                        <p><strong>Kode Booking:</strong> TIK-${String(data.id).padStart(6, '0')}</p>
                        <p><strong>Nama:</strong> ${data.nama}</p>
                        <p><strong>No HP:</strong> ${data.no_hp}</p>
                        <p><strong>Email:</strong> ${data.email || '-'}</p>
                        <p><strong>Pertandingan:</strong> ${data.pertandingan}</p>
                        <p><strong>Jenis Tiket:</strong> ${data.jenis_tiket}</p>
                        <p><strong>Jumlah:</strong> ${data.jumlah_tiket} tiket</p>
                        <p><strong>Total:</strong> Rp${new Intl.NumberFormat('id-ID').format(data.total_harga)}</p>
                        <p><strong>Status:</strong> ${data.status}</p>
                        <p><strong>Tanggal Pesan:</strong> ${new Date(data.tanggal_pesan).toLocaleString('id-ID')}</p>
                    </div>
                `;
                document.getElementById('viewContent').innerHTML = content;
                openModal('viewModal');
            });
    }

    // Delete Function
    function deleteData(type, id) {
        if (confirm('Apakah Anda yakin ingin menghapus data ini?')) {
            window.location.href = `../models/crud_${type}.php?action=delete&id=${id}`;
        }
    }

    // Logout
    function logout() {
        if (confirm('Apakah Anda yakin ingin logout?')) {
            window.location.href = '../logout.php';
        }
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.querySelector('.toggle-sidebar');
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });

    // Close modal when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });
</script>
</body>
</html>