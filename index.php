<?php
// HARUS DITARUH DI BARIS PALING ATAS
session_start();
require_once __DIR__ . '/config/config.php';

// PERBAIKAN QUERY: Hitung stok tersedia (stok - terjual) untuk merchandise
$merchandise_query = $pdo->query("
    SELECT *, 
           (stok - terjual) as stok_tersedia,
           CASE 
               WHEN (stok - terjual) > 0 THEN 'tersedia'
               ELSE 'habis'
           END as status_stok,
           CASE 
               WHEN (stok - terjual) <= 0 THEN 'disabled'
               ELSE 'available'
           END as css_class
    FROM merchandise 
    ORDER BY 
        CASE WHEN (stok - terjual) > 0 THEN 0 ELSE 1 END,
        id ASC
");
$merchandise_list = $merchandise_query->fetchAll();

// Hitung statistik merchandise (untuk internal)
$available_merchandise = array_filter($merchandise_list, function($item) {
    return $item['stok_tersedia'] > 0;
});
$out_of_stock = array_filter($merchandise_list, function($item) {
    return $item['stok_tersedia'] <= 0;
});

// PERBAIKAN QUERY: Ambil jadwal pertandingan yang upcoming
$jadwal_query = $pdo->query("
    SELECT jm.*,
           DATEDIFF(jm.tanggal, CURDATE()) as hari_menuju,
           CASE 
               WHEN jm.status = 'upcoming' THEN 'AKAN DATANG'
               WHEN jm.status = 'ongoing' THEN 'SEDANG BERLANGSUNG'
               WHEN jm.status = 'finished' THEN 'SELESAI'
               WHEN jm.status = 'cancelled' THEN 'DIBATALKAN'
               ELSE jm.status
           END as status_text
    FROM jadwal_match jm 
    WHERE jm.status IN ('upcoming', 'ongoing')
    ORDER BY jm.tanggal ASC
");
$jadwal_list = $jadwal_query->fetchAll();

// PERBAIKAN: Ambil tiket dengan menghitung stok yang benar
foreach ($jadwal_list as &$jadwal) {
    // HITUNG STOK TIKET YANG SEBENARNYA: stok - COALESCE(jumlah dipesan)
    $tiket_stok_query = $pdo->prepare("
        SELECT 
            COUNT(*) as jumlah_tiket,
            COALESCE(SUM(
                t.stok - COALESCE(
                    (SELECT SUM(jumlah_tiket) 
                     FROM pemesanan_tiket pt 
                     WHERE pt.tiket_id = t.id 
                     AND pt.status IN ('pending', 'confirmed', 'paid')), 
                    0
                )
            ), 0) as total_stok_available,
            COALESCE(MIN(
                t.stok - COALESCE(
                    (SELECT SUM(jumlah_tiket) 
                     FROM pemesanan_tiket pt 
                     WHERE pt.tiket_id = t.id 
                     AND pt.status IN ('pending', 'confirmed', 'paid')), 
                    0
                )
            ), 0) as stok_terkecil,
            COALESCE(MIN(t.harga), 0) as harga_terendah,
            COALESCE(MAX(t.harga), 0) as harga_tertinggi
        FROM tiket t
        WHERE t.match_id = ? 
        AND (
            t.stok - COALESCE(
                (SELECT SUM(jumlah_tiket) 
                 FROM pemesanan_tiket pt 
                 WHERE pt.tiket_id = t.id 
                 AND pt.status IN ('pending', 'confirmed', 'paid')), 
                0
            )
        ) > 0
    ");
    $tiket_stok_query->execute([$jadwal['id']]);
    $tiket_stok_data = $tiket_stok_query->fetch();
    
    // Ambil detail jenis tiket dengan stok tersedia
    $tiket_detail_query = $pdo->prepare("
        SELECT 
            t.id, 
            t.jenis_tiket, 
            t.harga, 
            t.stok as stok_awal,
            COALESCE(
                (SELECT SUM(jumlah_tiket) 
                 FROM pemesanan_tiket pt 
                 WHERE pt.tiket_id = t.id 
                 AND pt.status IN ('pending', 'confirmed', 'paid')), 
                0
            ) as sudah_dipesan,
            (t.stok - COALESCE(
                (SELECT SUM(jumlah_tiket) 
                 FROM pemesanan_tiket pt 
                 WHERE pt.tiket_id = t.id 
                 AND pt.status IN ('pending', 'confirmed', 'paid')), 
                0
            )) as stok_tersedia
        FROM tiket t 
        WHERE t.match_id = ? 
        AND (
            t.stok - COALESCE(
                (SELECT SUM(jumlah_tiket) 
                 FROM pemesanan_tiket pt 
                 WHERE pt.tiket_id = t.id 
                 AND pt.status IN ('pending', 'confirmed', 'paid')), 
                0
            )
        ) > 0
        ORDER BY t.harga ASC
    ");
    $tiket_detail_query->execute([$jadwal['id']]);
    $tiket_list = $tiket_detail_query->fetchAll();
    
    $jadwal['jumlah_tiket'] = $tiket_stok_data['jumlah_tiket'];
    $jadwal['total_stok'] = $tiket_stok_data['total_stok_available'];
    $jadwal['stok_terkecil'] = $tiket_stok_data['stok_terkecil'];
    $jadwal['harga_terendah'] = $tiket_stok_data['harga_terendah'];
    $jadwal['harga_tertinggi'] = $tiket_stok_data['harga_tertinggi'];
    $jadwal['tiket_list'] = $tiket_list;
    
    // Tentukan badge warna berdasarkan status
    $jadwal['badge_class'] = '';
    switch ($jadwal['status']) {
        case 'upcoming':
            $jadwal['badge_class'] = 'bg-warning';
            break;
        case 'ongoing':
            $jadwal['badge_class'] = 'bg-success';
            break;
        case 'finished':
            $jadwal['badge_class'] = 'bg-secondary';
            break;
        case 'cancelled':
            $jadwal['badge_class'] = 'bg-danger';
            break;
    }
}
unset($jadwal); // Hapus reference
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ULTRAS SMEKDA - Official Merchandise & Match Tickets</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;900&display=swap');
        
        * {
            margin:  5;
            padding: 5;
            box-sizing: border-box; 
        }

        /* FIX SEMUA PSEUDO ELEMENTS YANG BERMASALAH */
        *::before, *::after { 
            display: none !important; 
            background: none !important;
            content: none !important;
        }

        /* HIDE ELEMEN YANG TIDAK DIINGINKAN */
        [style*="User Interface"], [style*="Dataflowed"], [style*="Sensida"] {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
        }

       body {
    font-family: 'Poppins', sans-serif;
    background: #0f0714ff; /* 1 WARNA SOLID */
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
            background: rgba(13, 13, 16, 0.4);
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
            box-shadow: 0 5px 20px rgba(195, 130, 244, 0.3);
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
    color: #ffffff;
    color: #efeeeeff; 
    color: #ebebebff;
    display: flex;
    align-items: center;
    gap: 10px;
        }

        .nav-logo i {
            font-size: 1.5rem;
        }

        .nav-links {
            display: flex;
            gap: 2.5rem;
            list-style: none;
        }

        .nav-links a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 500;
            font-size: 1.1rem;
            padding: 0.5rem 0;
            position: relative;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-links a:hover {
            color: #fff;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #667eea, #ff6b6b);
            transition: width 0.4s ease;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .menu-toggle {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 8px;
        }

        .menu-toggle span {
            width: 25px;
            height: 3px;
            background: #fff;
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        .menu-toggle.active span:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .menu-toggle.active span:nth-child(2) {
            opacity: 0;
        }

        .menu-toggle.active span:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

        /* ========== HERO FLUID ========== */
        /* ========== HERO FLUID ========== */
header.hero {
    position: relative;
    height: 100vh;
    
    /* GAMBAR BACKGROUND */
     /* background-image: url('./assets/CYAKS.jpeg');     */
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
    
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding-top: 80px;
    overflow: hidden;
}

/* OVERLAY GELAP */
header.hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        135deg, 
        rgba(0, 0, 0, 0.7), 
        rgba(28, 28, 60, 0.6)
    );
    z-index: 1;
}

/* KONTEN DI ATAS OVERLAY */
.hero-content {
    position: relative;
    z-index: 10;
    padding: 3rem;
    background: transparent;
    max-width: 900px;
    margin: 0 auto;
}
.logo {
    font-size: 5.1rem;
    font-weight: 1000;
    letter-spacing: 0.1em;
    margin-bottom: 0.2rem;
    color: #E0B0FF; /* UNGU MUDA/LAVENDER */
    text-shadow: 
        0 0 30px rgba(138, 43, 226, 0.9),  /* GLOW UNGU DALAM */
        0 0 60px rgba(138, 43, 226, 0.6),  /* GLOW UNGU LUAR */
        0 4px 15px rgba(0, 0, 0, 1);       /* SHADOW HITAM TEBAL */
}
        @keyframes gradient-shift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

       .hero h1 {
    font-size: 2.8rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: #ffffffff; /* KUNING EMAS CERAH */
    text-shadow: 0 2px 10px rgba(255, 253, 253, 0.9),
                 0 0 20px rgba(41, 10, 39, 0.6); /* SHADOW GELAP + GLOW */
}
        .hero-tagline {
            font-size: 1.8rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 3rem;
            font-weight: 600;
        }

        /* ========== BUTTON FLUID ========== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background:  hsla(270, 78%, 66%, 1.00) 50%;
            color:  hsla(270, 30%, 96%, 1.00) ;
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

        .btn-ticket {
            background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
            box-shadow: 0 15px 40px rgba(254, 254, 254, 0.3);
            padding: 1.2rem 2.5rem;
            font-size: 1.1rem;
        }

        .btn-ticket:hover {
            box-shadow: 0 25px 60px rgba(255, 107, 107, 0.5);
        }

        .btn-disabled {
            background: linear-gradient(135deg, #666, #999) !important;
            cursor: not-allowed !important;
            opacity: 0.7 !important;
        }

        .btn-disabled:hover {
            transform: none !important;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3) !important;
        }

        /* ========== SECTION STYLE FLUID ========== */
        section {
            padding: 6rem 0;
            position: relative;
            background: #f2f2f7ff;
        }

        section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, transparent, #667eea, transparent);
            border-radius: 2px;
        }

       section h2 {
    font-size: 3.2rem;
    font-weight: 800;
    text-align: center;
    margin-bottom: 1.9rem;
    color: #a076f0ff; /* PUTIH */
    position: relative;
    display: inline-block;
    left: 50%;
    transform: translateX(-50%);
}

        .section-subtitle {
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.3rem;
            margin-bottom: 4rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.8;
        }

        /* ========== ABOUT FLUID ========== */
      #about {
    background: transparent; /* ATAU HAPUS PROPERTY BACKGROUND */
        }

        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            margin-top: 4rem;
        }

        .about-text {
            text-align: left;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            padding: 3rem;
            border-radius: 32px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .about-text h3 {
            font-size: 2.2rem;
            margin-bottom: 2rem;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .about-text p {
            text-align: left;
            line-height: 1.8;
            margin-bottom: 1.8rem;
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.1rem;
        }

        .about-image {
            position: relative;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(102, 126, 234, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .about-image img {
            width: 100%;
            height: 450px;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .about-image:hover img {
            transform: scale(1.05);
        }

        /* ========== MATCH CARD FLUID ========== */
        #jadwal-match {
    background: transparent; /* ATAU HAPUS PROPERTY BACKGROUND */
}

        .match-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 2.5rem;
            margin-top: 4rem;
        }

        .match-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(25px);
            border-radius: 32px;
            padding: 2.8rem;
            position: relative;
            overflow: hidden;
            transition: all 0.5s ease;
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
        }

        .match-card:hover {
            transform: translateY(-15px);
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 107, 107, 0.4);
            box-shadow: 0 30px 70px rgba(255, 107, 107, 0.3);
        }

        .match-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.6rem 1.5rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 2rem;
        }

        .bg-warning {
            background: linear-gradient(135deg, #feca57, #ff9f43);
            color: #000;
            box-shadow: 0 8px 20px rgba(254, 202, 87, 0.3);
        }

        .bg-success {
            background: linear-gradient(135deg, #25d366, #128c7e);
            color: #fff;
            box-shadow: 0 8px 20px rgba(37, 211, 102, 0.3);
        }

        .bg-danger {
            background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
            color: #fff;
            box-shadow: 0 8px 20px rgba(255, 107, 107, 0.3);
        }

        .match-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 2rem;
            line-height: 1.4;
        }

        .match-info {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
            margin-bottom: 2rem;
        }

        .match-info-item {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
        }

        .ticket-availability {
            background: rgba(37, 211, 102, 0.15);
            border: 1px solid rgba(246, 249, 247, 0.3);
            padding: 1.2rem;
            border-radius: 16px;
            text-align: center;
            color: #f8faf9ff;
            font-weight: 600;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
        }

        .ticket-sold-out {
            background: rgba(255, 107, 107, 0.15);
            border: 1px solid rgba(255, 107, 107, 0.3);
            padding: 1.2rem;
            border-radius: 16px;
            text-align: center;
            color: #ff6b6b;
            font-weight: 600;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
        }

        .price-range {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .price-item {
            text-align: center;
            flex: 1;
        }

        .price-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 0.5rem;
        }

        .price-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #feca57;
        }

        /* ========== MERCHANDISE FLUID ========== */
        #pricing {
    background: transparent; /* ATAU HAPUS PROPERTY BACKGROUND */
}

        .merchandise-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 3rem;
            margin-top: 4rem;
        }

        .merchandise-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(25px);
            border-radius: 32px;
            overflow: hidden;
            position: relative;
            transition: all 0.5s ease;
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
        }

        .merchandise-card.disabled {
            opacity: 0.6;
            pointer-events: none;
            cursor: not-allowed;
        }

        .merchandise-card.disabled::before {
            content: "STOK HABIS";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            background: rgba(255, 107, 107, 0.9);
            color: white;
            padding: 10px 30px;
            font-weight: bold;
            font-size: 1.5rem;
            border-radius: 5px;
            z-index: 10;
        }

        .merchandise-card:hover {
            transform: translateY(-15px);
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(102, 126, 234, 0.4);
            box-shadow: 0 30px 70px rgba(102, 126, 234, 0.3);
        }

        .merchandise-image {
            width: 100%;
            height: 280px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, 
                rgba(102, 126, 234, 0.3) 0%, 
                rgba(118, 75, 162, 0.3) 100%);
        }

        .merchandise-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .merchandise-card:hover .merchandise-image img {
            transform: scale(1.1);
        }

        .merchandise-content {
            padding: 2.5rem;
            text-align: center;
        }

        .merchandise-content h3 {
            font-size: 1.7rem;
            color: #fff;
            margin-bottom: 1.2rem;
            font-weight: 600;
        }

        .merchandise-price {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 1.5rem 0;
            line-height: 1;
        }

        .merchandise-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.05rem;
            line-height: 1.7;
            margin-bottom: 1.8rem;
        }

        .merchandise-stock {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.7rem 1.5rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .stock-available {
            background: rgba(37, 211, 102, 0.15);
            color: #fefefeff;
            border: 1px solid rgba(37, 211, 102, 0.3);
        }

        .stock-out {
            background: rgba(255, 107, 107, 0.15);
            color: #ff6b6b;
            border: 1px solid rgba(255, 107, 107, 0.3);
        }

        /* ========== ORDER STEPS FLUID ========== */
     #order-info {
    background: transparent; /* ATAU HAPUS PROPERTY BACKGROUND */
}

        .order-steps {
            max-width: 900px;
            margin: 0 auto;
            counter-reset: step;
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .order-steps li {
            position: relative;
            padding: 2.5rem 2.5rem 2.5rem 6.5rem;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border-radius: 28px;
            transition: all 0.4s ease;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .order-steps li::before {
            counter-increment: step;
            content: counter(step);
            position: absolute;
            left: 2rem;
            top: 50%;
            transform: translateY(-50%);
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 800;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            z-index: 2;
        }

        .order-steps li:hover {
            background: rgba(102, 126, 234, 0.15);
            transform: translateX(20px);
            border-color: rgba(102, 126, 234, 0.4);
        }

        .order-steps strong {
            color: #fff;
            font-size: 1.4rem;
            display: block;
            margin-bottom: 0.8rem;
        }

        /* ========== FORM FLUID ========== */
 #form-pemesanan {
    background: transparent; /* ATAU HAPUS PROPERTY BACKGROUND */
}

        .form-container {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(30px);
            padding: 4rem;
            border-radius: 40px;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.3);
            max-width: 800px;
            margin: 3rem auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
        }

        .form-container h3 {
            text-align: center;
            font-size: 2.8rem;
            margin-bottom: 3rem;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-group {
            margin-bottom: 2.5rem;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
            font-size: 1.2rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 1.4rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            font-size: 1.1rem;
            background: rgba(255, 255, 255, 0.07);
            color: #fff;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            backdrop-filter: blur(10px);
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 140px;
        }

        .total-price-display {
            padding: 2rem;
            background: rgba(102, 126, 234, 0.15);
            border: 2px solid rgba(102, 126, 234, 0.3);
            border-radius: 20px;
            font-size: 1.8rem;
            font-weight: 800;
            color: #feca57;
            text-align: center;
            margin: 1.5rem 0;
            backdrop-filter: blur(10px);
        }

        /* ========== CONTACT FLUID ========== */
     #contact {
    background: transparent; /* ATAU HAPUS PROPERTY BACKGROUND */
}
        .contact-links {
            display: flex;
            justify-content: center;
            gap: 2.5rem;
            flex-wrap: wrap;
            margin-top: 3rem;
        }

