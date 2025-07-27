import React, { useState, useEffect } from 'react';
import { useParams, useHistory, useLocation } from 'react-router-dom';
import CurrencySelector from './Currency/CurrencySelector';
import DatePicker from './Common/DatePicker';
import HistoricalTable from './Currency/HistoricalTable';
import HistoricalSummary from './Currency/HistoricalSummary';
import LoadingSpinner from './Common/LoadingSpinner';
import ErrorMessage from './Common/ErrorMessage';
import { useHistoricalRates } from '../hooks/useHistoricalRates';
import { formatDate } from '../utils/formatters';

/**
 * History page component with URL state management
 */
function HistoryPage() {
    const { currency: urlCurrency, date: urlDate } = useParams();
    const history = useHistory();
    const location = useLocation();
    
    // State management
    const [selectedCurrency, setSelectedCurrency] = useState(urlCurrency || 'EUR');
    const [selectedDate, setSelectedDate] = useState(urlDate || '');
    const [availableCurrencies] = useState(['EUR', 'USD', 'CZK', 'IDR', 'BRL']);

    // Historical data hook
    const { 
        data: historicalData, 
        loading, 
        error, 
        refresh 
    } = useHistoricalRates(selectedCurrency, selectedDate || null, 14);

    // Extract rates from API response
    const rates = historicalData?.success && historicalData?.data?.rates 
        ? historicalData.data.rates 
        : [];

    // Update URL when state changes
    useEffect(() => {
        const newPath = selectedDate 
            ? `/history/${selectedCurrency}/${selectedDate}`
            : `/history/${selectedCurrency}`;
            
        if (location.pathname !== newPath) {
            history.replace(newPath);
        }
    }, [selectedCurrency, selectedDate, history, location.pathname]);

    // Handle currency change
    const handleCurrencyChange = (newCurrency) => {
        setSelectedCurrency(newCurrency);
    };

    // Handle date change
    const handleDateChange = (newDate) => {
        setSelectedDate(newDate);
    };

    // Handle search button click
    const handleSearch = () => {
        refresh();
    };

    // Handle back to current rates
    const handleBackToCurrent = () => {
        history.push('/');
    };

    // Get today's date for date picker max
    const today = new Date().toISOString().split('T')[0];

    return (
        <div className="history-page">
            <div className="container">
                {/* Page header */}
                <div className="page-header">
                    <h1 className="page-title">Historia Kursów Walut</h1>
                    <p className="page-subtitle">
                        Analiza historycznych kursów wymiany z ostatnich 14 dni roboczych
                    </p>
                </div>

                {/* Controls section */}
                <div className="controls-section">
                    <div className="controls-grid">
                        <CurrencySelector
                            currencies={availableCurrencies}
                            selected={selectedCurrency}
                            onChange={handleCurrencyChange}
                            disabled={loading}
                        />
                        
                        <DatePicker
                            value={selectedDate}
                            onChange={handleDateChange}
                            max={today}
                            disabled={loading}
                        />
                        
                        <button 
                            className="search-button"
                            onClick={handleSearch}
                            disabled={loading}
                        >
                            <span className="search-icon">🔍</span>
                            <span className="search-text">
                                {loading ? 'Ładowanie...' : 'Szukaj'}
                            </span>
                        </button>
                    </div>
                </div>

                {/* Loading state */}
                {loading && (
                    <div className="loading-section">
                        <LoadingSpinner 
                            message="Pobieranie danych historycznych..." 
                            size="large"
                        />
                    </div>
                )}

                {/* Error state */}
                {error && !loading && (
                    <div className="error-section">
                        <ErrorMessage
                            message={error}
                            type="error"
                            onRetry={refresh}
                            retryText="Spróbuj ponownie"
                        />
                    </div>
                )}

                {/* Results section */}
                {!loading && !error && (
                    <div className="results-section">
                        {/* Summary */}
                        {rates.length > 0 && (
                            <div className="summary-section">
                                <HistoricalSummary 
                                    data={rates} 
                                    currency={selectedCurrency}
                                />
                            </div>
                        )}

                        {/* Table */}
                        <div className="table-section">
                            <HistoricalTable
                                data={rates}
                                loading={loading}
                                error={error}
                            />
                        </div>
                    </div>
                )}

                {/* Navigation */}
                <div className="navigation-section">
                    <button 
                        className="back-button"
                        onClick={handleBackToCurrent}
                    >
                        <span className="back-icon">←</span>
                        <span className="back-text">Powrót do aktualnych kursów</span>
                    </button>
                </div>

                {/* Additional info */}
                <div className="info-section">
                    <div className="info-card">
                        <h3 className="info-title">Informacje o danych</h3>
                        <ul className="info-list">
                            <li>Dane pochodzą z Narodowego Banku Polskiego</li>
                            <li>Kursy zawierają marże kantorowe</li>
                            <li>Pokazywane są tylko dni robocze</li>
                            <li>Zmiana obliczana jest względem poprzedniego dnia</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default HistoryPage; 