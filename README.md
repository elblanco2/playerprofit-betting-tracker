# 🏆 PlayerProfit Betting Tracker v1.0

A comprehensive betting tracking application designed specifically for **PlayerProfit** prop firm challenge participants. Features multi-account support, AI-powered bet import, real-time compliance monitoring, and advanced analytics with a modern responsive UI.

## 🎯 PlayerProfit Account Integration

This tracker is built specifically for PlayerProfit's prop firm structure:

### **Standard Accounts**
- **Account Sizes:** $1K, $5K, $10K, $25K, $50K, $100K  
- **Risk Limits:** Min $10 | Max $50 per bet
- **Phases:** Phase 1 → Phase 2 → Funded
- **Requirements:** 20 picks minimum per phase

### **Pro Accounts** 
- **Account Sizes:** $5K, $10K, $25K, $50K, $100K
- **Risk Limits:** Min $100 | Max $500 per bet  
- **Phases:** Phase 1 → Phase 2 → Funded
- **Requirements:** 20 picks minimum per phase

## 🚀 Quick Start

### **1. Start the Tracker**
```bash
cd /Users/luca/scripts/results/betsize/playerprofit
chmod +x run.sh
./run.sh start
```

### **2. Access Dashboard**
- Open: http://localhost:8004
- Complete account setup on first visit
- Choose your account tier (Standard/Pro)
- Select your account size

### **3. Start Tracking**
- Add your bets with automatic risk validation
- Monitor compliance in real-time
- Track progress toward profit targets
- Advance through phases automatically

## 📊 Key Features

### **🎯 Multi-Account Management**
- **Multiple Accounts:** Track Standard and Pro accounts simultaneously
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
- **Daily Loss Monitoring:** 10% max daily loss tracking
- **Drawdown Protection:** 15% max drawdown alerts  
- **Bet Size Validation:** Automatic min/max enforcement
- **Pick Count Tracking:** 20 pick minimum enforcement
- **Parlay Support:** Reverse odds calculation for winning parlays

### **📈 Phase Progression**
- **Phase 1:** 20% profit target to advance
- **Phase 2:** 20% profit target to get funded  
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
1. **Choose Tier:** Standard vs Pro accounts
2. **Select Size:** Pick your account size
3. **Start Phase 1:** Begin with 20% profit target

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
- **Daily Loss Tracking:** 10% limit monitoring
- **Drawdown Calculations:** 15% max from peak
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
- **Daily Loss:** Sum of today's P&L vs 10% account size
- **Max Drawdown:** Peak balance minus current lowest point
- **Profit Target:** 20% of phase starting balance
- **Pick Count:** Total completed bets vs 20 minimum

## 🎯 PlayerProfit Rules Compliance

### **Standard Account Rules**
| Rule | Limit | Tracking |
|------|-------|----------|
| Min Risk | $10 | ✅ Enforced |
| Max Risk | $50 | ✅ Enforced |
| Daily Loss | 10% | ✅ Monitored |
| Max Drawdown | 15% | ✅ Monitored |
| Pick Minimum | 20 picks | ✅ Tracked |
| Profit Target | 20% (Phase 1 & 2) | ✅ Tracked |
| Inactivity | 5 days (Funded) | ✅ Monitored |

### **Pro Account Rules** 
| Rule | Limit | Tracking |
|------|-------|----------|
| Min Risk | $100 | ✅ Enforced |
| Max Risk | $500 | ✅ Enforced |
| Daily Loss | 10% | ✅ Monitored |
| Max Drawdown | 15% | ✅ Monitored |
| Pick Minimum | 20 picks | ✅ Tracked |
| Profit Target | 20% (Phase 1 & 2) | ✅ Tracked |
| Inactivity | 5 days (Funded) | ✅ Monitored |

## 🚨 Violation Alerts

### **Critical Violations (Account Ending)**
- **Daily Loss Exceeded** - More than 10% loss in one day
- **Max Drawdown Hit** - More than 15% drawdown from peak  
- **Inactivity Violation** - More than 5 days without activity (Funded)

### **Warning Alerts**
- **Pick Minimum Not Met** - Less than 20 completed picks
- **Approaching Limits** - Getting close to violation thresholds

## 🎉 Phase Advancement

### **Phase 1 → Phase 2**
- ✅ 20% profit target achieved
- ✅ 20 picks minimum completed  
- ✅ No active violations
- 🎯 **Click "Advance to Phase 2"**

### **Phase 2 → Funded**
- ✅ 20% profit target achieved  
- ✅ 20 picks minimum completed
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

### v2.0 - Coming Soon
**Planned Features:**
- 🔄 First-time setup wizard for new users
- 🔄 Dynamic account creation system
- 🔄 All PlayerProfit account sizes ($1K, $5K, $10K, $25K, $50K, $100K)
- 🔄 Independent user setup (no pre-configured accounts)
- 🔄 Enhanced onboarding experience
- 🔄 Account template system

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
