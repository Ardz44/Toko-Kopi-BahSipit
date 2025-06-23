<?php
// pages/order_success.php
session_start();

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Ambil order ID dari parameter
$order_id = $_GET['order_id'] ?? '';

if (empty($order_id)) {
    header('Location: dashboard.php?error=invalid_order');
    exit;
}

// Ambil detail order
$order_query = "SELECT o.*, u.username 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.order_id = :order_id AND o.user_id = :user_id";
$order_stmt = $db->prepare($order_query);
$order_stmt->bindParam(':order_id', $order_id);
$order_stmt->bindParam(':user_id', $_SESSION['user_id']);
$order_stmt->execute();

$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: dashboard.php?error=order_not_found');
    exit;
}

// Ambil detail item order
$items_query = "SELECT oi.*, p.name as product_name 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = :order_id";
$items_stmt = $db->prepare($items_query);
$items_stmt->bindParam(':order_id', $order['id']);
$items_stmt->execute();

$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pesanan - Kopi Bah Sipit</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <div id="menu-btn" class="fas fa-bars"></div>
        <a href="#" class="logo">Kopi BahSipit <i class="fas fa-mug-hot"></i></a>
        <nav class="navbar">
            <a href="../index.php">home</a>
            <a href="../dashboard.php">dashboard</a>
            <a href="../logout.php">logout</a>
        </nav>
        <span class="btn">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
    </header>

    <section class="book" style="padding-top: 12rem;">
        <div class="container" style="max-width: 800px; margin: 0 auto; text-align: center;">
            
            <?php if ($order['payment_status'] == 'paid'): ?>
                <!-- Pembayaran Berhasil -->
                <div class="success-message" style="background: #d4edda; color: #155724; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem;">
                    <i class="fas fa-check-circle" style="font-size: 4rem; color: #28a745; margin-bottom: 1rem;"></i>
                    <h1 style="color: #28a745;">Pembayaran Berhasil!</h1>
                    <p style="font-size: 1.2rem;">Terima kasih atas pesanan Anda. Pembayaran telah berhasil diproses.</p>
                </div>
            
            <?php elseif ($order['payment_status'] == 'pending'): ?>
                <!-- Pembayaran Pending -->
                <div class="pending-message" style="background: #fff3cd; color: #856404; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem;">
                    <i class="fas fa-clock" style="font-size: 4rem; color: #ffc107; margin-bottom: 1rem;"></i>
                    <h1 style="color: #ffc107;">Menunggu Pembayaran</h1>
                    <p style="font-size: 1.2rem;">Pesanan Anda sedang menunggu pembayaran. Silakan selesaikan pembayaran Anda.</p>
                </div>
            
            <?php else: ?>
                <!-- Status Lainnya -->
                <div class="info-message" style="background: #f8f9fa; color: #6c757d; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem;">
                    <i class="fas fa-info-circle" style="font-size: 4rem; color: #6c757d; margin-bottom: 1rem;"></i>
                    <h1>Status Pesanan</h1>
                    <p style="font-size: 1.2rem;">Status pembayaran: <?php echo ucfirst($order['payment_status']); ?></p>
                </div>
            <?php endif; ?>

            <!-- Detail Pesanan -->
            <div class="order-details" style="background: #f9f9f9; padding: 2rem; border-radius: 1rem; text-align: left;">
                <h2 style="color: var(--main-color); margin-bottom: 1.5rem; text-align: center;">Detail Pesanan</h2>
                
                <div class="order-info" style="margin-bottom: 2rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <strong>Order ID:</strong><br>
                            <span style="color: var(--main-color);"><?php echo htmlspecialchars($order['order_id']); ?></span>
                        </div>
                        <div>
                            <strong>Tanggal:</strong><br>
                            <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <strong>Nama Pelanggan:</strong><br>
                            <?php echo htmlspecialchars($order['customer_name']); ?>
                        </div>
                        <div>
                            <strong>Email:</strong><br>
                            <?php echo htmlspecialchars($order['customer_email']); ?>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <strong>Alamat:</strong><br>
                        <?php echo htmlspecialchars($order['customer_address']); ?>
                    </div>
                </div>

                <!-- Item Pesanan -->
                <h3 style="color: var(--main-color); margin-bottom: 1rem;">Item Pesanan</h3>
                <div class="order-items">
                    <?php foreach ($order_items as $item): ?>
                    <div class="order-item" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid #ddd;">
                        <div>
                            <h4 style="color: var(--main-color); margin: 0;"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                            <p style="color: #666; margin: 0;">Qty: <?php echo $item['quantity']; ?> x $<?php echo number_format($item['price'], 2); ?></p>
                        </div>
                        <div style="font-weight: bold; color: var(--main-color);">
                            $<?php echo number_format($item['quantity'] * $item['price'], 2); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="total" style="text-align: right; margin-top: 1rem; padding-top: 1rem; border-top: 2px solid var(--main-color);">
                        <h3 style="color: var(--main-color);">Total: $<?php echo number_format($order['total_amount'], 2); ?></h3>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons" style="margin-top: 2rem;">
                <a href="../dashboard.php" class="btn" style="margin-right: 1rem;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>
                
                <?php if ($order['payment_status'] == 'pending'): ?>
                <a href="../checkout.php" class="btn" style="background: #ffc107;">
                    <i class="fas fa-credit-card"></i> Bayar Sekarang
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script src="../assets/js/script.js"></script>
</body>
</html>