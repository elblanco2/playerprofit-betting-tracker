/**
 * Enhanced Form Features for PlayerProfit Betting Tracker
 * Auto-complete, quick amounts, validation, and smart suggestions
 */

class EnhancedForm {
    constructor() {
        this.suggestions = {
            sports: ['NFL', 'NBA', 'MLB', 'NHL', 'Soccer', 'Tennis', 'MMA', 'Boxing'],
            teams: {
                'NFL': ['Patriots', 'Cowboys', 'Packers', 'Steelers', 'Giants', 'Eagles'],
                'NBA': ['Lakers', 'Warriors', 'Celtics', 'Bulls', 'Heat', 'Knicks'],
                'MLB': ['Yankees', 'Dodgers', 'Red Sox', 'Giants', 'Cubs', 'Astros'],
                'NHL': ['Rangers', 'Bruins', 'Kings', 'Blackhawks', 'Penguins', 'Capitals']
            },
            commonBets: [
                'Moneyline', 'Spread', 'Over/Under', 'First Half', 'Player Props'
            ]
        };
        
        this.recentSelections = JSON.parse(localStorage.getItem('recentSelections') || '[]');
        this.init();
    }

    init() {
        this.setupAutoComplete();
        this.setupQuickAmounts();
        this.setupSmartValidation();
        this.setupFormPersistence();
        this.setupMobileOptimizations();
        this.enhanceOddsInput();
        this.setupParlayEnhancements();
    }

    // === AUTO-COMPLETE FUNCTIONALITY ===
    setupAutoComplete() {
        const inputs = document.querySelectorAll('input[type="text"], select');
        inputs.forEach(input => {
            if (input.name === 'selection' || input.name === 'sport') {
                this.makeInputSmart(input);
            }
        });
    }

    makeInputSmart(input) {
        const wrapper = document.createElement('div');
        wrapper.className = 'smart-input';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const suggestionsDiv = document.createElement('div');
        suggestionsDiv.className = 'input-suggestions';
        suggestionsDiv.style.display = 'none';
        wrapper.appendChild(suggestionsDiv);

        input.addEventListener('input', (e) => {
            this.showSuggestions(input, suggestionsDiv, e.target.value);
        });

        input.addEventListener('focus', (e) => {
            if (e.target.value.length === 0) {
                this.showRecentSuggestions(input, suggestionsDiv);
            }
        });

        input.addEventListener('blur', (e) => {
            // Delay hiding to allow clicking on suggestions
            setTimeout(() => {
                suggestionsDiv.style.display = 'none';
            }, 150);
        });
    }

    showSuggestions(input, container, value) {
        if (value.length < 1) {
            container.style.display = 'none';
            return;
        }

        let suggestions = [];

        if (input.name === 'sport') {
            suggestions = this.suggestions.sports.filter(sport => 
                sport.toLowerCase().includes(value.toLowerCase())
            );
        } else if (input.name === 'selection') {
            const sport = document.querySelector('select[name="sport"]')?.value || '';
            suggestions = this.getSelectionSuggestions(value, sport);
        }

        this.renderSuggestions(container, suggestions, input);
    }

    getSelectionSuggestions(value, sport) {
        const suggestions = [];
        
        // Add team suggestions
        if (this.suggestions.teams[sport]) {
            this.suggestions.teams[sport].forEach(team => {
                if (team.toLowerCase().includes(value.toLowerCase())) {
                    suggestions.push(`${team} ML`);
                    suggestions.push(`${team} -1.5`);
                    suggestions.push(`${team} +1.5`);
                }
            });
        }

        // Add common bet type suggestions
        this.suggestions.commonBets.forEach(bet => {
            if (bet.toLowerCase().includes(value.toLowerCase())) {
                suggestions.push(bet);
            }
        });

        // Add recent selections
        this.recentSelections.forEach(recent => {
            if (recent.toLowerCase().includes(value.toLowerCase())) {
                suggestions.push(recent);
            }
        });

        return suggestions.slice(0, 8); // Limit to 8 suggestions
    }

    showRecentSuggestions(input, container) {
        if (this.recentSelections.length === 0) return;

        const suggestions = this.recentSelections.slice(0, 5);
        this.renderSuggestions(container, suggestions, input, 'Recent:');
    }

