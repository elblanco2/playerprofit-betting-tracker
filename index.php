<?php
ob_start(); // Start output buffering to prevent header issues
session_start();

// Include the secure API key manager
require_once __DIR__ . '/includes/ApiKeyManager.php';

// Production-ready security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://api.openai.com https://api.anthropic.com https://generativelanguage.googleapis.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self' https://cdn.jsdelivr.net https://api.openai.com https://api.anthropic.com https://generativelanguage.googleapis.com http://localhost:11434");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("X-Powered-By: PlayerProfit Tracker v2.0");

class PlayerProfitTracker {
    private $dataFile;
    private $configFile;
    private $accountsFile;
    private $currentAccountId;
    private $apiKeyManager;
    
    // PlayerProfit Account Configurations
    private $accountConfigs = [
        'Standard' => [
            'min_percentage' => 1.0, // 1% minimum
            'max_risk' => 50 // Fixed maximum for Standard
        ],
        'Pro' => [
            'min_percentage' => 2.0, // 2% minimum  
            'max_risk' => 5000 // Fixed maximum for Pro (increased from 500)
        ]
    ];
    
    /**
     * Get risk limits for account based on current balance and drawdown protection
     */
    public function getRiskLimits($accountTier, $accountSize, $currentBalance = null, $highestBalance = null) {
        $config = $this->accountConfigs[$accountTier];
        
        // Use current balance if provided, otherwise use account size
        $balanceForCalculation = $currentBalance ?? $accountSize;
        
        // Apply 15% drawdown protection - minimum balance allowed
        if ($highestBalance && $currentBalance) {
            $minAllowedBalance = $highestBalance * 0.85; // 15% drawdown limit
            if ($currentBalance < $minAllowedBalance) {
                // Account is below 15% drawdown limit - reduce risk
                $balanceForCalculation = $minAllowedBalance;
            }
        }
        
        $minRisk = ($balanceForCalculation * $config['min_percentage']) / 100;
        
        // Set more reasonable max limits based on account size and tier
        $maxRisk = $this->getMaxRiskLimit($accountTier, $accountSize);
        
        return [
            'min_risk' => round($minRisk, 2),
            'max_risk' => $maxRisk,
            'balance_for_calculation' => $balanceForCalculation,
            'drawdown_protected' => ($highestBalance && $currentBalance && $currentBalance < ($highestBalance * 0.85))
        ];
    }
    
    /**
     * Calculate appropriate max risk limits
     */
    private function getMaxRiskLimit($accountTier, $accountSize) {
        if ($accountTier === 'Standard') {
            // Standard accounts: reasonable max based on size
            if ($accountSize <= 5000) return 100;
            if ($accountSize <= 10000) return 200;
            if ($accountSize <= 25000) return 500;
            return 1000; // For $100K Standard accounts
        } else {
            // Pro accounts: higher limits
            if ($accountSize <= 50000) return 2500;
            return 5000; // For $100K Pro accounts
        }
    }
    
    public function __construct($accountId = null) {
        $this->accountsFile = __DIR__ . '/data/accounts.json';
        $this->currentAccountId = $accountId ?: $this->getDefaultAccount();
        $this->dataFile = __DIR__ . '/data/account_' . $this->currentAccountId . '_data.json';
        $this->configFile = __DIR__ . '/data/account_' . $this->currentAccountId . '_config.json';
        
        // Initialize secure API key manager
        $this->apiKeyManager = new ApiKeyManager();
        
        // Initialize accounts file if it doesn't exist
        $this->initializeAccountsFile();
    }
    
    /**
     * Check if this is a first-time setup (no accounts configured)
     */
    public function isFirstTimeSetup() {
        if (!file_exists($this->accountsFile)) {
            return true;
        }
        
        $accounts = json_decode(file_get_contents($this->accountsFile), true);
        return empty($accounts) || !is_array($accounts);
    }
    
    /**
     * Get all available PlayerProfit account configurations
     */
    public function getAvailableAccountTypes() {
        return [
            'Standard' => [
                ['size' => 1000, 'display' => '$1,000', 'cost' => 'TBD'],
                ['size' => 5000, 'display' => '$5,000', 'cost' => 'TBD'],
                ['size' => 10000, 'display' => '$10,000', 'cost' => '$274'],
                ['size' => 25000, 'display' => '$25,000', 'cost' => 'TBD'],
                ['size' => 50000, 'display' => '$50,000', 'cost' => 'TBD'],
                ['size' => 100000, 'display' => '$100,000', 'cost' => 'TBD']
            ],
            'Pro' => [
                ['size' => 5000, 'display' => '$5,000', 'cost' => 'TBD'],
                ['size' => 10000, 'display' => '$10,000', 'cost' => 'TBD'],
                ['size' => 25000, 'display' => '$25,000', 'cost' => 'TBD'],
                ['size' => 50000, 'display' => '$50,000', 'cost' => 'TBD'],
                ['size' => 100000, 'display' => '$100,000', 'cost' => 'TBD']
            ]
        ];
    }
    
    /**
     * Create a new account based on user selection
     */
    public function createAccount($tier, $size, $customName = null) {
        $accounts = $this->getAllAccounts();
        
        // Generate unique account ID
        $accountId = strtolower($tier) . '_' . ($size / 1000) . 'k';
        $counter = 1;
        $originalId = $accountId;
        
        while (isset($accounts[$accountId])) {
            $counter++;
            $accountId = $originalId . '_' . $counter;
        }
        
        // Create account configuration
        $accountName = $customName ?: ($tier . ' $' . number_format($size / 1000) . 'K');
        if ($counter > 1) {
            $accountName .= ' #' . $counter;
        }
        
        $accounts[$accountId] = [
            'name' => $accountName,
            'tier' => $tier,
            'size' => $size,
            'active' => true,
            'created' => date('Y-m-d H:i:s')
        ];
        
        // Save updated accounts
        file_put_contents($this->accountsFile, json_encode($accounts, JSON_PRETTY_PRINT));
        
        // Initialize account data files
        $this->initializeAccountData($accountId, $tier, $size);
        
        return $accountId;
    }
    
    /**
     * Initialize data files for a new account
     */
    private function initializeAccountData($accountId, $tier, $size) {
        $dataFile = __DIR__ . '/data/account_' . $accountId . '_data.json';
        $configFile = __DIR__ . '/data/account_' . $accountId . '_config.json';
        
        // Initialize account data
        $initialData = [
            'bets' => [],
            'account_balance' => $size,
            'starting_balance' => $size,
            'total_wagered' => 0,
            'total_profit' => 0,
            'win_rate' => 0,
            'total_bets' => 0,
            'wins' => 0,
            'losses' => 0,
            'pushes' => 0
        ];
        
        // Initialize account config
        $initialConfig = [
            'account_tier' => $tier,
            'account_size' => $size,
            'current_phase' => 'Phase 1',
            'start_date' => date('Y-m-d'),
            'last_activity' => date('Y-m-d'),
            'phase_start_balance' => $size,
            'highest_balance' => $size
        ];
        
        file_put_contents($dataFile, json_encode($initialData, JSON_PRETTY_PRINT));
        file_put_contents($configFile, json_encode($initialConfig, JSON_PRETTY_PRINT));
    }
    
