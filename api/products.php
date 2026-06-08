<?php
// api/products.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

// Helper to get input data (JSON or Form Data)
function getRequestData() {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        return $input;
    }
    return $_POST;
}

try {
    if ($method === 'GET') {
        // If all=1, get all products (active & inactive) for admin panel
        $showAll = isset($_GET['all']) && $_GET['all'] == '1';
        
        if ($showAll) {
            $stmt = $pdo->query("SELECT * FROM products ORDER BY category, id DESC");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE status = 1 ORDER BY category, id DESC");
            $stmt->execute();
        }
        
        $products = $stmt->fetchAll();
        echo json_encode($products);
        exit;
    }
    
    if ($method === 'POST') {
        $data = getRequestData();
        $action = isset($data['action']) ? $data['action'] : 'save';
        
        if ($action === 'toggle_status') {
            if (!isset($data['id'])) {
                throw new Exception("Product ID is required.");
            }
            
            // Toggle status (1 -> 0 or 0 -> 1)
            $stmt = $pdo->prepare("UPDATE products SET status = CASE WHEN status = 1 THEN 0 ELSE 1 END WHERE id = :id");
            $stmt->execute([':id' => $data['id']]);
            
            // Fetch the updated product
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
            $stmt->execute([':id' => $data['id']]);
            echo json_encode(['success' => true, 'product' => $stmt->fetch()]);
            exit;
        }
        
        if ($action === 'delete') {
            if (!isset($data['id'])) {
                throw new Exception("Product ID is required.");
            }
            
            // Delete product
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute([':id' => $data['id']]);
            echo json_encode(['success' => true, 'message' => 'Product deleted successfully.']);
            exit;
        }
        
        // Save action (Create or Update)
        $name = isset($data['name']) ? trim($data['name']) : '';
        $price = isset($data['price']) ? intval($data['price']) : 0;
        $category = isset($data['category']) ? trim($data['category']) : '';
        $image_url = isset($data['image_url']) ? trim($data['image_url']) : '';
        $status = isset($data['status']) ? intval($data['status']) : 1;
        
        if (empty($name)) {
            throw new Exception("Product name is required.");
        }
        if ($price < 0) {
            throw new Exception("Product price cannot be negative.");
        }
        if (!in_array($category, ['burger', 'sandwich', 'snack', 'drink'])) {
            throw new Exception("Invalid category.");
        }
        
        if (isset($data['id']) && !empty($data['id'])) {
            // Update
            $id = intval($data['id']);
            $stmt = $pdo->prepare("UPDATE products SET name = :name, price = :price, category = :category, image_url = :image_url, status = :status WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':price' => $price,
                ':category' => $category,
                ':image_url' => $image_url,
                ':status' => $status,
                ':id' => $id
            ]);
            
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(['success' => true, 'message' => 'Product updated successfully.', 'product' => $stmt->fetch()]);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO products (name, price, category, image_url, status) VALUES (:name, :price, :category, :image_url, :status)");
            $stmt->execute([
                ':name' => $name,
                ':price' => $price,
                ':category' => $category,
                ':image_url' => $image_url,
                ':status' => $status
            ]);
            
            $newId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
            $stmt->execute([':id' => $newId]);
            echo json_encode(['success' => true, 'message' => 'Product created successfully.', 'product' => $stmt->fetch()]);
        }
        exit;
    }
    
    throw new Exception("Unsupported request method.");
    
} catch (Exception $e) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