    renderSuggestions(container, suggestions, input, header = '') {
        if (suggestions.length === 0) {
            container.style.display = 'none';
            return;
        }

        let html = '';
        if (header) {
            html += `<div style="padding: 5px 15px; font-size: 11px; color: #999; border-bottom: 1px solid rgba(255,255,255,0.1);">${header}</div>`;
        }

        suggestions.forEach(suggestion => {
            html += `
                <div class="suggestion-item" onclick="this.parentElement.style.display='none'; 
                     document.querySelector('input[name=&quot;${input.name}&quot;]').value='${suggestion.replace(/'/g, "\\'")}';
                     document.querySelector('input[name=&quot;${input.name}&quot;]').focus();">
                    ${suggestion}
                </div>
            `;
        });

        container.innerHTML = html;
        container.style.display = 'block';
    }

    // === QUICK AMOUNT BUTTONS ===
    setupQuickAmounts() {
        const stakeInput = document.querySelector('input[name="stake"]');
        if (!stakeInput) return;

        const quickAmounts = document.createElement('div');
        quickAmounts.className = 'quick-bet-amounts';
        
        // Get account info for calculating percentages
        const accountBalance = this.getCurrentAccountBalance();
        const amounts = this.calculateQuickAmounts(accountBalance);
        
        amounts.forEach(amount => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'quick-amount-btn';
            btn.textContent = `$${amount.value}`;
            btn.title = amount.description;
            btn.onclick = () => {
                stakeInput.value = amount.value;
                stakeInput.dispatchEvent(new Event('input'));
                this.showNotification(`Set stake to ${amount.description}`, 'info', 2000);
            };
            quickAmounts.appendChild(btn);
        });