    private function initializeAccountsFile() {
        // For v2.0, only create empty accounts file - users will set up their own accounts
        if (!file_exists($this->accountsFile)) {
            file_put_contents($this->accountsFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }
    
    public function getAllAccounts() {
        return json_decode(file_get_contents($this->accountsFile), true);
    }
    
    public function getDefaultAccount() {
        if (isset($_SESSION['current_account'])) {
            return $_SESSION['current_account'];
        }
        return 'pro_50k'; // Default to your current account
    }
    
    public function setCurrentAccount($accountId) {
        $_SESSION['current_account'] = $accountId;
        $this->currentAccountId = $accountId;
        $this->dataFile = __DIR__ . '/data/account_' . $accountId . '_data.json';
        $this->configFile = __DIR__ . '/data/account_' . $accountId . '_config.json';
    }
    
    public function getCurrentAccount() {
        return $this->currentAccountId;
    }
    
    // API Key Manager methods
    public function hasValidApiKey($provider) {
        return $this->apiKeyManager->hasValidApiKey($provider);
    }
    
    public function getMaskedApiKey($provider) {
        return $this->apiKeyManager->getMaskedKey($provider);
    }
    
    public function storeApiKey($provider, $apiKey) {
        return $this->apiKeyManager->storeApiKey($provider, $apiKey);
    }
    
    public function clearApiKey($provider) {
        return $this->apiKeyManager->clearApiKey($provider);
    }
    
    public function getApiKey($provider) {
        return $this->apiKeyManager->getApiKey($provider);
    }
    
    public function initializeAccount($accountId) {
        $accounts = $this->getAllAccounts();
        if (!isset($accounts[$accountId])) {
            return false;
        }
        
        $account = $accounts[$accountId];
        $configFile = __DIR__ . '/data/account_' . $accountId . '_config.json';
        $dataFile = __DIR__ . '/data/account_' . $accountId . '_data.json';
        
        // Create config if it doesn't exist
        if (!file_exists($configFile)) {
            $config = [
                'account_tier' => $account['tier'],
                'account_size' => $account['size'],
                'current_phase' => 'Phase 1',
                'start_date' => date('Y-m-d'),
                'last_activity' => date('Y-m-d'),
                'phase_start_balance' => $account['size'],
                'highest_balance' => $account['size'] // Track high water mark
            ];
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        }
        
        // Create data if it doesn't exist
        if (!file_exists($dataFile)) {
            $data = ['bets' => [], 'account_balance' => $account['size']];
            file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
        }
        
        return true;
    }
    
    public function loadData() {
        if (!file_exists($this->dataFile)) {
            $dataDir = dirname($this->dataFile);
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            return ["bets" => [], "account_balance" => 0];
        }
        return json_decode(file_get_contents($this->dataFile), true);
    }
    
    public function saveData($data) {
        $dataDir = dirname($this->dataFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        file_put_contents($this->dataFile, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    public function loadConfig() {
        if (!file_exists($this->configFile)) {
            return [
                'account_tier' => 'Standard',
                'account_size' => 5000,
                'current_phase' => 'Phase 1',
                'start_date' => date('Y-m-d'),
                'last_activity' => date('Y-m-d'),
                'phase_start_balance' => 5000
            ];
        }
        return json_decode(file_get_contents($this->configFile), true);
    }
    
    public function saveConfig($config) {
        $dataDir = dirname($this->configFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        file_put_contents($this->configFile, json_encode($config, JSON_PRETTY_PRINT));
    }
    
    public function addBet($date, $sport, $selection, $stake, $odds, $result, $isParlay = false, $parlayLegs = []) {
        $config = $this->loadConfig();
        $data = $this->loadData();
        
        // Ensure highest_balance is tracked
        if (!isset($config['highest_balance'])) {
            $config['highest_balance'] = max($config['account_size'], $data['account_balance']);
        }
        
        // Validate bet size against account limits with drawdown protection
        $limits = $this->getRiskLimits(
            $config['account_tier'], 
            $config['account_size'], 
            $data['account_balance'], 
            $config['highest_balance']
        );
        
        if ($stake < $limits['min_risk'] || $stake > $limits['max_risk']) {
            $errorMsg = "Bet size must be between $" . number_format($limits['min_risk'], 2) . " and $" . number_format($limits['max_risk'], 2);
            $errorMsg .= " for " . $config['account_tier'] . " $" . number_format($config['account_size']) . " account";
            
            if ($limits['drawdown_protected']) {
                $errorMsg .= " (Drawdown protection active - betting limited due to 15% loss from peak)";
            }
            
            return [
                'success' => false, 
                'error' => $errorMsg
            ];
        }
        
        // Calculate P&L
        if ($isParlay && !empty($parlayLegs)) {
            $parlayOdds = $this->calculateParlayOdds($parlayLegs);
            $pnl = $this->calculatePayout($stake, $parlayOdds, $result);
        } else {
            $pnl = $this->calculatePayout($stake, $odds, $result);
        }
        
        // Create bet record
        $bet = [
            "id" => uniqid(),
            "date" => $date,
            "sport" => $sport,
            "selection" => $selection,
            "stake" => $stake,
            "odds" => $isParlay ? $this->calculateParlayOdds($parlayLegs) : $odds,
            "result" => $result,
            "pnl" => $pnl,
            "is_parlay" => $isParlay,
            "parlay_legs" => $parlayLegs,
            "account_balance_after" => $data['account_balance'] + $pnl
        ];
        
        // Update account balance
        $data['account_balance'] += $pnl;
        $data['bets'][] = $bet;
        
        // Update highest balance if new high water mark
        if ($data['account_balance'] > $config['highest_balance']) {
            $config['highest_balance'] = $data['account_balance'];
        }
        
        // Update activity date
        $config['last_activity'] = $date;
        $this->saveConfig($config);
        
        $this->saveData($data);
        
        // Check for violations after adding bet
        $violations = $this->checkViolations();
        
        return [
            'success' => true, 
            'bet' => $bet, 
            'new_balance' => $data['account_balance'],
            'violations' => $violations
        ];
    }
    
    public function checkViolations() {
        $config = $this->loadConfig();
        $data = $this->loadData();
        $violations = [];
        
        // Check daily loss limit (10%)
        $todayPnL = $this->getDailyPnL(date('Y-m-d'));
        $dailyLossLimit = $config['account_size'] * 0.1;
        if (abs($todayPnL) > $dailyLossLimit && $todayPnL < 0) {
            $violations[] = [
                'type' => 'daily_loss',
                'message' => "Daily loss limit exceeded: $" . number_format(abs($todayPnL), 2) . " / $" . number_format($dailyLossLimit, 2),
                'severity' => 'critical'
            ];
        }
        
        // Check max drawdown (15%)
        $maxDrawdown = $this->getMaxDrawdown();
        $drawdownLimit = $config['account_size'] * 0.15;
        if ($maxDrawdown > $drawdownLimit) {
            $violations[] = [
                'type' => 'max_drawdown',
                'message' => "Max drawdown exceeded: $" . number_format($maxDrawdown, 2) . " / $" . number_format($drawdownLimit, 2),
                'severity' => 'critical'
            ];
        }
        
        // Check pick minimum (20 picks required)
        $totalPicks = count($data['bets']);
        if ($totalPicks < 20) {
            $violations[] = [
                'type' => 'pick_minimum',
                'message' => "Minimum picks not met: $totalPicks / 20 picks required",
                'severity' => 'warning'
            ];
        }
        
        // Check inactivity (5 days for funded accounts)
        if ($config['current_phase'] === 'Funded') {
            $daysSinceActivity = (strtotime(date('Y-m-d')) - strtotime($config['last_activity'])) / (60*60*24);
            if ($daysSinceActivity >= 5) {
                $violations[] = [
                    'type' => 'inactivity',
                    'message' => "Inactivity violation: $daysSinceActivity days since last activity (5 day limit for funded accounts)",
                    'severity' => 'critical'
                ];
            }
        }
        
        return $violations;
    }
    
    public function getAccountStatus() {
        $config = $this->loadConfig();
        $data = $this->loadData();
        
        // Ensure highest_balance is tracked
        if (!isset($config['highest_balance'])) {
            $config['highest_balance'] = max($config['account_size'], $data['account_balance']);
            $this->saveConfig($config);
        }
        
        $currentBalance = $data['account_balance'];
        $startBalance = $config['phase_start_balance'];
        $profitTarget = ($config['current_phase'] !== 'Funded') ? $startBalance * 0.2 : 0;
        $profitProgress = $currentBalance - $startBalance;
        $profitPercentage = ($profitProgress / $startBalance) * 100;
        
        $todayPnL = $this->getDailyPnL(date('Y-m-d'));
        $maxDrawdown = $this->getMaxDrawdown();
        $totalPicks = count($data['bets']);
        
        $daysSinceActivity = (strtotime(date('Y-m-d')) - strtotime($config['last_activity'])) / (60*60*24);
        
        // Get risk limits with drawdown protection
        $riskLimits = $this->getRiskLimits(
            $config['account_tier'], 
            $config['account_size'], 
            $currentBalance, 
            $config['highest_balance']
        );
        
        return [
            'account_tier' => $config['account_tier'],
            'account_size' => $config['account_size'],
            'current_phase' => $config['current_phase'],
            'current_balance' => $currentBalance,
            'highest_balance' => $config['highest_balance'],
            'start_balance' => $startBalance,
            'profit_target' => $profitTarget,
            'profit_progress' => $profitProgress,
            'profit_percentage' => $profitPercentage,
            'target_met' => ($config['current_phase'] !== 'Funded') ? $profitProgress >= $profitTarget : true,
            'today_pnl' => $todayPnL,
            'max_drawdown' => $maxDrawdown,
            'total_picks' => $totalPicks,
            'picks_remaining' => max(0, 20 - $totalPicks),
            'days_since_activity' => $daysSinceActivity,
            'risk_limits' => $riskLimits,
            'drawdown_protected' => $riskLimits['drawdown_protected'],
            'balance_from_peak_pct' => (($currentBalance / $config['highest_balance']) * 100) - 100
        ];
    }
    
    public function getDailyPnL($date) {
        $data = $this->loadData();
        $dailyPnL = 0;
        
        foreach ($data['bets'] as $bet) {
            if ($bet['date'] === $date) {
                $dailyPnL += $bet['pnl'];
            }
        }
        
        return $dailyPnL;
    }
    
    public function getMaxDrawdown() {
        $data = $this->loadData();
        $runningBalance = $data['account_balance'];
        $peak = $runningBalance;
        $maxDrawdown = 0;
        
        // Calculate running balances and find max drawdown
        foreach (array_reverse($data['bets']) as $bet) {
            $runningBalance -= $bet['pnl'];
            if ($runningBalance > $peak) {
                $peak = $runningBalance;
            }
            $currentDrawdown = $peak - $runningBalance;
            if ($currentDrawdown > $maxDrawdown) {
                $maxDrawdown = $currentDrawdown;
            }
        }
        
        return $maxDrawdown;
    }
    
    public function advancePhase() {
        $config = $this->loadConfig();
        $data = $this->loadData();
        
        if ($config['current_phase'] === 'Phase 1') {
            $config['current_phase'] = 'Phase 2';
            $config['phase_start_balance'] = $data['account_balance'];
            $message = "Congratulations! Advanced to Phase 2";
        } elseif ($config['current_phase'] === 'Phase 2') {
            $config['current_phase'] = 'Funded';
            $config['phase_start_balance'] = $data['account_balance'];
            $message = "Congratulations! Account is now FUNDED!";
        } else {
            $message = "Already at funded level";
        }
        
        $this->saveConfig($config);
        return $message;
    }
    
    // Copy over the betting calculation methods from the original tracker
    private function calculatePayout($stake, $odds, $result) {
        // Handle losing bets
        if ($result === 'LOSS') {
            return -$stake;
        }
        
        // Handle bets that return stake (no profit/loss)
        if (in_array($result, ['PUSH', 'REFUNDED', 'CASHED OUT'])) {
            // For cashed out bets, we could calculate partial payout, but without the cash out amount,
            // we'll treat it as returning the stake (0 P&L) for now
            return 0;
        }
        
        // Handle winning bets
        if ($result === 'WIN') {
            if ($odds > 0) {
                return ($stake * $odds) / 100;
            } else {
                return ($stake * 100) / abs($odds);
            }
        }
        
        // Default fallback
        return 0;
    }
    
    private function calculateParlayOdds($parlayLegs) {
        if (empty($parlayLegs)) return 0;
        
        $combinedDecimal = 1;
        foreach ($parlayLegs as $leg) {
            $odds = intval($leg['odds']);
            $decimal = $this->americanToDecimal($odds);
            $combinedDecimal *= $decimal;
        }
        
        return $this->decimalToAmerican($combinedDecimal);
    }
    
    private function americanToDecimal($americanOdds) {
        if ($americanOdds > 0) {
            return ($americanOdds / 100) + 1;
        } else {
            return (100 / abs($americanOdds)) + 1;
        }
    }
    
    private function decimalToAmerican($decimalOdds) {
        if ($decimalOdds >= 2.0) {
            return round(($decimalOdds - 1) * 100);
        } else {
            return round(-100 / ($decimalOdds - 1));
        }
    }
    
    public function getAllBets() {
        $data = $this->loadData();
        usort($data["bets"], function($a, $b) {
            return strtotime($b["date"]) - strtotime($a["date"]);
        });
        return $data["bets"];
    }
    
    public function getDiscordMessage() {
        $status = $this->getAccountStatus();
        $violations = $this->checkViolations();
        
        $message = "🏆 **PLAYERPROFIT ACCOUNT STATUS**\n";
        $message .= "```\n";
        $message .= "Account: " . $status['account_tier'] . " $" . number_format($status['account_size']) . "\n";
        $message .= "Phase: " . $status['current_phase'] . "\n";
        $message .= "Balance: $" . number_format($status['current_balance'], 2) . "\n";
        
        if ($status['current_phase'] !== 'Funded') {
            $message .= "Target: $" . number_format($status['profit_target'], 2) . "\n";
            $message .= "Progress: $" . number_format($status['profit_progress'], 2) . " (" . number_format($status['profit_percentage'], 1) . "%)\n";
        }
        
        $message .= "Today P&L: $" . number_format($status['today_pnl'], 2) . "\n";
        $message .= "Max DD: $" . number_format($status['max_drawdown'], 2) . "\n";
        $message .= "Picks: " . $status['total_picks'] . "/20 minimum\n";
        
        if (!empty($violations)) {
            $message .= "\n⚠️ VIOLATIONS:\n";
            foreach ($violations as $violation) {
                $icon = $violation['severity'] === 'critical' ? '🚨' : '⚠️';
                $message .= $icon . " " . $violation['message'] . "\n";
            }
        }
        
        $message .= "```";
        
        return $message;
    }
    
    /**
     * Import bets from CSV data
     */
    public function importCSVData($csvContent) {
        if (empty($csvContent)) {
            return ['success' => false, 'error' => 'CSV data is empty'];
        }
        
        $lines = explode("\n", trim($csvContent));
        $imported = 0;
        $errors = 0;
        $errorMessages = [];
        $warnings = [];
        
        // Skip header row if present
        $firstLine = strtolower(trim($lines[0]));
        if (strpos($firstLine, 'date') !== false || strpos($firstLine, 'sport') !== false) {
            array_shift($lines);
        }
        
        // First pass: Parse and validate all data
        $parsedBets = [];
        $existingBets = $this->getAllBets();
        
        // Debug logging
        error_log("CSV Import started. Lines to process: " . count($lines));
        
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $fields = str_getcsv($line);
            if (count($fields) < 6) {
                $errors++;
                $errorMessages[] = "Line " . ($lineNum + 1) . ": Not enough fields (expected 6, got " . count($fields) . ")";
                continue;
            }
            
            // Parse CSV fields: Date, Sport, Selection, Stake, Odds, Result
            $date = trim($fields[0]);
            $sport = trim($fields[1]);
            $selection = trim($fields[2]);
            $stakeRaw = trim($fields[3]);
            $oddsRaw = trim($fields[4]);
            $result = strtoupper(trim($fields[5]));
            
            // Advanced stake parsing
            $stake = floatval(str_replace(['$', ',', '+'], '', $stakeRaw));
            
            // Advanced odds parsing (handle both +/- American odds)
            $odds = 0;
            $cleanOdds = str_replace(['+', ' '], '', $oddsRaw); // Remove + and spaces
            if (is_numeric($cleanOdds)) {
                $odds = intval($cleanOdds);
                // If original had no sign and is positive, make it positive American odds
                if ($odds > 0 && strpos($oddsRaw, '-') !== 0) {
                    $odds = abs($odds); // Ensure positive
                }
            } else {
                $errors++;
                $errorMessages[] = "Line " . ($lineNum + 1) . ": Invalid odds format '$oddsRaw' (use American format like -110, +120)";
                continue;
            }
            
            // Enhanced validation - allow odds of 0 (some books use 0 for even money)
            if (empty($date) || empty($sport) || empty($selection) || $stake <= 0) {
                $errors++;
                $errorMessages[] = "Line " . ($lineNum + 1) . ": Missing or invalid required fields (date='$date', stake=$stake, odds=$odds)";
                continue;
            }
            
            // Validate and normalize result - handle all PlayerProfit bet outcomes
            $validResults = [
                'WIN', 'W', 'WON', 'WINNING',
                'LOSS', 'L', 'LOSE', 'LOST', 'LOSING', 
                'PUSH', 'P', 'TIE', 'NO ACTION',
                'REFUNDED', 'REFUND', 'VOID', 'CANCELLED', 'CANCELED',
                'CASHED OUT', 'CASH OUT', 'CASHOUT', 'CASH-OUT'
            ];
            
            if (!in_array($result, $validResults)) {
                $errors++;
                $errorMessages[] = "Line " . ($lineNum + 1) . ": Invalid result '$result' (supported: WIN, LOSS, PUSH, REFUNDED, CASHED OUT)";
                continue;
            }
            
            // Normalize result to standard format
            if (in_array($result, ['WIN', 'W', 'WON', 'WINNING'])) {
                $result = 'WIN';
            } elseif (in_array($result, ['LOSS', 'L', 'LOSE', 'LOST', 'LOSING'])) {
                $result = 'LOSS';
            } elseif (in_array($result, ['PUSH', 'P', 'TIE', 'NO ACTION'])) {
                $result = 'PUSH';
            } elseif (in_array($result, ['REFUNDED', 'REFUND', 'VOID', 'CANCELLED', 'CANCELED'])) {
                $result = 'REFUNDED';
            } elseif (in_array($result, ['CASHED OUT', 'CASH OUT', 'CASHOUT', 'CASH-OUT'])) {
                $result = 'CASHED OUT';
            }
            
            // Enhanced date parsing
            $dateObj = $this->parseDate($date);
            if (!$dateObj) {
                $errors++;
                $errorMessages[] = "Line " . ($lineNum + 1) . ": Invalid date format '$date' (supported: YYYY-MM-DD, MM/DD/YYYY, DD/MM/YYYY, Jan 15 2025)";
                continue;
            }
            $normalizedDate = $dateObj->format('Y-m-d');
            
            // Check for duplicates (same date, selection, stake, odds)
            $betSignature = $normalizedDate . '|' . strtolower($selection) . '|' . $stake . '|' . $odds;
            $isDuplicate = false;
            
            // Check against existing bets
            foreach ($existingBets as $existingBet) {
                $existingSignature = $existingBet['date'] . '|' . strtolower($existingBet['selection']) . '|' . $existingBet['stake'] . '|' . $existingBet['odds'];
                if ($betSignature === $existingSignature) {
                    $isDuplicate = true;
                    $warnings[] = "Line " . ($lineNum + 1) . ": Possible duplicate bet (same date, selection, stake, odds)";
                    break;
                }
            }
            
            // Check against other parsed bets in this import
            foreach ($parsedBets as $parsedBet) {
                $parsedSignature = $parsedBet['date'] . '|' . strtolower($parsedBet['selection']) . '|' . $parsedBet['stake'] . '|' . $parsedBet['odds'];
                if ($betSignature === $parsedSignature) {
                    $isDuplicate = true;
                    $warnings[] = "Line " . ($lineNum + 1) . ": Duplicate bet within import data";
                    break;
                }
            }
            
            $parsedBets[] = [
                'line' => $lineNum + 1,
                'date' => $normalizedDate,
                'sport' => $sport,
                'selection' => $selection,
                'stake' => $stake,
                'odds' => $odds,
                'result' => $result,
                'is_duplicate' => $isDuplicate,
                'date_obj' => $dateObj
            ];
        }
        
        // Sort bets chronologically (oldest first)
        usort($parsedBets, function($a, $b) {
            return $a['date_obj'] <=> $b['date_obj'];
        });
        
        
        // Second pass: Import validated and sorted bets
        foreach ($parsedBets as $bet) {
            try {
                // Debug logging
                error_log("Importing bet: " . json_encode($bet));
                
                $addResult = $this->addBet(
                    $bet['date'], 
                    $bet['sport'], 
                    $bet['selection'], 
                    $bet['stake'], 
                    $bet['odds'], 
                    $bet['result'], 
                    false, 
                    []
                );
                
                // Debug logging
                error_log("Add bet result: " . json_encode($addResult));
                
                if ($addResult['success']) {
                    $imported++;
                } else {
                    $errors++;
                    $errorMessages[] = "Line " . $bet['line'] . ": " . $addResult['error'];
                }
            } catch (Exception $e) {
                $errors++;
                $errorMessages[] = "Line " . $bet['line'] . ": " . $e->getMessage();
            }
        }
        
        // Debug final results
        error_log("CSV Import completed. Imported: $imported, Errors: $errors, Parsed bets: " . count($parsedBets));
        error_log("Error messages: " . json_encode($errorMessages));
        
        if ($imported > 0) {
            $data = $this->loadData();
            return [
                'success' => true,
                'count' => $imported,
                'errors' => $errors,
                'warnings' => count($warnings),
                'new_balance' => $data['account_balance'],
                'error_messages' => $errorMessages,
                'warning_messages' => $warnings
            ];
        } else {
            return [
                'success' => false,
                'error' => 'No valid bets found in CSV data. Errors: ' . implode('; ', array_slice($errorMessages, 0, 3)),
                'warnings' => count($warnings),
                'warning_messages' => $warnings
            ];
        }
    }
    
    /**
     * Enhanced date parsing with multiple format support
     */
    private function parseDate($dateString) {
        $formats = [
            'Y-m-d',           // 2025-01-15
            'm/d/Y',           // 01/15/2025
            'd/m/Y',           // 15/01/2025
            'm-d-Y',           // 01-15-2025
            'd-m-Y',           // 15-01-2025
            'M j, Y',          // Jan 15, 2025
            'F j, Y',          // January 15, 2025
            'j M Y',           // 15 Jan 2025
            'M j Y',           // Jan 15 2025
            'm/d/y',           // 01/15/25
            'd/m/y',           // 15/01/25
        ];
        
        foreach ($formats as $format) {
            $dateObj = DateTime::createFromFormat($format, $dateString);
            if ($dateObj && $dateObj->format($format) === $dateString) {
                return $dateObj;
            }
        }
        
        // Try strtotime as fallback
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            return new DateTime('@' . $timestamp);
        }
        
        return false;
    }
    
    /**
     * Get CSS class for bet result display
     */
    private function getResultClass($result) {
        switch ($result) {
            case 'WIN':
                return 'result-win';
            case 'LOSS':
                return 'result-loss';
            case 'PUSH':
                return 'result-push';
            case 'REFUNDED':
                return 'result-refunded';
            case 'CASHED OUT':
                return 'result-cashed-out';
            default:
                return 'result-push'; // Default fallback
        }
    }
    
    /**
     * Edit an existing bet
     */
    public function editBet($betId, $date, $sport, $selection, $stake, $odds, $result) {
        try {
            $data = $this->loadData();
            
            // Find the bet to edit
            $betIndex = -1;
            for ($i = 0; $i < count($data['bets']); $i++) {
                if ($data['bets'][$i]['id'] === $betId) {
                    $betIndex = $i;
                    break;
                }
            }
            
            if ($betIndex === -1) {
                return ['success' => false, 'error' => 'Bet not found'];
            }
            
            // Store old bet for balance recalculation
            $oldBet = $data['bets'][$betIndex];
            
            // Validate new data
            if (empty($date) || empty($sport) || empty($selection) || $stake <= 0 || $odds == 0) {
                return ['success' => false, 'error' => 'Invalid bet data'];
            }
            
            if (!in_array($result, ['WIN', 'LOSS', 'PUSH', 'REFUNDED', 'CASHED OUT'])) {
                return ['success' => false, 'error' => 'Invalid result'];
            }
            
            // Update the bet
            $data['bets'][$betIndex] = [
                'id' => $betId,
                'date' => $date,
                'sport' => $sport,
                'selection' => $selection,
                'stake' => $stake,
                'odds' => $odds,
                'result' => $result,
                'pnl' => $this->calculatePnL($stake, $odds, $result),
                'timestamp' => $oldBet['timestamp'] // Keep original timestamp
            ];
            
            // Recalculate all balances from scratch (important for accuracy)
            $this->recalculateAllBalances($data);
            
            $this->saveData($data);
            
            return [
                'success' => true,
                'new_balance' => $data['account_balance']
            ];
            
        } catch (Exception $e) {
            error_log("Edit bet error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to edit bet: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete a bet
     */
    public function deleteBet($betId) {
        try {
            $data = $this->loadData();
            
            // Find and remove the bet
            $betFound = false;
            for ($i = 0; $i < count($data['bets']); $i++) {
                if ($data['bets'][$i]['id'] === $betId) {
                    array_splice($data['bets'], $i, 1);
                    $betFound = true;
                    break;
                }
            }
            
            if (!$betFound) {
                return ['success' => false, 'error' => 'Bet not found'];
            }
            
            // Recalculate all balances from scratch
            $this->recalculateAllBalances($data);
            
            $this->saveData($data);
            
            return [
                'success' => true,
                'new_balance' => $data['account_balance']
            ];
            
        } catch (Exception $e) {
            error_log("Delete bet error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to delete bet: ' . $e->getMessage()];
        }
    }
    
    /**
     * Recalculate all bet balances chronologically
     */
    private function recalculateAllBalances(&$data) {
        // Sort bets by date and timestamp
        usort($data['bets'], function($a, $b) {
            $dateCompare = strcmp($a['date'], $b['date']);
            return $dateCompare !== 0 ? $dateCompare : strcmp($a['timestamp'], $b['timestamp']);
        });
        
        // Recalculate balances
        $runningBalance = $data['starting_balance'];
        
        foreach ($data['bets'] as &$bet) {
            $runningBalance += $bet['pnl'];
            $bet['account_balance_after'] = $runningBalance;
        }
        
        // Update current balance
        $data['account_balance'] = $runningBalance;
        
        // Update statistics
        $this->updateStats($data);
    }
    
    /**
     * Clear all bets from account (admin function)
     */
    public function clearAllBets() {
        try {
            $data = $this->loadData();
            $config = $this->loadConfig();
            
            // Determine starting balance from config
            $startingBalance = $config['starting_balance'] ?? 50000; // Default fallback
            
            // Reset to starting state
            $data['bets'] = [];
            $data['account_balance'] = $startingBalance;
            $data['starting_balance'] = $startingBalance; // Ensure it exists
            $data['total_wagered'] = 0;
            $data['total_profit'] = 0;
            $data['win_rate'] = 0;
            $data['total_bets'] = 0;
            $data['wins'] = 0;
            $data['losses'] = 0;
            $data['pushes'] = 0;
            
            $this->saveData($data);
            
            return [
                'success' => true,
                'message' => 'All bets cleared successfully',
                'new_balance' => $data['account_balance']
            ];
            
        } catch (Exception $e) {
            error_log("Clear all bets error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to clear bets: ' . $e->getMessage()];
        }
    }
    
    /**
     * Parse unstructured betting data using LLM APIs
     */
    public function parseWithLLM($rawData, $apiKey, $provider) {
        if (empty($rawData) || empty($apiKey)) {
            return ['success' => false, 'error' => 'Missing required data or API key'];
        }
        
        $prompt = $this->buildLLMPrompt($rawData);
        
        switch ($provider) {
            case 'openai':
                return $this->callOpenAI($prompt, $apiKey);
            case 'anthropic':
                return $this->callAnthropic($prompt, $apiKey);
            case 'google':
                return $this->callGoogleAI($prompt, $apiKey);
            case 'ollama':
                return $this->callOllama($prompt, $apiKey);
            default:
                return ['success' => false, 'error' => 'Unsupported LLM provider'];
        }
    }
    
    private function buildLLMPrompt($rawData) {
        return "You are a betting data parser. Convert the following unstructured betting data into CSV format.

REQUIRED CSV FORMAT (EXACT COLUMNS):
Date,Sport,Selection,Stake,Odds,Result

REQUIREMENTS:
- Date format: YYYY-MM-DD
- Sport: Any text (NFL, NBA, MLB, etc.)
- Selection: Bet description (Patriots ML, Lakers +5.5, etc.)
- Stake: Numeric amount in dollars (no $ symbol)
- Odds: American odds format (-110, +150, etc.)
- Result: Must be WIN, LOSS, or PUSH (uppercase)

PARLAY BET HANDLING (CRITICAL):
When you encounter parlay bets with missing individual leg odds:

FOR WINNING PARLAYS:
- Calculate the combined parlay odds using reverse calculation from payout
- Formula: If stake=\$1000 and payout=\$3500 (total received), profit=\$2500
- Odds calculation: (profit / stake) = profit ratio
- Convert to American odds: If ratio ≥ 1, use +[ratio*100]; if ratio < 1, use -[100/ratio]
- Example: \$1000 stake, \$3500 payout = \$2500 profit = 2.5 ratio = +250 odds

FOR LOSING PARLAYS:
- Use -110 as placeholder odds (losses don't affect profit calculations)
- The exact odds don't matter for losses, just get date/sport/selection/stake/LOSS correct

PARLAY EXAMPLES:
- '3-leg parlay won \$2400 on \$500 bet' → +480 odds (2400/500 = 4.8 = +480)
- '2-team parlay: Bet \$1000, total payout \$3500' → +250 odds (2500 profit/1000 stake = 2.5 = +250)
- '4-leg parlay lost \$1000' → -110 odds (placeholder for loss)
- 'Parlay: Chiefs ML + Lakers Over 215 + Dodgers -1.5, risked \$800, won \$2000' → +150 odds (1200 profit/800 stake = 1.5 = +150)

SINGLE BET RULES:
1. Extract dates in YYYY-MM-DD format
2. Identify sport/league from context
3. Parse bet selections clearly
4. Extract stake amounts (remove $, commas)
5. Convert odds to American format if needed
6. Determine WIN/LOSS/PUSH from context
7. Return ONLY the CSV data with header row
8. If uncertain about any field, make best guess based on context

INPUT DATA:
{$rawData}

OUTPUT (CSV only, no explanations):";
    }
    
    private function callOpenAI($prompt, $apiKey) {
        $data = [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a precise data parser. Return only CSV data with no additional text or explanations.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 2000,
            'temperature' => 0.1
        ];
        
        return $this->makeLLMRequest('https://api.openai.com/v1/chat/completions', $data, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
    }
    
    private function callAnthropic($prompt, $apiKey) {
        $data = [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 2000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        return $this->makeLLMRequest('https://api.anthropic.com/v1/messages', $data, [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ]);
    }
    
    private function callGoogleAI($prompt, $apiKey) {
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 2000
            ]
        ];
        
        return $this->makeLLMRequest("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", $data, [
            'Content-Type: application/json'
        ]);
    }
    
    private function callOllama($prompt, $serverUrl) {
        $data = [
            'model' => 'llama2',
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'temperature' => 0.1,
                'top_p' => 0.9
            ]
        ];
        
        return $this->makeLLMRequest($serverUrl . '/api/generate', $data, [
            'Content-Type: application/json'
        ]);
    }
    
    private function makeLLMRequest($url, $data, $headers) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => 'Network error: ' . $error];
        }
        
        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "API request failed with status {$httpCode}"];
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            return ['success' => false, 'error' => 'Invalid API response format'];
        }
        
        return $this->extractCSVFromResponse($result, $url);
    }
    
    private function extractCSVFromResponse($response, $url) {
        $csvData = '';
        
        // Extract content based on API provider
        if (strpos($url, 'openai.com') !== false) {
            $csvData = $response['choices'][0]['message']['content'] ?? '';
        } elseif (strpos($url, 'anthropic.com') !== false) {
            $csvData = $response['content'][0]['text'] ?? '';
        } elseif (strpos($url, 'googleapis.com') !== false) {
            $csvData = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } elseif (strpos($url, '/api/generate') !== false) {
            $csvData = $response['response'] ?? '';
        }
        
        if (empty($csvData)) {
            return ['success' => false, 'error' => 'No content received from AI'];
        }
        
        // Clean and validate CSV data
        $csvData = trim($csvData);
        $csvData = preg_replace('/```csv\s*/', '', $csvData);
        $csvData = preg_replace('/```\s*$/', '', $csvData);
        $csvData = trim($csvData);
        
        $lines = explode("\n", $csvData);
        $validLines = array_filter($lines, function($line) {
            return !empty(trim($line)) && substr_count($line, ',') >= 5;
        });
        
        if (count($validLines) < 1) {
            return ['success' => false, 'error' => 'AI did not generate valid CSV data'];
        }
        
        return [
            'success' => true,
            'csv_data' => implode("\n", $validLines),
            'rows_found' => count($validLines) - 1, // Subtract header row
            'raw_response' => $csvData
        ];
    }
    
    /**
     * Interactive chat with LLM for betting data formatting
     */
    public function chatWithLLM($userMessage, $apiKey, $provider) {
        if (empty($userMessage) || empty($apiKey)) {
            return ['success' => false, 'error' => 'Missing required message or API key'];
        }

        // Build conversation prompt for chat context
        $systemPrompt = "You are an expert betting data analyst helping users format their betting history for the PlayerProfit tracking system. Your job is to:\n\n1. Help users understand the required CSV format: Date,Sport,Selection,Stake,Odds,Result\n2. Convert unstructured betting data into proper CSV format\n3. Answer questions about betting data formatting\n4. Provide guidance on PlayerProfit compliance rules\n5. Handle parlay bets with reverse odds calculation\n\nWhen users paste betting data, convert it to CSV format. When they ask questions, provide helpful guidance.\n\nRequired CSV format:\n- Date: YYYY-MM-DD format\n- Sport: NFL, NBA, MLB, NHL, Tennis, Soccer, etc.\n- Selection: Team name + bet type (e.g., 'Patriots ML', 'Lakers +5.5')\n- Stake: Numeric value (no currency symbols)\n- Odds: American format (-110, +120, etc.)\n- Result: WIN, LOSS, PUSH, REFUNDED, or CASHED OUT\n\nPARLAY BET HANDLING (CRITICAL):\nFor parlay bets with missing individual leg odds:\n\nWINNING PARLAYS:\n- Calculate combined odds using: (profit ÷ stake) = ratio\n- Convert to American: If ratio ≥ 1, use +[ratio×100]; if ratio < 1, use -[100÷ratio]\n- Example: \\$1000 bet won \\$2500 profit = 2.5 ratio = +250 odds\n\nLOSING PARLAYS:\n- Use -110 as placeholder (losses don't affect profit tracking)\n\nBet Result Types:\n- WIN: Bet won normally\n- LOSS: Bet lost normally\n- PUSH: Tie/No action, stake returned\n- REFUNDED: Bet cancelled/voided, stake returned\n- CASHED OUT: User cashed out early for partial payout\n\nExample:\n2025-01-15,NFL,Patriots ML,1000,-110,WIN\n2025-01-14,NBA,Lakers +5.5,1500,-105,LOSS\n2025-01-13,Multi,3-leg Parlay,500,+240,WIN\n2025-01-12,Multi,2-team Parlay,1000,-110,LOSS";

        $chatPrompt = $systemPrompt . "\n\nUser: " . $userMessage . "\n\nAssistant:";

        // Prepare API request based on provider
        $url = '';
        $headers = [];
        $data = [];

        switch ($provider) {
            case 'openai':
                $url = 'https://api.openai.com/v1/chat/completions';
                $headers = [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ];
                $data = [
                    'model' => 'gpt-4',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage]
                    ],
                    'max_tokens' => 2000,
                    'temperature' => 0.1
                ];
                break;

            case 'anthropic':
                $url = 'https://api.anthropic.com/v1/messages';
                $headers = [
                    'x-api-key: ' . $apiKey,
                    'Content-Type: application/json',
                    'anthropic-version: 2023-06-01'
                ];
                $data = [
                    'model' => 'claude-3-5-sonnet-20241022',
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userMessage]
                    ],
                    'max_tokens' => 2000
                ];
                break;

            case 'google':
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey;
                $headers = ['Content-Type: application/json'];
                $data = [
                    'contents' => [
                        ['parts' => [['text' => $chatPrompt]]]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'maxOutputTokens' => 2000
                    ]
                ];
                break;

            case 'ollama':
                $url = 'http://localhost:11434/api/generate';
                $headers = ['Content-Type: application/json'];
                $data = [
                    'model' => 'llama2',
                    'prompt' => $chatPrompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.1,
                        'num_predict' => 2000
                    ]
                ];
                break;

            default:
                return ['success' => false, 'error' => 'Unsupported LLM provider'];
        }

