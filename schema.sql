-- Schema for scrape_logs table
CREATE TABLE scrape_logs (
    id SERIAL PRIMARY KEY,
    message TEXT NOT NULL,
    error_details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
