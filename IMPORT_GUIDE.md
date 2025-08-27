# 📥 PlayerProfit Betting Tracker - Import Guide

## Overview
The PlayerProfit Betting Tracker offers multiple ways to import your betting history, making it easy to transition from manual tracking or other systems.

---

## 📋 Method 1: CSV Data Import

### Required CSV Format

Your CSV data must follow this **exact column order**:

```
Date,Sport,Selection,Stake,Odds,Result
```

### Column Specifications

| Column | Description | Format | Example |
|--------|-------------|--------|---------|
| **Date** | Bet placement date | YYYY-MM-DD, MM/DD/YYYY, or DD/MM/YYYY | `2025-01-15` |
| **Sport** | Sport/league name | Any text | `NFL`, `NBA`, `MLB`, `Soccer` |
| **Selection** | Bet description | Any text | `Patriots ML`, `Lakers +5.5` |
| **Stake** | Bet amount in dollars | Number ($ signs optional) | `1000`, `$1,500` |
| **Odds** | American odds | Integer with + or - | `-110`, `+150` |
| **Result** | Bet outcome | WIN, LOSS, or PUSH | `WIN` |

### ✅ Valid CSV Examples

```csv
Date,Sport,Selection,Stake,Odds,Result
2025-01-15,NFL,Patriots ML,1000,-110,WIN
2025-01-14,NBA,Lakers +5.5,1500,-105,LOSS
2025-01-13,MLB,Yankees Over 9.5,2000,+120,WIN
01/12/2025,Tennis,Djokovic ML,$800,-200,PUSH
```

### ❌ Common CSV Errors

```csv
# Missing columns (only 5 instead of 6)
2025-01-15,NFL,Patriots ML,1000,-110

# Invalid result (must be WIN/LOSS/PUSH)
2025-01-15,NFL,Patriots ML,1000,-110,Won

# Invalid odds format (missing +/-)
2025-01-15,NFL,Patriots ML,1000,110,WIN

# Invalid date format
Jan 15 2025,NFL,Patriots ML,1000,-110,WIN
```

---

## 📁 Method 2: File Upload

### Supported File Types
- `.csv` files
- `.txt` files with comma-separated values

### File Requirements
- Maximum file size: 10MB
- UTF-8 encoding recommended
- Can include header row (will be automatically detected and skipped)

### Creating CSV Files

**From Excel/Google Sheets:**
1. Format your data in the required columns
2. File → Save As → CSV (Comma delimited)
3. Upload the saved .csv file

**From PlayerProfit (Manual Export):**
1. Copy your bet history from PlayerProfit dashboard
2. Paste into Excel/Google Sheets
3. Rearrange columns to match required format
4. Save as CSV

---

## 🔗 Method 3: Smart Text Parser (LLM-Powered)

### When to Use
- You have unstructured bet data from PlayerProfit
- Copied text from various betting platforms  
- Mixed format data that doesn't fit CSV structure

### Supported Input Formats
- PlayerProfit dashboard copy/paste
- Sportsbook bet slips
- Free-form betting notes
- Mixed text and numbers

### Example Input Text
```
Patriots vs Bills game on 1/15/25, took Patriots ML for $1000 at -110 odds, WON
Lakers +5.5 yesterday $1500 at -105, lost that one
Yankees over 9.5 runs Jan 13 2025, bet $2K at +120 odds - WIN!
```

### API Configuration
You'll need an API key from one of these providers:
- **OpenAI GPT-4** (Recommended)
- **Anthropic Claude**
- **Google Gemini**
- **Local LLM** (Ollama, etc.)

---

## 🚀 Step-by-Step Import Process

### Using CSV Paste Method

1. **Navigate to Import Tab**
   - Click "📥 Import Bets" in the main navigation

2. **Prepare Your Data**
   - Format data according to CSV specifications above
   - Include header row for clarity (optional)

3. **Paste and Preview**
   - Paste your CSV data into the text area
   - Real-time preview shows validation results
   - ✅ Green rows = valid, ❌ Red rows = errors

4. **Review Preview**
   - Check the summary: "X valid rows, Y errors"
   - Fix any validation errors before importing

5. **Import**
   - Click "📥 Import CSV Data"
   - System processes each row and provides feedback

### Using File Upload Method

