import { useState, useEffect, useCallback } from 'react';
import CurrencyService from '../services/CurrencyService';

/**
 * Custom hook for managing current currency rates
 * @param {Object} options - Configuration options
 * @param {number} options.refreshInterval - Auto-refresh interval in milliseconds (default: 5 minutes)
 * @param {boolean} options.autoRefresh - Enable auto-refresh (default: true)
 * @return {Object} Hook state and methods
 */
export function useCurrencyRates(options = {}) {
    const {
        refreshInterval = 5 * 60 * 1000, // 5 minutes
        autoRefresh = true
    } = options;

    const [state, setState] = useState({
        data: null,
        loading: false,
        error: null,
        lastUpdated: null
    });

    /**
     * Fetch current rates from API
     */
    const fetchRates = useCallback(async () => {
        setState(prev => ({ ...prev, loading: true, error: null }));

        try {
            const response = await CurrencyService.getCurrentRates();
            
            setState({
                data: response,
                loading: false,
                error: null,
                lastUpdated: new Date()
            });

            // Cache successful result in localStorage
            if (response && response.success) {
                localStorage.setItem('currencyRates', JSON.stringify({
                    data: response,
                    timestamp: Date.now()
                }));
            }

        } catch (error) {
            console.error('Nie udało się pobrać kursów walut:', error);
            
            setState(prev => ({
                ...prev,
                loading: false,
                error: error.message || 'Nie udało się pobrać kursów walut'
            }));

            // Try to load from cache if available
            tryLoadFromCache();
        }
    }, []);

    /**
     * Try to load data from localStorage cache
     */
    const tryLoadFromCache = useCallback(() => {
        try {
            const cached = localStorage.getItem('currencyRates');
            if (cached) {
                const { data, timestamp } = JSON.parse(cached);
                const isStale = Date.now() - timestamp > refreshInterval;
                
                if (!isStale) {
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
            console.warn('Nie udało się załadować z pamięci podręcznej:', error);
        }
        return false;
    }, [refreshInterval]);

    /**
     * Refresh rates manually
     */
    const refresh = useCallback(() => {
        fetchRates();
    }, [fetchRates]);

    /**
     * Clear cache and reload
     */
    const clearCache = useCallback(() => {
        localStorage.removeItem('currencyRates');
        fetchRates();
    }, [fetchRates]);

    // Initial load
    useEffect(() => {
        // Try cache first, then fetch if not available
        if (!tryLoadFromCache()) {
            fetchRates();
        }
    }, [fetchRates, tryLoadFromCache]);

    // Auto-refresh setup
    useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(fetchRates, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchRates]);

    // Visibility change handler (refresh when tab becomes visible)
    useEffect(() => {
        if (!autoRefresh) return;

        const handleVisibilityChange = () => {
            if (!document.hidden && state.lastUpdated) {
                const timeSinceUpdate = Date.now() - state.lastUpdated.getTime();
                // Refresh if it's been more than half the refresh interval
                if (timeSinceUpdate > refreshInterval / 2) {
                    fetchRates();
                }
            }
        };

        document.addEventListener('visibilitychange', handleVisibilityChange);
        return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
    }, [autoRefresh, refreshInterval, fetchRates, state.lastUpdated]);

    return {
        ...state,
        refresh,
        clearCache,
        isStale: state.lastUpdated ? Date.now() - state.lastUpdated.getTime() > refreshInterval : false
    };
} 