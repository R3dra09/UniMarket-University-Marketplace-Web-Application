<?php
session_start();
require 'backend/db_connect.php';
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 0;
if(!$user_id){ echo json_encode(['items'=>[],'total_price'=>0]); exit; }

$stmt = $conn->prepare("SELECT c.id,c.quantity,p.name,p.price,p.image 
                        FROM cart c 
                        JOIN products p ON c.product_id=p.id 
                        WHERE c.user_id=?");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$res = $stmt->get_result();

$items=[];
$total=0;
while($row=$res->fetch_assoc()){
    $items[]=[
        'id'=>$row['id'],
        'name'=>$row['name'],
        'price'=>$row['price'],
        'quantity'=>$row['quantity'],
        'image'=>$row['image']
    ];
    $total += $row['price']*$row['quantity'];
}

echo json_encode(['items'=>$items,'total_price'=>$total]);