        stakeInput.parentNode.insertBefore(quickAmounts, stakeInput.nextSibling);
    }

    calculateQuickAmounts(balance) {
        const amounts = [];
        
        // Minimum bet (1% for Standard, 2% for Pro)
        const isProAccount = this.isProAccount();
        const minPercent = isProAccount ? 2 : 1;
        const minAmount = Math.round((balance * minPercent) / 100);
        
        amounts.push({ 
            value: minAmount, 
            description: `Min (${minPercent}%)` 
        });
        
        // Conservative amounts
        amounts.push({ 
            value: Math.round(minAmount * 1.5), 
            description: '1.5x Min' 
        });
        
        amounts.push({ 
            value: Math.round(minAmount * 2), 
            description: '2x Min' 
        });
        
        // Aggressive amounts (if not close to limits)
        if (minAmount * 3 < balance * 0.05) { // Less than 5% of balance
            amounts.push({ 
                value: Math.round(minAmount * 3), 
                description: '3x Min' 
            });
        }
        
        // Round numbers
        [500, 1000, 2000, 5000].forEach(amount => {
            if (amount >= minAmount && amount <= balance * 0.1 && 
                !amounts.find(a => a.value === amount)) {
                amounts.push({ 
                    value: amount, 
                    description: `$${amount}` 
                });
            }
        });
        
        return amounts.slice(0, 6); // Limit to 6 buttons
    }

    getCurrentAccountBalance() {
        // This would get actual account balance - placeholder for now
        return 50000;
    }

    isProAccount() {
        // This would check actual account type - placeholder for now
        return true;
    }

    // === SMART VALIDATION ===
    setupSmartValidation() {
        const form = document.querySelector('form');
        if (!form) return;

        const inputs = form.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('blur', (e) => this.validateField(e.target));
            input.addEventListener('input', (e) => this.clearFieldError(e.target));
        });

        form.addEventListener('submit', (e) => this.validateForm(e));
    }

    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let message = '';

        switch(field.name) {
            case 'stake':
                const stake = parseFloat(value);
                const balance = this.getCurrentAccountBalance();
                const minBet = this.isProAccount() ? balance * 0.02 : balance * 0.01;
                const maxBet = balance * 0.1; // 10% max
                
                if (stake < minBet) {
                    isValid = false;
                    message = `Minimum bet: $${minBet.toFixed(2)}`;
                } else if (stake > maxBet) {
                    isValid = false;
                    message = `Maximum bet: $${maxBet.toFixed(2)}`;
                }
                break;

            case 'odds':
                const odds = parseInt(value);
                if (odds === 0 || (odds > -50 && odds < 50 && odds !== 0)) {
                    isValid = false;
                    message = 'Odds seem unusual. Please verify.';
                }
                break;

            case 'selection':
                if (value.length < 3) {
                    isValid = false;
                    message = 'Selection description too short';
                }
                break;
        }

        this.showFieldValidation(field, isValid, message);
        return isValid;
    }

    showFieldValidation(field, isValid, message) {
        // Remove existing error
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) existingError.remove();

        if (!isValid && message) {
            const error = document.createElement('div');
            error.className = 'field-error';
            error.style.cssText = `
                color: #f44336;
                font-size: 12px;
                margin-top: 4px;
                padding: 4px 8px;
                background: rgba(244,67,54,0.1);
                border-radius: 4px;
                border-left: 3px solid #f44336;
            `;
            error.textContent = message;
            field.parentNode.appendChild(error);
            
            field.style.borderColor = '#f44336';
        } else {
            field.style.borderColor = '';
        }
    }

    clearFieldError(field) {
        const error = field.parentNode.querySelector('.field-error');
        if (error) error.remove();
        field.style.borderColor = '';
    }

    validateForm(event) {
        const form = event.target;
        const inputs = form.querySelectorAll('input[required], select[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });

        if (!isValid) {
            event.preventDefault();
            this.showNotification('Please fix the errors before submitting', 'danger', 3000);
        } else {
            // Save selection to recent list
            const selection = form.querySelector('input[name="selection"]')?.value;
            if (selection) {
                this.addToRecentSelections(selection);
            }
        }
    }

    // === FORM PERSISTENCE ===
    setupFormPersistence() {
        const form = document.querySelector('form');
        if (!form) return;

        // Load saved data
        this.loadFormData(form);

        // Save data on changes
        const inputs = form.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('input', () => this.saveFormData(form));
            input.addEventListener('change', () => this.saveFormData(form));
        });

        // Clear saved data on successful submit
        form.addEventListener('submit', () => {
            setTimeout(() => {
                if (!document.querySelector('.field-error')) {
                    this.clearSavedFormData();
                }
            }, 100);
        });
    }

    saveFormData(form) {
        const data = {};
        const inputs = form.querySelectorAll('input, select');
        
        inputs.forEach(input => {
            if (input.name && input.value) {
                data[input.name] = input.value;
            }
        });

        localStorage.setItem('formData', JSON.stringify(data));
    }

    loadFormData(form) {
        const savedData = localStorage.getItem('formData');
        if (!savedData) return;

        try {
            const data = JSON.parse(savedData);
            Object.keys(data).forEach(key => {
                const input = form.querySelector(`[name="${key}"]`);
                if (input && !input.value) {
                    input.value = data[key];
                }
            });
        } catch (e) {
            console.warn('Failed to load saved form data:', e);
        }
    }

    clearSavedFormData() {
        localStorage.removeItem('formData');
    }

    // === ENHANCED ODDS INPUT ===
    enhanceOddsInput() {
        const oddsInput = document.querySelector('input[name="odds"]');
        if (!oddsInput) return;

        // Add odds format toggle
        const formatToggle = document.createElement('div');
        formatToggle.className = 'odds-format-toggle';
        formatToggle.style.cssText = `
            display: flex;
            gap: 8px;
            margin-top: 8px;
            font-size: 12px;
        `;
        
        const formats = ['American', 'Decimal', 'Fractional'];
        formats.forEach(format => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = format;
            btn.className = format === 'American' ? 'format-btn active' : 'format-btn';
            btn.onclick = () => this.switchOddsFormat(format, btn);
            formatToggle.appendChild(btn);
        });

        oddsInput.parentNode.appendChild(formatToggle);

        // Add odds converter tooltip
        oddsInput.addEventListener('input', (e) => {
            this.showOddsConversions(e.target);
        });
    }

    switchOddsFormat(format, button) {
        // Update active button
        button.parentNode.querySelectorAll('.format-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        button.classList.add('active');

        // Convert current odds to new format
        const oddsInput = document.querySelector('input[name="odds"]');
        if (oddsInput.value) {
            const converted = this.convertOdds(parseFloat(oddsInput.value), 'American', format);
            oddsInput.value = converted;
            this.showOddsConversions(oddsInput);
        }
    }

    convertOdds(odds, fromFormat, toFormat) {
        if (fromFormat === toFormat) return odds;
        
        // Convert to decimal first
        let decimal;
        if (fromFormat === 'American') {
            decimal = odds > 0 ? (odds / 100) + 1 : (100 / Math.abs(odds)) + 1;
        } else if (fromFormat === 'Decimal') {
            decimal = odds;
        } else if (fromFormat === 'Fractional') {
            const [num, den] = odds.toString().split('/');
            decimal = (parseFloat(num) / parseFloat(den)) + 1;
        }

        // Convert from decimal to target format
        if (toFormat === 'American') {
            return decimal >= 2 ? Math.round((decimal - 1) * 100) : Math.round(-100 / (decimal - 1));
        } else if (toFormat === 'Decimal') {
            return decimal.toFixed(2);
        } else if (toFormat === 'Fractional') {
            const fractional = decimal - 1;
            return `${fractional.toFixed(2)}/1`;
        }
    }

    showOddsConversions(oddsInput) {
        const value = parseFloat(oddsInput.value);
        if (isNaN(value)) return;

        let tooltip = oddsInput.parentNode.querySelector('.odds-tooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.className = 'odds-tooltip';
            tooltip.style.cssText = `
                position: absolute;
                top: 100%;
                left: 0;
                background: rgba(0,0,0,0.9);
                color: white;
                padding: 8px;
                border-radius: 4px;
                font-size: 11px;
                z-index: 100;
                margin-top: 4px;
            `;
            oddsInput.parentNode.style.position = 'relative';
            oddsInput.parentNode.appendChild(tooltip);
        }

        const american = this.convertOdds(value, 'American', 'American');
        const decimal = this.convertOdds(value, 'American', 'Decimal');
        const probability = ((american > 0 ? 100 / (american + 100) : Math.abs(american) / (Math.abs(american) + 100)) * 100).toFixed(1);

        tooltip.innerHTML = `
            American: ${american > 0 ? '+' : ''}${american}<br>
            Decimal: ${decimal}<br>
            Probability: ${probability}%
        `;

        setTimeout(() => {
            if (tooltip.parentNode) tooltip.remove();
        }, 3000);
    }

    // === PARLAY ENHANCEMENTS ===
    setupParlayEnhancements() {
        const parlayCheckbox = document.getElementById('isParlay');
        if (!parlayCheckbox) return;

        // Add parlay calculator preview
        parlayCheckbox.addEventListener('change', () => {
            if (parlayCheckbox.checked) {
                this.showParlayHelper();
            } else {
                this.hideParlayHelper();
            }
        });
    }

    showParlayHelper() {
        let helper = document.getElementById('parlay-helper');
        if (!helper) {
            helper = document.createElement('div');
            helper.id = 'parlay-helper';
            helper.className = 'parlay-helper';
            helper.style.cssText = `
                background: rgba(255,215,0,0.1);
                border: 1px solid #FFD700;
                border-radius: 8px;
                padding: 15px;
                margin: 10px 0;
            `;
            
            helper.innerHTML = `
                <h4 style="color: #FFD700; margin: 0 0 10px 0;">Parlay Calculator</h4>
                <div id="parlay-preview">Add legs to see combined odds</div>
                <div style="margin-top: 10px; font-size: 12px; color: #ccc;">
                    Tip: Higher odds = higher risk but bigger payout
                </div>
            `;
            
            document.querySelector('.parlay-section').appendChild(helper);
        }
    }

    hideParlayHelper() {
        const helper = document.getElementById('parlay-helper');
        if (helper) helper.remove();
    }

    // === UTILITY FUNCTIONS ===
    addToRecentSelections(selection) {
        this.recentSelections = this.recentSelections.filter(s => s !== selection);
        this.recentSelections.unshift(selection);
        this.recentSelections = this.recentSelections.slice(0, 10); // Keep last 10
        localStorage.setItem('recentSelections', JSON.stringify(this.recentSelections));
    }

    showNotification(message, type, duration) {
        if (window.enhancedDashboard) {
            window.enhancedDashboard.showNotification(message, type, duration);
        }
    }

    // === MOBILE OPTIMIZATIONS ===
    setupMobileOptimizations() {
        if (window.innerWidth <= 768) {
            this.enableMobileFeatures();
        }

        window.addEventListener('resize', () => {
            if (window.innerWidth <= 768) {
                this.enableMobileFeatures();
            } else {
                this.disableMobileFeatures();
            }
        });
    }

    enableMobileFeatures() {
        // Larger touch targets
        const buttons = document.querySelectorAll('button, .quick-amount-btn');
        buttons.forEach(btn => {
            btn.style.minHeight = '44px';
            btn.style.minWidth = '44px';
        });

        // Auto-focus prevention on mobile
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', (e) => {
                // Prevent zoom on focus for better UX
                e.target.style.fontSize = '16px';
            });
        });
    }

    disableMobileFeatures() {
        // Reset styles for desktop
        const buttons = document.querySelectorAll('button, .quick-amount-btn');
        buttons.forEach(btn => {
            btn.style.minHeight = '';
            btn.style.minWidth = '';
        });
    }
}

// Initialize enhanced forms when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.enhancedForm = new EnhancedForm();
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EnhancedForm;
}