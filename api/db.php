<?php
// api/db.php

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db_name = 'pos_system';

try {
    // Connect to MySQL server first (without database selected, to avoid errors if it doesn't exist)
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    
    // Select the database
    $pdo->exec("USE `$db_name`;");
    
    // Create products table
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        price INT NOT NULL,
        category VARCHAR(50) NOT NULL,
        image_url VARCHAR(255),
        status TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Create orders table
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_no VARCHAR(50) NOT NULL UNIQUE,
        total_price INT NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'cash',
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Create order_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        price INT NOT NULL,
        quantity INT NOT NULL,
        subtotal INT NOT NULL,
        FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Insert default products if database is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    if ($stmt->fetchColumn() == 0) {
        $default_products = [
            ['name' => '經典牛肉起司堡', 'price' => 125, 'category' => 'burger', 'image_url' => 'assets/burger.png'],
            ['name' => '酥脆卡啦雞腿堡', 'price' => 115, 'category' => 'burger', 'image_url' => 'assets/burger.png'],
            ['name' => '招牌肉蛋三明治', 'price' => 65, 'category' => 'sandwich', 'image_url' => 'assets/sandwich.png'],
            ['name' => '芋泥肉鬆三明治', 'price' => 75, 'category' => 'sandwich', 'image_url' => 'assets/sandwich.png'],
            ['name' => '黃金脆薯', 'price' => 45, 'category' => 'snack', 'image_url' => 'assets/fries.png'],
            ['name' => '酥炸雞塊', 'price' => 50, 'category' => 'snack', 'image_url' => 'assets/fries.png'],
            ['name' => '經典奶茶', 'price' => 35, 'category' => 'drink', 'image_url' => 'assets/coffee.png'],
            ['name' => '美式咖啡', 'price' => 55, 'category' => 'drink', 'image_url' => 'assets/coffee.png']
        ];
        
        $insert_stmt = $pdo->prepare("INSERT INTO products (name, price, category, image_url, status) VALUES (:name, :price, :category, :image_url, 1)");
        foreach ($default_products as $prod) {
            $insert_stmt->execute([
                ':name' => $prod['name'],
                ':price' => $prod['price'],
                ':category' => $prod['category'],
                ':image_url' => $prod['image_url']
            ]);
        }
    }
    
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
