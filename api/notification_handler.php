<?php
// api/notification_handler.php

// Log semua incoming request untuk debugging
error_log('Midtrans notification received: ' . file_get_contents('php://input'));

// Include dependencies
require_once '../config/database.php';
require_once '../vendor/autoload.php';

// Konfigurasi Midtrans
\Midtrans\Config::$serverKey = 'SB-Mid-server-Wk-DogEWGSOYBwBeECNC9cNi';
\Midtrans\Config::$isProduction = false; // Sandbox mode
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;

try {
    // Terima dan decode notifikasi dari Midtrans
    $notif = new \Midtrans\Notification();
    
    // Extract data dari notifikasi
    $order_id = $notif->order_id;
    $transaction_status = $notif->transaction_status;
    $fraud_status = $notif->fraud_status ?? '';
    $payment_type = $notif->payment_type;
    $transaction_time = $notif->transaction_time;
    $gross_amount = $notif->gross_amount;
    
    // Log untuk debugging
    error_log("Notification received - Order ID: $order_id, Status: $transaction_status, Fraud: $fraud_status");
    
    // Connect ke database
    $database = new Database();
    $db = $database->getConnection();
    
    // Cari order berdasarkan order_id
    $query = "SELECT * FROM orders WHERE order_id = :order_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->execute();
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        error_log("Order not found: $order_id");
        http_response_code(404);
        echo "Order not found";
        exit;
    }
    
    // Tentukan payment status berdasarkan transaction_status dan fraud_status
    $payment_status = 'pending';
    
    if ($transaction_status == 'capture') {
        if ($fraud_status == 'challenge') {
            // Set payment status dalam merchant's database menjadi 'challenge'
            $payment_status = 'pending';
        } else if ($fraud_status == 'accept') {
            // Set payment status dalam merchant's database menjadi 'success'
            $payment_status = 'paid';
        }
    } else if ($transaction_status == 'settlement') {
        // Transaction sukses
        $payment_status = 'paid';
    } else if ($transaction_status == 'pending') {
        // Menunggu pembayaran
        $payment_status = 'pending';
    } else if ($transaction_status == 'deny') {
        // Pembayaran ditolak
        $payment_status = 'failed';
    } else if ($transaction_status == 'expire') {
        // Pembayaran kadaluarsa
        $payment_status = 'expired';
    } else if ($transaction_status == 'cancel') {
        // Pembayaran dibatalkan
        $payment_status = 'cancelled';
    }
    
    // Update status order di database
    $update_query = "UPDATE orders SET 
                     payment_status = :payment_status,
                     transaction_status = :transaction_status,
                     payment_type = :payment_type,
                     transaction_time = :transaction_time,
                     updated_at = NOW()
                     WHERE order_id = :order_id";
    
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':payment_status', $payment_status);
    $update_stmt->bindParam(':transaction_status', $transaction_status);
    $update_stmt->bindParam(':payment_type', $payment_type);
    $update_stmt->bindParam(':transaction_time', $transaction_time);
    $update_stmt->bindParam(':order_id', $order_id);
    
    if ($update_stmt->execute()) {
        error_log("Order $order_id status updated to: $payment_status");
        
        // Jika pembayaran berhasil, bisa tambahkan logic tambahan
        // Misalnya: kirim email konfirmasi, update stok, dll
        if ($payment_status == 'paid') {
            // Logic tambahan untuk pembayaran sukses
            error_log("Payment successful for order: $order_id");
            
            // Contoh: Update stok produk (opsional)
            // updateProductStock($db, $order['id']);
        }
        
        // Response sukses ke Midtrans
        http_response_code(200);
        echo "OK";
    } else {
        error_log("Failed to update order status for: $order_id");
        http_response_code(500);
        echo "Database update failed";
    }
    
} catch (Exception $e) {
    error_log('Notification handler error: ' . $e->getMessage());
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}

// Function untuk update stok produk (opsional)
function updateProductStock($db, $order_id) {
    try {
        // Ambil semua item dari order
        $items_query = "SELECT product_id, quantity FROM order_items WHERE order_id = :order_id";
        $items_stmt = $db->prepare($items_query);
        $items_stmt->bindParam(':order_id', $order_id);
        $items_stmt->execute();
        
        // Update stok untuk setiap produk
        while ($item = $items_stmt->fetch(PDO::FETCH_ASSOC)) {
            $stock_query = "UPDATE products SET stock = stock - :quantity WHERE id = :product_id AND stock >= :quantity";
            $stock_stmt = $db->prepare($stock_query);
            $stock_stmt->bindParam(':quantity', $item['quantity']);
            $stock_stmt->bindParam(':product_id', $item['product_id']);
            $stock_stmt->execute();
        }
        
        error_log("Stock updated for order: $order_id");
    } catch (Exception $e) {
        error_log("Failed to update stock for order $order_id: " . $e->getMessage());
    }
}
?>