1. **Prepare CSV File**
   - Create CSV file with proper format
   - Save with .csv extension

2. **Upload File**
   - Click "Choose File" in Method 2 section
   - Select your CSV file
   - Click "📁 Upload and Import File"

3. **Review Results**
   - System shows import summary
   - Any errors are reported with line numbers

### Using LLM Smart Parser

1. **Configure API**
   - Enter your preferred LLM API key
   - Select provider (OpenAI, Claude, etc.)

2. **Paste Raw Text**
   - Copy betting data from any source
   - Paste into the text area
   - Can be messy, unstructured text

3. **LLM Processing**
   - AI analyzes and structures your data
   - Converts to proper CSV format
   - Shows confidence scores

4. **Review & Import**
   - Check AI-generated CSV
   - Make manual corrections if needed
   - Import the processed data

---

## 🛠️ Troubleshooting

### Common Import Issues

**"Not enough fields" error:**
- Ensure exactly 6 columns: Date,Sport,Selection,Stake,Odds,Result
- Check for missing commas

**Date format errors:**
- Use YYYY-MM-DD, MM/DD/YYYY, or DD/MM/YYYY
- Avoid text dates like "January 15, 2025"

**Invalid odds:**
- Must be integers with + or - prefix
- Examples: -110, +150, -200
- Avoid decimal odds or fractional odds

**Stake validation:**
- Must be positive numbers
- Dollar signs and commas are automatically removed
- Ensure minimum bet requirements are met

### Import Validation

The system validates each bet against PlayerProfit rules:
- ✅ **Stake Amount**: Must meet account minimum bet size
- ✅ **Risk Limits**: Cannot exceed maximum bet limits  
- ✅ **Date Logic**: No future dates allowed
- ✅ **Duplicate Check**: Prevents importing same bet twice
- ✅ **Account Balance**: Maintains accurate running balance

---

## 📊 Best Practices

### Before Importing
1. **Backup Current Data**: Export existing bets before large imports
2. **Test Small Batches**: Start with 5-10 bets to verify format
3. **Sort by Date**: Import older bets first for chronological order

### Data Quality
1. **Consistent Formatting**: Use same date format throughout
2. **Accurate Stakes**: Double-check bet amounts
3. **Proper Results**: Only use WIN/LOSS/PUSH (case insensitive)

### Performance Tips
1. **Batch Size**: Import 100-500 bets at a time for best performance
2. **Network Stability**: Large imports need stable internet connection
3. **Browser Tab**: Keep import tab active during processing

---

## 🔄 PlayerProfit Platform Integration

### Current Limitations
PlayerProfit doesn't offer direct CSV export, making manual data extraction necessary.

### Workarounds

**Method A: Screenshot + OCR (Future Feature)**
- Take screenshots of bet history
- OCR extraction converts images to text
- LLM parser structures the data

**Method B: Browser Extension (Planned)**
- Automatic data extraction from PlayerProfit dashboard
- One-click export to CSV format
- Direct import to tracking system

**Method C: Manual Copy/Paste + LLM**
- Select and copy bet history from PlayerProfit
- Use LLM Smart Parser to structure data
- Import processed results

---

## 🆘 Support & Examples

### Sample Data Files
Download example CSV files to test import functionality:

- [Sample Standard Bets](sample-standard-bets.csv)
- [Sample Parlay Bets](sample-parlay-bets.csv)  
- [Sample Mixed Format](sample-mixed-format.csv)

### Need Help?
- Check the preview feature for validation feedback
- Review error messages for specific line issues
- Contact support with your CSV data for assistance

---

## 📝 FAQ

**Q: Can I import parlay bets?**
A: Currently, the import system processes each bet individually. Parlay support coming in future updates.

**Q: What happens to duplicate bets?**
A: The system checks for duplicates based on date, selection, and stake. Duplicates are skipped with a warning.

**Q: Can I import from other tracking systems?**
A: Yes! Export your data to CSV format matching our specifications, then import.

**Q: Is my betting data secure during import?**
A: All imports are processed locally. LLM parsing may send data to third-party APIs - check your provider's privacy policy.

**Q: What's the maximum number of bets I can import?**
A: No hard limit, but we recommend batches of 500 bets for optimal performance.

---

*Last Updated: August 2025*  
*Version: 2.0*