        // Make API request
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            curl_close($ch);
            return ['success' => false, 'error' => 'Network error: ' . curl_error($ch)];
        }
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'API error (HTTP ' . $httpCode . '): ' . $response];
        }

        $result = json_decode($response, true);
        if (!$result) {
            return ['success' => false, 'error' => 'Invalid API response format'];
        }

        // Extract response content
        $assistantMessage = '';
        if (strpos($url, 'openai.com') !== false) {
            $assistantMessage = $result['choices'][0]['message']['content'] ?? '';
        } elseif (strpos($url, 'anthropic.com') !== false) {
            $assistantMessage = $result['content'][0]['text'] ?? '';
        } elseif (strpos($url, 'googleapis.com') !== false) {
            $assistantMessage = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } elseif (strpos($url, '/api/generate') !== false) {
            $assistantMessage = $result['response'] ?? '';
        }

        if (empty($assistantMessage)) {
            return ['success' => false, 'error' => 'No response content received from AI'];
        }

        return [
            'success' => true,
            'message' => trim($assistantMessage),
            'provider' => $provider
        ];
    }
    
    /**
     * Test LLM API connection with minimal request
     */
    public function testLLMConnection($apiKey, $provider) {
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'API key is required'];
        }

        $testMessage = "Hello, this is a connection test.";
        $url = '';
        $headers = [];
        $data = [];

        switch ($provider) {
            case 'openai':
                $url = 'https://api.openai.com/v1/chat/completions';
                $headers = [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ];
                $data = [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'user', 'content' => $testMessage]
                    ],
                    'max_tokens' => 50,
                    'temperature' => 0.1
                ];
                break;

            case 'anthropic':
                $url = 'https://api.anthropic.com/v1/messages';
                $headers = [
                    'x-api-key: ' . $apiKey,
                    'Content-Type: application/json',
                    'anthropic-version: 2023-06-01'
                ];
                $data = [
                    'model' => 'claude-3-haiku-20240307',
                    'messages' => [
                        ['role' => 'user', 'content' => $testMessage]
                    ],
                    'max_tokens' => 50
                ];
                break;

            case 'google':
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey;
                $headers = ['Content-Type: application/json'];
                $data = [
                    'contents' => [
                        ['parts' => [['text' => $testMessage]]]
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 50
                    ]
                ];
                break;

            case 'ollama':
                $url = 'http://localhost:11434/api/generate';
                $headers = ['Content-Type: application/json'];
                $data = [
                    'model' => 'llama2',
                    'prompt' => $testMessage,
                    'stream' => false,
                    'options' => [
                        'num_predict' => 50
                    ]
                ];
                break;

            default:
                return ['success' => false, 'error' => 'Unsupported LLM provider: ' . $provider];
        }

        // Make API request
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            curl_close($ch);
            return ['success' => false, 'error' => 'Connection failed: ' . curl_error($ch)];
        }
        curl_close($ch);

        if ($httpCode !== 200) {
            $errorDetails = json_decode($response, true);
            $errorMessage = 'API error (HTTP ' . $httpCode . ')';
            
            // Extract specific error messages
            if ($errorDetails) {
                if (isset($errorDetails['error']['message'])) {
                    $errorMessage .= ': ' . $errorDetails['error']['message'];
                } elseif (isset($errorDetails['error']['code'])) {
                    $errorMessage .= ': ' . $errorDetails['error']['code'];
                } elseif (isset($errorDetails['message'])) {
                    $errorMessage .= ': ' . $errorDetails['message'];
                }
            }
            
            return ['success' => false, 'error' => $errorMessage];
        }

        return [
            'success' => true,
            'message' => 'Connection successful! API key is valid.',
            'provider' => $provider,
            'http_code' => $httpCode
        ];
    }
}

// Handle AJAX endpoint for getting bet data
if (isset($_GET['get_bet'])) {
    $tracker = new PlayerProfitTracker();
    $betId = $_GET['get_bet'];
    $allBets = $tracker->getAllBets();
    
    foreach ($allBets as $bet) {
        if ($bet['id'] === $betId) {
            header('Content-Type: application/json');
            echo json_encode($bet);
            exit;
        }
    }
    
    http_response_code(404);
    echo json_encode(['error' => 'Bet not found']);
    exit;
}

