/**
 * Utility functions for formatting data in the currency exchange app
 */

/**
 * Format currency value to 2 decimal places
 * @param {number} value - Currency value to format
 * @return {string} Formatted currency value
 */
export function formatCurrency(value) {
    if (value === null || value === undefined) {
        return 'N/A';
    }
    
    return Number(value).toFixed(4);
}

/**
 * Format date to DD.MM.YYYY format
 * @param {string|Date} date - Date to format
 * @return {string} Formatted date
 */
export function formatDate(date) {
    if (!date) return '';
    
    const dateObj = date instanceof Date ? date : new Date(date);
    
    if (isNaN(dateObj.getTime())) {
        return '';
    }
    
    const day = dateObj.getDate().toString().padStart(2, '0');
    const month = (dateObj.getMonth() + 1).toString().padStart(2, '0');
    const year = dateObj.getFullYear();
    
    return `${day}.${month}.${year}`;
}

/**
 * Get full currency name from currency code
 * @param {string} code - Currency code (e.g., 'EUR', 'USD')
 * @return {string} Full currency name
 */
export function getCurrencyName(code) {
    const currencyNames = {
        'EUR': 'Euro',
        'USD': 'US Dollar',
        'CZK': 'Czech Koruna',
        'IDR': 'Indonesian Rupiah',
        'BRL': 'Brazilian Real'
    };
    
    return currencyNames[code] || code;
}

/**
 * Format percentage value
 * @param {number} value - Percentage value
 * @return {string} Formatted percentage
 */
export function formatPercentage(value) {
    if (value === null || value === undefined) {
        return 'N/A';
    }
    
    return `${Number(value).toFixed(2)}%`;
}

/**
 * Get currency flag emoji (basic implementation)
 * @param {string} code - Currency code
 * @return {string} Flag emoji
 */
export function getCurrencyFlag(code) {
    const flags = {
        'EUR': '🇪🇺',
        'USD': '🇺🇸',
        'CZK': '🇨🇿',
        'IDR': '🇮🇩',
        'BRL': '🇧🇷'
    };
    
    return flags[code] || '💱';
}

/**
 * Calculate percentage change between two values
 * @param {number} current - Current value
 * @param {number} previous - Previous value
 * @return {number} Percentage change
 */
export function calculateChange(current, previous) {
    if (!current || !previous || previous === 0) {
        return 0;
    }
    
    return ((current - previous) / previous) * 100;
}

/**
 * Format change value with color coding
 * @param {number} value - Change value
 * @return {Object} Formatted change with color info
 */
export function formatChange(value) {
    if (value === null || value === undefined) {
        return {
            text: 'N/A',
            color: 'neutral',
            sign: ''
        };
    }
    
    const formattedValue = Math.abs(value).toFixed(2);
    const sign = value > 0 ? '+' : value < 0 ? '-' : '';
    const color = value > 0 ? 'positive' : value < 0 ? 'negative' : 'neutral';
    
    return {
        text: `${sign}${formattedValue}%`,
        color,
        sign: value > 0 ? '↗' : value < 0 ? '↘' : '→'
    };
}

/**
 * Format date range
 * @param {string|Date} fromDate - Start date
 * @param {string|Date} toDate - End date
 * @return {string} Formatted date range
 */
export function formatDateRange(fromDate, toDate) {
    if (!fromDate || !toDate) return '';
    
    const from = formatDate(fromDate);
    const to = formatDate(toDate);
    
    if (from === to) {
        return from;
    }
    
    return `${from} - ${to}`;
}

/**
 * Calculate statistics from historical data
 * @param {Array} rates - Array of historical rate objects
 * @return {Object} Statistics object
 */
export function calculateHistoricalStats(rates) {
    if (!rates || rates.length === 0) {
        return {
            min: null,
            max: null,
            avg: null,
            trend: null,
            count: 0
        };
    }
    
    const baseRates = rates.map(rate => rate.baseRate).filter(rate => rate !== null);
    
    if (baseRates.length === 0) {
        return {
            min: null,
            max: null,
            avg: null,
            trend: null,
            count: 0
        };
    }
    
    const min = Math.min(...baseRates);
    const max = Math.max(...baseRates);
    const avg = baseRates.reduce((sum, rate) => sum + rate, 0) / baseRates.length;
    
    // Calculate trend (first vs last rate)
    const firstRate = baseRates[0];
    const lastRate = baseRates[baseRates.length - 1];
    const trend = calculateChange(lastRate, firstRate);
    
    return {
        min,
        max,
        avg,
        trend,
        count: baseRates.length
    };
} 