<?php
require_once __DIR__ . '/config/config.php';

class MidtransConfig {
    public static function getSnapToken($order_id, $gross_amount, $customer_details, $item_details) {
        error_log("🔧 MidtransConfig::getSnapToken called");
        
        // Validasi amount
        if ($gross_amount < 1000) {
            error_log("❌ Amount terlalu kecil: $gross_amount");
            return null;
        }
        
        $params = [
            'transaction_details' => [
                'order_id' => $order_id,
                'gross_amount' => (int)$gross_amount,
            ],
            'customer_details' => $customer_details,
            'item_details' => $item_details,
            'callbacks' => [
                'finish' => BASE_URL . '/payment-success.php',
                'error' => BASE_URL . '/payment-error.php',
                'pending' => BASE_URL . '/payment-pending.php'
            ]
        ];
        
        error_log("📦 Midtrans Params: " . json_encode($params));
        
        try {
            // Cek apakah Midtrans ready
            if (!class_exists('Midtrans\Snap')) {
                error_log("❌ Midtrans\Snap class tidak ditemukan");
                return null;
            }
            
            if (empty(Midtrans\Config::$serverKey)) {
                error_log("❌ Server Key kosong");
                return null;
            }
            
            error_log("🔄 Memanggil Snap::getSnapToken...");
            $snapToken = Midtrans\Snap::getSnapToken($params);
            
            if ($snapToken) {
                error_log("✅ Snap Token berhasil dibuat");
                return $snapToken;
            } else {
                error_log("❌ Snap Token null");
                return null;
            }
            
        } catch (Exception $e) {
            error_log("❌ Midtrans Exception: " . $e->getMessage());
            return null;
        }
    }
}
?>