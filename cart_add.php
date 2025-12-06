<?php
session_start();
require 'backend/db_connect.php';
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])){
    echo json_encode(['success'=>false,'error'=>'Please login first']);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = intval($_POST['product_id'] ?? 0);
if(!$product_id){
    echo json_encode(['success'=>false,'error'=>'Invalid product']);
    exit;
}

// Check product
$stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE id=? AND approved=1");
$stmt->bind_param("i",$product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
if(!$product){
    echo json_encode(['success'=>false,'error'=>'Product not found']);
    exit;
}

// Add or update cart
$stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id=? AND product_id=?");
$stmt->bind_param("ii",$user_id,$product_id);
$stmt->execute();
$cart_item = $stmt->get_result()->fetch_assoc();

if($cart_item){
    $stmt = $conn->prepare("UPDATE cart SET quantity=quantity+1 WHERE id=?");
    $stmt->bind_param("i",$cart_item['id']);
}else{
    $stmt = $conn->prepare("INSERT INTO cart(user_id,product_id,quantity) VALUES(?,?,1)");
    $stmt->bind_param("ii",$user_id,$product_id);
}

if($stmt->execute()){
    echo json_encode(['success'=>true]);
}else{
    echo json_encode(['success'=>false,'error'=>'Failed to add to cart']);
}
