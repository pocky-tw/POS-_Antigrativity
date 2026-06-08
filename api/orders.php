<?php
// api/orders.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

function getRequestData() {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        return $input;
    }
    return $_POST;
}

try {
    if ($method === 'GET') {
        $statusType = isset($_GET['type']) ? $_GET['type'] : 'all'; // 'pending', 'history', 'all'
        
        $query = "SELECT * FROM orders";
        $params = [];
        
        if ($statusType === 'pending') {
            $query .= " WHERE status = 'pending'";
        } elseif ($statusType === 'history') {
            $query .= " WHERE status IN ('completed', 'cancelled')";
        }
        
        $query .= " ORDER BY id DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        if (count($orders) > 0) {
            // Fetch all items for these orders to avoid N+1 query overhead
            $orderIds = array_column($orders, 'id');
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            
            $itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id IN ($placeholders)");
            $itemStmt->execute($orderIds);
            $items = $itemStmt->fetchAll();
            
            // Map items to their respective orders
            $itemsByOrder = [];
            foreach ($items as $item) {
                $itemsByOrder[$item['order_id']][] = $item;
            }
            
            foreach ($orders as &$order) {
                $order['items'] = isset($itemsByOrder[$order['id']]) ? $itemsByOrder[$order['id']] : [];
            }
        }
        
        echo json_encode($orders);
        exit;
    }
    
    if ($method === 'POST') {
        $data = getRequestData();
        $action = isset($data['action']) ? $data['action'] : 'create';
        
        if ($action === 'update_status') {
            if (!isset($data['id']) || !isset($data['status'])) {
                throw new Exception("Order ID and target status are required.");
            }
            
            $id = intval($data['id']);
            $status = trim($data['status']);
            
            if (!in_array($status, ['completed', 'cancelled'])) {
                throw new Exception("Invalid order status.");
            }
            
            $stmt = $pdo->prepare("UPDATE orders SET status = :status WHERE id = :id");
            $stmt->execute([
                ':status' => $status,
                ':id' => $id
            ]);
            
            echo json_encode(['success' => true, 'message' => "Order marked as $status."]);
            exit;
        }
        
        // Default action: Create Order
        $items = isset($data['items']) ? $data['items'] : [];
        $totalPrice = isset($data['total_price']) ? intval($data['total_price']) : 0;
        $paymentMethod = isset($data['payment_method']) ? trim($data['payment_method']) : 'cash';
        
        if (empty($items)) {
            throw new Exception("Cannot create an empty order.");
        }
        
        // Start database transaction
        $pdo->beginTransaction();
        
        // Generate a sequential order number for today
        // Format: BR-YYYYMMDD-XXXX
        $todayStr = date('Ymd');
        $dateLike = "BR-" . $todayStr . "-%";
        
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_no LIKE :dateLike");
        $countStmt->execute([':dateLike' => $dateLike]);
        $orderCount = intval($countStmt->fetchColumn());
        
        $orderNo = "BR-" . $todayStr . "-" . sprintf('%04d', $orderCount + 1);
        
        // Insert into orders table
        $orderStmt = $pdo->prepare("INSERT INTO orders (order_no, total_price, payment_method, status) VALUES (:order_no, :total_price, :payment_method, 'pending')");
        $orderStmt->execute([
            ':order_no' => $orderNo,
            ':total_price' => $totalPrice,
            ':payment_method' => $paymentMethod
        ]);
        
        $orderId = $pdo->lastInsertId();
        
        // Insert items
        $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal) VALUES (:order_id, :product_id, :product_name, :price, :quantity, :subtotal)");
        
        foreach ($items as $item) {
            $prodId = intval($item['product_id']);
            $prodName = trim($item['product_name']);
            $price = intval($item['price']);
            $qty = intval($item['quantity']);
            $subtotal = intval($item['subtotal']);
            
            if ($qty <= 0) {
                throw new Exception("Item quantity must be greater than zero.");
            }
            
            $itemStmt->execute([
                ':order_id' => $orderId,
                ':product_id' => $prodId,
                ':product_name' => $prodName,
                ':price' => $price,
                ':quantity' => $qty,
                ':subtotal' => $subtotal
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Fetch full order details to return
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id");
        $stmt->execute([':id' => $orderId]);
        $newOrder = $stmt->fetch();
        
        $itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
        $itemStmt->execute([':order_id' => $orderId]);
        $newOrder['items'] = $itemStmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'message' => 'Order created successfully.',
            'order' => $newOrder
        ]);
        exit;
    }
    
    throw new Exception("Unsupported request method.");
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
