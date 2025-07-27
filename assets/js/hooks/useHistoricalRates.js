import { useState, useEffect, useCallback } from 'react';
import CurrencyService from '../services/CurrencyService';

/**
 * Custom hook for managing historical currency rates
 * @param {string} currency - Currency code (e.g., 'EUR')
 * @param {string} date - Date in YYYY-MM-DD format (optional)
 * @param {number} days - Number of days to fetch (optional, default: 14)
 * @return {Object} Hook state and methods
 */
export function useHistoricalRates(currency, date = null, days = 14) {
    const [state, setState] = useState({
        data: null,
        loading: false,
        error: null,
        lastUpdated: null
    });

    /**
     * Create cache key for localStorage
     */
    const getCacheKey = useCallback(() => {
        return `historicalRates_${currency}_${date || 'today'}_${days}`;
    }, [currency, date, days]);

    /**
     * Fetch historical rates from API
     */
    const fetchHistoricalRates = useCallback(async () => {
        if (!currency) {
            setState({
                data: null,
                loading: false,
                error: 'Currency code is required',
                lastUpdated: null
            });
            return;
        }

        setState(prev => ({ ...prev, loading: true, error: null }));

        try {
            const response = await CurrencyService.getHistoricalRates(currency, date, days);
            
            setState({
                data: response,
                loading: false,
                error: null,
                lastUpdated: new Date()
            });

            // Cache successful result in localStorage
            if (response && response.success) {
                const cacheKey = getCacheKey();
                localStorage.setItem(cacheKey, JSON.stringify({
                    data: response,
                    timestamp: Date.now()
                }));
            }

        } catch (error) {
            console.error('Failed to fetch historical rates:', error);
            
            setState(prev => ({
                ...prev,
                loading: false,
                error: error.message || 'Failed to fetch historical rates'
            }));

            // Try to load from cache if available
            tryLoadFromCache();
        }
    }, [currency, date, days, getCacheKey]);

    /**
     * Try to load data from localStorage cache
     */
    const tryLoadFromCache = useCallback(() => {
        try {
            const cacheKey = getCacheKey();
            const cached = localStorage.getItem(cacheKey);
            
            if (cached) {
                const { data, timestamp } = JSON.parse(cached);
                const cacheAge = Date.now() - timestamp;
                const maxCacheAge = 60 * 60 * 1000; // 1 hour for historical data
                
                if (cacheAge < maxCacheAge) {
                    setState(prev => ({
                        ...prev,
                        data,
                        loading: false,
                        lastUpdated: new Date(timestamp)
                    }));
                    return true;
                }
            }
        } catch (error) {
            console.warn('Failed to load historical data from cache:', error);
        }
        return false;
    }, [getCacheKey]);

    /**
     * Refresh historical rates manually
     */
    const refresh = useCallback(() => {
        fetchHistoricalRates();
    }, [fetchHistoricalRates]);

    /**
     * Clear cache for this specific query
     */
    const clearCache = useCallback(() => {
        const cacheKey = getCacheKey();
        localStorage.removeItem(cacheKey);
        fetchHistoricalRates();
    }, [getCacheKey, fetchHistoricalRates]);

    // Effect to fetch data when dependencies change
    useEffect(() => {
        if (!currency) return;

        // Try cache first, then fetch if not available
        if (!tryLoadFromCache()) {
            fetchHistoricalRates();
        }
    }, [currency, date, days, fetchHistoricalRates, tryLoadFromCache]);

    return {
        ...state,
        refresh,
        clearCache,
        isStale: state.lastUpdated ? Date.now() - state.lastUpdated.getTime() > 60 * 60 * 1000 : false
    };
} 