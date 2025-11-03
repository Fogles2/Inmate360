<?php
/**
 * JailTrak - Clayton County Court Case Scraper V2
 * Uses same scraping method as jail scraper
 * Scrapes from: https://weba.claytoncountyga.gov/casinqcgi-bin/wci201r.pgm
 */

require_once 'config.php';

class CourtScraperV2 {
    private $db;
    private $baseUrl = 'https://weba.claytoncountyga.gov/casinqcgi-bin/wci201r.pgm';
    
    public function __construct() {
        $this->db = new PDO('sqlite:' . DB_PATH);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    /**
     * Main scrape function - scrapes all court cases
     */
    public function scrapeAll($startYear = null, $endYear = null) {
        if (!$startYear) {
            $startYear = date('Y') - 2; // Last 2 years
        }
        if (!$endYear) {
            $endYear = date('Y');
        }
        
        $totalCases = 0;
        $startTime = time();
        
        echo "========================================\n";
        echo "Clayton County Court Scraper V2\n";
        echo "========================================\n";
        echo "Start Time: " . date('Y-m-d H:i:s') . "\n";
        echo "Year Range: $startYear - $endYear\n\n";
        
        for ($year = $startYear; $year <= $endYear; $year++) {
            echo "\n--- Scraping Year: $year ---\n";
            $yearCases = $this->scrapeYear($year);
            $totalCases += $yearCases;
            echo "Year $year: $yearCases cases found\n";
        }
        
        $endTime = time();
        $duration = $endTime - $startTime;
        
        echo "\n========================================\n";
        echo "Scrape Complete!\n";
        echo "========================================\n";
        echo "Total Cases: $totalCases\n";
        echo "Duration: " . gmdate("H:i:s", $duration) . "\n";
        echo "End Time: " . date('Y-m-d H:i:s') . "\n\n";
        
        $this->logScrape($totalCases, 'success', "Scraped $totalCases court cases");
        
        return $totalCases;
    }
    
    /**
     * Scrape all cases for a specific year
     */
    private function scrapeYear($year) {
        $casesFound = 0;
        $consecutiveEmpty = 0;
        $maxEmptyAttempts = 100;
        
        // Start from sequence 1 and go up
        for ($seq = 1; $seq <= 99999; $seq++) {
            $caseNumber = sprintf("%05d", $seq);
            
            // Build URL - same format as example
            $url = $this->baseUrl . "?ctt=U&dvt=C&cyr=$year&ctp=CR&csq=$caseNumber";
            
            try {
                $caseData = $this->scrapeCasePage($url, $year, $caseNumber);
                
                if ($caseData) {
                    $this->saveCase($caseData);
                    $casesFound++;
                    $consecutiveEmpty = 0;
                    
                    if ($casesFound % 50 == 0) {
                        echo "  Progress: $casesFound cases found (checking sequence $seq)...\n";
                    }
                } else {
                    $consecutiveEmpty++;
                    
                    // If we hit too many empty results, assume we're done
                    if ($consecutiveEmpty >= $maxEmptyAttempts) {
                        echo "  Reached end of cases (no results for $maxEmptyAttempts consecutive attempts)\n";
                        break;
                    }
                }
                
                // Rate limiting - be nice to the server
                usleep(250000); // 0.25 second delay
                
            } catch (Exception $e) {
                echo "  Error on case $year-$caseNumber: " . $e->getMessage() . "\n";
                $consecutiveEmpty++;
                
                if ($consecutiveEmpty >= $maxEmptyAttempts) {
                    break;
                }
            }
        }
        
        return $casesFound;
    }
    
    /**
     * Scrape individual case page
     */
    private function scrapeCasePage($url, $year, $caseNumber) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$html) {
            return null;
        }
        
        // Check if case exists
        if (stripos($html, 'no cases found') !== false || 
            stripos($html, 'invalid') !== false ||
            stripos($html, 'no records') !== false ||
            strlen($html) < 500) {
            return null;
        }
        
