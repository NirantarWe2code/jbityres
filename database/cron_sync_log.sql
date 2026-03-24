-- Cron Sync Log Table
-- Tracks each API sync run: when, date range, record counts, status
-- Run this once to create the table

CREATE TABLE IF NOT EXISTS cron_sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sync_started_at DATETIME NOT NULL COMMENT 'When cron run started',
    sync_completed_at DATETIME DEFAULT NULL COMMENT 'When cron run finished',
    date_from DATETIME NOT NULL COMMENT 'API from_date (start of range)',
    date_to DATETIME NOT NULL COMMENT 'API to_date (end of range)',
    records_fetched INT NOT NULL DEFAULT 0 COMMENT 'Records received from API',
    records_inserted INT NOT NULL DEFAULT 0 COMMENT 'New records inserted',
    records_updated INT NOT NULL DEFAULT 0 COMMENT 'Records updated (if any)',
    records_skipped INT NOT NULL DEFAULT 0 COMMENT 'Skipped due to validation',
    status ENUM('success', 'failed', 'partial') NOT NULL DEFAULT 'success',
    message TEXT COMMENT 'Status message or error detail',
    api_response_count INT DEFAULT NULL COMMENT 'data.count from API',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sync_started (sync_started_at),
    INDEX idx_status (status),
    INDEX idx_date_range (date_from, date_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
