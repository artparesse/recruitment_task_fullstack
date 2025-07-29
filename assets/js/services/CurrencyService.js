import axios from 'axios';

/**
 * Service class for currency exchange API communication
 */
class CurrencyService {
    constructor() {
        // Base URL for API - adjust if needed
        this.baseURL = window.location.origin;
        
        // Configure axios instance
        this.api = axios.create({
            baseURL: this.baseURL,
            timeout: 10000,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        // Add response interceptor for error handling
        this.api.interceptors.response.use(
            response => response,
            error => {
                console.error('API Error:', error);
                return Promise.reject(this.handleError(error));
            }
        );
    }

    /**
     * Get current exchange rates from backend
     * @return {Promise<Object>} Current rates data
     */
    async getCurrentRates() {
        try {
            const response = await this.api.get('/api/currencies/current');
            return response.data;
        } catch (error) {
            throw new Error(`Nie udało się pobrać aktualnych kursów: ${error.message}`);
        }
    }

    /**
     * Get historical exchange rates for a currency
     * @param {string} currency - Currency code (e.g., 'EUR')
     * @param {string} date - Date in YYYY-MM-DD format (optional)
     * @param {number} days - Number of days to fetch (optional, default: 14)
     * @return {Promise<Object>} Historical rates data
     */
    async getHistoricalRates(currency, date = null, days = 14) {
        if (!currency) {
            throw new Error('Kod waluty jest wymagany');
        }

        try {
            let url = `/api/currencies/${currency.toUpperCase()}/history`;
            const params = new URLSearchParams();

            if (date) {
                url += `/${date}`;
            }

            if (days !== 14) {
                params.append('days', days.toString());
            }

            if (params.toString()) {
                url += `?${params.toString()}`;
            }

            const response = await this.api.get(url);
            return response.data;
        } catch (error) {
            throw new Error(`Nie udało się pobrać historycznych kursów dla ${currency}: ${error.message}`);
        }
    }

    /**
     * Get API health status
     * @return {Promise<Object>} Health status data
     */
    async getHealthStatus() {
        try {
            const response = await this.api.get('/api/currencies/health');
            return response.data;
        } catch (error) {
            throw new Error(`Nie udało się sprawdzić statusu serwisu: ${error.message}`);
        }
    }

    /**
     * Handle API errors
     * @param {Object} error - Axios error object
     * @return {Object} Formatted error object
     */
    handleError(error) {
        if (error.response) {
            // Server responded with error status
            const { status, data } = error.response;
            return {
                message: data.message || data.error || 'Błąd serwera',
                status: status,
                data: data
            };
        } else if (error.request) {
            // Request was made but no response received
            return {
                message: 'Błąd sieci - sprawdź połączenie internetowe',
                status: 0,
                data: null
            };
        } else {
            // Something else happened
            return {
                message: error.message || 'Wystąpił nieoczekiwany błąd',
                status: 0,
                data: null
            };
        }
    }

    /**
     * Retry mechanism for failed requests
     * @param {Function} fn - Function to retry
     * @param {number} retries - Number of retries
     * @param {number} delay - Delay between retries in ms
     * @return {Promise<any>} Result of function call
     */
    async retry(fn, retries = 3, delay = 1000) {
        try {
            return await fn();
        } catch (error) {
            if (retries > 0) {
                console.warn(`Żądanie nie powiodło się, ponowna próba za ${delay}ms. Pozostałe próby: ${retries}`);
                await new Promise(resolve => setTimeout(resolve, delay));
                return this.retry(fn, retries - 1, delay * 2);
            }
            throw error;
        }
    }
}

// Export singleton instance
export default new CurrencyService(); 