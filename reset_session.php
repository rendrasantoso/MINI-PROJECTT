<?php
session_start();

// Hapus semua session pesanan
unset($_SESSION['pesanan_id']);
unset($_SESSION['order_id_midtrans']);
unset($_SESSION['snap_token']);
unset($_SESSION['total_harga']);
unset($_SESSION['pesanan_data']);
unset($_SESSION['error']);
unset($_SESSION['debug_post']);

echo "<h2>✅ Session Berhasil Direset!</h2>";
echo "<p>Semua data session pesanan telah dihapus.</p>";
echo "<a href='index.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Kembali ke Beranda</a>";
?>