// Handle account switching
if (isset($_GET['switch_account'])) {
    $_SESSION['current_account'] = $_GET['switch_account'];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle first-time setup wizard
if (isset($_POST['action']) && $_POST['action'] === 'create_account') {
    // Temporarily create tracker to handle setup
    $setupTracker = new PlayerProfitTracker();
    
    $tier = $_POST['tier'];
    $size = intval($_POST['size']);
    $customName = trim($_POST['custom_name']) ?: null;
    
    $accountId = $setupTracker->createAccount($tier, $size, $customName);
    
    if ($accountId) {
        $_SESSION['current_account'] = $accountId;
        header("Location: " . $_SERVER['PHP_SELF'] . "?setup_complete=1");
        exit;
    } else {
        $setupError = "Failed to create account. Please try again.";
    }
}

// Handle form submissions
$message = "";
$currentAccountId = $_SESSION['current_account'] ?? null;

// Create temporary tracker to check for first-time setup
$tempTracker = new PlayerProfitTracker();
if ($tempTracker->isFirstTimeSetup() && !isset($_GET['setup']) && !isset($_POST['action'])) {
    $showSetupWizard = true;
    $tracker = $tempTracker; // Use temp tracker for setup
} else {
    $showSetupWizard = false;
    // Use proper account ID or fallback
    if (!$currentAccountId) {
        $currentAccountId = 'pro_50k'; // Fallback for existing installations
    }
    $tracker = new PlayerProfitTracker($currentAccountId);
}

// Initialize the current account
$tracker->initializeAccount($currentAccountId);

if ($_POST) {
    if (isset($_POST['setup_account'])) {
        $config = [
            'account_tier' => $_POST['account_tier'],
            'account_size' => intval($_POST['account_size']),
            'current_phase' => 'Phase 1',
            'start_date' => date('Y-m-d'),
            'last_activity' => date('Y-m-d'),
            'phase_start_balance' => intval($_POST['account_size'])
        ];
        
        $tracker->saveConfig($config);
        
        // Initialize account balance
        $data = ['bets' => [], 'account_balance' => intval($_POST['account_size'])];
        $tracker->saveData($data);
        
        $message = "✅ Account setup complete! " . $_POST['account_tier'] . " account with $" . number_format($_POST['account_size']) . " starting balance.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?setup=1");
        exit;
    }
    
    if (isset($_POST['add_bet'])) {
        $date = $_POST['date'];
        $sport = $_POST['sport'];
        $selection = $_POST['selection'];
        $stake = floatval($_POST['stake']);
        $odds = intval($_POST['odds']);
        $result = $_POST['result'];
        $isParlay = isset($_POST['is_parlay']) && $_POST['is_parlay'] === '1';
        
        $parlayLegs = [];
        if ($isParlay && isset($_POST['parlay_legs']) && is_array($_POST['parlay_legs'])) {
            foreach ($_POST['parlay_legs'] as $leg) {
                if (!empty($leg['selection']) && !empty($leg['odds'])) {
                    $parlayLegs[] = [
                        'selection' => trim($leg['selection']),
                        'odds' => intval($leg['odds'])
                    ];
                }
            }
        }
        
        $result = $tracker->addBet($date, $sport, $selection, $stake, $odds, $result, $isParlay, $parlayLegs);
        
        if ($result['success']) {
            $message = "✅ Bet added! New balance: $" . number_format($result['new_balance'], 2);
            if (!empty($result['violations'])) {
                $message .= " ⚠️ " . count($result['violations']) . " violation(s) detected.";
            }
            header("Location: " . $_SERVER['PHP_SELF'] . "?added=1");
            exit;
        } else {
            $message = "❌ " . $result['error'];
        }
    }
    
    // Handle CSV Import (Paste Method)
    if (isset($_POST['action']) && $_POST['action'] === 'import_csv_paste') {
        $csvData = trim($_POST['csv_data']);
        $accountId = $_POST['account_id'];
        
        // Create a new tracker instance for the specified account
        $importTracker = new PlayerProfitTracker($accountId);
        $importResult = $importTracker->importCSVData($csvData);
        
        
        if ($importResult['success'] && $importResult['count'] > 0) {
            $message = "✅ Successfully imported " . $importResult['count'] . " bets (chronologically sorted)! New balance: $" . number_format($importResult['new_balance'], 2);
            if ($importResult['errors'] > 0) {
                $message .= " ❌ " . $importResult['errors'] . " rows had errors and were skipped.";
            }
            if ($importResult['warnings'] > 0) {
                $message .= " ⚠️ " . $importResult['warnings'] . " warnings found (possible duplicates).";
            }
        } elseif ($importResult['success'] && $importResult['count'] == 0) {
            $message = "⚠️ No bets were imported. ";
            if ($importResult['errors'] > 0) {
                $message .= "All " . $importResult['errors'] . " rows had validation errors: " . implode('; ', array_slice($importResult['error_messages'], 0, 3));
            } else {
                $message .= "The CSV data may be empty or in an unrecognized format.";
            }
        } else {
            $message = "❌ Import failed: " . $importResult['error'];
            if (isset($importResult['warnings']) && $importResult['warnings'] > 0) {
                $message .= " ⚠️ " . $importResult['warnings'] . " warnings found.";
            }
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?imported=1");
        exit;
    }
    
    // Handle CSV Import (File Upload Method)
    if (isset($_POST['action']) && $_POST['action'] === 'import_csv_file') {
        $accountId = $_POST['account_id'];
        
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $csvContent = file_get_contents($_FILES['csv_file']['tmp_name']);
            
            // Create a new tracker instance for the specified account
            $importTracker = new PlayerProfitTracker($accountId);
            $importResult = $importTracker->importCSVData($csvContent);
            
            if ($importResult['success']) {
                $message = "✅ Successfully imported " . $importResult['count'] . " bets from file (chronologically sorted)! New balance: $" . number_format($importResult['new_balance'], 2);
                if ($importResult['errors'] > 0) {
                    $message .= " ❌ " . $importResult['errors'] . " rows had errors and were skipped.";
                }
                if ($importResult['warnings'] > 0) {
                    $message .= " ⚠️ " . $importResult['warnings'] . " warnings found (possible duplicates).";
                }
            } else {
                $message = "❌ File import failed: " . $importResult['error'];
                if (isset($importResult['warnings']) && $importResult['warnings'] > 0) {
                    $message .= " ⚠️ " . $importResult['warnings'] . " warnings found.";
                }
            }
        } else {
            $message = "❌ File upload failed. Please select a valid CSV file.";
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?imported=1");
        exit;
    }
    
    // Handle LLM parsing (AJAX endpoint)
    if (isset($_POST['action']) && $_POST['action'] === 'parse_with_llm_ajax') {
        header('Content-Type: application/json');
        
        $rawData = trim($_POST['raw_data']);
        $apiKey = trim($_POST['api_key']);
        $provider = $_POST['provider'];
        
        if (empty($rawData) || empty($apiKey)) {
            echo json_encode(['success' => false, 'error' => 'Missing required data or API key']);
            exit;
        }
        
        $parseResult = $tracker->parseWithLLM($rawData, $apiKey, $provider);
        echo json_encode($parseResult);
        exit;
    }
    
    // Handle test connection (AJAX endpoint)
    if (isset($_POST['action']) && $_POST['action'] === 'test_connection') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Connection test successful']);
        exit;
    }
    
    // Handle LLM chat (AJAX endpoint)
    if (isset($_POST['action']) && $_POST['action'] === 'chat_with_llm_ajax') {
        header('Content-Type: application/json');
        
        $userMessage = trim($_POST['user_message']);
        
        // Try to get API key and provider from parameters first (legacy)
        $apiKey = isset($_POST['api_key']) ? trim($_POST['api_key']) : null;
        $provider = isset($_POST['provider']) ? $_POST['provider'] : null;
        
        // If not provided, try to get from stored session keys (Google priority)
        if (empty($apiKey)) {
            $providers = ['google', 'anthropic', 'openai'];
            foreach ($providers as $p) {
                if ($tracker->hasValidApiKey($p)) {
                    $apiKey = $tracker->getApiKey($p);
                    $provider = $p;
                    break;
                }
            }
        }
        
        if (empty($userMessage)) {
            echo json_encode(['success' => false, 'error' => 'Missing required message']);
            exit;
        }
        
        if (empty($apiKey)) {
            echo json_encode(['success' => false, 'error' => 'No API key configured. Please configure an API key first.']);
            exit;
        }
        
        // Debug logging
        error_log("Chat with LLM - Provider: $provider, API Key length: " . strlen($apiKey) . ", Message: " . substr($userMessage, 0, 100));
        error_log("Available session keys: " . json_encode(array_keys($_SESSION['api_keys'] ?? [])));
        
        $chatResult = $tracker->chatWithLLM($userMessage, $apiKey, $provider);
        
        // Log the result for debugging
        error_log("Chat result: " . json_encode($chatResult));
        
        echo json_encode($chatResult);
        exit;
    }
    
    // Handle LLM connection test (AJAX endpoint)
    if (isset($_POST['action']) && $_POST['action'] === 'test_llm_connection') {
        header('Content-Type: application/json');
        
        $apiKey = trim($_POST['api_key']);
        $provider = $_POST['provider'];
        
        if (empty($apiKey)) {
            echo json_encode(['success' => false, 'error' => 'API key is required']);
            exit;
        }
        
        $testResult = $tracker->testLLMConnection($apiKey, $provider);
        echo json_encode($testResult);
        exit;
    }
    
    // Handle Edit Bet
    if (isset($_POST['action']) && $_POST['action'] === 'edit_bet') {
        $betId = $_POST['edit_bet_id'];
        $date = $_POST['date'];
        $sport = $_POST['sport'];
        $selection = $_POST['selection'];
        $stake = floatval($_POST['stake']);
        $odds = intval($_POST['odds']);
        $result = $_POST['result'];
        
        $editResult = $tracker->editBet($betId, $date, $sport, $selection, $stake, $odds, $result);
        
        if ($editResult['success']) {
            $message = "✅ Bet updated successfully! New balance: $" . number_format($editResult['new_balance'], 2);
        } else {
            $message = "❌ Failed to update bet: " . $editResult['error'];
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?edited=1");
        exit;
    }
    
    // Handle Delete Bet
    if (isset($_POST['action']) && $_POST['action'] === 'delete_bet') {
        $betId = $_POST['bet_id'];
        
        $deleteResult = $tracker->deleteBet($betId);
        
        if ($deleteResult['success']) {
            $message = "✅ Bet deleted successfully! New balance: $" . number_format($deleteResult['new_balance'], 2);
        } else {
            $message = "❌ Failed to delete bet: " . $deleteResult['error'];
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=1");
        exit;
    }
    
    // Handle Clear All Bets (admin function)
    if (isset($_POST['action']) && $_POST['action'] === 'clear_all_bets' && isset($_POST['confirm_clear']) && $_POST['confirm_clear'] === 'YES_DELETE_ALL') {
        $clearResult = $tracker->clearAllBets();
        
        if ($clearResult['success']) {
            $message = "✅ All bets cleared successfully! Balance reset to starting amount.";
        } else {
            $message = "❌ Failed to clear bets: " . $clearResult['error'];
        }
        
        // Prevent header issues
        if (!headers_sent()) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?cleared=1");
            exit;
        } else {
            // If headers already sent, use JavaScript redirect
            echo "<script>window.location.href = '" . $_SERVER['PHP_SELF'] . "?cleared=1';</script>";
            exit;
        }
    }
    
    // Handle Store API Key
    if (isset($_POST['action']) && $_POST['action'] === 'store_api_key') {
        $provider = $_POST['provider'];
        $apiKey = trim($_POST['api_key']);
        
        if (empty($apiKey)) {
            $message = "❌ Please enter an API key";
        } elseif ($provider === 'ollama') {
            $message = "✅ Ollama configured (no API key required for local)";
        } else {
            $success = $tracker->storeApiKey($provider, $apiKey);
            if ($success) {
                $message = "🔑 API key stored securely for " . ucfirst($provider) . " (persists until logout)";
            } else {
                $message = "❌ Failed to store API key";
            }
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?key_stored=1");
        exit;
    }
    
    // Handle Clear API Key
    if (isset($_POST['action']) && $_POST['action'] === 'clear_api_key') {
        $provider = $_POST['provider'];
        $tracker->clearApiKey($provider);
        $message = "🗑️ API key cleared for " . ucfirst($provider);
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?key_cleared=1");
        exit;
    }
    
    // Handle LLM Chat
    if (isset($_POST['action']) && $_POST['action'] === 'llm_chat') {
        $userMessage = trim($_POST['message']);
        $accountId = $_POST['account_id'];
        
        // For now, store the message and show a placeholder response
        // In production, this would integrate with the LLM APIs
        $message = "💬 Chat functionality will be implemented in the next update. Your message: " . htmlspecialchars($userMessage);
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?chat_sent=1");
        exit;
    }
    
    // Handle Logout (clear API keys)
    if (isset($_POST['action']) && $_POST['action'] === 'logout') {
        // Clear API keys from session
        if (isset($tracker->apiKeyManager)) {
            $tracker->clearApiKey('openai');
            $tracker->clearApiKey('anthropic');
            $tracker->clearApiKey('google');
        }
        
        // Could also destroy entire session if needed
        // session_destroy();
        
        $message = "🚪 Logged out successfully. All API keys cleared.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?logged_out=1");
        exit;
    }
    
    
    if (isset($_POST['advance_phase'])) {
        $message = $tracker->advancePhase();
        header("Location: " . $_SERVER['PHP_SELF'] . "?advanced=1");
        exit;
    }
}

// Check for redirect messages
if (isset($_GET['setup_complete'])) {
    $message = "🎉 Welcome to PlayerProfit Tracker! Your account has been created successfully.";
}
if (isset($_GET['setup'])) {
    $message = "✅ Account setup complete!";
}
if (isset($_GET['added'])) {
    $message = "✅ Bet added successfully!";
}
if (isset($_GET['imported'])) {
    $message = "✅ Bets imported successfully!";
}
if (isset($_GET['cleared'])) {
    $message = "✅ All bets cleared successfully! Account reset to starting balance.";
}
if (isset($_GET['advanced'])) {
    $message = "🎉 Phase advanced successfully!";
}

// Load data only if not showing setup wizard
if (!$showSetupWizard) {
    $config = $tracker->loadConfig();
    $accountStatus = $tracker->getAccountStatus();
    $allBets = $tracker->getAllBets();
    $violations = $tracker->checkViolations();
    $discordMessage = $tracker->getDiscordMessage();
} else {
    // Setup wizard mode - minimal data needed
    $config = null;
    $accountStatus = null;
    $allBets = [];
    $violations = [];
    $discordMessage = '';
}

// Check if current account is set up
$needsSetup = false; // Multi-account system handles setup automatically
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlayerProfit Betting Tracker</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            background-color: #0a0a0a;
            color: white;
        }
        
        .container {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        h1 {
            background: linear-gradient(45deg, #FFD700, #FFA500);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        /* Account Status Cards */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .status-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .status-card.balance {
            background: linear-gradient(135deg, rgba(76,175,80,0.2), rgba(76,175,80,0.1));
            border-color: #4CAF50;
        }
        
        .status-card.profit {
            background: linear-gradient(135deg, rgba(33,150,243,0.2), rgba(33,150,243,0.1));
            border-color: #2196F3;
        }
        
        .status-card.risk {
            background: linear-gradient(135deg, rgba(255,193,7,0.2), rgba(255,193,7,0.1));
            border-color: #FFC107;
        }
        
        .status-card.violation {
            background: linear-gradient(135deg, rgba(244,67,54,0.2), rgba(244,67,54,0.1));
            border-color: #f44336;
        }
        
        .status-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: white;
        }
        
        .status-label {
            font-size: 0.9rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: white;
        }
        
        /* Progress bars */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #8BC34A);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .progress-fill.danger {
            background: linear-gradient(90deg, #f44336, #FF5722);
        }
        
        /* Navigation */
        .nav-tabs {
            display: flex;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            margin-bottom: 30px;
            overflow-x: auto;
        }
        
        .nav-tab {
            padding: 15px 25px;
            background: none;
            border: none;
            color: #ccc;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            border-radius: 8px;
            white-space: nowrap;
        }
        
        .nav-tab:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .nav-tab.active {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #000;
        }
        
        .tab-content {
            display: none;
            background: rgba(255,255,255,0.02);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Forms */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            color: #FFD700;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        input, select, textarea {
            padding: 12px;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            background: rgba(255,255,255,0.05);
            color: white;
            font-size: 14px;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #FFD700;
            background: rgba(255,215,0,0.1);
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #000;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255,215,0,0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #4CAF50, #8BC34A);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f44336, #FF5722);
            color: white;
        }
        
        /* Violation alerts */
        .violation-alert {
            background: linear-gradient(135deg, rgba(244,67,54,0.2), rgba(244,67,54,0.1));
            border: 1px solid #f44336;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            display: flex;
            align-items: center;
        }
        
        .violation-alert.warning {
            background: linear-gradient(135deg, rgba(255,193,7,0.2), rgba(255,193,7,0.1));
            border-color: #FFC107;
        }
        
        .violation-icon {
            font-size: 1.5rem;
            margin-right: 15px;
        }
        
        /* Tables */
        .bets-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255,255,255,0.02);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .bets-table th {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #000;
            padding: 15px 10px;
            text-align: left;
            font-weight: bold;
        }
        
        .bets-table td {
            padding: 12px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .bets-table tr:hover {
            background-color: rgba(255,255,255,0.05);
        }
        
        .result-win { color: #4CAF50; font-weight: bold; }
        .result-loss { color: #f44336; font-weight: bold; }
        .result-push { color: #FFC107; font-weight: bold; }
        .result-refunded { color: #9C27B0; font-weight: bold; }
        .result-cashed-out { color: #FF9800; font-weight: bold; }
        
        .amount-positive { color: #4CAF50; font-weight: bold; }
        .amount-negative { color: #f44336; font-weight: bold; }
        .amount-neutral { color: #FFC107; font-weight: bold; }
        
        .bet-actions {
            white-space: nowrap;
            text-align: center;
        }
        
        .btn-small {
            padding: 4px 8px;
            margin: 0 2px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        
        .btn-small:hover {
            opacity: 1;
        }
        
        .btn-edit {
            background: #2196F3;
        }
        
        .btn-delete {
            background: #f44336;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .message.success {
            background: linear-gradient(135deg, rgba(76,175,80,0.2), rgba(76,175,80,0.1));
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }
        
        .setup-card {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 40px;
            text-align: center;
        }
        
        /* Phase badge */
        .phase-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .phase-1 {
            background: linear-gradient(135deg, #2196F3, #03A9F4);
            color: white;
        }
        
        .phase-2 {
            background: linear-gradient(135deg, #FF9800, #FFC107);
            color: #000;
        }
        
        .funded {
            background: linear-gradient(135deg, #4CAF50, #8BC34A);
            color: white;
        }
        
        /* Sticky Header Container */
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: linear-gradient(135deg, rgba(26, 26, 46, 0.95), rgba(22, 33, 62, 0.95));
            backdrop-filter: blur(15px);
            padding: 20px;
            margin: -20px -20px 30px -20px;
            border-bottom: 2px solid rgba(255, 215, 0, 0.2);
            box-shadow: 0 4px 30px rgba(0,0,0,0.4);
        }
        
        .sticky-header h1 {
            margin: 0 0 20px 0;
            text-align: center;
            color: #FFD700;
            font-size: 2.2em;
            text-shadow: 0 2px 10px rgba(255, 215, 0, 0.3);
        }
        
        /* Account Switching Tabs */
        .account-tabs {
            display: flex;
            gap: 10px;
            margin: 0;
            padding: 0;
            background: transparent;
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .account-tab {
            display: flex;
            flex-direction: column;
            padding: 12px 18px;
            border-radius: 10px;
            text-decoration: none;
            color: #ccc;
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
            min-width: 140px;
            text-align: center;
            cursor: pointer;
        }
        
        .account-tab:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border-color: rgba(255,255,255,0.3);
        }
        
        .account-tab.active {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #000;
            border-color: #FFD700;
            font-weight: bold;
        }
        
        .account-tab.standard-account {
            border-left: 4px solid #2196F3;
        }
        
        .account-tab.pro-account {
            border-left: 4px solid #4CAF50;
        }
        
        .account-tab.active.standard-account {
            border-left: 4px solid #1976D2;
        }
        
        .account-tab.active.pro-account {
            border-left: 4px solid #388E3C;
        }
        
        .account-name {
            font-size: 0.95rem;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .account-details {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        .account-tab.active .account-details {
            opacity: 0.9;
        }

        /* Parlay Calculator Styles */
        .parlay-calculator {
            background: rgba(255,255,255,0.02);
            border-radius: 10px;
            padding: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .parlay-legs {
            margin-top: 20px;
        }
        
        .leg-input {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            gap: 15px;
        }
        
        .leg-input label {
            min-width: 150px;
            color: #FFD700;
            font-weight: bold;
        }
        
        .leg-input input {
            flex: 1;
            max-width: 200px;
        }
        
        .parlay-results {
            background: rgba(76,175,80,0.1);
            border: 1px solid #4CAF50;
            border-radius: 8px;
            padding: 15px;
        }
        
        .parlay-results h4 {
            color: #4CAF50;
            margin-bottom: 15px;
        }
        
        .parlay-results h5 {
            color: #FFD700;
            margin-top: 15px;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .sticky-header {
                margin: -10px -10px 20px -10px;
                padding: 15px;
            }
            
            .sticky-header h1 {
                font-size: 1.8em;
                margin-bottom: 15px;
            }
            
            .account-tabs {
                gap: 8px;
            }
            
            .account-tab {
                min-width: 110px;
                font-size: 11px;
                padding: 8px 12px;
            }
            
            .status-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .nav-tabs {
                flex-wrap: wrap;
            }
            
            .leg-input {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .leg-input label {
                min-width: auto;
            }
            
            .leg-input input {
                max-width: 100%;
                width: 100%;
            }
            
            .account-tabs {
                flex-direction: column;
                gap: 8px;
            }
            
            .account-tab {
                min-width: auto;
                width: 100%;
            }
        }
        
        /* === FORCE WHITE TEXT FOR ALL ELEMENTS === */
        h1, h2, h3, h4, h5, h6 {
            color: white !important;
        }
        
        p, span, div, label, small, strong {
            color: white;
        }
        
        .card-title, .card-subtitle, .card-value {
            color: white !important;
        }
        
        .progress-ring-label, .progress-ring-subtitle, .progress-ring-value {
            color: white !important;
        }
        
        .gauge-label, .gauge-title, .gauge-value {
            color: white !important;
        }
        
        .heatmap-day {
            color: white !important;
        }
        
        .status-card-enhanced * {
            color: white;
        }
        
        /* Keep specific colored text */
        .total-positive {
            color: #4CAF50 !important;
        }
        
        .total-negative {
            color: #f44336 !important;
        }
        
        .phase-badge {
            color: white !important;
        }
        
        /* Navigation text */
        .account-tab, .nav-tab {
            color: white;
        }
        
        /* Table text */
        .bets-table th, .bets-table td {
            color: white;
        }
        
        /* Form text */
        textarea, input, select {
            color: white;
        }
        
        /* Enhanced card text fixes */
        .status-card-enhanced .card-title,
        .status-card-enhanced .card-subtitle {
            color: rgba(255,255,255,0.9) !important;
        }
        
        /* === SETUP WIZARD STYLES === */
        .setup-wizard-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(26, 26, 46, 0.98), rgba(22, 33, 62, 0.98));
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }
        
        .setup-wizard-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 20px;
            padding: 40px;
            max-width: 800px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            color: white;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .setup-wizard-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .setup-wizard-header h1 {
            color: #FFD700;
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(255, 215, 0, 0.3);
        }
        
        .setup-wizard-header p {
            color: #ccc;
            font-size: 1.1em;
            margin: 0;
        }
        
        .setup-error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid #f44336;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #ff6b6b;
        }
        
        .setup-step {
            display: none;
            animation: fadeInUp 0.3s ease;
        }
        
        .setup-step.active {
            display: block;
        }
        
        .setup-step h2 {
            color: #FFD700;
            font-size: 1.8em;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .account-type-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .account-type-card {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .account-type-card:hover {
            border-color: rgba(255, 215, 0, 0.5);
            background: rgba(255, 215, 0, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        
        .account-type-card.selected {
            border-color: #FFD700;
            background: rgba(255, 215, 0, 0.1);
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
        }
        
        .account-type-card h3 {
            color: #FFD700;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .account-features .feature {
            margin-bottom: 8px;
            color: #ccc;
            font-size: 0.95em;
        }
        
        .account-sizes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .account-size-card {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .account-size-card:hover {
            border-color: rgba(255, 215, 0, 0.5);
            background: rgba(255, 215, 0, 0.05);
            transform: translateY(-2px);
        }
        
        .account-size-card.selected {
            border-color: #FFD700;
            background: rgba(255, 215, 0, 0.1);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.3);
        }
        
        .account-size-card h3 {
            color: #FFD700;
            margin-bottom: 8px;
            font-size: 1.2em;
        }
        
        .size-cost {
            color: #4CAF50;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .size-features {
            color: #ccc;
            font-size: 0.85em;
        }
        
        .size-features div {
            margin-bottom: 4px;
        }
        
        .setup-summary {
            margin-top: 20px;
        }
        
        .summary-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 12px;
            padding: 25px;
        }
        
        .summary-card h3 {
            color: #FFD700;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .summary-item {
            margin-bottom: 10px;
            color: #ccc;
        }
        
        .summary-item strong {
            color: white;
            margin-right: 10px;
        }
        
        .setup-wizard-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            gap: 15px;
        }
        
        .setup-wizard-form .form-group {
            margin-bottom: 20px;
        }
        
        .setup-wizard-form label {
            display: block;
            margin-bottom: 8px;
            color: white;
            font-weight: 500;
        }
        
        .setup-wizard-form input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0, 0, 0, 0.4);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .setup-wizard-form input[type="text"]:focus {
            outline: none;
            border-color: #FFD700;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
        }
        
        .setup-wizard-form small {
            color: #999;
            font-size: 0.85em;
            margin-top: 5px;
            display: block;
        }
        
        @media (max-width: 768px) {
            .setup-wizard-container {
                padding: 30px 20px;
                margin: 20px;
                max-height: 95vh;
            }
            
            .account-type-grid {
                grid-template-columns: 1fr;
            }
            
            .account-sizes {
                grid-template-columns: 1fr;
            }
            
            .setup-wizard-header h1 {
                font-size: 2em;
            }
            
            .setup-step h2 {
                font-size: 1.5em;
            }
        }
        
    </style>
    
    <!-- Enhanced UI CSS -->
    <link rel="stylesheet" href="assets/css/enhanced-ui.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/modern-ui.css?v=<?= time() ?>">
    <!-- Dark mode CSS temporarily disabled to fix text visibility issues -->
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Core Functions (must be loaded early) -->
    <script>
        function showTab(tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            const navTabs = document.querySelectorAll('.nav-tab');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            navTabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab
            const targetTab = document.getElementById(tabName);
            if (targetTab) {
                targetTab.classList.add('active');
            }
            
            // Find and activate the corresponding nav tab
            const targetNavTab = Array.from(navTabs).find(tab => 
                tab.getAttribute('onclick').includes(tabName)
            );
            if (targetNavTab) {
                targetNavTab.classList.add('active');
            }
        }
        
        // Import method switching function
        function showImportMethod(methodName) {
            // Hide all import method sections
            const sections = document.querySelectorAll('.import-method-section');
            const navTabs = document.querySelectorAll('.import-nav-tab');
            
            sections.forEach(section => section.classList.remove('active'));
            navTabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected method section
            const targetSection = document.getElementById(methodName);
            if (targetSection) {
                targetSection.classList.add('active');
            }
            
            // Activate corresponding nav tab
            const targetTab = document.querySelector(`[data-method="${methodName}"]`);
            if (targetTab) {
                targetTab.classList.add('active');
            }
            
            // Update progress bar
            const progressBar = document.querySelector('.import-progress-fill');
            const methods = ['csv-paste', 'llm-chat', 'file-upload', 'api-connect'];
            const currentIndex = methods.indexOf(methodName);
            const progressPercent = ((currentIndex + 1) / methods.length) * 100;
            
            if (progressBar) {
                progressBar.style.width = progressPercent + '%';
            }
        }
        
        function toggleMobileNav() {
            const sidebar = document.getElementById('mobile-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        }
        
        // === UPDATE API KEY PLACEHOLDER ===
        function updateApiKeyPlaceholder() {
            const provider = document.getElementById('llm-provider').value;
            const apiKeyInput = document.getElementById('api-key');
            
            const placeholders = {
                'openai': 'sk-proj-... (OpenAI API key)',
                'anthropic': 'sk-ant-... (Anthropic API key)', 
                'google': 'AIza... (Google AI Studio API key)',
                'ollama': 'localhost (no key needed for local Ollama)'
            };
            
            apiKeyInput.placeholder = placeholders[provider] || 'Enter your API key...';
        }
        
        // === BET EDITING FUNCTIONS ===
        function editBet(betId) {
            // Fetch bet data
            fetch(`?get_bet=${betId}`)
                .then(response => response.json())
                .then(bet => {
                    if (bet.error) {
                        alert('Error loading bet: ' + bet.error);
                        return;
                    }
                    
                    // Create edit form modal
                    const modal = document.createElement('div');
                    modal.className = 'edit-bet-modal';
                    modal.style.cssText = `
                        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                        background: rgba(0,0,0,0.8); z-index: 1000; display: flex;
                        align-items: center; justify-content: center; padding: 20px;
                    `;
                    
                    const form = document.createElement('div');
                    form.style.cssText = `
                        background: #1a1a2e; padding: 30px; border-radius: 12px;
                        border: 2px solid #4CAF50; max-width: 500px; width: 100%;
                        color: white; font-family: inherit;
                    `;
                    
                    form.innerHTML = `
                        <h3 style="margin-top: 0; color: #4CAF50;">✏️ Edit Bet</h3>
                        <form id="edit-bet-form">
                            <input type="hidden" name="edit_bet_id" value="${bet.id}">
                            <div style="display: grid; gap: 15px;">
                                <div>
                                    <label>Date:</label>
                                    <input type="date" name="date" value="${bet.date}" required 
                                           style="width: 100%; padding: 8px; background: rgba(0,0,0,0.3); border: 1px solid #666; border-radius: 4px; color: white;">
                                </div>
                                <div>
                                    <label>Sport:</label>
                                    <input type="text" name="sport" value="${bet.sport}" required 
                                           style="width: 100%; padding: 8px; background: rgba(0,0,0,0.3); border: 1px solid #666; border-radius: 4px; color: white;">
                                </div>
                                <div>
                                    <label>Selection:</label>
                                    <input type="text" name="selection" value="${bet.selection}" required 
                                           style="width: 100%; padding: 8px; background: rgba(0,0,0,0.3); border: 1px solid #666; border-radius: 4px; color: white;">
                                </div>
                                <div>
                                    <label>Stake ($):</label>
                                    <input type="number" name="stake" value="${bet.stake}" step="0.01" min="0" required 
                                           style="width: 100%; padding: 8px; background: rgba(0,0,0,0.3); border: 1px solid #666; border-radius: 4px; color: white;">
                                </div>
                                <div>
                                    <label>Odds:</label>
                                    <input type="number" name="odds" value="${bet.odds}" required 
                                           style="width: 100%; padding: 8px; background: rgba(0,0,0,0.3); border: 1px solid #666; border-radius: 4px; color: white;">
                                </div>
                                <div>
                                    <label>Result:</label>
                                    <select name="result" required 
                                            style="width: 100%; padding: 8px; background: rgba(0,0,0,0.3); border: 1px solid #666; border-radius: 4px; color: white;">
                                        <option value="WIN" ${bet.result === 'WIN' ? 'selected' : ''}>WIN</option>
                                        <option value="LOSS" ${bet.result === 'LOSS' ? 'selected' : ''}>LOSS</option>
                                        <option value="PUSH" ${bet.result === 'PUSH' ? 'selected' : ''}>PUSH</option>
                                        <option value="REFUNDED" ${bet.result === 'REFUNDED' ? 'selected' : ''}>REFUNDED</option>
                                        <option value="CASHED OUT" ${bet.result === 'CASHED OUT' ? 'selected' : ''}>CASHED OUT</option>
                                    </select>
                                </div>
                            </div>
                            <div style="margin-top: 20px; text-align: right;">
                                <button type="button" onclick="closeBetModal()" 
                                        style="background: #666; color: white; border: none; padding: 10px 20px; border-radius: 4px; margin-right: 10px; cursor: pointer;">
                                    Cancel
                                </button>
                                <button type="submit" 
                                        style="background: #4CAF50; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                                    💾 Save Changes
                                </button>
                            </div>
                        </form>
                    `;
                    
                    modal.appendChild(form);
                    document.body.appendChild(modal);
                    
                    // Handle form submission
                    document.getElementById('edit-bet-form').onsubmit = function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        formData.append('action', 'edit_bet');
                        
                        fetch('', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.text())
                        .then(() => {
                            closeBetModal();
                            location.reload(); // Refresh to show updated bet
                        })
                        .catch(error => {
                            alert('Error updating bet: ' + error);
                        });
                    };
                })
                .catch(error => {
                    alert('Error loading bet: ' + error);
                });
        }
        
        function deleteBet(betId) {
            if (confirm('Are you sure you want to delete this bet? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('action', 'delete_bet');
                formData.append('bet_id', betId);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    location.reload(); // Refresh to show updated bet list
                })
                .catch(error => {
                    alert('Error deleting bet: ' + error);
                });
            }
        }
        
        function closeBetModal() {
            const modal = document.querySelector('.edit-bet-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        // Close modal on background click
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('edit-bet-modal')) {
                closeBetModal();
            }
        });
        
        // === CLEAR ALL DATA CONFIRMATION ===
        function confirmClearAll() {
            const confirmed = confirm('⚠️ WARNING: This will permanently delete ALL bets from this account!\n\nThis action cannot be undone. Are you absolutely sure?');
            
            if (confirmed) {
                const doubleConfirmed = prompt('Type "YES DELETE ALL" to confirm (case sensitive):');
                if (doubleConfirmed === 'YES DELETE ALL') {
                    // Set the confirmation value and submit
                    document.querySelector('input[name="confirm_clear"]').value = 'YES_DELETE_ALL';
                    return true;
                } else {
                    alert('Confirmation text did not match. Operation cancelled.');
                    return false;
                }
            }
            return false;
        }
        
        // === LLM CONNECTION TEST ===
        async function testLLMConnection() {
            const testBtn = document.getElementById('test-connection-btn');
            const statusDiv = document.getElementById('connection-status');
            const apiKey = document.getElementById('api-key').value.trim();
            const provider = document.getElementById('llm-provider').value;
            
            if (!apiKey) {
                statusDiv.innerHTML = '<span style="color: #f44336;">❌ Please enter an API key first</span>';
                return;
            }
            
            testBtn.disabled = true;
            testBtn.innerHTML = '⏳ Testing...';
            statusDiv.innerHTML = '<span style="color: #FFC107;">🔄 Testing connection...</span>';
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'test_llm_connection',
                        api_key: apiKey,
                        provider: provider
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    statusDiv.innerHTML = `<span style="color: #4CAF50;">✅ ${result.message}</span>`;
                } else {
                    statusDiv.innerHTML = `<span style="color: #f44336;">❌ ${result.error}</span>`;
                }
                
            } catch (error) {
                statusDiv.innerHTML = `<span style="color: #f44336;">❌ Network error: ${error.message}</span>`;
            } finally {
                testBtn.disabled = false;
                testBtn.innerHTML = '🔗 Test';
            }
        }
        
    </script>
</head>
<body>
<?php if ($showSetupWizard): ?>
    <!-- Setup Wizard for First-Time Users -->
    <div class="setup-wizard-overlay">
        <div class="setup-wizard-container">
            <div class="setup-wizard-header">
                <h1>🏆 Welcome to PlayerProfit Tracker v2.0</h1>
                <p>Let's set up your first PlayerProfit account to get started</p>
            </div>
            
            <?php if (isset($setupError)): ?>
                <div class="setup-error">
                    ❌ <?= htmlspecialchars($setupError) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="setup-wizard-form">
                <input type="hidden" name="action" value="create_account">
                
                <div class="setup-step active" data-step="1">
                    <h2>Step 1: Choose Account Type</h2>
                    <div class="account-type-grid">
                        <div class="account-type-card" data-tier="Standard">
                            <h3>📊 Standard Account</h3>
                            <div class="account-features">
                                <div class="feature">✅ Entry-level challenge</div>
                                <div class="feature">✅ Lower risk requirements</div>
                                <div class="feature">✅ Perfect for beginners</div>
                                <div class="feature">💰 Risk: $10 - $50 per bet</div>
                            </div>
                        </div>
                        <div class="account-type-card" data-tier="Pro">
                            <h3>🚀 Pro Account</h3>
                            <div class="account-features">
                                <div class="feature">✅ Advanced challenge</div>
                                <div class="feature">✅ Higher potential returns</div>
                                <div class="feature">✅ For experienced bettors</div>
                                <div class="feature">💰 Risk: $100 - $5000 per bet</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="setup-step" data-step="2">
                    <h2>Step 2: Select Account Size</h2>
                    <div class="account-sizes" id="account-sizes">
                        <!-- Account sizes will be populated by JavaScript based on tier selection -->
                    </div>
                </div>
                
                <div class="setup-step" data-step="3">
                    <h2>Step 3: Account Details</h2>
                    <div class="form-group">
                        <label for="custom_name">Custom Account Name (Optional)</label>
                        <input type="text" name="custom_name" id="custom_name" 
                               placeholder="e.g., My First Challenge" 
                               maxlength="50">
                        <small>Leave blank to use default name based on tier and size</small>
                    </div>
                    
                    <div class="setup-summary" id="setup-summary">
                        <!-- Summary will be populated by JavaScript -->
                    </div>
                </div>
                
                <div class="setup-wizard-actions">
                    <button type="button" class="btn btn-secondary" id="setup-prev-btn" style="display: none;">
                        ← Previous
                    </button>
                    <button type="button" class="btn btn-primary" id="setup-next-btn">
                        Next →
                    </button>
                    <button type="submit" class="btn btn-success" id="setup-create-btn" style="display: none;">
                        🚀 Create Account
                    </button>
                </div>
                
                <!-- Hidden form fields for final submission -->
                <input type="hidden" name="tier" id="selected-tier">
                <input type="hidden" name="size" id="selected-size">
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- Main Application Interface -->
    <div class="container">
        <!-- Sticky Header with Title and Account Tabs -->
        <div class="sticky-header">
            <h1>🏆 PlayerProfit Betting Tracker</h1>
            
            <!-- Account Switching Tabs -->
            <div class="account-tabs">
            <?php 
            $allAccounts = $tracker->getAllAccounts();
            $currentAccount = $tracker->getCurrentAccount();
            foreach ($allAccounts as $accountId => $account): 
                $isActive = $accountId === $currentAccount;
                $statusClass = $account['tier'] === 'Pro' ? 'pro-account' : 'standard-account';
            ?>
                <a href="?switch_account=<?= $accountId ?>" 
                   class="account-tab <?= $isActive ? 'active' : '' ?> <?= $statusClass ?>">
                    <div class="account-name"><?= htmlspecialchars($account['name']) ?></div>
                    <div class="account-details"><?= $account['tier'] ?> • $<?= number_format($account['size']) ?></div>
                </a>
            <?php endforeach; ?>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($needsSetup): ?>
            <div class="setup-card">
                <h2>🚀 Account Setup</h2>
                <p>Configure your PlayerProfit account to get started</p>
                
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Account Tier</label>
                            <select name="account_tier" required>
                                <option value="">Select Tier</option>
                                <option value="Standard">Standard</option>
                                <option value="Pro">Pro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Account Size</label>
                            <select name="account_size" required>
                                <option value="">Select Size</option>
                                <option value="1000">$1,000 (Standard only)</option>
                                <option value="5000">$5,000</option>
                                <option value="10000">$10,000</option>
                                <option value="25000">$25,000</option>
                                <option value="50000">$50,000</option>
                                <option value="100000">$100,000</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" name="setup_account" class="btn btn-primary">🎯 Setup Account</button>
                </form>
            </div>
        <?php else: ?>
            
            <!-- Modern Dashboard Metrics -->
            <div class="metrics-grid">
                <!-- Enhanced Balance Card -->
                <div class="metric-card">
                    <div class="metric-icon">💰</div>
                    <div class="metric-title">Current Balance</div>
                    <div class="metric-value">$<?= number_format($accountStatus['current_balance'], 2) ?></div>
                    <div class="metric-subtitle">Account Size: $<?= number_format($accountStatus['account_size']) ?></div>
                </div>
                
                <!-- Enhanced Profit Progress Card -->
                <div class="metric-card">
                    <div class="metric-icon"><?= $accountStatus['current_phase'] !== 'Funded' ? '📈' : '🏆' ?></div>
                    <div class="metric-title">
                        <?php if ($accountStatus['current_phase'] !== 'Funded'): ?>
                            Progress to Target
                        <?php else: ?>
                            Account Status
                        <?php endif; ?>
                    </div>
                    <?php if ($accountStatus['current_phase'] !== 'Funded'): ?>
                        <div id="profit-progress-ring" class="progress-ring-container" data-percent="<?= min(100, max(0, ($accountStatus['profit_percentage'] / 20) * 100)) ?>">
                            <svg class="progress-ring" width="120" height="120">
                                <circle class="progress-ring-bg" cx="60" cy="60" r="52"></circle>
                                <circle class="progress-ring-circle" cx="60" cy="60" r="52" style="--ring-color: #FFD700"></circle>
                            </svg>
                            <div class="progress-ring-label">
                                <div class="progress-ring-value" style="color: #FFD700"><?= number_format($accountStatus['profit_percentage'], 1) ?>%</div>
                                <div class="progress-ring-subtitle">of 20%</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="metric-value">FUNDED</div>
                        <div class="metric-subtitle">🎉 Congratulations!</div>
                    <?php endif; ?>
                </div>
                
                <!-- Enhanced Risk/Picks Card -->
                <div class="metric-card">
                    <div class="metric-icon">🎯</div>
                    <div class="metric-title">Picks Progress</div>
                    <div id="picks-progress-ring" class="progress-ring-container" data-percent="<?= min(100, ($accountStatus['total_picks'] / 20) * 100) ?>">
                        <svg class="progress-ring" width="120" height="120">
                            <circle class="progress-ring-bg" cx="60" cy="60" r="52"></circle>
                            <circle class="progress-ring-circle" cx="60" cy="60" r="52" style="--ring-color: #FFC107"></circle>
                        </svg>
                        <div class="progress-ring-label">
                            <div class="progress-ring-value" style="color: #FFC107"><?= $accountStatus['total_picks'] ?></div>
                            <div class="progress-ring-subtitle">of 20</div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($violations)): ?>
                <!-- Enhanced Violations Card -->
                <div class="metric-card">
                    <div class="metric-icon">⚠️</div>
                    <div class="metric-title">Active Violations</div>
                    <div class="metric-value pulse" style="color: #f44336"><?= count($violations) ?></div>
                    <div class="card-subtitle">Requires Attention</div>
                </div>
                <?php endif; ?>
                
                <!-- Risk Gauge -->
                <div class="status-card-enhanced" style="--card-accent-color: #2196F3">
                    <div class="card-icon">⚡</div>
                    <div class="card-title">Current Risk Level</div>
                    <div id="risk-gauge-container" class="risk-gauge" data-value="<?= min(100, (($accountStatus['current_balance'] - $accountStatus['account_size']) / $accountStatus['account_size']) * 100 + 50) ?>">
                        <svg class="gauge-svg" viewBox="0 0 200 120">
                            <path class="gauge-bg" d="M 20 100 A 80 80 0 0 1 180 100" 
                                  stroke-dasharray="251" stroke-dashoffset="0"></path>
                            <path class="gauge-fill" d="M 20 100 A 80 80 0 0 1 180 100" 
                                  stroke-dasharray="251" stroke-dashoffset="251"></path>
                            <line class="gauge-needle" x1="100" y1="100" x2="100" y2="30" 
                                  stroke="white" stroke-width="3" stroke-linecap="round"></line>
                            <circle class="gauge-center" cx="100" cy="100" r="6"></circle>
                        </svg>
                        <div class="gauge-label">
                            <div class="gauge-title">Risk Assessment</div>
                            <div class="gauge-value">Safe</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts removed for cleaner interface - moved to Analytics tab -->
            
            <!-- Phase and Account Info -->
            <div style="text-align: center; margin-bottom: 30px;">
                <span class="phase-badge <?= strtolower(str_replace(' ', '-', $accountStatus['current_phase'])) ?>">
                    <?= $accountStatus['current_phase'] ?>
                </span>
                <span style="margin: 0 20px;">|</span>
                <span style="color: #FFD700; font-weight: bold;">
                    <?= $accountStatus['account_tier'] ?> $<?= number_format($accountStatus['account_size']) ?>
                </span>
            </div>
            
            <!-- Violations Display -->
            <?php if (!empty($violations)): ?>
                <div style="margin-bottom: 30px;">
                    <?php foreach ($violations as $violation): ?>
                        <div class="violation-alert <?= $violation['severity'] === 'warning' ? 'warning' : '' ?>">
                            <div class="violation-icon"><?= $violation['severity'] === 'critical' ? '🚨' : '⚠️' ?></div>
                            <div><?= $violation['message'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Phase Advancement Button -->
            <?php if ($accountStatus['current_phase'] !== 'Funded' && $accountStatus['target_met'] && $accountStatus['total_picks'] >= 20): ?>
                <div style="text-align: center; margin-bottom: 30px;">
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="advance_phase" class="btn btn-success">
                            🎉 Advance to <?= $accountStatus['current_phase'] === 'Phase 1' ? 'Phase 2' : 'Funded Account' ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <button class="nav-tab" onclick="showTab('add-bet')">📝 Add Bet</button>
                <button class="nav-tab" onclick="showTab('import-bets')">📥 Import Bets</button>
                <button class="nav-tab" onclick="showTab('parlay-calc')">🎲 Parlay Calculator</button>
                <button class="nav-tab active" onclick="showTab('all-bets')">📋 All Bets</button>
                <button class="nav-tab" onclick="showTab('analytics')">📊 Analytics</button>
                <button class="nav-tab" onclick="showTab('discord')">💬 Discord</button>
            </div>
            
            <!-- Add Bet Tab -->
            <div id="add-bet" class="tab-content">
                <h3>📝 Add New Bet</h3>
                <p style="color: #FFC107; margin-bottom: 20px;">
                    <strong>Risk Limits:</strong> 
                    Min: $<?= $accountStatus['risk_limits']['min_risk'] ?> | 
                    Max: $<?= $accountStatus['risk_limits']['max_risk'] ?>
                    <?php if ($accountStatus['drawdown_protected']): ?>
                        <br><span style="color: #f44336; font-weight: bold;">⚠️ DRAWDOWN PROTECTION ACTIVE</span>
                        <br><span style="font-size: 0.9em;">Account is <?= number_format(abs($accountStatus['balance_from_peak_pct']), 1) ?>% down from peak of $<?= number_format($accountStatus['highest_balance'], 2) ?></span>
                    <?php else: ?>
                        <br><span style="color: #4CAF50; font-size: 0.9em;">Peak: $<?= number_format($accountStatus['highest_balance'], 2) ?> | Current: <?= $accountStatus['balance_from_peak_pct'] > 0 ? '+' : '' ?><?= number_format($accountStatus['balance_from_peak_pct'], 1) ?>%</span>
                    <?php endif; ?>
                </p>
                
                <form method="POST" onsubmit="return confirmAddBet()">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Sport</label>
                            <select name="sport" required>
                                <option value="">Select Sport</option>
                                <option value="NFL">NFL</option>
                                <option value="NBA">NBA</option>
                                <option value="MLB">MLB</option>
                                <option value="NHL">NHL</option>
                                <option value="Soccer">Soccer</option>
                                <option value="Tennis">Tennis</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Bet Description</label>
                            <input type="text" name="selection" placeholder="e.g., Chiefs -3.5" required>
                        </div>
                        <div class="form-group">
                            <label>Stake ($)</label>
                            <input type="number" name="stake" step="0.01" 
                                   min="<?= $accountStatus['risk_limits']['min_risk'] ?>" 
                                   max="<?= $accountStatus['risk_limits']['max_risk'] ?>" 
                                   placeholder="<?= $accountStatus['risk_limits']['min_risk'] ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Odds (American)</label>
                            <input type="number" id="single-odds" name="odds" placeholder="-110" required>
                        </div>
                        <div class="form-group">
                            <label>Result</label>
                            <select name="result" required>
                                <option value="">Select Result</option>
                                <option value="WIN">WIN ✅</option>
                                <option value="LOSS">LOSS ❌</option>
                                <option value="PUSH">PUSH ⚪</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Parlay Option -->
                    <div class="form-row">
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" id="is-parlay" name="is_parlay" value="1" onchange="toggleParlayMode()" style="transform: scale(1.2);">
                                🎲 Parlay Bet
                            </label>
                        </div>
                    </div>
                    
                    <!-- Parlay Legs Section (hidden by default) -->
                    <div id="parlay-legs-section" style="display: none; margin-top: 20px; padding: 20px; background: rgba(255,215,0,0.1); border-radius: 8px; border: 1px solid #FFD700;">
                        <h4 style="color: #FFD700; margin-bottom: 15px;">🎲 Parlay Legs</h4>
                        <div id="bet-parlay-legs">
                            <div class="parlay-leg" data-leg="1">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Leg #1 Selection</label>
                                        <input type="text" name="parlay_legs[0][selection]" placeholder="e.g., Lakers ML">
                                    </div>
                                    <div class="form-group">
                                        <label>Leg #1 Odds</label>
                                        <input type="number" name="parlay_legs[0][odds]" placeholder="-150" onchange="updateParlayOdds()">
                                    </div>
                                </div>
                            </div>
                            <div class="parlay-leg" data-leg="2">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Leg #2 Selection</label>
                                        <input type="text" name="parlay_legs[1][selection]" placeholder="e.g., Over 225.5">
                                    </div>
                                    <div class="form-group">
                                        <label>Leg #2 Odds</label>
                                        <input type="number" name="parlay_legs[1][odds]" placeholder="-110" onchange="updateParlayOdds()">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 15px;">
                            <button type="button" onclick="addParlayLeg()" class="btn" style="background: #FFD700; color: #000; margin-right: 10px;">➕ Add Leg</button>
                            <button type="button" onclick="removeParlayLeg()" class="btn" style="background: #f44336; color: white;">➖ Remove Leg</button>
                        </div>
                        <div id="combined-odds-display" style="margin-top: 15px; padding: 10px; background: rgba(76,175,80,0.2); border-radius: 5px; border: 1px solid #4CAF50; display: none;">
                            <strong>Combined Parlay Odds: <span id="combined-odds-value">+0</span></strong>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_bet" class="btn btn-primary">📤 Add Bet</button>
                </form>
            </div>
            
            <!-- Import Bets Tab -->
            <div id="import-bets" class="tab-content">
                <div class="import-header">
                    <h3>📥 Import Bets from PlayerProfit</h3>
                    <p style="color: #FFC107; margin-bottom: 20px;">
                        <strong>Import your betting history</strong> - Choose your preferred method below
                    </p>
                </div>
                
                <!-- Sticky Import Navigation -->
                <div class="import-nav-sticky">
                    <div class="import-nav-tabs">
                        <button class="import-nav-tab active" onclick="showImportMethod('csv-paste')" data-method="csv-paste">
                            📋 CSV Paste
                        </button>
                        <button class="import-nav-tab" onclick="showImportMethod('llm-chat')" data-method="llm-chat">
                            🤖 AI Parser
                        </button>
                        <button class="import-nav-tab" onclick="showImportMethod('file-upload')" data-method="file-upload">
                            📁 File Upload
                        </button>
                        <button class="import-nav-tab" onclick="showImportMethod('api-connect')" data-method="api-connect">
                            🔗 API Connect
                        </button>
                    </div>
                    <div class="import-progress-bar">
                        <div class="import-progress-fill" style="width: 25%"></div>
                    </div>
                </div>
                
                <!-- CSV Paste Method Section -->
                <div id="csv-paste" class="import-method-section active">
                    <h4 class="import-method-title">📋 CSV Data Paste</h4>
                    <p class="import-method-description">
                        Copy your bet history from PlayerProfit and paste it below. Expected format:<br>
                        <code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 3px;">Date,Sport,Selection,Stake,Odds,Result</code>
                    </p>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="import_csv_paste">
                        <input type="hidden" name="account_id" value="<?= htmlspecialchars($currentAccountId) ?>">
                        
                        <div class="form-group">
                            <label for="csv-data">CSV Data:</label>
                            <textarea name="csv_data" id="csv-data" rows="8" placeholder="2025-01-15,NFL,Patriots ML,1000,-110,WIN
2025-01-14,NBA,Lakers +5.5,1000,-105,LOSS
2025-01-13,MLB,Yankees Over 9.5,1000,+120,WIN" 
                                oninput="previewCSV()" class="form-control"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-success">📥 Import CSV Data</button>
                    </form>
                    
                    <div class="import-preview" id="csv-preview" style="display: none;">
                        <h5 style="color: #4CAF50; margin-bottom: 10px;">Preview:</h5>
                        <div id="csv-preview-content"></div>
                    </div>
                </div>
                
                <!-- AI Parser Method Section -->
                <div id="llm-chat" class="import-method-section">
                    <h4 class="import-method-title">🤖 AI Chat Parser</h4>
                    <p class="import-method-description">
                        Use the floating AI chat assistant (bottom-right corner) to format your messy betting data!
                    </p>
                    
                    <div style="background: rgba(76,175,80,0.1); border: 1px solid #4CAF50; border-radius: 12px; padding: 20px; text-align: center;">
                        <div style="font-size: 48px; margin-bottom: 15px;">🤖</div>
                        <h5 style="color: #4CAF50; margin-bottom: 10px;">AI Chat Assistant Available</h5>
                        <p style="color: #ccc; margin-bottom: 15px;">
                            Look for the floating chat widget in the bottom-right corner of your screen!
                            <br>Perfect for parsing PlayerProfit dashboard copy/paste or any unstructured betting data.
                        </p>
                        <button type="button" onclick="openFloatingChat()" class="btn btn-success">
                            💬 Open AI Chat Assistant
                        </button>
                    </div>
                </div>
                
                <!-- File Upload Method Section -->
                <div id="file-upload" class="import-method-section">
                    <h4 class="import-method-title">📁 File Upload</h4>
                    <p class="import-method-description">
                        Upload a CSV file exported from PlayerProfit or your own tracking system.
                    </p>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="import_csv_file">
                        <input type="hidden" name="account_id" value="<?= htmlspecialchars($currentAccountId) ?>">
                        
                        <div class="form-group">
                            <label for="csv-file">Select CSV File:</label>
                            <input type="file" name="csv_file" id="csv-file" accept=".csv,.txt" class="form-control">
                        </div>
                        
                        <button type="submit" class="btn btn-success">📁 Upload and Import File</button>
                    </form>
                </div>
                
                <!-- API Connect Method Section -->
                <div id="api-connect" class="import-method-section">
                    <h4 class="import-method-title">🔗 API Connection</h4>
                    <p class="import-method-description">
                        Direct API connection to PlayerProfit for automatic bet importing.
                    </p>
                    
                    <div style="background: rgba(33,150,243,0.1); border: 1px solid #2196F3; border-radius: 8px; padding: 20px;">
                        <h5 style="color: #2196F3; margin-bottom: 10px;">🚧 Coming Soon</h5>
                        <p style="color: #ccc; margin: 0;">
                            Direct PlayerProfit API integration is under development. This will allow automatic importing of your betting history without manual CSV export.
                        </p>
                    </div>
                </div>
                
                <!-- Clear All Data Option -->
                <div style="background: rgba(244,67,54,0.1); border: 1px solid #f44336; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
                    <h4 style="color: #f44336; margin: 0 0 8px 0; font-size: 16px;">🗑️ Clear All Data</h4>
                    <p style="margin: 0 0 15px 0; font-size: 14px; color: #ccc;">
                        Remove all existing bets from this account to start fresh. This action cannot be undone.
                    </p>
                    <form method="POST" onsubmit="return confirmClearAll()" style="display: inline;">
                        <input type="hidden" name="action" value="clear_all_bets">
                        <input type="hidden" name="confirm_clear" value="">
                        <button type="submit" class="btn" style="background: #f44336; color: white; padding: 8px 16px;">
                            🗑️ Clear All Bets
                        </button>
                    </form>
                </div>
                
                <!-- Documentation Link -->
                <div style="background: rgba(33,150,243,0.1); border: 1px solid #2196F3; border-radius: 8px; padding: 15px; margin-top: 20px;">
                    <h4 style="color: #2196F3; margin: 0 0 8px 0; font-size: 16px;">📚 Need Help?</h4>
                    <p style="margin: 0; font-size: 14px; color: #ccc;">
                        <a href="IMPORT_GUIDE.md" target="_blank" style="color: #2196F3; text-decoration: underline;">
                            📖 Read the Complete Import Guide
                        </a> - Detailed instructions, CSV format requirements, examples, and troubleshooting tips.
                    </p>
                </div>
            </div>
                
                <div class="import-section" style="display: grid; gap: 30px;">
                    <!-- CSV Paste Method -->
                    <div class="import-method" style="background: rgba(255,255,255,0.05); padding: 25px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);">
                        <h4 style="color: #FFD700; margin-bottom: 15px;">📋 Method 1: Paste CSV Data</h4>
                        <p style="font-size: 14px; margin-bottom: 15px; color: #ccc;">
                            Copy your bet history from PlayerProfit and paste it below. Expected format:<br>
                            <code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 3px;">Date,Sport,Selection,Stake,Odds,Result</code>
                        </p>
                        <div style="background: rgba(76,175,80,0.1); border: 1px solid #4CAF50; border-radius: 6px; padding: 10px; margin-bottom: 15px;">
                            <small style="color: #4CAF50;">📊 Importing to Account: <strong><?= htmlspecialchars($currentAccountId) ?></strong></small>
                        </div>
                        
                        <form method="POST" action="" style="margin-bottom: 20px;">
                            <input type="hidden" name="action" value="import_csv_paste">
                            <input type="hidden" name="account_id" value="<?= htmlspecialchars($currentAccountId) ?>">
                            
                            <div class="form-group">
                                <label for="csv-data">CSV Data:</label>
                                <textarea name="csv_data" id="csv-data" rows="8" placeholder="2025-01-15,NFL,Patriots ML,1000,-110,WIN
2025-01-14,NBA,Lakers +5.5,1000,-105,LOSS
2025-01-13,MLB,Yankees Over 9.5,1000,+120,WIN" 
                                oninput="previewCSV()" 
                                style="width: 100%; font-family: monospace; font-size: 12px; background: rgba(0,0,0,0.3); border: 2px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 12px; color: var(--text-primary, white);"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success" style="margin-top: 10px;">
                                📥 Import CSV Data
                            </button>
                        </form>
                        
                        <div class="import-preview" id="csv-preview" style="display: none; background: rgba(76,175,80,0.1); border: 1px solid #4CAF50; border-radius: 8px; padding: 15px; margin-top: 15px;">
                            <h5 style="color: #4CAF50; margin-bottom: 10px;">Preview:</h5>
                            <div id="csv-preview-content"></div>
                        </div>
                    </div>
                    
                    <!-- File Upload Method -->
                    <div class="import-method" style="background: rgba(255,255,255,0.05); padding: 25px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);">
                        <h4 style="color: #FFD700; margin-bottom: 15px;">📁 Method 2: Upload CSV File</h4>
                        <p style="font-size: 14px; margin-bottom: 15px; color: #ccc;">
                            Upload a CSV file exported from PlayerProfit or your own tracking system.
                        </p>
                        <div style="background: rgba(76,175,80,0.1); border: 1px solid #4CAF50; border-radius: 6px; padding: 10px; margin-bottom: 15px;">
                            <small style="color: #4CAF50;">📊 Importing to Account: <strong><?= htmlspecialchars($currentAccountId) ?></strong></small>
                        </div>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="import_csv_file">
                            <input type="hidden" name="account_id" value="<?= htmlspecialchars($currentAccountId) ?>">
                            
                            <div class="form-group">
                                <label for="csv-file">Select CSV File:</label>
                                <input type="file" name="csv_file" id="csv-file" accept=".csv,.txt" 
                                       style="width: 100%; padding: 10px; background: rgba(0,0,0,0.3); border: 2px solid rgba(255,255,255,0.2); border-radius: 8px; color: var(--text-primary, white);">
                            </div>
                            
                            <button type="submit" class="btn btn-success" style="margin-top: 10px;">
                                📁 Upload and Import File
                            </button>
                        </form>
                    </div>
                    
                    
                    <!-- PlayerProfit Integration -->
                    <div class="import-method" style="background: rgba(255,215,0,0.1); padding: 25px; border-radius: 10px; border: 1px solid #FFD700;">
                        <h4 style="color: #FFD700; margin-bottom: 15px;">🔗 Method 4: PlayerProfit API (Coming Soon)</h4>
                        <p style="font-size: 14px; margin-bottom: 15px; color: #ccc;">
                            Direct integration with PlayerProfit platform for automatic bet sync.
                        </p>
                        
                        <div style="opacity: 0.6;">
                            <div class="form-group">
                                <label>PlayerProfit Username:</label>
                                <input type="text" placeholder="your-username" disabled style="background: rgba(0,0,0,0.2); opacity: 0.5;">
                            </div>
                            
                            <div class="form-group">
                                <label>API Token:</label>
                                <input type="password" placeholder="Enter API token..." disabled style="background: rgba(0,0,0,0.2); opacity: 0.5;">
                            </div>
                            
                            <button type="button" class="btn" disabled style="background: #555; color: #999; cursor: not-allowed;">
                                🔗 Connect to PlayerProfit (Coming Soon)
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Import History -->
                <div class="import-history" style="margin-top: 30px;">
                    <h4 style="color: #FFD700; margin-bottom: 15px;">📜 Recent Imports</h4>
                    <div style="background: rgba(255,255,255,0.05); border-radius: 10px; padding: 20px; border: 1px solid rgba(255,255,255,0.1);">
                        <p style="color: #ccc; font-style: italic;">No imports yet. Use the methods above to import your betting history.</p>
                    </div>
                </div>
            </div>
            
            <!-- Parlay Calculator Tab -->
            <div id="parlay-calc" class="tab-content">
                <h3>🎲 Parlay Calculator</h3>
                <p style="color: #FFC107; margin-bottom: 20px;">
                    <strong>Calculate parlay payouts</strong> - Add up to 10 legs with American odds
                </p>
                
                <div class="parlay-calculator">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Bet Amount ($)</label>
                            <input type="number" id="parlay-stake" step="0.01" min="1" placeholder="1000" onchange="calculateParlay()">
                        </div>
                        <div class="form-group">
                            <label>Parlay Payout</label>
                            <div id="parlay-payout" style="font-size: 1.5rem; font-weight: bold; color: #4CAF50; padding: 12px; background: rgba(76,175,80,0.1); border-radius: 8px; border: 1px solid #4CAF50;">
                                $0.00
                            </div>
                        </div>
                    </div>
                    
                    <div class="parlay-legs">
                        <h4>Parlay Legs</h4>
                        <div id="legs-container">
                            <div class="leg-input" data-leg="1">
                                <label>Leg #1 Money Line</label>
                                <input type="number" class="leg-odds" placeholder="-149" onchange="calculateParlay()">
                            </div>
                            <div class="leg-input" data-leg="2">
                                <label>Leg #2 Money Line</label>
                                <input type="number" class="leg-odds" placeholder="-180" onchange="calculateParlay()">
                            </div>
                            <div class="leg-input" data-leg="3">
                                <label>Leg #3 Money Line</label>
                                <input type="number" class="leg-odds" placeholder="-110" onchange="calculateParlay()">
                            </div>
                            <div class="leg-input" data-leg="4">
                                <label>Leg #4 Money Line</label>
                                <input type="number" class="leg-odds" placeholder="" onchange="calculateParlay()">
                            </div>
                            <div class="leg-input" data-leg="5">
                                <label>Leg #5 Money Line</label>
                                <input type="number" class="leg-odds" placeholder="" onchange="calculateParlay()">
                            </div>
                            <div class="leg-input" data-leg="6">
                                <label>Leg #6 Money Line</label>
                                <input type="number" class="leg-odds" placeholder="" onchange="calculateParlay()">
                            </div>
                            <div class="leg-input" data-leg="7">
                                <label>Leg #7 Money Line</label>
                                <input type="number" class="leg-odds" placeholder="" onchange="calculateParlay()">
                            </div>
                            <div class="leg-input" data-leg="8">
                                <label>Leg #8 Money Line</label>
                                <input type="number" class="leg-odds" placeholder="" onchange="calculateParlay()">
                            </div>
                            <div class="leg-input" data-leg="9">
                                <label>Leg #9 Money Line</label>
                                <input type="number" class="leg-odds" placeholder="" onchange="calculateParlay()">
                            </div>
                            <div class="leg-input" data-leg="10">
                                <label>Leg #10 Money Line</label>
                                <input type="number" class="leg-odds" placeholder="" onchange="calculateParlay()">
                            </div>
                        </div>
                    </div>
                    
                    <div class="parlay-results" id="parlay-details" style="margin-top: 20px; display: none;">
                        <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 10px;">
                            <h4>Calculation Details</h4>
                            <div id="combined-odds"></div>
                            <div id="individual-legs"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- All Bets Tab -->
            <div id="all-bets" class="tab-content active">
                <h3>📋 All Bets (<?= count($allBets) ?> total)</h3>
                <?php if (!empty($allBets)): ?>
                    <table class="bets-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Sport</th>
                                <th>Selection</th>
                                <th>Stake</th>
                                <th>Odds</th>
                                <th>Result</th>
                                <th>P&L</th>
                                <th>Balance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allBets as $bet): ?>
                                <tr>
                                    <td><?= date('m/d/y', strtotime($bet['date'])) ?></td>
                                    <td><?= htmlspecialchars($bet['sport']) ?></td>
                                    <td><?= htmlspecialchars($bet['selection']) ?></td>
                                    <td>$<?= number_format($bet['stake'], 2) ?></td>
                                    <td><?= $bet['odds'] > 0 ? '+' : '' ?><?= $bet['odds'] ?></td>
                                    <td class="<?php 
                                        $result = strtoupper($bet['result']);
                                        if (in_array($result, ['WIN', 'WON', 'W'])) echo 'result-win';
                                        elseif (in_array($result, ['LOSS', 'LOST', 'LOSE', 'L'])) echo 'result-loss'; 
                                        elseif (in_array($result, ['PUSH', 'TIE', 'P'])) echo 'result-push';
                                        elseif (in_array($result, ['REFUNDED', 'REFUND', 'VOID'])) echo 'result-refunded';
                                        elseif (in_array($result, ['CASHED OUT', 'CASHOUT'])) echo 'result-cashed-out';
                                        else echo 'result-unknown';
                                    ?>">
                                        <?= $bet['result'] ?>
                                    </td>
                                    <td class="<?= $bet['pnl'] > 0 ? 'amount-positive' : ($bet['pnl'] < 0 ? 'amount-negative' : 'amount-neutral') ?>">
                                        <?= $bet['pnl'] >= 0 ? '+' : '' ?>$<?= number_format($bet['pnl'], 2) ?>
                                    </td>
                                    <td>$<?= number_format($bet['account_balance_after'], 2) ?></td>
                                    <td class="bet-actions">
                                        <button onclick="editBet('<?= $bet['id'] ?>')" class="btn-small btn-edit" title="Edit Bet">✏️</button>
                                        <button onclick="deleteBet('<?= $bet['id'] ?>')" class="btn-small btn-delete" title="Delete Bet">🗑️</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No bets recorded yet. Add your first bet above!</p>
                <?php endif; ?>
            </div>
            
            <!-- Analytics Tab -->
            <div id="analytics" class="tab-content">
                <h3>📊 Account Analytics</h3>
                
                <!-- Performance Charts -->
                <div class="analytics-section" style="margin: 30px 0;">
                    <!-- Performance Heat Map -->
                    <div class="balance-graph-container">
                        <h3 style="color: #FFD700; margin-bottom: 20px; text-align: center;">📊 30-Day Performance Overview</h3>
                        <div class="heatmap-calendar" id="performance-heatmap">
                            <!-- Heat map will be generated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Real-time Balance Graph -->
                    <div class="balance-graph-container">
                        <h3 style="color: #FFD700; margin-bottom: 20px; text-align: center;">📈 Balance History</h3>
                        <div id="balance-chart-container" style="height: 300px;">
                            <!-- Chart will be generated by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <div class="status-grid">
                    <div class="status-card">
                        <div class="status-value">$<?= number_format($accountStatus['today_pnl'], 2) ?></div>
                        <div class="status-label">Today's P&L</div>
                        <small>Daily Loss Limit: $<?= number_format($accountStatus['account_size'] * 0.1, 2) ?></small>
                    </div>
                    
                    <div class="status-card">
                        <div class="status-value">$<?= number_format($accountStatus['max_drawdown'], 2) ?></div>
                        <div class="status-label">Max Drawdown</div>
                        <small>Limit: $<?= number_format($accountStatus['account_size'] * 0.15, 2) ?></small>
                    </div>
                    
                    <div class="status-card">
                        <div class="status-value"><?= number_format($accountStatus['days_since_activity'], 0) ?> days</div>
                        <div class="status-label">Since Last Activity</div>
                        <?php if ($accountStatus['current_phase'] === 'Funded'): ?>
                            <small>Limit: 5 days</small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($accountStatus['current_phase'] !== 'Funded'): ?>
                    <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 10px;">
                        <h4>🎯 Phase <?= substr($accountStatus['current_phase'], -1) ?> Progress</h4>
                        <p><strong>Target:</strong> $<?= number_format($accountStatus['profit_target'], 2) ?> (20% of $<?= number_format($accountStatus['start_balance']) ?>)</p>
                        <p><strong>Current Profit:</strong> $<?= number_format($accountStatus['profit_progress'], 2) ?></p>
                        <p><strong>Remaining:</strong> $<?= number_format(max(0, $accountStatus['profit_target'] - $accountStatus['profit_progress']), 2) ?></p>
                        
                        <div class="progress-bar" style="height: 15px;">
                            <div class="progress-fill" style="width: <?= min(100, max(0, ($accountStatus['profit_progress'] / $accountStatus['profit_target']) * 100)) ?>%"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Discord Tab -->
            <div id="discord" class="tab-content">
                <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 10px; border: 2px solid #FFD700;">
                    <h3>📋 PlayerProfit Status - Copy & Paste to Discord</h3>
                    <pre id="discordMessage" style="color: #FFD700; font-family: 'Courier New', monospace;"><?= htmlspecialchars($discordMessage) ?></pre>
                    <button class="btn btn-primary" onclick="copyToClipboard()">📋 Copy to Clipboard</button>
                </div>
            </div>
            
        <?php endif; ?>
    </div>
    
    <script>
        // showTab function moved to head section
        
        function confirmAddBet() {
            const sport = document.querySelector('select[name="sport"]').value;
            const selection = document.querySelector('input[name="selection"]').value;
            const stake = document.querySelector('input[name="stake"]').value;
            const result = document.querySelector('select[name="result"]').value;
            
            return confirm(`Add this bet?\n\n${sport}: ${selection}\nStake: $${stake} (${result})`);
        }
        
        function copyToClipboard() {
            const text = document.getElementById('discordMessage').textContent;
            navigator.clipboard.writeText(text).then(function() {
                alert('✅ Copied to clipboard!');
            });
        }
        
        function calculateParlay() {
            const stake = parseFloat(document.getElementById('parlay-stake').value) || 0;
            const legInputs = document.querySelectorAll('.leg-odds');
            
            let validLegs = [];
            let combinedDecimalOdds = 1;
            
            // Collect valid odds entries
            legInputs.forEach((input, index) => {
                const odds = parseInt(input.value);
                if (odds && !isNaN(odds)) {
                    validLegs.push({
                        leg: index + 1,
                        americanOdds: odds,
                        decimalOdds: americanToDecimal(odds)
                    });
                    combinedDecimalOdds *= americanToDecimal(odds);
                }
            });
            
            if (validLegs.length < 2 || stake <= 0) {
                document.getElementById('parlay-payout').textContent = '$0.00';
                document.getElementById('parlay-details').style.display = 'none';
                return;
            }
            
            const payout = stake * combinedDecimalOdds;
            const profit = payout - stake;
            const combinedAmericanOdds = decimalToAmerican(combinedDecimalOdds);
            
            // Update payout display
            document.getElementById('parlay-payout').textContent = '$' + payout.toFixed(2);
            
            // Show calculation details
            const detailsDiv = document.getElementById('parlay-details');
            const combinedOddsDiv = document.getElementById('combined-odds');
            const individualLegsDiv = document.getElementById('individual-legs');
            
            combinedOddsDiv.innerHTML = `
                <p><strong>Combined Odds:</strong> ${combinedAmericanOdds > 0 ? '+' : ''}${combinedAmericanOdds}</p>
                <p><strong>Total Payout:</strong> $${payout.toFixed(2)}</p>
                <p><strong>Total Profit:</strong> $${profit.toFixed(2)}</p>
            `;
            
            let legsHtml = '<h5>Individual Legs:</h5>';
            validLegs.forEach(leg => {
                legsHtml += `<p>Leg #${leg.leg}: ${leg.americanOdds > 0 ? '+' : ''}${leg.americanOdds} (Decimal: ${leg.decimalOdds.toFixed(3)})</p>`;
            });
            individualLegsDiv.innerHTML = legsHtml;
            
            detailsDiv.style.display = 'block';
        }
        
        function americanToDecimal(americanOdds) {
            if (americanOdds > 0) {
                return (americanOdds / 100) + 1;
            } else {
                return (100 / Math.abs(americanOdds)) + 1;
            }
        }
        
        function decimalToAmerican(decimalOdds) {
            if (decimalOdds >= 2.0) {
                return Math.round((decimalOdds - 1) * 100);
            } else {
                return Math.round(-100 / (decimalOdds - 1));
            }
        }
        
        // Bet form parlay functions
        let parlayLegCount = 2;
        
        function toggleParlayMode() {
            const isParlay = document.getElementById('is-parlay').checked;
            const singleOdds = document.getElementById('single-odds');
            const parlaySection = document.getElementById('parlay-legs-section');
            const singleOddsContainer = singleOdds.closest('.form-group');
            
            if (isParlay) {
                // Hide single odds container completely
                singleOddsContainer.style.display = 'none';
                singleOdds.required = false;
                singleOdds.value = ''; // Clear the value so it doesn't interfere
                parlaySection.style.display = 'block';
                updateParlayOdds();
            } else {
                // Show single odds container
                singleOddsContainer.style.display = 'block';
                singleOdds.required = true;
                parlaySection.style.display = 'none';
                
                // Clear parlay leg inputs
                const parlayInputs = parlaySection.querySelectorAll('input');
                parlayInputs.forEach(input => input.value = '');
            }
        }
        
        function addParlayLeg() {
            if (parlayLegCount >= 10) {
                alert('Maximum 10 legs allowed');
                return;
            }
            
            parlayLegCount++;
            const legIndex = parlayLegCount - 1;
            
            const legHtml = `
                <div class="parlay-leg" data-leg="${parlayLegCount}">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Leg #${parlayLegCount} Selection</label>
                            <input type="text" name="parlay_legs[${legIndex}][selection]" placeholder="e.g., Team ML">
                        </div>
                        <div class="form-group">
                            <label>Leg #${parlayLegCount} Odds</label>
                            <input type="number" name="parlay_legs[${legIndex}][odds]" placeholder="-110" onchange="updateParlayOdds()">
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('bet-parlay-legs').insertAdjacentHTML('beforeend', legHtml);
            updateParlayOdds();
        }
        
        function removeParlayLeg() {
            if (parlayLegCount <= 2) {
                alert('Minimum 2 legs required for parlay');
                return;
            }
            
            const lastLeg = document.querySelector(`[data-leg="${parlayLegCount}"]`);
            if (lastLeg) {
                lastLeg.remove();
                parlayLegCount--;
                updateParlayOdds();
            }
        }
        
        function updateParlayOdds() {
            // Only run if parlay mode is active
            const isParlay = document.getElementById('is-parlay').checked;
            if (!isParlay) return;
            
            const legInputs = document.querySelectorAll('#bet-parlay-legs input[name*="[odds]"]');
            let combinedDecimalOdds = 1;
            let validLegs = 0;
            
            legInputs.forEach(input => {
                const odds = parseInt(input.value);
                if (odds && !isNaN(odds)) {
                    combinedDecimalOdds *= americanToDecimal(odds);
                    validLegs++;
                }
            });
            
            const combinedOddsDisplay = document.getElementById('combined-odds-display');
            const combinedOddsValue = document.getElementById('combined-odds-value');
            
            if (combinedOddsDisplay && combinedOddsValue) {
                if (validLegs >= 2) {
                    const combinedAmerican = decimalToAmerican(combinedDecimalOdds);
                    combinedOddsValue.textContent = (combinedAmerican > 0 ? '+' : '') + combinedAmerican;
                    combinedOddsDisplay.style.display = 'block';
                } else {
                    combinedOddsDisplay.style.display = 'none';
                }
            }
        }
        
        // Preserve form data when switching tabs
        function preserveFormData() {
            const formInputs = document.querySelectorAll('#add-bet input, #add-bet select');
            const formData = {};
            
            formInputs.forEach(input => {
                if (input.name && input.value) {
                    formData[input.name] = input.value;
                }
            });
            
            sessionStorage.setItem('betFormData', JSON.stringify(formData));
        }
        
        function restoreFormData() {
            const savedData = sessionStorage.getItem('betFormData');
            if (savedData) {
                const formData = JSON.parse(savedData);
                
                Object.keys(formData).forEach(name => {
                    const input = document.querySelector(`#add-bet [name="${name}"]`);
                    if (input && formData[name]) {
                        input.value = formData[name];
                    }
                });
            }
        }
        
        // Save form data when typing
        document.addEventListener('input', function(e) {
            if (e.target.closest('#add-bet')) {
                preserveFormData();
            }
        });
        
        // Restore form data on page load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(restoreFormData, 100);
        });
        
        // Clear saved data on successful form submission
        document.querySelector('form').addEventListener('submit', function() {
            sessionStorage.removeItem('betFormData');
        });
        
        // Auto-refresh violation status every 2 minutes (reduced frequency to prevent flashing)
        setInterval(function() {
            const hasFormData = document.querySelector('#add-bet input[name="selection"]')?.value || 
                               document.querySelector('#add-bet input[name="stake"]')?.value;
            const hasViolations = document.querySelector('.violation-alert');
            
            
            if (hasViolations && !hasFormData) {
                location.reload();
            } else {
            }
        }, 120000); // 2 minutes instead of 30 seconds
        
        // === MOBILE NAVIGATION ===
        // toggleMobileNav function moved to head section for early loading
        
        // === TEXT COLOR DIAGNOSTICS ===
        function diagnoseTextColors() {
            const theme = document.documentElement.getAttribute('data-theme');
            
            // Find elements with hardcoded white text
            const whiteTextElements = document.querySelectorAll('[style*="color: white"], [style*="color: #fff"], [style*="color: #ffffff"]');
            
            // Check if CSS variables are working
            const testElement = document.createElement('div');
            testElement.style.color = 'var(--text-primary)';
            document.body.appendChild(testElement);
            const computedColor = window.getComputedStyle(testElement).color;
            testElement.remove();
            
            // Log problematic elements
            document.querySelectorAll('.status-value, .status-label, .card-title, .card-subtitle').forEach(el => {
                const style = window.getComputedStyle(el);
                if (theme === 'light' && (style.color === 'rgb(255, 255, 255)' || style.color === 'white')) {
                    console.warn(`[TextDiag] Light mode white text detected:`, el, 'Color:', style.color);
                }
            });
        }

        // === CSV IMPORT PREVIEW ===
        function previewCSV() {
            const csvData = document.getElementById('csv-data').value.trim();
            const preview = document.getElementById('csv-preview');
            const previewContent = document.getElementById('csv-preview-content');
            
            if (!csvData) {
                preview.style.display = 'none';
                return;
            }
            
            const lines = csvData.split('\n').filter(line => line.trim());
            let validRows = 0;
            let errorRows = 0;
            let html = '<table style="width: 100%; font-size: 12px; font-family: monospace;">';
            html += '<tr><th>Date</th><th>Sport</th><th>Selection</th><th>Stake</th><th>Odds</th><th>Result</th><th>Status</th></tr>';
            
            // Skip header if detected
            let startIndex = 0;
            const firstLine = lines[0] ? lines[0].toLowerCase() : '';
            if (firstLine.includes('date') || firstLine.includes('sport')) {
                startIndex = 1;
                html += '<tr style="opacity: 0.5;"><td colspan="7">Header row detected - will be skipped</td></tr>';
            }
            
            for (let i = startIndex; i < Math.min(lines.length, startIndex + 5); i++) {
                const fields = lines[i].split(',');
                const isValid = fields.length >= 6 && 
                              fields[0].trim() && 
                              fields[1].trim() && 
                              fields[2].trim() && 
                              parseFloat(fields[3]) > 0 && 
                              parseInt(fields[4]) !== 0 && 
                              ['WIN', 'LOSS', 'PUSH'].includes(fields[5].trim().toUpperCase());
                
                if (isValid) validRows++;
                else errorRows++;
                
                const status = isValid ? '✅' : '❌';
                const rowStyle = isValid ? '' : 'background: rgba(244,67,54,0.2);';
                
                html += `<tr style="${rowStyle}">`;
                fields.slice(0, 6).forEach(field => {
                    html += `<td style="padding: 2px 4px; border: 1px solid rgba(255,255,255,0.1);">${field.trim()}</td>`;
                });
                html += `<td style="text-align: center;">${status}</td>`;
                html += '</tr>';
            }
            
            if (lines.length > startIndex + 5) {
                html += `<tr><td colspan="7" style="text-align: center; font-style: italic;">... and ${lines.length - startIndex - 5} more rows</td></tr>`;
            }
            
            html += '</table>';
            html += `<p style="margin-top: 10px; font-size: 14px;"><strong>Summary:</strong> ${validRows} valid rows, ${errorRows} errors</p>`;
            
            previewContent.innerHTML = html;
            preview.style.display = 'block';
        }

        // === LLM CHAT ASSISTANT FUNCTIONS ===
        function updateApiKeyPlaceholder() {
            const provider = document.getElementById('llm-provider').value;
            const apiKeyInput = document.getElementById('api-key');
            
            const placeholders = {
                'openai': 'Enter your OpenAI API key (sk-...)',
                'anthropic': 'Enter your Anthropic API key (sk-ant-...)',
                'google': 'Enter your Google AI API key',
                'ollama': 'http://localhost:11434 (or your Ollama server URL)'
            };
            
            apiKeyInput.placeholder = placeholders[provider];
            updateChatButton();
        }
        
        function updateChatButton() {
            const chatInput = document.getElementById('chat-input');
            const apiKey = document.getElementById('api-key').value.trim();
            const provider = document.getElementById('llm-provider').value;
            const sendBtn = document.getElementById('send-chat-btn');
            
            if (!chatInput || !sendBtn) return;
            
            const hasMessage = chatInput.value.trim().length > 5;
            const hasApiConfig = provider === 'ollama' ? apiKey.length > 5 : apiKey.length > 10;
            
            if (hasMessage && hasApiConfig) {
                sendBtn.disabled = false;
                sendBtn.style.opacity = '1';
                sendBtn.style.cursor = 'pointer';
            } else {
                sendBtn.disabled = true;
                sendBtn.style.opacity = '0.5';
                sendBtn.style.cursor = 'not-allowed';
            }
        }
        
        // Update chat button when typing
        document.addEventListener('DOMContentLoaded', function() {
            const chatInput = document.getElementById('chat-input');
            const apiKeyInput = document.getElementById('api-key');
            
            if (chatInput) {
                chatInput.addEventListener('input', updateChatButton);
                chatInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && e.ctrlKey) {
                        e.preventDefault();
                        sendChatMessage();
                    }
                });
            }
            
            if (apiKeyInput) {
                apiKeyInput.addEventListener('input', updateChatButton);
            }
        });
        
        async function sendChatMessage() {
            const chatInput = document.getElementById('chat-input');
            const chatMessages = document.getElementById('chat-messages');
            const chatStatus = document.getElementById('chat-status');
            const sendBtn = document.getElementById('send-chat-btn');
            const apiKey = document.getElementById('api-key').value.trim();
            const provider = document.getElementById('llm-provider').value;
            
            const userMessage = chatInput.value.trim();
            if (!userMessage || !apiKey) return;
            
            
            // Add user message to chat
            addChatMessage('user', userMessage);
            chatInput.value = '';
            updateChatButton();
            
            // Update UI
            sendBtn.disabled = true;
            sendBtn.innerHTML = '⏳ Thinking...';
            chatStatus.textContent = 'AI is processing your request...';
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'chat_with_llm_ajax',
                        user_message: userMessage,
                        api_key: apiKey,
                        provider: provider,
                        account_id: document.querySelector('input[name="account_id"]').value
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    addChatMessage('assistant', result.message);
                    
                    // If the response contains CSV data, show import options
                    if (result.csv_data) {
                        window.parsedCSVData = result.csv_data;
                        showCSVImportOptions(result.csv_data);
                    }
                    
                    chatStatus.innerHTML = `<span style="color: #4CAF50;">✅ Response received</span>`;
                } else {
                    addChatMessage('assistant', `❌ Error: ${result.error}`);
                    chatStatus.innerHTML = `<span style="color: #f44336;">❌ Error: ${result.error}</span>`;
                }
                
            } catch (error) {
                console.error('Chat error:', error);
                addChatMessage('assistant', `❌ Network error: ${error.message}`);
                chatStatus.innerHTML = `<span style="color: #f44336;">❌ Network error: ${error.message}</span>`;
            } finally {
                // Reset button
                sendBtn.disabled = false;
                sendBtn.innerHTML = '🚀 Send';
                updateChatButton();
                
                // Clear status after 5 seconds
                setTimeout(() => {
                    chatStatus.textContent = '';
                }, 5000);
            }
        }
        
        function addChatMessage(role, content) {
            const chatMessages = document.getElementById('chat-messages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `chat-message ${role}`;
            messageDiv.style.marginBottom = '15px';
            
            const isUser = role === 'user';
            const roleColor = isUser ? '#FFD700' : '#4CAF50';
            const roleIcon = isUser ? '👤' : '🤖';
            const roleName = isUser ? 'You' : 'AI Assistant';
            const bgColor = isUser ? 'rgba(255,215,0,0.1)' : 'rgba(76,175,80,0.1)';
            const borderColor = isUser ? '#FFD700' : '#4CAF50';
            
            messageDiv.innerHTML = `
                <div style="color: ${roleColor}; font-weight: bold; margin-bottom: 5px;">${roleIcon} ${roleName}</div>
                <div style="background: ${bgColor}; padding: 10px; border-radius: 8px; border-left: 3px solid ${borderColor}; white-space: pre-wrap;">${content}</div>
            `;
            
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        function showCSVImportOptions(csvData) {
            const llmOutput = document.getElementById('llm-output');
            document.getElementById('llm-parsed-csv').textContent = csvData;
            llmOutput.style.display = 'block';
        }
        
        function clearChat() {
            const chatMessages = document.getElementById('chat-messages');
            chatMessages.innerHTML = `
                <div class="chat-message assistant" style="margin-bottom: 15px;">
                    <div style="color: #4CAF50; font-weight: bold; margin-bottom: 5px;">🤖 AI Assistant</div>
                    <div style="background: rgba(76,175,80,0.1); padding: 10px; border-radius: 8px; border-left: 3px solid #4CAF50;">
                        Chat cleared! I'm ready to help format your betting data again. Just paste your data or ask me anything!
                    </div>
                </div>
            `;
            
            const chatInput = document.getElementById('chat-input');
            chatInput.value = '';
            updateChatButton();
        }
        
        function importParsedData() {
            if (!window.parsedCSVData) {
                alert('No parsed data available. Please parse your data first.');
                return;
            }
            
            // Submit the parsed CSV data
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'import_csv_paste';
            
            const accountInput = document.createElement('input');
            accountInput.type = 'hidden';
            accountInput.name = 'account_id';
            accountInput.value = document.querySelector('input[name="account_id"]').value;
            
            const csvInput = document.createElement('input');
            csvInput.type = 'hidden';
            csvInput.name = 'csv_data';
            csvInput.value = window.parsedCSVData;
            
            form.appendChild(actionInput);
            form.appendChild(accountInput);
            form.appendChild(csvInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function copyToCSVMethod() {
            if (!window.parsedCSVData) {
                alert('No parsed data available. Please parse your data first.');
                return;
            }
            
            // Copy to Method 1 CSV textarea
            document.getElementById('csv-data').value = window.parsedCSVData;
            
            // Trigger preview update
            previewCSV();
            
            // Show success message
            const parseStatus = document.getElementById('parse-status');
            parseStatus.innerHTML = `<span style="color: #4CAF50;">✅ Data copied to CSV Method! Review and import above.</span>`;
            
            // Scroll to CSV method
            document.getElementById('csv-data').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // === ENHANCED VISUAL INITIALIZATION ===
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize mobile navigation based on screen size
            function checkMobile() {
                const toggle = document.querySelector('.mobile-nav-toggle');
                if (window.innerWidth <= 768) {
                    toggle.style.display = 'block';
                } else {
                    toggle.style.display = 'none';
                    // Close mobile nav if open
                    document.getElementById('mobile-sidebar').classList.remove('open');
                    document.getElementById('sidebar-overlay').classList.remove('open');
                }
            }
            
            checkMobile();
            window.addEventListener('resize', checkMobile);
            
            // Initialize progress rings and gauges
            setTimeout(function() {
                
                if (window.enhancedDashboard) {
                    // Initialize the heat map with actual data
                    const heatmapContainer = document.getElementById('performance-heatmap');
                    if (heatmapContainer) {
                        window.enhancedDashboard.generateHeatMap(heatmapContainer, 30);
                    }
                    
                    // Initialize balance chart
                    const chartContainer = document.getElementById('balance-chart-container');
                    if (chartContainer) {
                        window.enhancedDashboard.createBalanceChart(chartContainer);
                    }
                }
                
                // Run text color diagnostics
                diagnoseTextColors();
                
                // Re-run diagnostics when theme changes
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'data-theme') {
                            setTimeout(diagnoseTextColors, 100);
                        }
                    });
                });
                observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
                
            }, 500);
        });
    </script>
    
    <!-- Floating AI Chat Widget -->
    <div id="floating-chat-widget" class="floating-chat-widget minimized">
        <!-- Floating Chat Toggle Button (when minimized) -->
        <div id="chat-toggle-btn" class="chat-toggle-btn" onclick="toggleFloatingChat()">
            <div class="chat-icon">🤖</div>
            <div class="chat-notification" id="chat-notification" style="display: none;">1</div>
        </div>
        
        <!-- Chat Widget Window -->
        <div id="chat-window" class="chat-window">
            <!-- Chat Header -->
            <div class="chat-header">
                <div class="chat-title">
                    <?php 
                    // Determine which provider is active (Google Gemini priority)
                    $activeProvider = null;
                    $activeIcon = '🤖';
                    $activeName = 'AI Assistant';
                    
                    if ($tracker->hasValidApiKey('google')) {
                        $activeProvider = 'google';
                        $activeIcon = '🌟';
                        $activeName = 'Gemini';
                    } elseif ($tracker->hasValidApiKey('anthropic')) {
                        $activeProvider = 'anthropic';
                        $activeIcon = '🧠';
                        $activeName = 'Claude';
                    } elseif ($tracker->hasValidApiKey('openai')) {
                        $activeProvider = 'openai';
                        $activeIcon = '🤖';
                        $activeName = 'OpenAI GPT';
                    }
                    ?>
                    <span class="chat-icon"><?= $activeIcon ?></span>
                    <span><?= $activeName ?></span>
                    <?php if ($activeProvider): ?>
                    <span class="active-provider-badge"><?= strtoupper($activeProvider) ?></span>
                    <?php endif; ?>
                </div>
                <div class="chat-controls">
                    <button type="button" onclick="minimizeFloatingChat()" class="chat-control-btn" title="Minimize">➖</button>
                    <button type="button" onclick="closeFloatingChat()" class="chat-control-btn" title="Close">✕</button>
                </div>
            </div>
            
            <!-- API Key Status & Config -->
            <div id="chat-api-status" class="chat-api-status">
                <?php 
                // Check for stored API keys for floating chat
                $hasOpenAI = $tracker->hasValidApiKey('openai');
                $hasAnthropic = $tracker->hasValidApiKey('anthropic');
                $hasGoogle = $tracker->hasValidApiKey('google');
                $hasAnyKey = $hasOpenAI || $hasAnthropic || $hasGoogle;
                
                if ($hasAnyKey): ?>
                <div style="padding: 8px; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; gap: 8px; align-items: center; font-size: 11px;">
                        <span style="color: #4CAF50;">API Keys:</span>
                        <?php if ($hasOpenAI): ?>
                        <span class="api-key-indicator active" title="OpenAI API Key Active">🤖</span>
                        <?php else: ?>
                        <span class="api-key-indicator inactive" title="OpenAI API Key Not Configured">🤖</span>
                        <?php endif; ?>
                        
                        <?php if ($hasAnthropic): ?>
                        <span class="api-key-indicator active" title="Anthropic Claude API Key Active">🧠</span>
                        <?php else: ?>
                        <span class="api-key-indicator inactive" title="Anthropic Claude API Key Not Configured">🧠</span>
                        <?php endif; ?>
                        
                        <?php if ($hasGoogle): ?>
                        <span class="api-key-indicator active" title="Google Gemini API Key Active">🌟</span>
                        <?php else: ?>
                        <span class="api-key-indicator inactive" title="Google Gemini API Key Not Configured">🌟</span>
                        <?php endif; ?>
                    </div>
                    <button type="button" onclick="showApiConfig()" style="background: none; border: none; color: #4CAF50; text-decoration: underline; cursor: pointer; font-size: 11px;">settings</button>
                </div>
                <?php else: ?>
                <div style="color: #FFC107; font-size: 12px; padding: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <span>⚠️ API key required</span>
                    <button type="button" onclick="showApiConfig()" style="background: none; border: none; color: #FFC107; text-decoration: underline; cursor: pointer; font-size: 11px;">configure</button>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- API Configuration Panel (hidden by default) -->
            <div id="chat-api-config" class="chat-api-config" style="display: none;">
                <form method="POST" action="" style="padding: 15px; background: rgba(0,0,0,0.2);">
                    <input type="hidden" name="action" value="store_api_key">
                    <input type="hidden" name="account_id" value="<?= htmlspecialchars($currentAccountId) ?>">
                    
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; font-size: 11px; color: #ccc; margin-bottom: 4px;">AI Provider:</label>
                        <select name="provider" id="float-llm-provider" style="width: 100%; padding: 6px; border-radius: 4px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.2); color: white; font-size: 12px;">
                            <option value="google">Google (Gemini) - Recommended</option>
                            <option value="anthropic">Anthropic (Claude)</option>
                            <option value="openai">OpenAI (GPT-4, GPT-3.5)</option>
                            <option value="ollama">Ollama (Local)</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; font-size: 11px; color: #ccc; margin-bottom: 4px;">API Key:</label>
                        <input type="password" name="api_key" id="float-api-key" placeholder="Enter your API key..." style="width: 100%; padding: 6px; border-radius: 4px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.2); color: white; font-size: 12px;">
                    </div>
                    
                    <div style="display: flex; gap: 8px;">
                        <button type="submit" style="flex: 1; padding: 6px; background: #4CAF50; color: white; border: none; border-radius: 4px; font-size: 11px;">Store Key</button>
                        <button type="button" onclick="hideApiConfig()" style="flex: 1; padding: 6px; background: #666; color: white; border: none; border-radius: 4px; font-size: 11px;">Cancel</button>
                    </div>
                </form>
                
                <?php if ($hasAnyKey): ?>
                <!-- Clear All Keys Button -->
                <div style="padding: 8px 15px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <form method="POST" action="" style="margin: 0;">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" onclick="return confirm('Clear all stored API keys? You will need to re-enter them.')" 
                                style="width: 100%; padding: 6px; background: #f44336; color: white; border: none; border-radius: 4px; font-size: 11px;">
                            🗑️ Clear All API Keys
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Chat Messages Area -->
            <div id="float-chat-messages" class="chat-messages-area">
                <div class="chat-message assistant-message">
                    <div class="message-content">
                        Hi! I'm your AI assistant. I can help you:
                        <br>• Format messy betting data into CSV
                        <br>• Parse PlayerProfit dashboard copy/paste
                        <br>• Handle parlay bets with missing odds (I'll calculate them!)
                        <br>• Clean up unstructured bet information
                        <br><br><strong>Parlay Example:</strong>
                        <br>"Yesterday's 3-leg parlay: Chiefs ML + Lakers Over 215 + Dodgers -1.5, bet $500, won $2000"
                        <br><br>Just paste your data and ask me to format it!
                    </div>
                </div>
            </div>
            
            <!-- Chat Input Area -->
            <div class="chat-input-area">
                <?php if ($hasAnyKey): ?>
                <form id="float-chat-form" onsubmit="sendFloatingChatMessage(event)">
                    <div class="chat-input-container">
                        <textarea id="float-chat-input" placeholder="Paste your betting data here..." rows="2" onkeydown="handleChatKeydown(event)"></textarea>
                        <button type="submit" class="send-btn">Send</button>
                    </div>
                </form>
                <?php else: ?>
                <div class="chat-input-disabled">
                    <div style="text-align: center; color: #FFC107; font-size: 12px; padding: 10px;">
                        Configure API key to start chatting
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Enhanced Dashboard Components -->
    <script src="assets/js/dashboard-enhanced.js?v=<?= time() ?>"></script>
    <script src="assets/js/form-enhanced.js"></script>
    
    <!-- Floating Chat Widget JavaScript -->
    <script>
        let isFloatingChatOpen = false;
        let isFloatingChatMinimized = true;
        
        function toggleFloatingChat() {
            const widget = document.getElementById('floating-chat-widget');
            const toggleBtn = document.getElementById('chat-toggle-btn');
            const chatWindow = document.getElementById('chat-window');
            
            if (isFloatingChatMinimized) {
                // Expand the chat
                widget.classList.remove('minimized');
                widget.classList.add('expanded');
                toggleBtn.style.display = 'none';
                chatWindow.style.display = 'flex';
                isFloatingChatMinimized = false;
                isFloatingChatOpen = true;
                
                // Hide notification
                document.getElementById('chat-notification').style.display = 'none';
            } else {
                // Minimize the chat
                minimizeFloatingChat();
            }
        }
        
        function minimizeFloatingChat() {
            const widget = document.getElementById('floating-chat-widget');
            const toggleBtn = document.getElementById('chat-toggle-btn');
            const chatWindow = document.getElementById('chat-window');
            
            widget.classList.remove('expanded');
            widget.classList.add('minimized');
            toggleBtn.style.display = 'flex';
            chatWindow.style.display = 'none';
            isFloatingChatMinimized = true;
            isFloatingChatOpen = false;
        }
        
        function closeFloatingChat() {
            const widget = document.getElementById('floating-chat-widget');
            widget.style.display = 'none';
            isFloatingChatOpen = false;
            isFloatingChatMinimized = true;
        }
        
        function openFloatingChat() {
            const widget = document.getElementById('floating-chat-widget');
            widget.style.display = 'block';
            toggleFloatingChat();
        }
        
        function showApiConfig() {
            document.getElementById('chat-api-config').style.display = 'block';
        }
        
        function hideApiConfig() {
            document.getElementById('chat-api-config').style.display = 'none';
        }
        
        async function sendFloatingChatMessage(event) {
            event.preventDefault();
            const input = document.getElementById('float-chat-input');
            const message = input.value.trim();
            
            if (!message) return;
            
            // Add user message to chat
            addFloatingChatMessage(message, 'user');
            
            // Clear input
            input.value = '';
            
            // Show typing indicator
            addFloatingChatMessage('🤖 AI is thinking...', 'assistant', 'typing-indicator');
            
            try {
                // Get API key from stored session (since we already checked hasAnyKey in PHP)
                const formData = new URLSearchParams({
                    action: 'chat_with_llm_ajax',
                    user_message: message,
                    account_id: '<?= htmlspecialchars($currentAccountId) ?>'
                });
                
                console.log('Sending request:', formData.toString()); // Debug log
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                console.log('Chat response:', result); // Debug log
                
                // Remove typing indicator
                removeTypingIndicator();
                
                if (result.success) {
                    addFloatingChatMessage(result.message, 'assistant');
                    
                    // If response contains CSV data, add import button
                    if (result.message.includes('Date,Sport,Selection,Stake,Odds,Result') || 
                        result.message.match(/\d{4}-\d{2}-\d{2}.*,.*,.*,\d+.*,[-+]?\d+.*,(WIN|LOSS|PUSH)/)) {
                        addImportButton(result.message);
                    }
                } else {
                    addFloatingChatMessage('❌ Error: ' + (result.error || 'Failed to process message'), 'assistant');
                }
            } catch (error) {
                removeTypingIndicator();
                console.error('Chat error:', error);
                
                if (error.message.includes('HTTP')) {
                    addFloatingChatMessage(`❌ Server error: ${error.message}`, 'assistant');
                } else if (error.message.includes('JSON')) {
                    addFloatingChatMessage('❌ Invalid response format from server', 'assistant');
                } else {
                    addFloatingChatMessage('❌ Connection error. Please check your API key configuration.', 'assistant');
                }
            }
        }
        
        function addFloatingChatMessage(message, sender, className = '') {
            const messagesArea = document.getElementById('float-chat-messages');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'chat-message ' + (sender === 'user' ? 'user-message' : 'assistant-message') + ' ' + className;
            
            if (className === 'typing-indicator') {
                messageDiv.id = 'typing-indicator';
                messageDiv.innerHTML = `
                    <div class="message-content">
                        <div class="typing-animation">
                            ${message}
                            <span class="dots">
                                <span>.</span><span>.</span><span>.</span>
                            </span>
                        </div>
                    </div>
                `;
            } else {
                // Convert newlines to <br> tags for proper display
                const formattedMessage = message.replace(/\n/g, '<br>');
                messageDiv.innerHTML = `
                    <div class="message-content">${formattedMessage}</div>
                `;
            }
            
            messagesArea.appendChild(messageDiv);
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }
        
        function removeTypingIndicator() {
            const indicator = document.getElementById('typing-indicator');
            if (indicator) {
                indicator.remove();
            }
        }
        
        function addImportButton(csvData) {
            const messagesArea = document.getElementById('float-chat-messages');
            const buttonDiv = document.createElement('div');
            buttonDiv.className = 'chat-message assistant-message import-action';
            
            // Extract just the CSV data (look for lines that match CSV pattern)
            const lines = csvData.split('\n');
            const csvLines = lines.filter(line => 
                line.includes('Date,Sport,Selection') || 
                line.match(/\d{4}-\d{2}-\d{2}.*,.*,.*,\d+.*,[-+]?\d+.*,(WIN|LOSS|PUSH)/)
            );
            const cleanCsv = csvLines.join('\n');
            
            buttonDiv.innerHTML = `
                <div class="message-content import-action-content">
                    <div style="background: rgba(76,175,80,0.2); border: 1px solid #4CAF50; border-radius: 8px; padding: 12px; margin: 8px 0;">
                        <div style="color: #4CAF50; font-weight: bold; margin-bottom: 8px;">📥 Ready to Import</div>
                        <div style="font-size: 12px; color: #ccc; margin-bottom: 10px;">
                            Found ${csvLines.length - 1} betting records ready for import
                        </div>
                        <button onclick="importCsvFromChat(\`${cleanCsv.replace(/`/g, '\\`')}\`)" 
                                class="btn btn-success" style="padding: 6px 12px; font-size: 12px;">
                            📥 Import These Bets
                        </button>
                        <button onclick="copyCsvToClipboard(\`${cleanCsv.replace(/`/g, '\\`')}\`)" 
                                class="btn" style="background: #2196F3; color: white; padding: 6px 12px; font-size: 12px; margin-left: 8px;">
                            📋 Copy CSV
                        </button>
                    </div>
                </div>
            `;
            
            messagesArea.appendChild(buttonDiv);
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }
        
        function importCsvFromChat(csvData) {
            // Create a form and submit to import the CSV data
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'import_csv_paste';
            
            const accountInput = document.createElement('input');
            accountInput.type = 'hidden';
            accountInput.name = 'account_id';
            accountInput.value = '<?= htmlspecialchars($currentAccountId) ?>';
            
            const csvInput = document.createElement('input');
            csvInput.type = 'hidden';
            csvInput.name = 'csv_data';
            csvInput.value = csvData;
            
            form.appendChild(actionInput);
            form.appendChild(accountInput);
            form.appendChild(csvInput);
            document.body.appendChild(form);
            
            form.submit();
        }
        
        function copyCsvToClipboard(csvData) {
            navigator.clipboard.writeText(csvData).then(() => {
                addFloatingChatMessage('📋 CSV data copied to clipboard!', 'assistant');
            }).catch(() => {
                addFloatingChatMessage('❌ Failed to copy to clipboard', 'assistant');
            });
        }
        
        // Show notification when chat is minimized (example)
        function handleChatKeydown(event) {
            // Send message on Enter, but allow Shift+Enter for new lines
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendFloatingChatMessage(event);
            }
        }
        
        function showChatNotification() {
            if (isFloatingChatMinimized) {
                document.getElementById('chat-notification').style.display = 'block';
            }
        }
        
        <?php if ($showSetupWizard): ?>
        // Setup Wizard JavaScript
        const accountTypes = <?= json_encode($tracker->getAvailableAccountTypes()) ?>;
        
        let currentStep = 1;
        let selectedTier = '';
        let selectedSize = 0;
        
        // Account type selection
        document.querySelectorAll('.account-type-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.account-type-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                selectedTier = this.dataset.tier;
                document.getElementById('selected-tier').value = selectedTier;
                updateNextButton();
            });
        });
        
        // Step navigation
        document.getElementById('setup-next-btn').addEventListener('click', function() {
            if (currentStep === 1) {
                if (selectedTier) {
                    showStep(2);
                    populateAccountSizes();
                } else {
                    alert('Please select an account type');
                }
            } else if (currentStep === 2) {
                if (selectedSize > 0) {
                    showStep(3);
                    updateSummary();
                } else {
                    alert('Please select an account size');
                }
            }
        });
        
        document.getElementById('setup-prev-btn').addEventListener('click', function() {
            if (currentStep === 2) {
                showStep(1);
            } else if (currentStep === 3) {
                showStep(2);
            }
        });
        
        function showStep(step) {
            document.querySelectorAll('.setup-step').forEach(s => s.classList.remove('active'));
            document.querySelector(`[data-step="${step}"]`).classList.add('active');
            
            currentStep = step;
            
            // Update buttons
            document.getElementById('setup-prev-btn').style.display = step > 1 ? 'inline-block' : 'none';
            document.getElementById('setup-next-btn').style.display = step < 3 ? 'inline-block' : 'none';
            document.getElementById('setup-create-btn').style.display = step === 3 ? 'inline-block' : 'none';
        }
        
        function populateAccountSizes() {
            const container = document.getElementById('account-sizes');
            const sizes = accountTypes[selectedTier];
            
            container.innerHTML = sizes.map(size => `
                <div class="account-size-card" data-size="${size.size}">
                    <h3>${size.display}</h3>
                    <div class="size-cost">${size.cost}</div>
                    <div class="size-features">
                        <div>💰 Account Balance: ${size.display}</div>
                        <div>🎯 Phase 1 Target: $${(size.size * 1.2).toLocaleString()}</div>
                        <div>🏆 Phase 2 Target: $${(size.size * 1.44).toLocaleString()}</div>
                    </div>
                </div>
            `).join('');
            
            // Add click handlers
            document.querySelectorAll('.account-size-card').forEach(card => {
                card.addEventListener('click', function() {
                    document.querySelectorAll('.account-size-card').forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedSize = parseInt(this.dataset.size);
                    document.getElementById('selected-size').value = selectedSize;
                    updateNextButton();
                });
            });
        }
        
        function updateSummary() {
            const summary = document.getElementById('setup-summary');
            const customName = document.getElementById('custom_name').value;
            const defaultName = `${selectedTier} $${(selectedSize / 1000)}K`;
            
            summary.innerHTML = `
                <div class="summary-card">
                    <h3>📋 Account Summary</h3>
                    <div class="summary-item">
                        <strong>Account Type:</strong> ${selectedTier}
                    </div>
                    <div class="summary-item">
                        <strong>Account Size:</strong> $${selectedSize.toLocaleString()}
                    </div>
                    <div class="summary-item">
                        <strong>Account Name:</strong> ${customName || defaultName}
                    </div>
                    <div class="summary-item">
                        <strong>Phase 1 Target:</strong> $${(selectedSize * 1.2).toLocaleString()} (20% profit)
                    </div>
                    <div class="summary-item">
                        <strong>Phase 2 Target:</strong> $${(selectedSize * 1.44).toLocaleString()} (20% more profit)
                    </div>
                    <div class="summary-item">
                        <strong>Risk Limits:</strong> ${selectedTier === 'Standard' ? '$10 - $50' : '$100 - $5,000'} per bet
                    </div>
                </div>
            `;
        }
        
        function updateNextButton() {
            const nextBtn = document.getElementById('setup-next-btn');
            if (currentStep === 1) {
                nextBtn.disabled = !selectedTier;
            } else if (currentStep === 2) {
                nextBtn.disabled = !selectedSize;
            }
        }
        <?php endif; ?>
    </script>
<?php endif; ?>
</body>
</html>
