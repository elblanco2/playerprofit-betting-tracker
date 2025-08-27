# 🏆 PlayerProfit Betting Tracker

A comprehensive betting tracker designed for PlayerProfit prop firm accounts with advanced visual dashboards, multi-account support, and intelligent import capabilities.

## 🚀 Quick Start

1. **Start the Application**
   ```bash
   docker-compose up -d
   ```

2. **Access the Tracker**
   - Open: http://localhost:8004
   - Setup your first account
   - Start tracking bets!

## ✨ Key Features

### 📊 **Advanced Visual Dashboard**
- **Circular Progress Rings** - Animated profit target tracking
- **Risk Gauge Meters** - Real-time risk assessment 
- **Heat Map Calendar** - 30-day performance visualization
- **Interactive Charts** - Balance history with Chart.js
- **Dark Mode Interface** - Professional dark theme

### 🏦 **Multi-Account Support**
- Track up to 6 PlayerProfit accounts simultaneously
- Individual risk limits and phase progression
- Account switching with session management
- Separate data storage per account

### 📥 **Intelligent Import System**
- **CSV Import** - Structured data with real-time validation
- **File Upload** - Batch import from CSV files
- **LLM Smart Parser** - AI-powered parsing of unstructured text
- **PlayerProfit Integration** - Direct platform connection (coming soon)

### 📱 **Enhanced User Experience**  
- **Mobile Responsive** - Optimized for all devices
- **Keyboard Shortcuts** - Quick access (Ctrl+B, Ctrl+P, etc.)
- **Auto-complete Forms** - Smart suggestions and validation
- **Real-time Updates** - Live dashboard components

### 🛡️ **PlayerProfit Compliance**
- **Risk Management** - 1% Standard, 2% Pro minimum bets
- **Drawdown Protection** - 15% from peak balance
- **Violation Monitoring** - Real-time compliance checking
- **Phase Progression** - Automatic advancement tracking

## 📥 Import Your Betting History

### Method 1: CSV Data Paste
Perfect for small to medium datasets:
```csv
Date,Sport,Selection,Stake,Odds,Result
2025-01-15,NFL,Patriots ML,1000,-110,WIN
2025-01-14,NBA,Lakers +5.5,1500,-105,LOSS
```

### Method 2: File Upload
Upload CSV files exported from other systems:
- Supports `.csv` and `.txt` files
- Automatic header detection
- Comprehensive error reporting

### Method 3: LLM Smart Parser 🧠
AI-powered parsing for messy data:
```
Patriots vs Bills game on 1/15/25, took Patriots ML for $1000 at -110 odds, WON
Lakers +5.5 yesterday $1500 at -105, lost that one
```

**Supported LLM Providers:**
- OpenAI GPT-4 (Recommended)
- Anthropic Claude
- Google Gemini  
- Local Ollama

## 📚 Documentation

- **[📖 Complete Import Guide](IMPORT_GUIDE.md)** - Detailed import instructions
- **[📋 Sample CSV Files](sample-standard-bets.csv)** - Example data formats
- **Mixed Format Examples** - Flexible import examples

## 🔧 Technical Details

### Architecture
- **Backend:** PHP 8.2 with Apache
- **Frontend:** Vanilla JavaScript with Chart.js
- **Storage:** JSON file-based data persistence
- **Containerization:** Docker with docker-compose

### File Structure
```
playerprofit/
├── index.php              # Main application
├── assets/
│   ├── css/               # Enhanced UI styles
│   └── js/                # Dashboard components
├── data/                  # Account data storage
├── IMPORT_GUIDE.md        # Comprehensive guide
└── sample-*.csv           # Example data files
```

### API Integration
The LLM parser supports multiple providers:
- Secure API key handling
- Provider-specific prompt optimization  
- Response validation and cleanup
- Error handling with fallbacks

## 🎯 PlayerProfit Rules

### Account Types
- **Standard Accounts:** 1% minimum bet size, $50-$1000 max risk
- **Pro Accounts:** 2% minimum bet size, $2500-$5000 max risk

### Risk Management
- **Drawdown Protection:** 15% maximum from peak balance
- **Daily Limits:** 10% of account size maximum loss
- **Activity Requirements:** 5-day limit for funded accounts

### Phase Progression
- **Phase 1:** Reach 20% profit target
- **Phase 2:** Maintain consistency requirements  
- **Funded:** Full profit sharing activated

## 🚀 Coming Soon

- **PlayerProfit API Integration** - Direct platform connection
- **Parlay Import Support** - Multi-leg bet handling
- **Advanced Analytics** - Detailed performance metrics
- **Browser Extension** - One-click data extraction
- **Mobile App** - Native iOS/Android applications

## 📞 Support

Need help with imports or have questions?
- Check the [Import Guide](IMPORT_GUIDE.md) for detailed instructions
- Review sample CSV files for format examples
- Use the built-in validation and preview features

---

*PlayerProfit Betting Tracker - Professional prop firm account management*  
*Version 2.0 | Production Ready | August 2025*