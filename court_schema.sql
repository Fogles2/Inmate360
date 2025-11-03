-- JailTrak Court Case Tracking Database Schema
-- Clayton County Superior Court Data

-- Court Cases Table
CREATE TABLE IF NOT EXISTS court_cases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    case_year INTEGER NOT NULL,
    case_sequence TEXT NOT NULL,
    case_number TEXT UNIQUE NOT NULL,
    inquiry_type TEXT DEFAULT 'Criminal',
    defendant_name TEXT NOT NULL,
    offense TEXT,
    filing_date DATE,
    case_status TEXT,
    judge TEXT,
    attorney TEXT,
    disposition TEXT,
    disposition_date DATE,
    sentence TEXT,
    bond_amount DECIMAL(10,2),
    next_court_date DATE,
    arrest_date DATE,
    indictment_date DATE,
    plea TEXT,
    trial_date DATE,
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Court Charges Table
CREATE TABLE IF NOT EXISTS court_charges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id INTEGER NOT NULL,
    charge_description TEXT NOT NULL,
    charge_type TEXT,
    charge_code TEXT,
    count_number INTEGER,
    plea TEXT,
    verdict TEXT,
    sentence TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES court_cases(id) ON DELETE CASCADE
);

-- Court Events/Docket Table
CREATE TABLE IF NOT EXISTS court_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id INTEGER NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME,
    event_type TEXT,
    event_description TEXT,
    judge TEXT,
    location TEXT,
    outcome TEXT,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES court_cases(id) ON DELETE CASCADE
);

-- Court Scrape Logs
CREATE TABLE IF NOT EXISTS court_scrape_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    scrape_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    cases_found INTEGER DEFAULT 0,
    status TEXT,
    message TEXT
);

-- Link table between inmates and court cases
CREATE TABLE IF NOT EXISTS inmate_court_cases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    inmate_id TEXT NOT NULL,
    case_id INTEGER NOT NULL,
    relationship TEXT DEFAULT 'Defendant',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES court_cases(id) ON DELETE CASCADE
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_court_case_number ON court_cases(case_number);
CREATE INDEX IF NOT EXISTS idx_court_defendant ON court_cases(defendant_name);
CREATE INDEX IF NOT EXISTS idx_court_status ON court_cases(case_status);
CREATE INDEX IF NOT EXISTS idx_court_judge ON court_cases(judge);
CREATE INDEX IF NOT EXISTS idx_court_filing_date ON court_cases(filing_date);
CREATE INDEX IF NOT EXISTS idx_court_year ON court_cases(case_year);
CREATE INDEX IF NOT EXISTS idx_court_charges_case ON court_charges(case_id);
CREATE INDEX IF NOT EXISTS idx_court_events_case ON court_events(case_id);
CREATE INDEX IF NOT EXISTS idx_court_events_date ON court_events(event_date);
CREATE INDEX IF NOT EXISTS idx_inmate_court_inmate ON inmate_court_cases(inmate_id);
CREATE INDEX IF NOT EXISTS idx_inmate_court_case ON inmate_court_cases(case_id);