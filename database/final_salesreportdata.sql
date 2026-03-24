-- Final Sales Report Data
-- Only columns from API response

CREATE TABLE IF NOT EXISTS final_salesreportdata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(200) DEFAULT NULL,
    delivery_name VARCHAR(255) DEFAULT NULL,
    delivery_routes VARCHAR(100) DEFAULT NULL,
    sales_rep VARCHAR(100) DEFAULT NULL,
    account_type VARCHAR(100) DEFAULT NULL,
    address TEXT,
    invoice_num VARCHAR(50) DEFAULT NULL,
    order_num VARCHAR(50) DEFAULT NULL,
    dated DATETIME DEFAULT NULL,
    product VARCHAR(300) DEFAULT NULL,
    stock_id VARCHAR(50) DEFAULT NULL,
    quantity DECIMAL(10,2) DEFAULT 0,
    unit_price DECIMAL(10,2) DEFAULT 0,
    unit_gst DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) DEFAULT 0,
    po_number VARCHAR(50) DEFAULT NULL,
    purchase_price DECIMAL(10,2) DEFAULT 0,
    reward_inclusive VARCHAR(10) DEFAULT 'No',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice (invoice_num),
    INDEX idx_dated (dated),
    INDEX idx_business (business_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