        return $this->parseCase($html, $year, $caseNumber);
    }
    
    /**
     * Parse case HTML - same method as jail scraper
     */
    private function parseCase($html, $year, $caseNumber) {
        // Initialize data structure
        $data = [
            'case_year' => $year,
            'case_sequence' => $caseNumber,
            'case_number' => "$year-CR-" . ltrim($caseNumber, '0'),
            'inquiry_type' => 'Criminal',
            'defendant_name' => null,
            'offense' => null,
            'filing_date' => null,
            'case_status' => null,
            'judge' => null,
            'attorney' => null,
            'disposition' => null,
            'disposition_date' => null,
            'bond_amount' => null,
            'arrest_date' => null,
            'charges' => [],
            'events' => []
        ];
        
        // Clean HTML for parsing
        $html = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $html);
        $html = preg_replace('/\s+/', ' ', $html);
        
        // Parse defendant name - multiple patterns
        $namePatterns = [
            '/<b>\s*(?:Defendant|Name|Party)\s*:\s*<\/b>\s*([^<]+)/i',
            '/defendant\s*name\s*:\s*([^<\n]+)/i',
            '/<td[^>]*>\s*defendant\s*<\/td>\s*<td[^>]*>([^<]+)/i'
        ];
        
        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $data['defendant_name'] = trim(strip_tags($matches[1]));
                break;
            }
        }
        
        // Parse case status
        if (preg_match('/<b>\s*(?:Status|Case Status)\s*:\s*<\/b>\s*([^<]+)/i', $html, $matches)) {
            $data['case_status'] = trim(strip_tags($matches[1]));
        }
        
        // Parse judge
        if (preg_match('/<b>\s*Judge\s*:\s*<\/b>\s*([^<]+)/i', $html, $matches)) {
            $data['judge'] = trim(strip_tags($matches[1]));
        }
        
        // Parse offense/charge
        if (preg_match('/<b>\s*(?:Offense|Charge|Crime)\s*:\s*<\/b>\s*([^<]+)/i', $html, $matches)) {
            $data['offense'] = trim(strip_tags($matches[1]));
        }
        
        // Parse filing date
        if (preg_match('/<b>\s*(?:Filing Date|Filed|Date Filed)\s*:\s*<\/b>\s*([0-9\/\-]+)/i', $html, $matches)) {
            $data['filing_date'] = $this->formatDate($matches[1]);
        }
        
        // Parse arrest date
        if (preg_match('/<b>\s*Arrest Date\s*:\s*<\/b>\s*([0-9\/\-]+)/i', $html, $matches)) {
            $data['arrest_date'] = $this->formatDate($matches[1]);
        }
        
        // Parse bond amount
        if (preg_match('/<b>\s*Bond\s*:\s*<\/b>\s*\$?\s*([0-9,\.]+)/i', $html, $matches)) {
            $data['bond_amount'] = str_replace(',', '', $matches[1]);
        }
        
        // Parse attorney
        if (preg_match('/<b>\s*Attorney\s*:\s*<\/b>\s*([^<]+)/i', $html, $matches)) {
            $data['attorney'] = trim(strip_tags($matches[1]));
        }
        
        // Parse disposition
        if (preg_match('/<b>\s*Disposition\s*:\s*<\/b>\s*([^<]+)/i', $html, $matches)) {
            $data['disposition'] = trim(strip_tags($matches[1]));
        }
        
        // Parse disposition date
        if (preg_match('/<b>\s*Disposition Date\s*:\s*<\/b>\s*([0-9\/\-]+)/i', $html, $matches)) {
            $data['disposition_date'] = $this->formatDate($matches[1]);
        }
        
        // Parse charges table
        $data['charges'] = $this->parseChargesTable($html);
        
        // Parse events/docket
        $data['events'] = $this->parseEventsTable($html);
        
        // Only return if we have defendant name
        if ($data['defendant_name']) {
            return $data;
        }
        
        return null;
    }
    
    /**
     * Parse charges table from HTML
     */
    private function parseChargesTable($html) {
        $charges = [];
        
        // Look for table with charges
        if (preg_match('/<table[^>]*>(.*?)<\/table>/is', $html, $tableMatch)) {
            $tableHtml = $tableMatch[1];
            
            // Extract rows
            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $tableHtml, $rows);
            
            foreach ($rows[1] as $row) {
                preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $row, $cells);
                
                if (!empty($cells[1])) {
                    $cellData = array_map('strip_tags', $cells[1]);
                    $cellData = array_map('trim', $cellData);
                    
                    // Look for charge descriptions
                    foreach ($cellData as $cell) {
                        if (strlen($cell) > 10 && 
                            !preg_match('/^[0-9\/\-]+$/', $cell) &&
                            stripos($cell, 'count') === false &&
                            stripos($cell, 'charge') === false) {
                            
                            // Determine charge type
                            $chargeType = 'Unknown';
                            if (stripos($cell, 'felony') !== false) {
                                $chargeType = 'Felony';
                            } elseif (stripos($cell, 'misdemeanor') !== false) {
                                $chargeType = 'Misdemeanor';
                            }
                            
                            $charges[] = [
                                'charge_description' => $cell,
                                'charge_type' => $chargeType
                            ];
                        }
                    }
                }
            }
        }
        
        return array_unique($charges, SORT_REGULAR);
    }
    
    /**
     * Parse events/docket table
     */
    private function parseEventsTable($html) {
        $events = [];
        
        // Look for docket/events section
        if (preg_match_all('/<tr[^>]*>.*?<td[^>]*>([0-9\/\-]+)<\/td>.*?<td[^>]*>([^<]+)<\/td>.*?<\/tr>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $date = $this->formatDate(trim(strip_tags($match[1])));
                $event = trim(strip_tags($match[2]));
                
                if ($date && !empty($event) && strlen($event) > 5) {
                    $events[] = [
                        'event_date' => $date,
                        'event_description' => $event
                    ];
                }
            }
        }
        
        return $events;
    }
    
    /**
     * Format date to YYYY-MM-DD
     */
    private function formatDate($dateStr) {
        $dateStr = trim($dateStr);
        if (empty($dateStr) || $dateStr === '00/00/0000') return null;
        
        // Try different date formats
        $formats = ['m/d/Y', 'Y-m-d', 'm-d-Y', 'd/m/Y'];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateStr);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }
        
        return null;
    }
    
    /**
     * Save case to database
     */
    private function saveCase($data) {
        try {
            // Check if case already exists
            $stmt = $this->db->prepare("SELECT id FROM court_cases WHERE case_number = ?");
            $stmt->execute([$data['case_number']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing case
                $stmt = $this->db->prepare("
                    UPDATE court_cases SET
                        defendant_name = ?,
                        offense = ?,
                        filing_date = ?,
                        case_status = ?,
                        judge = ?,
                        attorney = ?,
                        disposition = ?,
                        disposition_date = ?,
                        bond_amount = ?,
                        arrest_date = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $data['defendant_name'],
                    $data['offense'],
                    $data['filing_date'],
                    $data['case_status'],
                    $data['judge'],
                    $data['attorney'],
                    $data['disposition'],
                    $data['disposition_date'],
                    $data['bond_amount'],
                    $data['arrest_date'],
                    $existing['id']
                ]);
                
                $caseId = $existing['id'];
            } else {
                // Insert new case
                $stmt = $this->db->prepare("
                    INSERT INTO court_cases 
                    (case_year, case_sequence, case_number, inquiry_type, defendant_name, offense, 
                     filing_date, case_status, judge, attorney, disposition, disposition_date, 
                     bond_amount, arrest_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $data['case_year'],
                    $data['case_sequence'],
                    $data['case_number'],
                    $data['inquiry_type'],
                    $data['defendant_name'],
                    $data['offense'],
                    $data['filing_date'],
                    $data['case_status'],
                    $data['judge'],
                    $data['attorney'],
                    $data['disposition'],
                    $data['disposition_date'],
                    $data['bond_amount'],
                    $data['arrest_date']
                ]);
                
                $caseId = $this->db->lastInsertId();
            }
            
            // Save charges
            if (!empty($data['charges'])) {
                // Delete old charges
                $stmt = $this->db->prepare("DELETE FROM court_charges WHERE case_id = ?");
                $stmt->execute([$caseId]);
                
                // Insert new charges
                $stmt = $this->db->prepare("
                    INSERT INTO court_charges (case_id, charge_description, charge_type)
                    VALUES (?, ?, ?)
                ");
                
                foreach ($data['charges'] as $charge) {
                    $stmt->execute([
                        $caseId,
                        $charge['charge_description'],
                        $charge['charge_type']
                    ]);
                }
            }
            
            // Save events
            if (!empty($data['events'])) {
                // Delete old events
                $stmt = $this->db->prepare("DELETE FROM court_events WHERE case_id = ?");
                $stmt->execute([$caseId]);
                
                // Insert new events
                $stmt = $this->db->prepare("
                    INSERT INTO court_events (case_id, event_date, event_description)
                    VALUES (?, ?, ?)
                ");
                
                foreach ($data['events'] as $event) {
                    $stmt->execute([
                        $caseId,
                        $event['event_date'],
                        $event['event_description']
                    ]);
                }
            }
            
            return true;
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Log scrape activity
     */
    private function logScrape($casesFound, $status, $message) {
        $stmt = $this->db->prepare("
            INSERT INTO court_scrape_logs (scrape_time, cases_found, status, message)
            VALUES (CURRENT_TIMESTAMP, ?, ?, ?)
        ");
        $stmt->execute([$casesFound, $status, $message]);
    }
    
    /**
     * Test scrape for a specific case
     */
    public function testScrape($year, $caseNumber) {
        echo "Testing scrape for case: $year-CR-$caseNumber\n\n";
        
        $url = $this->baseUrl . "?ctt=U&dvt=C&cyr=$year&ctp=CR&csq=$caseNumber";
        echo "URL: $url\n\n";
        
        $caseData = $this->scrapeCasePage($url, $year, $caseNumber);
        
        if ($caseData) {
            echo "✓ Case found!\n\n";
            print_r($caseData);
            
            echo "\nSaving to database...\n";
            $this->saveCase($caseData);
            echo "✓ Saved successfully!\n";
        } else {
            echo "✗ No case found or unable to parse\n";
        }
    }
}

// CLI Usage
if (php_sapi_name() === 'cli') {
    $scraper = new CourtScraperV2();
    
    // Check for test mode
    if (isset($argv[1]) && $argv[1] === 'test') {
        $year = $argv[2] ?? date('Y');
        $caseNumber = $argv[3] ?? '01586';
        $scraper->testScrape($year, $caseNumber);
    } else {
        $startYear = $argv[1] ?? (date('Y') - 1);
        $endYear = $argv[2] ?? date('Y');
        $scraper->scrapeAll($startYear, $endYear);
    }
}
?>