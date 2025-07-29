import React from 'react';
import { formatCurrency, formatDate, calculateChange, formatChange } from '../../utils/formatters';

/**
 * Historical rates table component
 * @param {Object} props - Component props
 * @param {Array} props.data - Historical rates data
 * @param {boolean} props.loading - Loading state
 * @param {string} props.error - Error message
 */
function HistoricalTable({ data = [], loading = false, error = null }) {
    const renderTableContent = () => {
        if (error) {
            return (
                <tr>
                    <td colSpan="6" className="error-row">
                        <div className="error-content">
                            <span className="error-icon">⚠️</span>
                            <span className="error-message">{error}</span>
                        </div>
                    </td>
                </tr>
            );
        }

        if (loading && data.length === 0) {
            return Array.from({ length: 5 }).map((_, index) => (
                <tr key={`skeleton-${index}`} className="historical-row loading">
                    <td><div className="skeleton skeleton-text"></div></td>
                    <td><div className="skeleton skeleton-text"></div></td>
                    <td><div className="skeleton skeleton-text"></div></td>
                    <td><div className="skeleton skeleton-text"></div></td>
                    <td><div className="skeleton skeleton-text"></div></td>
                    <td><div className="skeleton skeleton-text"></div></td>
                </tr>
            ));
        }

        if (data.length === 0) {
            return (
                <tr>
                    <td colSpan="6" className="empty-row">
                        <div className="empty-content">
                            <span className="empty-icon">📊</span>
                            <span className="empty-message">Brak danych historycznych</span>
                        </div>
                    </td>
                </tr>
            );
        }

        // Sort by date (newest first)
        const sortedData = [...data].sort((a, b) => new Date(b.date) - new Date(a.date));

        return sortedData.map((rate, index) => {
            const previousRate = index < sortedData.length - 1 ? sortedData[index + 1].baseRate : null;
            const change = previousRate ? calculateChange(rate.baseRate, previousRate) : 0;
            const formattedChange = formatChange(change);

            return (
                <tr key={rate.date} className="historical-row">
                    <td className="date-cell">
                        <span className="date-value">{formatDate(rate.date)}</span>
                    </td>
                    
                    <td className="base-rate">
                        <span className="rate-value">
                            {formatCurrency(rate.baseRate)} PLN
                        </span>
                    </td>
                    
                    <td className="buy-rate">
                        {rate.buyRate !== null ? (
                            <span className="rate-value buy">
                                {formatCurrency(rate.buyRate)} PLN
                            </span>
                        ) : (
                            <span className="rate-unavailable">Nie skupujemy</span>
                        )}
                    </td>
                    
                    <td className="sell-rate">
                        <span className="rate-value sell">
                            {formatCurrency(rate.sellRate)} PLN
                        </span>
                    </td>
                    
                    <td className="change-cell">
                        <span className={`change-value ${formattedChange.color}`}>
                            <span className="change-sign">{formattedChange.sign}</span>
                            {formattedChange.text}
                        </span>
                    </td>
                    
                    <td className="spread-cell">
                        {rate.buyRate !== null ? (
                            <span className="spread-value">
                                {formatCurrency(rate.sellRate - rate.buyRate)} PLN
                            </span>
                        ) : (
                            <span className="spread-unavailable">-</span>
                        )}
                    </td>
                </tr>
            );
        });
    };

    return (
        <div className="historical-table-container">
            <div className="table-header">
                <h3 className="table-title">Historia kursów</h3>
                <span className="table-subtitle">
                    Dane z ostatnich 14 dni roboczych
                </span>
            </div>

            <div className="table-wrapper">
                <table className="historical-table">
                    <thead>
                        <tr>
                            <th className="date-header">Data</th>
                            <th className="rate-header">Kurs NBP</th>
                            <th className="rate-header">Kupno</th>
                            <th className="rate-header">Sprzedaż</th>
                            <th className="change-header">Zmiana</th>
                            <th className="spread-header">Spread</th>
                        </tr>
                    </thead>
                    <tbody>
                        {renderTableContent()}
                    </tbody>
                </table>
            </div>

            {loading && data.length > 0 && (
                <div className="loading-overlay">
                    <div className="loading-spinner">
                        <div className="spinner"></div>
                        <span>Aktualizowanie danych...</span>
                    </div>
                </div>
            )}
        </div>
    );
}

export default HistoricalTable; 