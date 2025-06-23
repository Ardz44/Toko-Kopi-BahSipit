<?php
// api/process_payment.php
session_start();
header('Content-Type: application/json');

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Pastikan method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Include dependencies
require_once '../config/database.php';
require_once '../vendor/autoload.php';

// Konfigurasi Midtrans
\Midtrans\Config::$serverKey = 'SB-Mid-server-Wk-DogEWGSOYBwBeECNC9cNi';
\Midtrans\Config::$isProduction = false; // Sandbox mode
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;

try {
    // Ambil data dari request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validasi input
    $required_fields = ['customer_name', 'customer_email', 'customer_address', 'total_amount'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Field $field is required");
        }
    }
    
    // Pastikan keranjang tidak kosong
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        throw new Exception('Cart is empty');
    }
    
    // Connect ke database
    $database = new Database();
    $db = $database->getConnection();
    
    // Generate order ID unik
    $order_id = 'KOPI-' . time() . '-' . $_SESSION['user_id'];
    
    // Ambil detail produk dari keranjang
    $cart_items = [];
    $calculated_total = 0;
    $product_ids = array_keys($_SESSION['cart']);
    
    if (!empty($product_ids)) {
        $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
        $products_query = "SELECT * FROM products WHERE id IN ($placeholders)";
        $products_stmt = $db->prepare($products_query);
        $products_stmt->execute($product_ids);
        
        while ($product = $products_stmt->fetch(PDO::FETCH_ASSOC)) {
            $quantity = $_SESSION['cart'][$product['id']];
            $subtotal = $product['price'] * $quantity;
            
            $cart_items[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'subtotal' => $subtotal
            ];
            
            $calculated_total += $subtotal;
        }
    }
    
    // Validasi total amount
    if (abs($calculated_total - $input['total_amount']) > 0.01) {
        throw new Exception('Total amount mismatch');
    }
    
    // Mulai transaksi database
    $db->beginTransaction();
    
    try {
        // Simpan order ke database dengan kolom tambahan untuk Midtrans
        $order_query = "INSERT INTO orders (user_id, order_id, total_amount, payment_status, customer_name, customer_email, customer_address, transaction_status, payment_type, created_at) 
                       VALUES (:user_id, :order_id, :total_amount, 'pending', :customer_name, :customer_email, :customer_address, 'pending', NULL, NOW())";
        $order_stmt = $db->prepare($order_query);
        $order_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $order_stmt->bindParam(':order_id', $order_id);
        $order_stmt->bindParam(':total_amount', $calculated_total);
        $order_stmt->bindParam(':customer_name', $input['customer_name']);
        $order_stmt->bindParam(':customer_email', $input['customer_email']);
        $order_stmt->bindParam(':customer_address', $input['customer_address']);
        $order_stmt->execute();
        
        $db_order_id = $db->lastInsertId();
        
        // Simpan order items
        $item_query = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (:order_id, :product_id, :quantity, :price)";
        $item_stmt = $db->prepare($item_query);
        
        foreach ($cart_items as $item) {
            $item_stmt->bindParam(':order_id', $db_order_id);
            $item_stmt->bindParam(':product_id', $item['id']);
            $item_stmt->bindParam(':quantity', $item['quantity']);
            $item_stmt->bindParam(':price', $item['price']);
            $item_stmt->execute();
        }
        
        // Commit transaksi database
        $db->commit();
        
        // Setup parameter untuk Midtrans
        $transaction_details = [
            'order_id' => $order_id,
            'gross_amount' => (int)($calculated_total) // Midtrans dalam Rupiah, tidak perlu dikali 100
        ];
        
        // Setup item details untuk Midtrans
        $item_details = [];
        foreach ($cart_items as $item) {
            $item_details[] = [
                'id' => $item['id'],
                'price' => (int)($item['price']), // Dalam Rupiah
                'quantity' => $item['quantity'],
                'name' => $item['name']
            ];
        }
        
        // Setup customer details
        $customer_details = [
            'first_name' => $input['customer_name'],
            'email' => $input['customer_email'],
            'phone' => '081234567890', // Default phone, bisa dimodifikasi jika ada input phone
            'billing_address' => [
                'first_name' => $input['customer_name'],
                'email' => $input['customer_email'],
                'phone' => '081234567890',
                'address' => $input['customer_address'],
                'city' => 'Jakarta',
                'postal_code' => '12345',
                'country_code' => 'IDN'
            ],
            'shipping_address' => [
                'first_name' => $input['customer_name'],
                'email' => $input['customer_email'],
                'phone' => '081234567890',
                'address' => $input['customer_address'],
                'city' => 'Jakarta',
                'postal_code' => '12345',
                'country_code' => 'IDN'
            ]
        ];
        
        // Parameter lengkap untuk Midtrans
        $params = [
            'transaction_details' => $transaction_details,
            'item_details' => $item_details,
            'customer_details' => $customer_details,
            'enabled_payments' => [
                'credit_card', 
                'bca_va', 
                'bni_va', 
                'bri_va', 
                'echannel', 
                'permata_va', 
                'gopay', 
                'shopeepay',
                'other_qris'
            ],
            'vtweb' => [
                'finish_redirect_url' => 'https://yourdomain.com/pages/order_success.php?order_id=' . $order_id,
                'unfinish_redirect_url' => 'https://yourdomain.com/checkout.php',
                'error_redirect_url' => 'https://yourdomain.com/checkout.php?error=payment_error'
            ],
            'custom_expiry' => [
                'order_time' => date('Y-m-d H:i:s O'),
                'expiry_duration' => 60, // 60 menit
                'unit' => 'minute'
            ]
        ];
        
        // Dapatkan Snap token dari Midtrans
        $snapToken = \Midtrans\Snap::getSnapToken($params);
        
        // Kosongkan keranjang setelah berhasil membuat order
        unset($_SESSION['cart']);
        
        // Return success response dengan token
        echo json_encode([
            'status' => 'success',
            'token' => $snapToken,
            'order_id' => $order_id,
            'message' => 'Payment token generated successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaksi database jika ada error
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log error untuk debugging
    error_log('Payment processing error: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>