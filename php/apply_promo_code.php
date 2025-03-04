<?php
session_start();
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Replace with your RDS database credentials
$servername = "csicg4.czptxhzjxjrt.us-east-1.rds.amazonaws.com";
$username = "group4";
$password = "Groupfour";
$dbname = "ice_shop";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['promoCode'], $data['totalPrice'], $data['orderId'])) {
        throw new Exception("Promo code, total price, and order ID are required.");
    }

    $promoCode = $data['promoCode'];
    $totalPrice = floatval($data['totalPrice']);
    $orderId = intval($data['orderId']);

    // Check if the promo code is valid
    $stmt = $conn->prepare("SELECT discount_percentage FROM promotion_codes WHERE code = ? AND is_active = 1");
    $stmt->bind_param("s", $promoCode);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($discountPercentage);
        $stmt->fetch();

        // Calculate the discounted price
        $discountAmount = ($totalPrice * $discountPercentage) / 100;
        $discountedPrice = $totalPrice - $discountAmount;

        // Update the total price in the database
        $updateStmt = $conn->prepare("UPDATE orders SET total_price = ? WHERE order_id = ?");
        $updateStmt->bind_param("di", $discountedPrice, $orderId);
        if ($updateStmt->execute()) {
            echo json_encode(['success' => true, 'discountedPrice' => $discountedPrice]);
        } else {
            throw new Exception("Failed to update total price in the database.");
        }
        $updateStmt->close();
    } else {
        throw new Exception("Invalid or inactive promo code.");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