.contact-links a {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem 3rem;
    color: white;
    max-height: 600px;
    justify-content: center;
    font-size: 1.8rem; /* TAMBAHKAN INI - ukuran font */
}

.contact-links a svg {
    width: 50px !important;  /* TAMBAHKAN INI - ukuran icon WA */
    height: 50px !important;
}

.contact-links a i {
    font-size: 2.5rem; /* TAMBAHKAN INI - ukuran icon Instagram */
}

      

        .social-icons {
            display: flex;
            justify-content: center;
            gap: 2.5rem;
            margin-top: 4rem;
        }

        .social-icons a {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            transition: all 0.4s ease;
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
            color: white;
            text-decoration: none;
        }

        .social-icons a:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 60px rgba(102, 126, 234, 0.5);
        }

        /* ========== FOOTER FLUID ========== */
        footer {
           background: #19193aff; /* atau warna pilihanmu */
            text-align: center;
            padding: 3rem 1rem;
            border-top: 1px solid rgba(102, 126, 234, 0.2);
            backdrop-filter: blur(10px);
        }

        footer p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 0.5rem;
        }

        /* ========== LOADING & BACK TO TOP ========== */
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

        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            z-index: 999;
            backdrop-filter: blur(10px);
        }

        .back-to-top.show {
            opacity: 1;
            visibility: visible;
        }

        .back-to-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.6);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1024px) {
            .logo {
                font-size: 3    .5rem;
            }
            
            .hero h1 {
                font-size: 2.4rem;
            }
            
            .about-content {
                grid-template-columns: 1fr;
                gap: 3rem;
            }
        }

        @media (max-width: 768px) {
            .logo {
                font-size: 2.8rem;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            section h2 {
                font-size: 2.5rem;
            }
            
            .match-container,
            .merchandise-grid {
                grid-template-columns: 1fr;
            }
            
            .form-container {
                padding: 2.5rem;
            }
            
            .nav-links {
                position: fixed;
                top: 70px;
                left: 0;
                width: 100%;
                background: rgba(10, 10, 10, 0.98);
                backdrop-filter: blur(20px);
                flex-direction: column;
                padding: 2rem;
                gap: 1.5rem;
                transform: translateY(-100%);
                opacity: 0;
                visibility: hidden;
                transition: all 0.4s ease;
            }
            
            .nav-links.active {
                transform: translateY(0);
                opacity: 1;
                visibility: visible;
            }
            
            .menu-toggle {
                display: flex;
            }
        }

        @media (max-width: 480px) {
            .logo {
                font-size: 2.2rem;
            }
                
            .hero h1 {
                font-size: 1.8rem;
            }
            
            .hero-content {
                padding: 2rem;
            }
            
            .form-container {
                padding: 2rem 1.5rem;
            }
            
            .order-steps li {
                padding: 2rem 2rem 2rem 5rem;
            }
            
            .order-steps li::before {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
                left: 1.5rem;
            }
            
            .contact-links a {
                min-width: 100%;
                padding: 1.2rem 2rem;
            }
        }
select, option {
    background-color: #58585cff !important;
    color: white !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    padding: 10px !important;
    border-radius: 8px !important;
}

 select:focus {
    outline: none !important;
    border-color: #f2f2f4ff !important;
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
            <div class="nav-logo">
                <i class="fas fa-fire"></i>
             SMEKDA 1912
            </div>
            <ul class="nav-links">
                <li><a href="#about"><i class="fas fa-info-circle"></i> About</a></li>
                <li><a href="#jadwal-match"><i class="fas fa-calendar"></i> Jadwal</a></li>
                <li><a href="#pricing"><i class="fas fa-tshirt"></i> Merchandise</a></li>
                <li><a href="#form-pemesanan"><i class="fas fa-shopping-bag"></i> Order</a></li>
                <li><a href="#contact"><i class="fas fa-headset"></i> Contact</a></li>
            </ul>
            <div class="menu-toggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero">
        <div class="hero-content">
            <div class="logo">ULTRAS SMEKDA</div>
            <h1>Premium Merchandise & Match Tickets</h1>
            <p class="hero-tagline">Come With Heart, Sing With Pride</p>
            <button class="btn" onclick="document.getElementById('form-pemesanan').scrollIntoView({ behavior: 'smooth' });">
                <i class="fas fa-shopping-cart"></i> Order Now
            </button>
        </div>
    </header>

    <!-- About Section -->
    <section id="about" class="container">
        <h2><i class="fas fa-flag"></i> IDENTITAS SMEKDA</h2>
        <p class="section-subtitle">GAK SMEKDA GAK LIAR - LEBIH DARI SEKEDAR MERCHANDISE, INI ADALAH IDENTITAS KAMI</p>
        
        <div class="about-content">
            <div class="about-text">
                <h3><i class="fas fa-history"></i> TENTANG KAMI</h3>
                <p>Smekda bukan hanya sebuah nama, tetapi sebuah keluarga besar yang dipersatukan oleh semangat dan dedikasi terhadap tim kesayangan. Kami adalah suporter yang selalu hadir, mendukung dalam suka maupun duka.</p>
                <p>Setiap merchandise yang kami tawarkan adalah simbol kebanggaan dan identitas. Dengan mengenakan jersey Smekda, Anda bukan hanya mendukung tim, tetapi juga menjadi bagian dari keluarga besar yang solid dan penuh semangat.</p>
                <p><strong>GAK SMEKDA GAK LIAR</strong>     !</p>
            </div>
            <div class="about-image">
                <img src="./assets/smekda.jpg" alt="Smekda Community" 
                     onerror="this.onerror=null; this.parentElement.innerHTML='<div style=\'width: 100%; height: 400px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; display: flex; align-items: center; justify-content: center;\'><div style=\'text-align: center; color: white;\'><i class=\'fas fa-users\' style=\'font-size: 5rem; margin-bottom: 1rem; opacity: 0.8;\'></i><h3 style=\'font-size: 2rem; font-weight: 700;\'>ULTRAS SMEKDA</h3><p style=\'font-size: 1.2rem; opacity: 0.9;\'>Community Spirit</p></div></div>';">
            </div>
        </div>
    </section>

    <!-- JADWAL PERTANDINGAN -->
    <section id="jadwal-match" class="container">
        <div style="margin-top: 3rem; display: flex; gap: 2rem; flex-wrap: wrap;">
        <h2><i class="fas fa-calendar-check" style='margin-top: 5rem'></i> JADWAL PERTANDINGAN</h2>
    </div>
        <p class="section-subtitle">Jangan lewatkan pertandingan seru kami! Pesan tiket sekarang juga</p>
        
        <?php if (count($jadwal_list) > 0): ?>
        <div class="match-container">
            <?php foreach ($jadwal_list as $jadwal): ?>
            <div class="match-card">
                <span class="match-badge <?php echo $jadwal['badge_class']; ?>">
                    <i class="fas fa-fire"></i> <?php echo $jadwal['status_text']; ?>
                </span>
                
                <h3 class="match-title"><?php echo htmlspecialchars($jadwal['pertandingan']); ?></h3>
                
                <div class="match-info">
                    <div class="match-info-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span><?php echo date('l, d F Y', strtotime($jadwal['tanggal'])); ?></span>
                    </div>
                    <div class="match-info-item">
                        <i class="fas fa-clock"></i>
                        <span><?php echo date('H:i', strtotime($jadwal['waktu'])); ?> WIB</span>
                    </div>
                    <div class="match-info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($jadwal['lokasi']); ?></span>
                    </div>
                    <div class="match-info-item">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Total Tiket: <?php echo $jadwal['total_stok']; ?> tersedia</span>
                    </div>
                    <?php if ($jadwal['hari_menuju'] > 0): ?>
                    <div class="match-info-item">
                        <i class="fas fa-hourglass-half"></i>
                        <span><?php echo $jadwal['hari_menuju']; ?> hari menuju pertandingan</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Tampilkan detail tiket DENGAN STOK YANG BENAR -->
                <?php if (count($jadwal['tiket_list']) > 0): ?>
                <div style="margin-bottom: 1.5rem; background: rgba(255, 255, 255, 0.05); padding: 1rem; border-radius: 10px;">
                    <strong style="color: #feca57; display: block; margin-bottom: 0.5rem;">
                        <i class="fas fa-list"></i> Jenis Tiket Tersedia:
                    </strong>
                    <?php foreach ($jadwal['tiket_list'] as $tiket): 
                        $stok_tersedia = $tiket['stok_tersedia'];
                        $stok_awal = $tiket['stok_awal'];
                        $sudah_dipesan = $tiket['sudah_dipesan'];
                    ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                        <div>
                            <span><?php echo htmlspecialchars($tiket['jenis_tiket']); ?></span>
                            <small style="color: #aaa; font-size: 0.8rem; margin-left: 0.5rem;">
                                (Stok: <?php echo $stok_awal; ?>, Dipesan: <?php echo $sudah_dipesan; ?>)
                            </small>
                        </div>
                        <span style="color: #25d366; font-weight: 600;">
                            Rp<?php echo number_format($tiket['harga'], 0, ',', '.'); ?>
                            <small style="color: <?php echo $stok_tersedia > 0 ? '#25d366' : '#ff6b6b'; ?>; font-size: 0.8rem;">
                                (<?php echo $stok_tersedia; ?> tersedia)
                            </small>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($jadwal['total_stok'] > 0): ?>
                <div class="ticket-availability">
                    <i class="fas fa-check-circle"></i> Tiket Tersedia (<?php echo $jadwal['total_stok']; ?> tiket)
                </div>
                <a href="tiket.php?match_id=<?php echo $jadwal['id']; ?>" class="btn btn-ticket" style="width: 100%; text-align: center;">
                    <i class="fas fa-ticket-alt"></i> Pesan Tiket Sekarang
                </a>
                <?php else: ?>
                <div class="ticket-sold-out">
                    <i class="fas fa-times-circle"></i> Tiket Habis Terjual
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 3rem; background: rgba(255, 255, 255, 0.05); border-radius: 20px; margin-top: 2rem;">
            <i class="fas fa-calendar-times" style="font-size: 4rem; color: #667eea; margin-bottom: 1rem;"></i>
            <h3 style="color: #fff; margin-bottom: 1rem;">Belum Ada Jadwal Pertandingan</h3>
            <p style="color: #aaa;">
                <i class="fas fa-info-circle"></i> Jadwal pertandingan akan diumumkan segera. Stay tuned!
            </p>
        </div>
        <?php endif; ?>
    </section>

    <!-- Merchandise Section -->
    <section id="pricing" class="container">
        <h2><i class="fas fa-tshirt"></i> KOLEKSI MERCHANDISE</h2>
        <p class="section-subtitle">Pilihan terbaik untuk menunjukkan kebanggaan Anda</p>
        <div class="merchandise-grid">
            <?php foreach ($merchandise_list as $item): 
                $stok_tersedia = $item['stok_tersedia'];
                $is_stok_habis = $stok_tersedia <= 0;
                $css_class = $is_stok_habis ? 'disabled' : '';
            ?>
            <div class="merchandise-card <?php echo $css_class; ?>">
                <div class="merchandise-image">
                    <?php if (!empty($item['gambar']) && file_exists($item['gambar'])): ?>
                        <img src="<?php echo htmlspecialchars($item['gambar']); ?>" alt="<?php echo htmlspecialchars($item['nama_produk']); ?>"
                             onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIwIiBoZWlnaHQ9IjI1MCIgdmlld0JveD0iMCAwIDMyMCAyNTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMjAiIGhlaWdodD0iMjUwIiBmaWxsPSJ1cmwoI2dyYWRpZW50MCkiLz4KPGRlZnM+CjxsaW5lYXJHcmFkaWVudCBpZD0iZ3JhZGllbnQwIiB4MT0iMCIgeTE9IjAiIHgyPSIzMjAiIHkyPSIyNTAiIGdyYWRpZW50VW5pdHM9InVzZXJTcGFjZU9uVXNlIj4KPHN0b3Agc3RvcC1jb2xvcj0iIzY2N2VlYSIvPgo8c3RvcCBvZmZzZXQ9IjEiIHN0b3AtY29sb3I9IiM3NjRiYTIiLz4KPC9saW5lYXJHcmFkaWVudD4KPC9kZWZzPgo8dGV4dCB4PSI1MCUiIHk9IjUwJSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0id2hpdGUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZm9udC13ZWlnaHQ9ImJvbGQiPjx0c3BhbiB4PSI1MCUiIHk9IjQ1JSIgZmlsbD0id2hpdGUiPk1lcmNoYW5kaXNlPC90c3Bhbj48dHNwYW4geD0iNTAlIiB5PSI2NSUiIGZpbGw9IndoaXRlIiBmb250LXNpemU9IjEyIj48dHNwYW4gZHk9IjEuMmVtIj5JbWFnZSBQcmV2aWV3PC90c3Bhbj48L3RzcGFuPjwvdGV4dD4KPC9zdmc+';">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                            <div style="text-align: center;">
                                <i class="fas fa-<?php echo $item['icon'] ?? 'tshirt'; ?>" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.8;"></i>
                                <h3 style="font-size: 1.5rem; font-weight: 700;"><?php echo htmlspecialchars($item['nama_produk']); ?></h3>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="merchandise-content">
                    <h3><i class="fas fa-tag"></i> <?php echo htmlspecialchars($item['nama_produk']); ?></h3>
                    <div class="merchandise-price">
                        <i class="fas fa-money-bill-wave"></i> Rp<?php echo number_format($item['harga'], 0, ',', '.'); ?>
                    </div>
                    <p class="merchandise-description">
                        <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($item['deskripsi'] ?? 'Produk berkualitas tinggi dari Smekda'); ?>
                    </p>
                    
                    <?php if ($stok_tersedia > 0): ?>
                    <div class="merchandise-stock stock-available">
                        <i class="fas fa-check-circle"></i> Stok: <?php echo $stok_tersedia; ?> tersedia
                        <br><small style="font-size: 0.8rem; opacity: 0.8;">
                            <i class="fas fa-chart-line"></i> Terjual: <?php echo $item['terjual']; ?> unit
                        </small>
                    </div>
                    <?php else: ?>
                    <div class="merchandise-stock stock-out">
                        <i class="fas fa-times-circle"></i> Stok habis
                        <br><small style="font-size: 0.8rem; opacity: 0.8;">
                            <i class="fas fa-chart-line"></i> Total terjual: <?php echo $item['terjual']; ?> unit
                        </small>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 10px; font-size: 0.9rem; color: <?php 
                        echo $item['status'] == 'pre-order' ? '#feca57' : 
                               ($item['status'] == 'habis' ? '#ff6b6b' : '#25d366'); 
                    ?>;">
                        <i class="fas fa-info-circle"></i> Status: <?php echo strtoupper($item['status']); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Order Info Section -->
    <section id="order-info" class="container">
        <h2><i class="fas fa-info-circle"></i> Cara Pemesanan</h2>
        <p class="section-subtitle">Ikuti langkah mudah berikut untuk memesan merchandise:</p>
        <ol class="order-steps">
            <li>
                <strong><i class="fas fa-shopping-cart"></i> Pilih Produk</strong><br>
                Pilih produk yang tersedia di UltrasSmekda sesuai kebutuhan Anda
            </li>
            <li>
                <strong><i class="fas fa-edit"></i> Isi Form</strong><br>
                Isi form pemesanan dengan lengkap dan benar termasuk foto kartu pelajar
            </li>
            <li>
                <strong><i class="fas fa-user-check"></i> Konfirmasi Admin</strong><br>
                Tunggu konfirmasi dari admin via WhatsApp dalam 1x24 jam
            </li>
            <li>
                <strong><i class="fas fa-money-bill-wave"></i> Pembayaran</strong><br>
                Transfer pembayaran ke rekening yang diberikan dan kirim bukti transfer atau bisa pembayaran offline dengan membawa kartu pelajar
            </li>
            <li>
                <strong><i class="fas fa-truck"></i> Pengambilan</strong><br>
                Pengambilan produk di WARKOP SATAM sesuai jadwal yang ditentukan
            </li>
        </ol>
    </section>

    <!-- Form Pemesanan Section -->
    <section id="form-pemesanan" class="container">
        <div class="form-container">
            <h3><i class="fas fa-shopping-bag"></i> Form Pemesanan</h3>
            
            <!-- TAMPILKAN ERROR JIKA ADA -->
            <?php if (isset($_SESSION['error'])): ?>
            <div style="background: rgba(255, 107, 107, 0.1); border: 1px solid #ff6b6b; color: #ff6b6b; padding: 1rem; border-radius: 10px; margin-bottom: 2rem;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>
            
            <!-- TAMPILKAN SUKSES JIKA ADA -->
            <?php if (isset($_SESSION['success'])): ?>
            <div style="background: rgba(37, 211, 102, 0.1); border: 1px solid #25d366; color: #25d366; padding: 1rem; border-radius: 10px; margin-bottom: 2rem;">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
            <?php endif; ?>
                
            <form action="proses_pemesanan.php" method="POST" enctype="multipart/form-data" id="orderForm">
                <div class="form-group">
                    <label for="nama"><i class="fas fa-user"></i> Nama Lengkap *</label>
                    <input type="text" id="nama" name="nama" placeholder="Masukkan nama lengkap Anda" required>
                </div>

                <div class="form-group">
                    <label for="no_hp"><i class="fas fa-phone"></i> EMAIL*</label>
                    <input type="tel" id="no_hp" name="no_hp" placeholder="Contoh: ultrasmskeda@gmail.com" pattern="[0-9]{10,13}" required>
                    <small><i class="fas fa-info-circle"></i> Format: xxxxxxxx@gmail.com</small>
                </div>
            
                <div class="form-group">
                    <label for="produk"><i class="fas fa-box"></i> Pilih Produk *</label>
                    <select id="produk" name="produk" required onchange="updateProductStock()">
                        <option value="">-- Pilih Produk --</option>
                        <?php foreach ($merchandise_list as $item): 
                            $stok_tersedia = $item['stok_tersedia'];
                            $is_available = $stok_tersedia > 0;
                        ?>
                        <option value="<?php echo htmlspecialchars($item['nama_produk']); ?>" 
                                data-price="<?php echo $item['harga']; ?>"
                                data-stock="<?php echo $stok_tersedia; ?>"
                                <?php echo !$is_available ? 'disabled' : ''; ?>>
                            <?php echo htmlspecialchars($item['nama_produk']); ?> 
                            - Rp<?php echo number_format($item['harga'], 0, ',', '.'); ?>
                            <?php if ($is_available): ?>
                                (Stok: <?php echo $stok_tersedia; ?>)
                            <?php else: ?>
                                (STOK HABIS)
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="stockInfo" style="margin-top: 10px; display: none;">
                        <small style="color: #25d366;" id="availableStock">
                            <i class="fas fa-check-circle"></i> Stok tersedia: <span id="stockCount">0</span> unit
                        </small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="jumlah"><i class="fas fa-hashtag"></i> Jumlah *</label>
                    <input type="number" id="jumlah" name="jumlah" min="1" value="1" placeholder="Masukkan jumlah pesanan" required onchange="validateStock()">
                    <small><i class="fas fa-info-circle"></i> Maksimal <span id="maxStock">10</span> item per pesanan</small>
                    <div id="stockWarning" style="margin-top: 5px; display: none;">
                        <small style="color: #ff6b6b;" id="stockMessage">
                            <i class="fas fa-exclamation-triangle"></i> <span></span>
                        </small>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calculator"></i> Total Harga</label>
                    <div class="total-price-display">
                        Rp<span id="totalHarga">0</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="kartu_pelajar"><i class="fas fa-id-card"></i> Foto Kartu Pelajar *</label>
                    <input type="file" 
                           id="kartu_pelajar" 
                           name="kartu_pelajar" 
                           accept="image/*" 
                           required
                           onchange="previewImage(event)">
                    <small>
                        <i class="fas fa-info-circle"></i> Upload foto kartu pelajar yang masih berlaku (Max 5MB, Format: JPG/PNG/GIF)
                    </small>
                    <div id="imagePreview" style="display: none; margin-top: 10px;">
                        <img id="preview" src="" alt="Preview" style="max-width: 200px; border-radius: 10px;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="catatan"><i class="fas fa-comment"></i> Catatan (Opsional)</label>
                    <textarea id="catatan" name="catatan" placeholder="Tambahkan catatan untuk pesanan Anda (ukuran, warna, dll)"></textarea>
                </div>

                <button type="submit" class="btn" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Pesan Sekarang
                </button>
            </form>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="container">
        <h2><i class="fas fa-headset"></i> Hubungi Kami</h2>
        <p class="section-subtitle">Punya pertanyaan? Kami siap membantu Anda</p>
        <div class='contact-links a'>
            <a href="https://wa.me/6281235033165" >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-whatsapp" viewBox="0 0 16 16">
  <path d="M13.601 2.326A7.85 7.85 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.71 1.916.81 2.049c.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232"/>
</svg>
            </a>
            <a href="https://www.instagram.com/ultrassmekda/">
                <i class="fas fa-envelope"></i> Instagram
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <p>&copy; 2025 Ultras Smekda. All Rights Reserved.</p>
        <p style="margin-top: 0.5rem; color: #ccd1e7ff;">
            <i class="fas fa-heart" style="color: #ff6b6b;"></i> Made with passion for the community
        </p>
        <div style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 2rem; flex-wrap: wrap;">
            <a href="#" style="color: rgba(255, 255, 255, 0.7); text-decoration: none; font-size: 0.9rem;">
                <i class="fas fa-shield-alt"></i> Privacy Policy
            </a>
            <a href="#" style="color: rgba(255, 255, 255, 0.7); text-decoration: none; font-size: 0.9rem;">
                <i class="fas fa-file-contract"></i> Terms of Service
            </a>
            <a href="#" style="color: rgba(255, 255, 255, 0.7); text-decoration: none; font-size: 0.9rem;">
                <i class="fas fa-question-circle"></i> FAQ
            </a>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <div class="back-to-top">
        <i class="fas fa-arrow-up"></i>
    </div>

    <script>
        // Mobile Menu Toggle
        const menuToggle = document.querySelector('.menu-toggle');
        const navLinks = document.querySelector('.nav-links');

        menuToggle.addEventListener('click', () => {
            menuToggle.classList.toggle('active');
            navLinks.classList.toggle('active');
        });

        // Close menu when clicking on a link
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                menuToggle.classList.remove('active');
                navLinks.classList.remove('active');
            });
        });

        // Navbar scroll effect
        const navbar = document.querySelector('.navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 100) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth scroll for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const target = document.querySelector(targetId);
                if (target) {
                    const offset = 80;
                    const targetPosition = target.offsetTop - offset;
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Back to Top Button
        const backToTop = document.querySelector('.back-to-top');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });

        backToTop.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Image Preview Function
        function previewImage(event) {
            const preview = document.getElementById('preview');
            const previewContainer = document.getElementById('imagePreview');
            const file = event.target.files[0];
            
            if (file) {
                // Validasi ukuran file (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Ukuran file terlalu besar! Maksimal 5MB');
                    event.target.value = '';
                    previewContainer.style.display = 'none';
                    return;
                }
                
                // Validasi tipe file
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Hanya file gambar (JPG, PNG, GIF) yang diperbolehkan!');
                    event.target.value = '';
                    previewContainer.style.display = 'none';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                previewContainer.style.display = 'none';
            }
        }

        // Fungsi untuk memperbarui informasi stok
        function updateProductStock() {
            const produkSelect = document.getElementById('produk');
            const selectedOption = produkSelect.options[produkSelect.selectedIndex];
            const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
            const stockInfo = document.getElementById('stockInfo');
            const stockCount = document.getElementById('stockCount');
            const maxStock = document.getElementById('maxStock');
            
            if (stock > 0) {
                stockInfo.style.display = 'block';
                stockCount.textContent = stock;
                maxStock.textContent = Math.min(stock, 10);
                document.getElementById('jumlah').max = Math.min(stock, 10);
                document.getElementById('jumlah').value = 1;
            } else {
                stockInfo.style.display = 'none';
                maxStock.textContent = '0';
                document.getElementById('jumlah').max = 0;
                document.getElementById('jumlah').value = 0;
            }
            
            updateTotalHarga();
            validateStock();
        }

        // Validasi stok saat mengubah jumlah
        function validateStock() {
            const produkSelect = document.getElementById('produk');
            const selectedOption = produkSelect.options[produkSelect.selectedIndex];
            const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
            const jumlahInput = document.getElementById('jumlah');
            const jumlah = parseInt(jumlahInput.value) || 0;
            const stockWarning = document.getElementById('stockWarning');
            const stockMessage = document.getElementById('stockMessage');
            const submitBtn = document.getElementById('submitBtn');
            
            if (stock <= 0) {
                stockWarning.style.display = 'block';
                stockMessage.querySelector('span').textContent = 'Produk ini stok habis!';
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-disabled');
                return;
            }
            
            if (jumlah > stock) {
                stockWarning.style.display = 'block';
                stockMessage.querySelector('span').textContent = `Jumlah melebihi stok! Stok tersedia: ${stock} unit`;
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-disabled');
            } else if (jumlah <= 0) {
                stockWarning.style.display = 'block';
                stockMessage.querySelector('span').textContent = 'Jumlah harus minimal 1 unit';
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-disabled');
            } else if (jumlah > 10) {
                stockWarning.style.display = 'block';
                stockMessage.querySelector('span').textContent = 'Maksimal 10 item per pesanan';
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-disabled');
            } else {
                stockWarning.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-disabled');
            }
        }

        // Update total harga
        function updateTotalHarga() {
            const produkSelect = document.getElementById('produk');
            const selectedOption = produkSelect.options[produkSelect.selectedIndex];
            const price = parseInt(selectedOption.getAttribute('data-price')) || 0;
            const jumlahInput = document.getElementById('jumlah');
            const jumlah = parseInt(jumlahInput.value) || 0;
            const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
            
            // Batasi jumlah sesuai stok
            if (jumlah > stock) {
                jumlahInput.value = stock;
                jumlahInput.dispatchEvent(new Event('input'));
                return;
            }
            
            const total = price * jumlah;
            document.getElementById('totalHarga').textContent = total.toLocaleString('id-ID');
        }

        // Event listeners untuk form
        document.getElementById('produk').addEventListener('change', updateProductStock);
        document.getElementById('jumlah').addEventListener('input', function() {
            updateTotalHarga();
            validateStock();
        });

        // Form validation
        const orderForm = document.getElementById('orderForm');
        orderForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const produkSelect = document.getElementById('produk');
            const selectedOption = produkSelect.options[produkSelect.selectedIndex];
            const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
            const jumlah = parseInt(document.getElementById('jumlah').value) || 0;
            const noHp = document.getElementById('no_hp').value;
            const phoneRegex = /^[0-9]{10,13}$/;
            const fileInput = document.getElementById('kartu_pelajar');
            
            // Validasi produk
            if (produkSelect.value === '') {
                alert('Silakan pilih produk terlebih dahulu!');
                return false;
            }
            
            // Validasi stok
            if (stock <= 0) {
                alert('Maaf, produk yang Anda pilih stok habis!');
                return false;
            }
            
            if (jumlah > stock) {
                alert(`Jumlah pesanan melebihi stok yang tersedia! Stok tersedia: ${stock} unit`);
                return false;
            }
            
            if (jumlah <= 0) {
                alert('Jumlah pesanan harus minimal 1 unit!');
                return false;
            }
            
            if (jumlah > 10) {
                alert('Maksimal 10 item per pesanan!');
                return false;
            }
            
            // Validasi nomor HP
            if (!phoneRegex.test(noHp)) {
                alert('Nomor HP harus berisi 10-13 digit angka!');
                return false;
            }
            
            // Validasi file upload
            if (fileInput.files.length === 0) {
                alert('Silakan upload foto kartu pelajar!');
                return false;
            }

            // Show loading
            document.querySelector('.loading').classList.add('active');
            
            // Submit form
            setTimeout(() => {
                this.submit();
            }, 1000);
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateProductStock();
            updateTotalHarga();
            validateStock();
        });

        // Intersection Observer untuk animasi
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animated');
                }
            });
        }, observerOptions);

        // Observe cards
        document.querySelectorAll('.merchandise-card, .match-card').forEach(card => {
            observer.observe(card);
        });

        // Parallax effect for hero
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero');
            if (hero) {
                const rate = scrolled * -0.5;
                hero.style.transform = `translateY(${rate}px)`;
            }
        });
    </script>
</body>
</html>