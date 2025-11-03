-- JailTrak Main Database Schema
-- Clayton County Jail Inmate Tracking System

-- Inmates Table
CREATE TABLE IF NOT EXISTS inmates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    inmate_id TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    age INTEGER,
    sex TEXT,
    race TEXT,
    height TEXT,
    weight TEXT,
    hair_color TEXT,
    eye_color TEXT,
    booking_date TEXT,
    booking_time TEXT,
    bond_amount TEXT,
    arresting_agency TEXT DEFAULT 'Clayton County Sheriff''s Office',
    booking_officer TEXT,
    facility_location TEXT DEFAULT 'Clayton County Jail',
    cell_block TEXT,
    classification TEXT,
    in_jail INTEGER DEFAULT 1,
    release_date TEXT,
    release_time TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Charges Table
CREATE TABLE IF NOT EXISTS charges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    inmate_id TEXT NOT NULL,
    charge_description TEXT NOT NULL,
    charge_type TEXT,
    charge_code TEXT,
    court_case_number TEXT,
    charge_date TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inmate_id) REFERENCES inmates(inmate_id) ON DELETE CASCADE
);

-- Scrape Logs Table
CREATE TABLE IF NOT EXISTS scrape_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    scrape_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    inmates_found INTEGER DEFAULT 0,
    status TEXT,
    message TEXT,
    error_details TEXT
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_inmate_id ON inmates(inmate_id);
CREATE INDEX IF NOT EXISTS idx_inmate_name ON inmates(name);
CREATE INDEX IF NOT EXISTS idx_booking_date ON inmates(booking_date);
CREATE INDEX IF NOT EXISTS idx_in_jail ON inmates(in_jail);
CREATE INDEX IF NOT EXISTS idx_charge_inmate ON charges(inmate_id);
CREATE INDEX IF NOT EXISTS idx_charge_type ON charges(charge_type);
CREATE INDEX IF NOT EXISTS idx_charge_desc ON charges(charge_description);
CREATE INDEX IF NOT EXISTS idx_arresting_agency ON inmates(arresting_agency);
CREATE INDEX IF NOT EXISTS idx_facility ON inmates(facility_location);