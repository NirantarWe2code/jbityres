-- Cron Sync Skipped Records
-- Stores each skipped record with reason for debugging
-- For insert_failed: error message is in record_data JSON as _skip_error

CREATE TABLE IF NOT EXISTS cron_sync_skipped (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cron_sync_log_id INT NOT NULL COMMENT 'Links to cron_sync_log',
    skip_reason VARCHAR(100) NOT NULL COMMENT 'Why skipped: duplicate, missing_invoice, missing_business, missing_product, no_matching_columns, insert_failed',
    invoice_num VARCHAR(50) DEFAULT NULL,
    business_name VARCHAR(200) DEFAULT NULL,
    product VARCHAR(300) DEFAULT NULL,
    record_data JSON DEFAULT NULL COMMENT 'Full API record for debugging',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cron_log (cron_sync_log_id),
    INDEX idx_skip_reason (skip_reason),
    INDEX idx_invoice (invoice_num)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
