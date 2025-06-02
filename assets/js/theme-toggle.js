// Theme Toggle JavaScript functionality
class ThemeToggle {
    constructor() {
        this.currentTheme = 'light';
        this.init();
    }

    init() {
        this.loadSavedTheme();
        this.bindEvents();
        this.setupThemeToggleButton();
        this.applySystemTheme();
    }

    bindEvents() {
        // Theme toggle button
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('theme-toggle-btn') || 
                e.target.closest('.theme-toggle-btn')) {
                this.toggleTheme();
            }
        });

        // Listen for system theme changes
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            mediaQuery.addEventListener('change', () => {
                if (this.currentTheme === 'auto') {
                    this.applySystemTheme();
                }
            });
        }

        // Keyboard shortcut (Ctrl+Shift+T)
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                this.toggleTheme();
            }
        });
    }

    setupThemeToggleButton() {
        const themeButton = document.querySelector('.theme-toggle-btn');
        if (themeButton) {
            this.updateThemeButton(themeButton);
        }
    }

    loadSavedTheme() {
        const savedTheme = localStorage.getItem('fileserver-theme');
        if (savedTheme && ['light', 'dark', 'auto'].includes(savedTheme)) {
            this.currentTheme = savedTheme;
        } else {
            this.currentTheme = 'auto'; // Default to auto
        }
        this.applyTheme();
    }

    toggleTheme() {
        const themes = ['light', 'dark', 'auto'];
        const currentIndex = themes.indexOf(this.currentTheme);
        const nextIndex = (currentIndex + 1) % themes.length;
        
        this.currentTheme = themes[nextIndex];
        this.saveTheme();
        this.applyTheme();
        this.showThemeNotification();
    }

    applyTheme() {
        const html = document.documentElement;
        
        // Remove existing theme classes
        html.classList.remove('theme-light', 'theme-dark', 'theme-auto');
        
        // Apply current theme
        html.classList.add(`theme-${this.currentTheme}`);
        
        if (this.currentTheme === 'auto') {
            this.applySystemTheme();
        } else {
            this.setThemeAttributes(this.currentTheme);
        }

        // Update theme toggle button
        const themeButton = document.querySelector('.theme-toggle-btn');
        if (themeButton) {
            this.updateThemeButton(themeButton);
        }

        // Trigger theme change event
        this.dispatchThemeChangeEvent();
    }

    applySystemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            this.setThemeAttributes('dark');
        } else {
            this.setThemeAttributes('light');
        }
    }

    setThemeAttributes(theme) {
        const html = document.documentElement;
        html.setAttribute('data-theme', theme);
        
        // Update meta theme-color for mobile browsers
        const themeColor = theme === 'dark' ? '#1a1a1a' : '#ffffff';
        this.updateMetaThemeColor(themeColor);
    }

    updateMetaThemeColor(color) {
        let metaTheme = document.querySelector('meta[name="theme-color"]');
        if (!metaTheme) {
            metaTheme = document.createElement('meta');
            metaTheme.name = 'theme-color';
            document.head.appendChild(metaTheme);
        }
        metaTheme.content = color;
    }

    updateThemeButton(button) {
        const icons = {
            light: '☀️',
            dark: '🌙',
            auto: '🌓'
        };

        const labels = {
            light: 'Light Theme',
            dark: 'Dark Theme',
            auto: 'Auto Theme'
        };

        const icon = button.querySelector('.theme-icon');
        const label = button.querySelector('.theme-label');

        if (icon) {
            icon.textContent = icons[this.currentTheme];
        } else {
            // If no icon element, update button text
            const currentIcon = icons[this.currentTheme];
            if (button.innerHTML.includes('🌙') || button.innerHTML.includes('☀️') || button.innerHTML.includes('🌓')) {
                button.innerHTML = button.innerHTML.replace(/[🌙☀️🌓]/, currentIcon);
            } else {
                button.innerHTML = currentIcon + ' ' + button.innerHTML;
            }
        }

        if (label) {
            label.textContent = labels[this.currentTheme];
        }

        // Update tooltip
        button.setAttribute('title', `Current theme: ${labels[this.currentTheme]}`);
        button.setAttribute('data-tooltip', `Switch theme (${labels[this.currentTheme]})`);
    }

    saveTheme() {
        localStorage.setItem('fileserver-theme', this.currentTheme);
    }

    showThemeNotification() {
        const themeLabels = {
            light: 'Light',
            dark: 'Dark',
            auto: 'Auto (System)'
        };

        if (typeof showNotification === 'function') {
            showNotification(`Theme changed to ${themeLabels[this.currentTheme]}`, 'info', 2000);
        }
    }

    dispatchThemeChangeEvent() {
        const event = new CustomEvent('themechange', {
            detail: {
                theme: this.currentTheme,
                effectiveTheme: this.getEffectiveTheme()
            }
        });
        document.dispatchEvent(event);
    }

    getEffectiveTheme() {
        if (this.currentTheme === 'auto') {
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return this.currentTheme;
    }

    // Public methods for external use
    setTheme(theme) {
        if (['light', 'dark', 'auto'].includes(theme)) {
            this.currentTheme = theme;
            this.saveTheme();
            this.applyTheme();
        }
    }

    getCurrentTheme() {
        return this.currentTheme;
    }

    getEffectiveTheme() {
        return this.getEffectiveTheme();
    }

    // Utility method to check if dark theme is active
    isDarkTheme() {
        return this.getEffectiveTheme() === 'dark';
    }

    // Method to create theme selector dropdown
    createThemeSelector(container) {
        if (!container) return;

        const selector = document.createElement('select');
        selector.className = 'theme-selector';
        selector.innerHTML = `
            <option value="light">☀️ Light</option>
            <option value="dark">🌙 Dark</option>
            <option value="auto">🌓 Auto</option>
        `;
        
        selector.value = this.currentTheme;
        
        selector.addEventListener('change', (e) => {
            this.setTheme(e.target.value);
        });

        container.appendChild(selector);
        return selector;
    }

    // Animation support for theme transitions
    enableThemeTransitions() {
        const style = document.createElement('style');
        style.textContent = `
            * {
                transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
            }
        `;
        document.head.appendChild(style);

        // Remove transitions after initial load to prevent flashing
        setTimeout(() => {
            style.remove();
        }, 500);
    }
}

// Initialize theme toggle
document.addEventListener('DOMContentLoaded', () => {
    window.themeToggle = new ThemeToggle();
});

// Global functions for template access
window.toggleTheme = () => {
    if (window.themeToggle) {
        window.themeToggle.toggleTheme();
    }
};

window.setTheme = (theme) => {
    if (window.themeToggle) {
        window.themeToggle.setTheme(theme);
    }
};

window.getCurrentTheme = () => {
    return window.themeToggle ? window.themeToggle.getCurrentTheme() : 'light';
};

window.isDarkTheme = () => {
    return window.themeToggle ? window.themeToggle.isDarkTheme() : false;
};
