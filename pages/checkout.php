<?php
// checkout.php
session_start();

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Ambil data user
$user_query = "SELECT * FROM users WHERE id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Pastikan keranjang tidak kosong
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: dashboard.php?error=cart_empty');
    exit;
}

// Ambil detail produk dari keranjang
$cart_items = [];
$total_amount = 0;
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
        
        $total_amount += $subtotal;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Kopi Bah Sipit</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Midtrans Snap.js -->
    <script type="text/javascript"
            src="https://app.sandbox.midtrans.com/snap/snap.js"
            data-client-key="SB-Mid-client-Sw44LEsnY1HEHPK7"></script>
    <style>
        .checkout-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }
        .order-summary, .customer-form {
            background: #fff;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        .order-item:last-child {
            border-bottom: none;
        }
        .total-section {
            background: linear-gradient(135deg, var(--main-color), #ff6b35);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            text-align: center;
            margin-top: 1rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: var(--main-color);
        }
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            display: none;
        }
    </style>
</head>
<body>
    <header class="header">
        <div id="menu-btn" class="fas fa-bars"></div>
        <a href="#" class="logo">Kopi BahSipit <i class="fas fa-mug-hot"></i></a>
        <nav class="navbar">
            <a href="index.php">home</a>
            <a href="dashboard.php">dashboard</a>
            <a href="logout.php">logout</a>
        </nav>
        <span class="btn">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
    </header>

    <section class="book" style="padding-top: 12rem;">
        <h1 class="heading">checkout <span>pembayaran</span></h1>
        
        <div class="checkout-container">
            <!-- Error Message -->
            <div id="error-message" class="error-message"></div>
            
            <!-- Ringkasan Pesanan -->
            <div class="order-summary">
                <h3 style="color: var(--main-color); margin-bottom: 1.5rem;">
                    <i class="fas fa-shopping-cart"></i> Ringkasan Pesanan
                </h3>
                
                <?php foreach ($cart_items as $item): ?>
                <div class="order-item">
                    <div>
                        <h4 style="color: var(--main-color); margin: 0 0 0.5rem 0;">
                            <?php echo htmlspecialchars($item['name']); ?>
                        </h4>
                        <p style="color: #666; margin: 0;">
                            Qty: <?php echo $item['quantity']; ?> × 
                            Rp <?php echo number_format($item['price'], 0, ',', '.'); ?>
                        </p>
                    </div>
                    <div style="font-weight: bold; color: var(--main-color);">
                        Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="total-section">
                    <h3 style="margin: 0; font-size: 1.8rem;">
                        <i class="fas fa-calculator"></i> 
                        Total: Rp <?php echo number_format($total_amount, 0, ',', '.'); ?>
                    </h3>
                </div>
            </div>

            <!-- Form Detail Pelanggan -->
            <div class="customer-form">
                <h3 style="color: var(--main-color); margin-bottom: 1.5rem;">
                    <i class="fas fa-user"></i> Detail Pelanggan
                </h3>
                
                <form id="checkout-form">
                    <div class="form-group">
                        <label for="customer_name">
                            <i class="fas fa-user"></i> Nama Lengkap *
                        </label>
                        <input type="text" id="customer_name" name="customer_name" 
                               value="<?php echo htmlspecialchars($user['username']); ?>" 
                               placeholder="Masukkan nama lengkap" class="box" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customer_email">
                            <i class="fas fa-envelope"></i> Email *
                        </label>
                        <input type="email" id="customer_email" name="customer_email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                               placeholder="contoh@email.com" class="box" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customer_phone">
                            <i class="fas fa-phone"></i> Nomor Telepon
                        </label>
                        <input type="tel" id="customer_phone" name="customer_phone" 
                               placeholder="08xxxxxxxxxx" class="box">
                    </div>
                    
                    <div class="form-group">
                        <label for="customer_address">
                            <i class="fas fa-map-marker-alt"></i> Alamat Lengkap *
                        </label>
                        <textarea id="customer_address" name="customer_address" 
                                  placeholder="Masukkan alamat lengkap untuk pengiriman" 
                                  class="box" rows="4" required></textarea>
                    </div>
                    
                    <button type="button" id="pay-button" class="btn" style="width: 100%; text-align: center; font-size: 1.8rem; padding: 1.5rem;">
                        <i class="fas fa-credit-card"></i> 
                        Bayar Sekarang - Rp <?php echo number_format($total_amount, 0, ',', '.'); ?>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <script>
        // Fungsi untuk menampilkan error message
        function showError(message) {
            const errorDiv = document.getElementById('error-message');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            
            // Scroll ke atas untuk melihat error
            window.scrollTo({top: 0, behavior: 'smooth'});
            
            // Hide error after 5 seconds
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }

        // Fungsi untuk validasi form
        function validateForm() {
            const name = document.getElementById('customer_name').value.trim();
            const email = document.getElementById('customer_email').value.trim();
            const address = document.getElementById('customer_address').value.trim();
            
            if (!name) {
                showError('Nama lengkap harus diisi!');
                return false;
            }
            
            if (!email) {
                showError('Email harus diisi!');
                return false;
            }
            
            // Validasi format email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showError('Format email tidak valid!');
                return false;
            }
            
            if (!address) {
                showError('Alamat lengkap harus diisi!');
                return false;
            }
            
            return true;
        }

        // Event listener untuk tombol bayar
        document.getElementById('pay-button').onclick = function (event) {
            event.preventDefault();
            
            // Validasi form
            if (!validateForm()) {
                return;
            }
            
            // Disable button dan ubah text
            const payButton = this;
            payButton.disabled = true;
            payButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses Pembayaran...';
            payButton.classList.add('loading');
            
            // Ambil data form
            const formData = {
                customer_name: document.getElementById('customer_name').value.trim(),
                customer_email: document.getElementById('customer_email').value.trim(),
                customer_address: document.getElementById('customer_address').value.trim(),
                customer_phone: document.getElementById('customer_phone').value.trim(),
                total_amount: <?php echo $total_amount; ?>
            };
            
            // Kirim data ke backend
            fetch('api/process_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    // Jalankan Midtrans Snap
                    snap.pay(data.token, {
                        onSuccess: function (result) {
                            console.log('Payment success:', result);
                            alert('Pembayaran berhasil! Terima kasih atas pesanan Anda.');
                            window.location.href = 'pages/order_success.php?order_id=' + data.order_id;
                        },
                        onPending: function (result) {
                            console.log('Payment pending:', result);
                            alert('Pembayaran sedang diproses. Silakan selesaikan pembayaran Anda.');
                            window.location.href = 'pages/order_success.php?order_id=' + data.order_id;
                        },
                        onError: function (result) {
                            console.log('Payment error:', result);
                            showError('Pembayaran gagal. Silakan coba lagi.');
                            resetPayButton();
                        },
                        onClose: function () {
                            console.log('Payment popup closed without completing payment');
                            resetPayButton();
                        }
                    });
                } else {
                    showError(data.message || 'Terjadi kesalahan saat memproses pembayaran');
                    resetPayButton();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Terjadi kesalahan koneksi. Silakan coba lagi.');
                resetPayButton();
            });
        };

        // Fungsi untuk reset tombol pembayaran
        function resetPayButton() {
            const payButton = document.getElementById('pay-button');
            payButton.disabled = false;
            payButton.innerHTML = '<i class="fas fa-credit-card"></i> Bayar Sekarang - Rp <?php echo number_format($total_amount, 0, ',', '.'); ?>';
            payButton.classList.remove('loading');
        }

        // Auto-resize textarea
        document.getElementById('customer_address').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    </script>

    <script src="assets/js/script.js"></script>
</body>
</html>