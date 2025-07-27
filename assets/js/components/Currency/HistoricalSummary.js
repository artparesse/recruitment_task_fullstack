import React from 'react';
import { formatCurrency, formatChange, calculateHistoricalStats } from '../../utils/formatters';

/**
 * Historical summary component with key metrics
 * @param {Object} props - Component props
 * @param {Array} props.data - Historical rates data
 * @param {string} props.currency - Currency code
 */
function HistoricalSummary({ data = [], currency = '' }) {
    const stats = calculateHistoricalStats(data);
    const trendChange = formatChange(stats.trend);

    if (!data || data.length === 0) {
        return (
            <div className="historical-summary empty">
                <div className="summary-content">
                    <span className="summary-icon">📊</span>
                    <p className="summary-message">Brak danych do wyświetlenia</p>
                </div>
            </div>
        );
    }

    return (
        <div className="historical-summary">
            <div className="summary-header">
                <h3 className="summary-title">
                    Podsumowanie dla {currency}
                </h3>
                <span className="summary-period">
                    Okres: {data.length} dni roboczych
                </span>
            </div>

            <div className="summary-grid">
                {/* Trend */}
                <div className="summary-card trend">
                    <div className="card-header">
                        <span className="card-icon">📈</span>
                        <span className="card-title">Trend</span>
                    </div>
                    <div className="card-value">
                        <span className={`trend-value ${trendChange.color}`}>
                            <span className="trend-sign">{trendChange.sign}</span>
                            {trendChange.text}
                        </span>
                    </div>
                    <div className="card-description">
                        {stats.trend > 0 ? 'Wzrost' : stats.trend < 0 ? 'Spadek' : 'Stabilny'}
                    </div>
                </div>

                {/* Highest Rate */}
                <div className="summary-card highest">
                    <div className="card-header">
                        <span className="card-icon">⬆️</span>
                        <span className="card-title">Najwyższy kurs</span>
                    </div>
                    <div className="card-value">
                        {stats.max ? formatCurrency(stats.max) : 'N/A'}
                    </div>
                    <div className="card-description">PLN</div>
                </div>

                {/* Lowest Rate */}
                <div className="summary-card lowest">
                    <div className="card-header">
                        <span className="card-icon">⬇️</span>
                        <span className="card-title">Najniższy kurs</span>
                    </div>
                    <div className="card-value">
                        {stats.min ? formatCurrency(stats.min) : 'N/A'}
                    </div>
                    <div className="card-description">PLN</div>
                </div>

                {/* Average Rate */}
                <div className="summary-card average">
                    <div className="card-header">
                        <span className="card-icon">📊</span>
                        <span className="card-title">Średni kurs</span>
                    </div>
                    <div className="card-value">
                        {stats.avg ? formatCurrency(stats.avg) : 'N/A'}
                    </div>
                    <div className="card-description">PLN</div>
                </div>

                {/* Volatility */}
                <div className="summary-card volatility">
                    <div className="card-header">
                        <span className="card-icon">📉</span>
                        <span className="card-title">Zmienność</span>
                    </div>
                    <div className="card-value">
                        {stats.max && stats.min 
                            ? formatCurrency(stats.max - stats.min) 
                            : 'N/A'}
                    </div>
                    <div className="card-description">
                        Różnica max-min
                    </div>
                </div>

                {/* Data Points */}
                <div className="summary-card datapoints">
                    <div className="card-header">
                        <span className="card-icon">📅</span>
                        <span className="card-title">Liczba dni</span>
                    </div>
                    <div className="card-value">
                        {stats.count}
                    </div>
                    <div className="card-description">
                        Dni robocze
                    </div>
                </div>
            </div>

            {/* Additional Info */}
            <div className="summary-footer">
                <p className="summary-note">
                    * Dane pochodzą z Narodowego Banku Polskiego
                </p>
                <p className="summary-note">
                    * Kursy zawierają marże kantorowe
                </p>
            </div>
        </div>
    );
}

export default HistoricalSummary; 