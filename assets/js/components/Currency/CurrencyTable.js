import React from 'react';
import CurrencyRow from './CurrencyRow';
import { formatDate } from '../../utils/formatters';

/**
 * Currency rates table component
 * @param {Object} props - Component props
 * @param {Array} props.currencies - Array of currency objects
 * @param {boolean} props.loading - Loading state
 * @param {string} props.error - Error message
 * @param {Date} props.lastUpdated - Last update timestamp
 * @param {Function} props.onRefresh - Refresh callback
 */
function CurrencyTable({ 
    currencies = [], 
    loading = false, 
    error = null, 
    lastUpdated = null,
    onRefresh = null
}) {
    const renderTableContent = () => {
        if (error) {
            return (
                <tr>
                    <td colSpan="5" className="error-row">
                        <div className="error-content">
                            <span className="error-icon">⚠️</span>
                            <span className="error-message">{error}</span>
                            {onRefresh && (
                                <button 
                                    className="retry-button" 
                                    onClick={onRefresh}
                                    disabled={loading}
                                >
                                    Spróbuj ponownie
                                </button>
                            )}
                        </div>
                    </td>
                </tr>
            );
        }

        if (loading && currencies.length === 0) {
            // Show skeleton rows while loading
            return Array.from({ length: 5 }).map((_, index) => (
                <CurrencyRow key={`skeleton-${index}`} isLoading={true} />
            ));
        }

        if (currencies.length === 0) {
            return (
                <tr>
                    <td colSpan="5" className="empty-row">
                        <div className="empty-content">
                            <span className="empty-icon">📊</span>
                            <span className="empty-message">Brak danych o kursach walut</span>
                        </div>
                    </td>
                </tr>
            );
        }

        return currencies.map((currency) => (
            <CurrencyRow 
                key={currency.currency} 
                currency={currency}
                isLoading={loading}
            />
        ));
    };

    return (
        <div className="currency-table-container">
            {/* Table header with refresh info */}
            <div className="table-header">
                <div className="table-title">
                    <h2>Aktualne Kursy Walut</h2>
                    {lastUpdated && (
                        <span className="last-updated">
                            Ostatnia aktualizacja: {formatDate(lastUpdated)} {lastUpdated.toLocaleTimeString()}
                        </span>
                    )}
                </div>
                
                {onRefresh && (
                    <button 
                        className={`refresh-button ${loading ? 'loading' : ''}`}
                        onClick={onRefresh}
                        disabled={loading}
                        title="Odśwież kursy"
                    >
                        <span className="refresh-icon">🔄</span>
                        <span className="refresh-text">
                            {loading ? 'Odświeżanie...' : 'Odśwież'}
                        </span>
                    </button>
                )}
            </div>

            {/* Responsive table wrapper */}
            <div className="table-wrapper">
                <table className="currency-table">
                    <thead>
                        <tr>
                            <th className="currency-header">Waluta</th>
                            <th className="rate-header">Kurs NBP</th>
                            <th className="rate-header">Kupno</th>
                            <th className="rate-header">Sprzedaż</th>
                            <th className="rate-header">Spread</th>
                        </tr>
                    </thead>
                    <tbody>
                        {renderTableContent()}
                    </tbody>
                </table>
            </div>

            {/* Loading overlay */}
            {loading && currencies.length > 0 && (
                <div className="loading-overlay">
                    <div className="loading-spinner">
                        <div className="spinner"></div>
                        <span>Aktualizowanie kursów...</span>
                    </div>
                </div>
            )}

            {/* Table footer */}
            <div className="table-footer">
                <p className="disclaimer">
                    * Kursy są orientacyjne. Ostateczne kursy wymiany ustala kantor w dniu transakcji.
                </p>
                <p className="data-source">
                    Źródło: Narodowy Bank Polski (NBP)
                </p>
            </div>
        </div>
    );
}

export default CurrencyTable; 