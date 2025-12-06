<?php
session_start();
require 'backend/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle AJAX updates for quantity and delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cart_id = intval($_POST['cart_id'] ?? 0);

    if ($action === 'update' && isset($_POST['quantity'])) {
        $quantity = max(1, intval($_POST['quantity']));
        $stmt = $conn->prepare("UPDATE cart SET quantity=? WHERE id=? AND user_id=?");
        $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
        $stmt->execute();
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM cart WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $cart_id, $user_id);
        $stmt->execute();
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'clear') {
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'place_order') {
        $payment_method = $_POST['payment_method'] ?? '';
        $phone_number = $_POST['phone_number'] ?? '';
        $receipt_option = $_POST['receipt_option'] ?? '';

        if (empty($payment_method) || empty($phone_number)) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit;
        }

        // Start transaction
        $conn->begin_transaction();

        try {
            // Fetch cart items to validate and calculate grand total
            $stmt = $conn->prepare("SELECT c.id as cart_id, c.product_id, c.quantity, p.price, p.name, p.image 
                                    FROM cart c 
                                    JOIN products p ON c.product_id = p.id 
                                    WHERE c.user_id=?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $cart_result = $stmt->get_result();
            
            if ($cart_result->num_rows === 0) {
                throw new Exception('Cart is empty');
            }

            $grand_total = 0;
            $cart_items_data = [];
            while ($item = $cart_result->fetch_assoc()) {
                $subtotal = $item['price'] * $item['quantity'];
                $grand_total += $subtotal;
                $cart_items_data[] = $item;
            }

            // Insert individual order items into orders table (one row per item)
            $order_date = date('Y-m-d H:i:s');
            $order_ids = [];
            
            foreach ($cart_items_data as $item) {
                // Verify product still exists
                $verify_stmt = $conn->prepare("SELECT id, price FROM products WHERE id = ?");
                $verify_stmt->bind_param("i", $item['product_id']);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                
                if ($verify_result->num_rows === 0) {
                    throw new Exception("Product ID " . $item['product_id'] . " no longer exists");
                }
                
                $current_price = $verify_result->fetch_assoc()['price'];
                $subtotal = $current_price * $item['quantity'];

                // Insert individual order item row
                $item_stmt = $conn->prepare("INSERT INTO orders (user_id, product_id, quantity, price, total_amount, order_date, payment_method, phone_number, receipt_option) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $item_stmt->bind_param("iiiddssss", $user_id, $item['product_id'], $item['quantity'], $current_price, $subtotal, $order_date, $payment_method, $phone_number, $receipt_option);
                $item_stmt->execute();
                $order_ids[] = $conn->insert_id;
            }

            // Commit transaction without clearing cart here
            $conn->commit();

            echo json_encode(['success' => true, 'grand_total' => $grand_total, 'item_count' => count($order_ids)]);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

// Fetch cart items for display
$stmt = $conn->prepare("SELECT c.id as cart_id, c.product_id, p.name, p.price, p.image, c.quantity 
                        FROM cart c 
                        JOIN products p ON c.product_id = p.id 
                        WHERE c.user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result();

$grand_total = 0;
if ($cart_items && $cart_items->num_rows > 0) {
    while($item = $cart_items->fetch_assoc()) {
        $subtotal = $item['price'] * $item['quantity'];
        $grand_total += $subtotal;
    }
    $cart_items->data_seek(0); // Reset for display loop
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UniMarket | Your Cart</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { 
    background: linear-gradient(135deg, #121212 0%, #1a1a2e 50%, #16213e 100%);
    color: #e0e0e0; 
    font-family: 'Montserrat', sans-serif; 
    margin: 0; 
    padding: 0; 
    min-height: 100vh;
}
a { text-decoration: none; color: #fff; }
header, footer { display: none; } /* hide header/footer for simplicity */
.cart-container { 
    max-width: 1200px; 
    margin: 0 auto; 
    padding: 2rem; 
    display: flex; 
    gap: 2rem; 
    align-items: flex-start; 
}
.left-panel { 
    flex: 1; 
    background: rgba(255, 255, 255, 0.05); 
    border-radius: 20px; 
    padding: 2rem; 
    backdrop-filter: blur(10px); 
    border: 1px solid rgba(255, 255, 255, 0.1); 
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}
.page-header { 
    display: flex; 
    align-items: center; 
    gap: 0.5rem; 
    margin-bottom: 2rem; 
}
.page-icon { 
    font-size: 2rem; 
    color: #facc15; 
}
.page-title { 
    font-size: 2rem; 
    color: #facc15; 
    margin: 0; 
}
.cart-item { 
    display: flex; 
    align-items: center; 
    background: rgba(30, 41, 59, 0.8); 
    padding: 1.5rem; 
    margin-bottom: 1rem; 
    border-radius: 15px; 
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2); 
    transition: transform 0.2s ease;
}
.cart-item:hover { transform: translateY(-2px); }
.cart-item img { 
    width: 80px; 
    height: 80px; 
    object-fit: cover; 
    border-radius: 10px; 
    margin-right: 1rem; 
    border: 1px solid rgba(55, 65, 81, 0.5); 
}
.cart-details { 
    flex: 1; 
    overflow: hidden; 
}
.cart-details h3 { 
    margin: 0 0 0.25rem 0; 
    color: #facc15; 
    font-size: 1.1rem; 
}
.cart-details p { 
    margin: 0 0 0.25rem 0; 
    opacity: 0.8; 
    font-size: 0.9rem; 
}
.quantity-badge { 
    background: rgba(250, 204, 21, 0.2); 
    color: #facc15; 
    border: 1px solid rgba(250, 204, 21, 0.3); 
    border-radius: 50%; 
    width: 24px; 
    height: 24px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 0.8rem; 
    font-weight: 600; 
}
.price { 
    font-weight: 600; 
    color: #facc15; 
    margin-left: auto; 
}
.remove-btn { 
    background: rgba(239, 68, 68, 0.2); 
    color: #ef4444; 
    border: 1px solid rgba(239, 68, 68, 0.3); 
    border-radius: 50%; 
    width: 32px; 
    height: 32px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    cursor: pointer; 
    transition: all 0.2s ease; 
    margin-left: 1rem; 
}
.remove-btn:hover { 
    background: rgba(239, 68, 68, 0.3); 
    transform: scale(1.1); 
}
.quantity-controls { 
    display: flex; 
    align-items: center; 
    gap: 0.5rem; 
    margin-left: 1rem; 
}
.btn-qty { 
    background: rgba(250, 204, 21, 0.2); 
    color: #facc15; 
    border: 1px solid rgba(250, 204, 21, 0.3); 
    border-radius: 50%; 
    width: 28px; 
    height: 28px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    cursor: pointer; 
    transition: all 0.2s ease; 
}
.btn-qty:hover { 
    background: rgba(250, 204, 21, 0.3); 
    transform: scale(1.1); 
}
.quantity-input { 
    width: 40px; 
    text-align: center; 
    background: rgba(55, 65, 81, 0.8); 
    color: #e0e0e0; 
    border: 1px solid rgba(55, 65, 81, 0.5); 
    border-radius: 6px; 
    padding: 0.25rem; 
    font-size: 0.9rem; 
}
.right-panel { 
    width: 320px; 
    background: rgba(30, 41, 59, 0.8); 
    border-radius: 20px; 
    padding: 2rem; 
    backdrop-filter: blur(10px); 
    border: 1px solid rgba(55, 65, 81, 0.5); 
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}
.panel-header { 
    display: flex; 
    align-items: center; 
    gap: 0.5rem; 
    margin-bottom: 1rem; 
}
.panel-dot { 
    width: 6px; 
    height: 6px; 
    background: #facc15; 
    border-radius: 50%; 
}
.panel-title { 
    font-size: 1.3rem; 
    color: #facc15; 
    margin: 0; 
}
.card-type-group { 
    display: flex; 
    gap: 0.5rem; 
    margin-bottom: 1rem; 
}
.card-type-btn { 
    flex: 1; 
    padding: 0.75rem; 
    background: rgba(55, 65, 81, 0.5); 
    color: #e0e0e0; 
    border: 1px solid rgba(55, 65, 81, 0.8); 
    border-radius: 8px; 
    cursor: pointer; 
    transition: all 0.2s ease; 
    font-size: 0.9rem; 
}
.card-type-btn.active { 
    background: #facc15; 
    color: #000; 
    border-color: #facc15; 
}
.card-type-btn:hover { 
    background: rgba(250, 204, 21, 0.1); 
    border-color: #facc15; 
}
.form-group { 
    margin-bottom: 1rem; 
}
.form-group label { 
    display: block; 
    margin-bottom: 0.25rem; 
    color: #e0e0e0; 
    font-size: 0.9rem; 
}
.form-group input { 
    width: 92%; 
    padding: 0.75rem; 
    background: rgba(55, 65, 81, 0.8); 
    color: #e0e0e0; 
    border: 1px solid rgba(55, 65, 81, 0.5); 
    border-radius: 8px; 
    font-size: 1rem; 
}
.form-group input:focus { 
    outline: none; 
    border-color: #facc15; 
    box-shadow: 0 0 0 2px rgba(250, 204, 21, 0.2); 
}
.expiry-cvv { 
    display: grid; 
    grid-template-columns: 2fr 1fr; 
    gap: 1rem; 
}
.subtotal-row { 
    display: flex; 
    justify-content: space-between; 
    margin-bottom: 1rem; 
    padding-bottom: 0.5rem; 
    border-bottom: 1px solid rgba(55, 65, 81, 0.5); 
    font-size: 1.1rem; 
    color: #e0e0e0; 
}
.checkout-btn { 
    width: 100%; 
    background: linear-gradient(135deg, #facc15 0%, #eab308 100%); 
    color: #000; 
    padding: 1rem; 
    border-radius: 12px; 
    font-weight: 700; 
    cursor: pointer; 
    border: none; 
    transition: all 0.3s ease; 
    font-size: 1.1rem; 
    box-shadow: 0 4px 15px rgba(250, 204, 21, 0.3); 
}
.checkout-btn:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 6px 20px rgba(250, 204, 21, 0.4); 
}
.checkout-btn:disabled { 
    background: rgba(55, 65, 81, 0.5); 
    color: #a1a1aa; 
    cursor: not-allowed; 
    transform: none; 
    box-shadow: none; 
}
.back-btn { 
    display: block; 
    width: 100%; 
    background: transparent; 
    color: #e0e0e0; 
    padding: 0.75rem; 
    border: 1px solid rgba(55, 65, 81, 0.5); 
    border-radius: 8px; 
    cursor: pointer; 
    transition: all 0.2s ease; 
    text-align: center; 
    margin-bottom: 1rem; 
}
.back-btn:hover { 
    background: rgba(55, 65, 81, 0.3); 
    border-color: #facc15; 
}
.empty-msg { 
    text-align: center; 
    font-size: 1.5rem; 
    font-weight: bold;
    color: #f87171; 
    margin-top: 2rem; 
}
.empty-btn { 
    display: block; 
    margin: 1rem auto 0; 
    background: linear-gradient(135deg, #facc15 0%, #facc15 100%); 
    color: #000000ff; 
    padding: 0.75rem 1.5rem; 
    border-radius: 8px; 
    font-weight: 600; 
    cursor: pointer; 
    border: none; 
    transition: all 0.2s ease; 
}
.empty-btn:hover { 
    transform: translateY(-1px); 
    box-shadow: 0 4px 12px rgba(26, 26, 27, 0.4); 
}
.popup { 
    display: none; 
    position: fixed; 
    top: 50%; 
    left: 50%; 
    transform: translate(-50%, -50%); 
    background: rgba(16, 185, 129, 0.9); 
    padding: 2rem; 
    border-radius: 15px; 
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5); 
    color: #fff; 
    text-align: center; 
    z-index: 1000; 
    font-size: 1.2rem; 
    animation: fadeIn 0.3s ease-in-out; 
}
.popup.show { display: block; }
@keyframes fadeIn { 
    from { opacity: 0; } 
    to { opacity: 1; } 
}
.error-popup { 
    background: rgba(239, 68, 68, 0.9); 
}
@media (max-width: 768px) {
    .cart-container { flex-direction: column; padding: 1rem; }
    .right-panel { width: 100%; }
    .expiry-cvv { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="cart-container">
    <div class="left-panel">
        <div class="page-header">
            <div class="page-icon">🛒</div>
            <h1 class="page-title">Your Shopping Cart</h1>
        </div>
        <?php if($cart_items && $cart_items->num_rows > 0): 
            while($item = $cart_items->fetch_assoc()):
                $subtotal = $item['price'] * $item['quantity'];
                $usd_price = number_format($item['price'], 2);
        ?>
        <div class="cart-item" data-id="<?php echo $item['cart_id']; ?>">
            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
            <div class="cart-details">
                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                <p><?php echo htmlspecialchars($item['name']); ?> Ref: <?php echo substr(md5($item['product_id']), 0, 8); ?></p>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="color: #a1a1aa;">Blue</span>
                        <div class="quantity-badge"><?php echo $item['quantity']; ?></div>
                    </div>
                    <span class="price">$<?php echo $usd_price; ?></span>
                </div>
            </div>
            <div style="display: flex; align-items: center;">
                <div class="quantity-controls">
                    <button class="btn-qty" onclick="updateQuantity(<?php echo $item['cart_id']; ?>, -1)">-</button>
                    <input type="number" class="quantity-input" value="<?php echo $item['quantity']; ?>" min="1" onchange="updateQuantity(<?php echo $item['cart_id']; ?>, this.value)">
                    <button class="btn-qty" onclick="updateQuantity(<?php echo $item['cart_id']; ?>, 1)">+</button>
                </div>
                <button class="remove-btn" onclick="removeItem(<?php echo $item['cart_id']; ?>)">
                    <i class="fas fa-times" style="font-size: 0.8rem;"></i>
                </button>
            </div>
        </div>
        <?php endwhile; ?>
        <button class="back-btn" onclick="window.location.href='index.php'">← Back to Shop</button>
        <?php else: ?>
        <p class="empty-msg">Your cart is empty.</p>
        <button class="empty-btn" onclick="window.location.href='index.php'">Back to Home</button>
        <?php endif; ?>
    </div>
    
    <?php if($cart_items && $cart_items->num_rows > 0): ?>
    <div class="right-panel">
        <div class="panel-header">
            <div class="panel-dot"></div>
            <h2 class="panel-title">Payment Details</h2>
        </div>
        <div class="card-type-group">
            <button class="card-type-btn active" id="bkash-btn" data-method="bkash">bKash</button>
            <button class="card-type-btn" id="nagad-btn" data-method="nagad">Nagad</button>
        </div>
        <div class="form-group">
            <label>Phone Number</label>
            <input type="text" id="phone-number" placeholder="01XXXXXXXX" maxlength="11" oninput="formatPhoneNumber(this)" required>
        </div>
        <div class="expiry-cvv">
            <div class="form-group">
                <label>Total Payable</label>
                <input type="text" value="$<?php echo number_format($grand_total, 2); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Receipt</label>
                <div class="card-type-group">
                    <button class="card-type-btn active" id="receiptYes" data-option="yes">Yes</button>
                    <button class="card-type-btn" id="receiptNo" data-option="no">No</button>
                </div>
            </div>
        </div>
        <div class="subtotal-row">
            <span>Subtotal:</span>
            <span>$<?php echo number_format($grand_total, 2); ?></span>
        </div>
        <button class="checkout-btn" id="place-order">Place Order</button>
    </div>
    <?php endif; ?>
</div>
<div class="popup" id="successPopup">Order placed successfully!<br><button class="back-btn" style="margin-top: 1rem;" onclick="window.location.href='index.php'">Back to Shopping</button></div>
<div class="popup error-popup" id="errorPopup"><span id="errorMessage"></span></div>

<script>
// Card type selection
function selectCardType(btn) {
    document.querySelectorAll('#bkash-btn, #nagad-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

// Receipt selection
function selectReceipt(btn) {
    document.querySelectorAll('#receiptYes, #receiptNo').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

// Attach click listeners so buttons actually toggle
document.querySelectorAll('#bkash-btn, #nagad-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        selectCardType(this);
    });
});
document.querySelectorAll('#receiptYes, #receiptNo').forEach(btn => {
    btn.addEventListener('click', function(e) {
        selectReceipt(this);
    });
});

// Phone number formatting
function formatPhoneNumber(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    if (value.length > 2 && value.startsWith('01')) {
        input.value = value.substring(0, 11);
    } else {
        input.value = value;
    }
}

// Remove item
function removeItem(cartId) {
    if (confirm('Remove this item from cart?')) {
        fetch('cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete&cart_id=${cartId}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
}

// Update quantity
function updateQuantity(cartId, qtyChange) {
    const input = document.querySelector(`.cart-item[data-id="${cartId}"] .quantity-input`);
    let newQty = qtyChange === -1 ? Math.max(1, parseInt(input.value) - 1) : 
                qtyChange === 1 ? parseInt(input.value) + 1 : Math.max(1, parseInt(qtyChange));
    input.value = newQty;
    fetch('cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update&cart_id=${cartId}&quantity=${newQty}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

// Place order button
document.getElementById('place-order')?.addEventListener('click', () => {
    const activePaymentBtn = document.querySelector('#bkash-btn.active, #nagad-btn.active');
    const paymentMethod = activePaymentBtn ? activePaymentBtn.dataset.method : '';
    const phoneNumber = document.getElementById('phone-number').value.trim();
    const activeReceiptBtn = document.querySelector('#receiptYes.active, #receiptNo.active');
    const receiptOption = activeReceiptBtn ? activeReceiptBtn.dataset.option : 'no';

    if (!paymentMethod) {
        alert('Please select a payment method');
        return;
    }

    if (!phoneNumber || phoneNumber.length < 10) {
        alert('Please enter a valid phone number');
        return;
    }

     if(confirm('Are you sure you want to place the order?')){
        const btn=document.getElementById('place-order'); btn.disabled=true; btn.textContent='Processing...';
        fetch('cart.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=place_order&payment_method=${encodeURIComponent(paymentMethod)}&phone_number=${encodeURIComponent(phoneNumber)}&receipt_option=${encodeURIComponent(receiptOption)}`})
        .then(r=>r.json()).then(data=>{
            btn.disabled=false; btn.textContent='Place Order';
            if(data.success){
                const popup=document.getElementById('successPopup');
                popup.innerHTML=`Order placed successfully! Total: $${(data.grand_total??0).toFixed(2)} (${data.item_count} items)<br><button class="back-btn" style="margin-top:1rem;" onclick="window.location.href='index.php'">Back to Shopping</button>`;
                popup.classList.add('show');
                if(receiptOption==='yes'){
                    window.location.href='generate_receipt.php';
                }else{
                    fetch('cart.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=clear'})
                    .then(r=>r.json())
                    .then(d=>setTimeout(()=>{popup.classList.remove('show'); location.reload();},3000));
                }
            }else{ alert('Error: '+(data.error||'Unknown error'));}
        }).catch(err=>{alert('Network error: '+err.message); btn.disabled=false; btn.textContent='Place Order';});
    }
});

// Note: removed lines that forced defaults so users can change selections freely
// (no automatic re-adding of .active)
</script>
</body>
</html>
