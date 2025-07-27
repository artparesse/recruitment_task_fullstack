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