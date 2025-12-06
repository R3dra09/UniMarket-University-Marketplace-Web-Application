<?php
ob_start();
session_start();
require_once 'backend/db_connect.php';
require_once 'fpdf/fpdf.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user info
$stmt = $conn->prepare("SELECT name, email FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) die("User not found.");

// Fetch cart items
$stmt = $conn->prepare("
    SELECT p.name, p.price, c.quantity 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result();
$stmt->close();

if ($cart_items->num_rows == 0) die("Your cart is empty.");

// Generate random order code
$randomCode = strtoupper(bin2hex(random_bytes(4)));

// Prepare items and total
$grand_total = 0;
$items_list = [];
while ($item = $cart_items->fetch_assoc()) {
    $subtotal = $item['price'] * $item['quantity'];
    $grand_total += $subtotal;
    $items_list[] = [
        'name' => $item['name'],
        'quantity' => $item['quantity'],
        'price' => $item['price'],
        'subtotal' => $subtotal
    ];
}

if (ob_get_length()) ob_end_clean();

$pdf = new FPDF('P','mm','A4');
$pdf->AddPage();

// Page border
$pdf->SetLineWidth(1);
$pdf->Rect(5, 5, 200, 287);

// Header
$pdf->SetFont('Arial','B',26);
$pdf->SetTextColor(255, 215, 0); // Gold color
$pdf->SetFillColor(34,34,34); // Dark background
$pdf->Cell(0,20,'UniMarket',0,1,'C',true);

// Subtitle
$pdf->SetFont('Arial','',14);
$pdf->SetTextColor(0,0,0);
$pdf->Cell(0,10,'Order Receipt',0,1,'C');
$pdf->Ln(5);

// Order info
$pdf->SetFont('Arial','B',12);
$pdf->SetTextColor(0,0,0);
$pdf->Cell(40,8,'Order Code:',0,0);
$pdf->SetFont('Arial','',12);
$pdf->Cell(60,8,$randomCode,0,1);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(40,8,'Customer Name:',0,0);
$pdf->SetFont('Arial','',12);
$pdf->Cell(60,8,$user['name'],0,1);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(40,8,'Email:',0,0);
$pdf->SetFont('Arial','',12);
$pdf->Cell(60,8,$user['email'],0,1);
$pdf->Ln(5);

// Table Header
$pdf->SetFont('Arial','B',12);
$pdf->SetFillColor(34,34,34); // Dark header
$pdf->SetTextColor(255,255,255); // White text
$pdf->Cell(90,10,'Product',1,0,'C',true);
$pdf->Cell(30,10,'Qty',1,0,'C',true);
$pdf->Cell(35,10,'Price',1,0,'C',true);
$pdf->Cell(35,10,'Subtotal',1,1,'C',true);

// Table Content
$pdf->SetFont('Arial','',12);
$pdf->SetTextColor(20,20,20);
$fill = false;

foreach ($items_list as $i) {
    $pdf->SetFillColor($fill ? 45 : 55, $fill ? 45 : 55, $fill ? 45 : 55);
    $pdf->Cell(90,8,$i['name'],1,0,'L',$fill);
    $pdf->Cell(30,8,$i['quantity'],1,0,'C',$fill);
    $pdf->Cell(35,8,'$'.$i['price'],1,0,'R',$fill);
    $pdf->Cell(35,8,'$'.$i['subtotal'],1,1,'R',$fill);
    //$fill = !$fill;
}

// Grand Total
$pdf->SetFont('Arial','B',12);
$pdf->SetFillColor(255, 215, 0); //Gold accent
$pdf->SetTextColor(0,0,0);
$pdf->Cell(155,10,'Grand Total',1,0,'R',true);
$pdf->Cell(35,10,'$'.$grand_total,1,1,'R',true);

$pdf->Ln(8);

// Footer Note
$pdf->SetFont('Arial','I',10);
$pdf->SetTextColor(100,100,100);
$pdf->MultiCell(0,6,"Thank you for shopping with UniMarket!\nThis is an automated receipt.",0,'C');

// --- QR Code using qrserver.com ---
$qr_data = $randomCode;
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=".urlencode($qr_data);

// Download QR image temporarily
$temp_qr = tempnam(sys_get_temp_dir(),'qr').'.png';
$qr_image = @file_get_contents($qr_url);
if ($qr_image) {
    file_put_contents($temp_qr, $qr_image);
    // Place QR at bottom center
    $pdf->Image($temp_qr, ($pdf->GetPageWidth()/2)-25, 230, 50, 50);
    @unlink($temp_qr);
}

// Output PDF
$pdf->Output('D','Order_Receipt_'.$randomCode.'.pdf');

// Clear cart
$stmt = $conn->prepare("DELETE FROM cart WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

exit;
