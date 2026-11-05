# 🏆 Playvo Betting Tracker v2.1

> **Playvo** is the current name of the platform formerly called **PlayerProfit**
> (`playerprofit.com` now redirects to `playvo.us`). Rules below were verified
> against playvo.us on **2026-11-04**.

A comprehensive betting tracking application designed specifically for **Playvo** (ex-PlayerProfit) challenge participants. Features first-time setup wizard, multi-account support, AI-powered bet import, real-time compliance monitoring, and advanced analytics with a modern responsive UI.

[![GitHub release (latest by date)](https://img.shields.io/github/v/release/elblanco2/playerprofit-betting-tracker)](https://github.com/elblanco2/playerprofit-betting-tracker/releases)
[![GitHub](https://img.shields.io/github/license/elblanco2/playerprofit-betting-tracker)](LICENSE)
[![Docker](https://img.shields.io/badge/docker-ready-blue.svg)](Dockerfile)

## 🎯 Playvo Challenge Integration

Verified against [playvo.us/challenges](https://www.playvo.us/challenges) and
[playvo.us/faqs](https://www.playvo.us/faqs) on **2026-11-04**.

### The three challenge types — each has DIFFERENT rules

| Type | Entry | Rounds | Profit target / round | Picks / round | Live picks |
|---|---|---|---|---|---|
| **Standard** | $19.99 | 2 | **+25%** | **25** | ❌ **not allowed** |
| **Pro** | $29.99 | 2 | **+15%** | 20 | ✅ |
| **Blitz** | $39.99 | **1** | +25% | 20 | ✅ |

### Rules common to all types

- **Risk per pick:** **1% – 5%** of bankroll ("1% to 5% of bankroll per pick")
- **Max drawdown:** **10%**, *trailing from your high-water mark* —
  *"Your account cannot drop 10% or more below your highest balance."*
  **The floor rises as you profit:** peak $1,000 → floor $900; peak $1,200 → floor $1,080.
- **Daily loss limit:** anchored to your **starting** balance (static), *not* the peak.
  ⚠️ **The percentage is not published on playvo.us.** This app assumes **10%** and
  labels it unconfirmed (`DAILY_LOSS_CONFIRMED = false`) until verified on your
  dashboard. Shown as a guide, not asserted as fact.
- **Payout once funded:** 70% of profit · **Early cash-out:** 10% fee

### Bankroll sizes

$1,000 ($29.99) · $5,000 ($109.99) · $10,000 ($209.99) ·
$25,000 ($499.99) · $50,000 ($899.99) · $100,000 ($1,499.99)

### ⚠️ Scope: new accounts only

This tracker targets the **current** Playvo products. **Grandfathered PlayerProfit
accounts are out of scope** — their original terms are not recoverable online
(playerprofit.com redirects to playvo.us), and applying today's rules to an old
account would show the wrong limits. If you hold one, track it by hand.

⚠️ **An account's rules are frozen when it is created**, so a later change to a
Playvo product cannot rewrite the terms of an account already in progress.

## 🚀 Quick Start

### **1. Start the Tracker**
```bash
cd /Users/x/playerprofit
chmod +x run.sh
./run.sh start
```

### **2. Access Dashboard**
- Open: http://localhost:8004
- Complete account setup on first visit
- Choose your challenge type (Standard / Pro / Blitz)
- Select your account size

### **3. Start Tracking**
- Add your bets with automatic risk validation
- Monitor compliance in real-time
- Track progress toward profit targets
- Advance through phases automatically

## 📊 Key Features

### **🎯 Multi-Account Management**
- **Multiple Accounts:** Standard, Pro and Blitz tracked simultaneously, each under its own rules
- **Account Switching:** Seamless switching between different account sizes
- **Isolated Data:** Each account maintains separate betting history
- **Real-time Analytics:** Individual progress tracking per account

### **🤖 AI-Powered Import**
- **OpenAI GPT-4:** Parse messy betting data into clean CSV format
- **Anthropic Claude:** Advanced data parsing and analysis
- **Google Gemini:** Alternative AI parsing option
- **Secure Storage:** Encrypted session-based API key management
- **Floating Chat:** Persistent AI chat widget with minimize functionality

### **📥 Import Methods**
- **CSV Import:** Bulk import from spreadsheets with validation
- **AI Parser:** Copy/paste betting data for AI processing
- **Manual Entry:** Traditional form-based bet entry
- **File Upload:** Direct CSV file uploads
- **API Integration:** Future API connectivity options

### **🎯 Risk Management**
- **Daily Loss Monitoring:** 10% of starting balance ⚠️ *unconfirmed — see rules above*
- **Drawdown Protection:** 10% trailing from your high-water mark, showing the floor and dollars-to-breach  
- **Bet Size Validation:** Automatic min/max enforcement
- **Pick Count Tracking:** per challenge type (Standard 25, Pro/Blitz 20)
- **Parlay Support:** Reverse odds calculation for winning parlays

### **📈 Phase Progression**
- **Round 1:** target per type (+25% Standard/Blitz, +15% Pro)
- **Round 2:** same target again (Blitz is a single round)  
- **Funded:** No profit target, focus on consistency
- **Auto-advancement:** Visual indicators when targets are met

### **⚠️ Violation Detection**
- **Critical Violations:** Account-ending rule breaks
- **Warning Alerts:** Approaching violation thresholds
- **Real-time Monitoring:** Instant feedback on compliance
- **Inactivity Timer:** 5-day limit for funded accounts

### **🎨 Modern UI/UX**
- **Responsive Design:** Works on desktop, tablet, and mobile
- **Dark Theme:** Easy on the eyes with modern glassmorphism
- **Sticky Navigation:** Always see which account you're working with
- **Progress Rings:** Visual profit target and compliance indicators
- **Real-time Updates:** Dynamic statistics without page refresh

## 🎮 Usage Guide

### **Account Setup**
1. **Choose Type:** Standard, Pro or Blitz — the rules differ
2. **Select Size:** Pick your account size
3. **Start Round 1:** the target depends on the type you chose

### **Adding Bets**
- **Risk Validation:** Automatic min/max checking
- **Real-time Feedback:** Instant violation warnings  
- **Progress Updates:** See phase advancement status
- **Balance Tracking:** Running account balance

### **Phase Advancement**
- **Automatic Eligibility:** When profit targets are met
- **One-click Advancement:** Easy phase progression
- **Status Updates:** Visual phase indicators
- **Target Recalculation:** New targets for next phase

### **Compliance Monitoring**
- **Daily Loss Tracking:** against the daily limit ⚠️ *% unconfirmed*
- **Drawdown Calculations:** 10% from peak, trailing
- **Activity Tracking:** Inactivity timer for funded accounts
- **Pick Requirements:** 20 minimum pick enforcement

## 📱 Interface Overview

### **🏠 Dashboard**
- **Account Status Cards** - Balance, progress, risk metrics
- **Phase Badge** - Current phase with visual indicator
- **Violation Alerts** - Real-time compliance warnings
- **Progress Bars** - Visual profit target tracking

### **📝 Add Bet Tab** 
- **Risk-validated Form** - Auto min/max enforcement
- **Sport Selection** - NFL, NBA, MLB, NHL, Soccer, Tennis
- **Bet Tracking** - Stakes, odds, results with P&L calculation
- **Instant Feedback** - Immediate violation warnings

### **📋 All Bets Tab**
- **Complete Bet History** - All bets with running balances
- **Result Tracking** - WIN/LOSS/PUSH with color coding
- **P&L Analysis** - Individual and cumulative profits
- **Account Balance** - Balance after each bet

### **📊 Analytics Tab**
- **Daily P&L Tracking** - Today's performance vs limits
- **Drawdown Analysis** - Maximum drawdown from peak
- **Activity Monitoring** - Days since last activity
- **Phase Progress** - Visual progress to targets

### **💬 Discord Tab**
- **PlayerProfit Status** - Formatted for Discord sharing
- **Account Summary** - Tier, size, phase, balance
- **Violation Reporting** - Active compliance issues
- **One-click Copy** - Easy Discord posting

## 🔧 Management Commands

```bash
# Start the tracker
./run.sh start

# Check status  
./run.sh status

# View logs
./run.sh logs

# Restart (after updates)
./run.sh restart

# Stop tracker
./run.sh stop
```

## ⚙️ Technical Details

### **Port & Access**
- **Port:** 8004 (different from main tracker on 8003)
- **URL:** http://localhost:8004
- **Data Storage:** `./data/` folder with automatic persistence

### **Account Configuration**
- **Config File:** `data/account_config.json`
- **Betting Data:** `data/playerprofit_data.json`
- **Automatic Backup** - All data persists between restarts

### **Risk Calculations**
- **Daily Loss:** today's P&L vs 10% of the STARTING balance ⚠️ *unconfirmed*
- **Max Drawdown:** Peak balance minus current lowest point
- **Profit Target:** per type — 25% (Standard/Blitz) or 15% (Pro) of the round's starting balance
- **Pick Count:** completed bets vs the type's minimum (25 or 20)

## 🎯 Playvo rules compliance

### Per-type rules, as enforced by this app

| Rule | Standard | Pro | Blitz |
|---|---|---|---|
| Risk per pick | 1–5% | 1–5% | 1–5% |
| Max drawdown | 10% trailing from peak | 10% trailing | 10% trailing |
| Daily loss | 10% of start ⚠️ unconfirmed | same | same |
| Pick minimum | **25** | 20 | 20 |
| Profit target / round | **+25%** | **+15%** | +25% |
| Rounds | 2 | 2 | **1** |
| Live picks | ❌ | ✅ | ✅ |
| Inactivity (funded) | 5 days | 5 days | 5 days |

**Worked example — $1,000 Pro:** risk $10–$50 per pick · target $150 per round ·
20 picks · drawdown floor $900, rising to $908.85 once the balance reaches $1,009.83.

## 🚨 Violation Alerts

### **Critical Violations (Account Ending)**
- **Daily Loss Exceeded** - more than the daily limit in one day ⚠️ *% unconfirmed*
- **Max Drawdown Hit** - 10% or more below your high-water mark  
- **Inactivity Violation** - More than 5 days without activity (Funded)

### **Warning Alerts**
- **Pick Minimum Not Met** - fewer picks than the type requires (25 or 20)
- **Approaching Limits** - Getting close to violation thresholds

## 🎉 Phase Advancement

### **Phase 1 → Phase 2**
- ✅ the type's profit target achieved
- ✅ the type's pick minimum completed  
- ✅ No active violations
- 🎯 **Click "Advance to Phase 2"**

### **Phase 2 → Funded**
- ✅ the type's profit target achieved  
- ✅ the type's pick minimum completed
- ✅ No active violations
- 🎯 **Click "Advance to Funded Account"**

### **Funded Account**
- 🏆 **No profit target** - focus on consistency
- ⏰ **5-day inactivity limit** - must stay active
- 💰 **Profit sharing** - keep your gains

## 📞 Support & Updates

- **Main Tracker:** Available at port 8003
- **PlayerProfit Tracker:** Available at port 8004
- **Data Independence:** Each tracker maintains separate data
- **Docker Management:** Use `./run.sh` commands for easy management

## 🚀 Version History

### v1.0 - Current Release
**Features Completed:**
- ✅ Multi-account betting tracking system
- ✅ PlayerProfit rule compliance monitoring
- ✅ AI-powered bet import (OpenAI, Claude, Gemini)
- ✅ CSV import functionality with validation
- ✅ Modern responsive UI with dark theme
- ✅ Secure encrypted API key management
- ✅ Floating AI chat widget with minimize feature
- ✅ Progress tracking and analytics dashboard
- ✅ Sticky navigation for better UX
- ✅ Parlay bet support with reverse odds calculation
- ✅ Real-time violation detection and warnings

### v2.0 - Current Release ✨
**Latest Features:**
- ✅ First-time setup wizard for new users
- ✅ Dynamic account creation system  
- ✅ All PlayerProfit account sizes ($1K, $5K, $10K, $25K, $50K, $100K)
- ✅ Independent user setup (no pre-configured accounts)
- ✅ Enhanced onboarding experience
- ✅ Multiple account creation flow
- ✅ Critical compliance fixes (high watermark tracking)
- ✅ Professional account creation success page

### v2.1 - Coming Soon
**Planned Features:**
- 🔄 Enhanced parlay bet analysis
- 🔄 Advanced statistics and reporting
- 🔄 Export functionality
- 🔄 Mobile app companion

## 🔧 Development

### Git Repository
```bash
git init
git add .
git commit -m "v1.0 - Initial release with multi-account tracking and AI import"
git tag v1.0
```

### Security Notes
- User data files (`data/*.json`) are ignored by git for privacy
- API keys encrypted using AES-256-GCM encryption
- No sensitive data stored in plain text or committed to repository
- Session-based security for production deployments

---

**🏆 Built specifically for PlayerProfit prop firm compliance and success tracking!** 🎯
