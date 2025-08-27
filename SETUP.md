# 🚀 PlayerProfit Tracker - Quick Setup Guide

## Prerequisites
- Docker and Docker Compose installed
- Port 8004 available (different from main tracker on 8003)

## Setup Steps

### 1. Make Scripts Executable
```bash
cd /Users/luca/scripts/results/betsize/playerprofit
chmod +x run.sh
```

### 2. Start the Tracker
```bash
./run.sh start
```

### 3. Initial Configuration
1. **Open:** http://localhost:8004
2. **Select Account Tier:** Standard or Pro
3. **Choose Account Size:** Based on your PlayerProfit account
4. **Click "Setup Account"**

### 4. Start Tracking
- Add your first bet with proper stake limits
- Monitor compliance in real-time
- Track progress toward 20% profit target
- Watch for violation alerts

## Account Tiers

### Standard Accounts
- **Sizes:** $1K, $5K, $10K, $25K, $50K, $100K
- **Risk per bet:** $10 - $50
- **Daily loss limit:** 10% of account size
- **Max drawdown:** 15% of account size

### Pro Accounts  
- **Sizes:** $5K, $10K, $25K, $50K, $100K
- **Risk per bet:** $100 - $500
- **Daily loss limit:** 10% of account size  
- **Max drawdown:** 15% of account size

## Phase Progression

### Phase 1
- **Target:** 20% profit to advance
- **Requirement:** 20 picks minimum
- **Advance to:** Phase 2

### Phase 2  
- **Target:** 20% profit to get funded
- **Requirement:** 20 picks minimum
- **Advance to:** Funded Account

### Funded
- **Target:** No profit target
- **Requirement:** Stay active (5-day limit)
- **Status:** Keep your profits!

## Management Commands

```bash
# Check status
./run.sh status

# View logs  
./run.sh logs

# Restart
./run.sh restart

# Stop
./run.sh stop
```

## Key Features

✅ **Real-time Risk Monitoring** - Instant violation detection
✅ **Phase Progression Tracking** - Visual progress indicators  
✅ **Automatic Compliance** - Built-in PlayerProfit rules
✅ **Discord Integration** - Formatted status reports
✅ **Professional UI** - Clean, modern interface

## Support

- **Main Tracker:** Available at http://localhost:8003
- **PlayerProfit Tracker:** Available at http://localhost:8004
- **Data Location:** `./data/` folder with automatic persistence

Ready to dominate your PlayerProfit challenge! 🏆
