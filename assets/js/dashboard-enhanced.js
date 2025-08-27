/**
 * Enhanced Dashboard Components for PlayerProfit Betting Tracker
 * Modern visual components with real-time updates
 */

class EnhancedDashboard {
    constructor() {
        this.animations = [];
        this.updateInterval = null;
        this.init();
    }

    init() {
        this.setupProgressRings();
        this.setupRiskGauges();
        this.setupHeatMap();
        this.setupBalanceGraph();
        this.setupNotificationSystem();
        this.setupKeyboardShortcuts();
        this.setupRealTimeUpdates();
    }

    // === CIRCULAR PROGRESS RINGS ===
    setupProgressRings() {
        const containers = document.querySelectorAll('.progress-ring-container');
        containers.forEach(container => {
            const ring = container.querySelector('.progress-ring');
            const circle = ring.querySelector('.progress-ring-circle');
            const radius = circle.r.baseVal.value;
            const circumference = radius * 2 * Math.PI;
            
            circle.style.setProperty('--circumference', circumference);
            this.updateProgressRing(container);
        });
    }

    updateProgressRing(container, targetPercent = null) {
        const circle = container.querySelector('.progress-ring-circle');
        const label = container.querySelector('.progress-ring-value');
        const circumference = parseFloat(circle.style.getPropertyValue('--circumference'));
        
        if (targetPercent === null) {
            targetPercent = parseFloat(container.dataset.percent) || 0;
        }
        
        const offset = circumference - (targetPercent / 100) * circumference;
        
        // Animate the progress
        this.animateValue(circle, 'stroke-dashoffset', 
            parseFloat(circle.style.strokeDashoffset) || circumference, 
            offset, 1000);
        
        // Animate the counter
        const currentValue = parseFloat(label.textContent) || 0;
        const targetValue = targetPercent;
        this.animateCounter(label, currentValue, targetValue, 1000, '%');
    }

    createProgressRing(containerId, title, percent, color = '#4CAF50') {
        const container = document.getElementById(containerId);
        if (!container) return;

        const html = `
            <div class="progress-ring-container" data-percent="${percent}">
                <svg class="progress-ring" width="120" height="120">
                    <circle class="progress-ring-bg" cx="60" cy="60" r="52"></circle>
                    <circle class="progress-ring-circle" cx="60" cy="60" r="52" 
                            style="--ring-color: ${color}"></circle>
                </svg>
                <div class="progress-ring-label">
                    <div class="progress-ring-value" style="color: ${color}">0%</div>
                    <div class="progress-ring-subtitle">${title}</div>
                </div>
            </div>
        `;
        
        container.innerHTML = html;
        this.setupProgressRings();
    }

    // === RISK GAUGE METERS ===
    setupRiskGauges() {
        const gauges = document.querySelectorAll('.risk-gauge');
        gauges.forEach(gauge => this.updateRiskGauge(gauge));
    }

    updateRiskGauge(gaugeElement, value = null, max = 100) {
        if (value === null) {
            value = parseFloat(gaugeElement.dataset.value) || 0;
        }
        
        const percentage = Math.min(value / max, 1);
        const angle = percentage * 180 - 90; // -90 to 90 degrees
        
        const needle = gaugeElement.querySelector('.gauge-needle');
        const fill = gaugeElement.querySelector('.gauge-fill');
        const valueLabel = gaugeElement.querySelector('.gauge-value');
        
        // Update needle position
        if (needle) {
            needle.style.transform = `rotate(${angle}deg)`;
        }
        
        // Update gauge fill
        if (fill) {
            const circumference = 314; // Approximate for semicircle
            const offset = circumference - (percentage * circumference);
            fill.style.strokeDashoffset = offset;
            
            // Color based on risk level
            if (percentage < 0.5) {
                fill.classList.add('gauge-safe');
                fill.classList.remove('gauge-warning', 'gauge-danger');
            } else if (percentage < 0.8) {
                fill.classList.add('gauge-warning');
                fill.classList.remove('gauge-safe', 'gauge-danger');
            } else {
                fill.classList.add('gauge-danger');
                fill.classList.remove('gauge-safe', 'gauge-warning');
            }
        }
        
        // Update value display
        if (valueLabel) {
            this.animateCounter(valueLabel, 
                parseFloat(valueLabel.textContent) || 0, 
                value, 800, '%');
        }
    }

