-- Tyre Dashboard Sales Data Import
-- Database: tyre_dashboard
-- Table: sales_data

CREATE DATABASE IF NOT EXISTS tyre_dashboard;
USE tyre_dashboard;

CREATE TABLE IF NOT EXISTS sales_data (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Core transaction data
    `Dated` VARCHAR(50) NOT NULL,
    `Business_Name` VARCHAR(200) NOT NULL,
    `Sales_Rep` VARCHAR(100),
    `Invoice_Num` VARCHAR(50) NOT NULL,
    `product` VARCHAR(300) NOT NULL,
    `Delivery_Profile` TEXT,

    -- Financial data
    `Quantity` DECIMAL(10,3) NOT NULL DEFAULT 0,
    `Unit_Price` DECIMAL(10,3) NOT NULL DEFAULT 0,
    `Purchase_Price` DECIMAL(10,3) DEFAULT 0,

    -- Deduplication key (optional, for performance)
    dedupe_key VARCHAR(255) UNIQUE KEY DEFAULT NULL,

    -- System fields
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes for performance
    INDEX idx_dated (`Dated`),
    INDEX idx_business (`Business_Name`),
    INDEX idx_sales_rep (`Sales_Rep`),
    INDEX idx_invoice (`Invoice_Num`),
    INDEX idx_product (`product`),
    INDEX idx_dedupe_key (dedupe_key)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data (optional)
-- INSERT INTO sales_data (`Dated`, `Business_Name`, `Sales_Rep`, `Invoice_Num`, `product`, `Delivery_Profile`, `Quantity`, `Unit_Price`, `Purchase_Price`) VALUES
-- ('01/01/2024 10:00:00', 'Sample Customer', 'John Doe', 'INV001', 'Sample Product', 'Standard', 10.000, 100.000, 80.000);</content>
<parameter name="filePath">c:\xampp\htdocs\finalReport\database\database_sell_report_import.sql