-- Database creation
CREATE DATABASE IF NOT EXISTS water_refill;
USE water_refill;

-- Users table (customers, staff, admin)
CREATE TABLE tb_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('customer','staff','admin') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    contact VARCHAR(20),
    brgy VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE tb_orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    order_type ENUM('walk-in','delivery') NOT NULL,
    gallons INT NOT NULL,
    points_earned INT DEFAULT 0,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES tb_users(id)
);

-- Rewards table
CREATE TABLE tb_rewards (
    reward_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    points INT DEFAULT 0,
    FOREIGN KEY (customer_id) REFERENCES tb_users(id)
);