    createRiskGauge(containerId, title, value, max = 100) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const html = `
            <div class="risk-gauge" data-value="${value}" data-max="${max}">
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
                    <div class="gauge-title">${title}</div>
                    <div class="gauge-value">0%</div>
                </div>
            </div>
        `;
        
        container.innerHTML = html;
        this.setupRiskGauges();
    }

    // === HEAT MAP CALENDAR ===
    setupHeatMap() {
        const heatmaps = document.querySelectorAll('.heatmap-calendar');
        heatmaps.forEach(heatmap => {
            // Clear any existing content and show placeholder
            heatmap.innerHTML = '';
            heatmap.innerHTML = '<div style="text-align: center; padding: 40px; color: #ccc; border: 2px dashed rgba(255,255,255,0.1); border-radius: 10px; background: rgba(0,0,0,0.1);">📊 No betting data available<br><small>Heat map will populate when you add bets</small></div>';
        });
    }

    generateHeatMap(container, days = 30) {
        // Disabled - no longer generate fake heat map data
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #ccc;">No betting data available. Heat map will populate when you add bets.</div>';
        return;
    }

    getBetDataForDate(date) {
        // Return empty data since we're not fetching real data yet
        // This should be connected to actual bet data from the backend
        return { profit: 0, count: 0 };
    }

    getHeatMapClass(profit) {
        if (profit > 500) return 'profit-high';
        if (profit > 100) return 'profit-medium';
        if (profit > 0) return 'profit-low';
        if (profit > -100) return 'loss-low';
        if (profit > -500) return 'loss-medium';
        if (profit < -500) return 'loss-high';
        return 'no-bets';
    }

    // === BALANCE GRAPH ===
    setupBalanceGraph() {
        const containers = document.querySelectorAll('.balance-graph-container');
        containers.forEach(container => {
            // Display message instead of creating fake charts
            const chartDiv = container.querySelector('#balance-chart-container');
            if (chartDiv) {
                chartDiv.innerHTML = '<div style="text-align: center; padding: 40px; color: #ccc; border: 2px dashed rgba(255,255,255,0.1); border-radius: 10px;">Balance chart will display once you have betting history</div>';
            }
        });
    }

    createBalanceChart(container) {
        // Check if chart already exists to prevent recreation
        if (container.querySelector('canvas')) {
            return;
        }
        
        const canvas = document.createElement('canvas');
        canvas.width = 800;
        canvas.height = 300;
        container.appendChild(canvas);

        const ctx = canvas.getContext('2d');
        
        // Sample data - in real implementation, this would come from actual bet history
        const data = this.generateSampleBalanceData();
        
        this.balanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Account Balance',
                    data: data.balance,
                    borderColor: '#FFD700',
                    backgroundColor: 'rgba(255, 215, 0, 0.1)',
                    tension: 0.3,
                    fill: true
                }, {
                    label: 'Drawdown Limit',
                    data: data.drawdownLimit,
                    borderColor: '#f44336',
                    borderDash: [5, 5],
                    fill: false
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: { color: 'white' }
                    }
                },
                scales: {
                    x: { 
                        ticks: { color: 'white' },
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    },
                    y: { 
                        ticks: { color: 'white' },
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    }
                }
            }
        });
    }

    generateSampleBalanceData() {
        // Return empty data until we implement real data fetching
        const labels = [];
        const balance = [];
        const drawdownLimit = [];
        
        // Show message that no data is available
        for (let i = 0; i < 7; i++) {
            const date = new Date();
            date.setDate(date.getDate() - (6 - i));
            labels.push(date.toLocaleDateString());
            balance.push(null); // No data points
            drawdownLimit.push(null);
        }
        
        return { labels, balance, drawdownLimit };
    }

    // === NOTIFICATION SYSTEM ===
    setupNotificationSystem() {
        const container = document.createElement('div');
        container.className = 'notification-container';
        document.body.appendChild(container);
        this.notificationContainer = container;
    }

    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="background: none; border: none; color: inherit; font-size: 18px; cursor: pointer;">×</button>
            </div>
        `;
        
        this.notificationContainer.appendChild(notification);
        
        // Auto-remove after duration
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, duration);
        
        return notification;
    }

    // === KEYBOARD SHORTCUTS ===
    setupKeyboardShortcuts() {
        const shortcuts = document.createElement('div');
        shortcuts.className = 'keyboard-shortcuts';
        shortcuts.innerHTML = `
            <div><span class="shortcut-key">Ctrl</span> + <span class="shortcut-key">B</span> Add Bet</div>
            <div><span class="shortcut-key">Ctrl</span> + <span class="shortcut-key">P</span> Toggle Parlay</div>
            <!-- Theme toggle removed - dark mode only -->
            <div><span class="shortcut-key">Escape</span> Close/Cancel</div>
        `;
        document.body.appendChild(shortcuts);

        // Show/hide shortcuts with Ctrl+?
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'b':
                        e.preventDefault();
                        this.focusAddBetForm();
                        break;
                    case 'p':
                        e.preventDefault();
                        this.toggleParlayMode();
                        break;
                    // Theme toggle removed - dark mode only
                    case '?':
                        e.preventDefault();
                        shortcuts.classList.toggle('visible');
                        break;
                }
            } else if (e.key === 'Escape') {
                shortcuts.classList.remove('visible');
                this.closeModals();
            }
        });
    }

    focusAddBetForm() {
        const sportSelect = document.querySelector('select[name="sport"]');
        if (sportSelect) {
            sportSelect.focus();
            this.showNotification('Quick add bet mode activated', 'info', 2000);
        }
    }

    toggleParlayMode() {
        const checkbox = document.getElementById('isParlay');
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            if (typeof toggleParlayMode === 'function') {
                toggleParlayMode();
            }
            this.showNotification(`Parlay mode ${checkbox.checked ? 'enabled' : 'disabled'}`, 'info', 2000);
        }
    }

    closeModals() {
        // Close any open modals or overlays
        const modals = document.querySelectorAll('.modal, .dropdown, .sidebar');
        modals.forEach(modal => modal.classList.remove('open', 'visible'));
    }

    // === THEME TOGGLE (DISABLED - DARK MODE ONLY) ===
    // Theme toggle functionality removed to prevent light mode issues

    // === REAL-TIME UPDATES ===
    setupRealTimeUpdates() {
        this.updateInterval = setInterval(() => {
            this.updateDashboardData();
        }, 60000); // Update every 60 seconds
    }

    updateDashboardData() {
        // Real-time updates disabled until connected to actual data
        // This was generating fake random data changes
        return;
    }

    calculateUpdatedPercent(current) {
        // This would calculate actual progress based on real data
        // For now, simulate small changes
        return Math.max(0, Math.min(100, current + (Math.random() - 0.5) * 2));
    }

    calculateUpdatedRisk(current) {
        // This would calculate actual risk based on real data
        // For now, simulate small changes
        return Math.max(0, Math.min(100, current + (Math.random() - 0.5) * 5));
    }

    // === ANIMATION UTILITIES ===
    animateValue(element, property, start, end, duration) {
        const startTime = performance.now();
        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function
            const easeOutCubic = 1 - Math.pow(1 - progress, 3);
            const current = start + (end - start) * easeOutCubic;
            
            element.style.setProperty(property, current);
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        requestAnimationFrame(animate);
    }

    animateCounter(element, start, end, duration, suffix = '') {
        const startTime = performance.now();
        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function
            const easeOutCubic = 1 - Math.pow(1 - progress, 3);
            const current = start + (end - start) * easeOutCubic;
            
            element.textContent = Math.round(current) + suffix;
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        requestAnimationFrame(animate);
    }

    // === CLEANUP ===
    destroy() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        this.animations.forEach(animation => {
            if (animation.cancel) animation.cancel();
        });
    }
}

// Initialize the enhanced dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.enhancedDashboard = new EnhancedDashboard();
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EnhancedDashboard;
}