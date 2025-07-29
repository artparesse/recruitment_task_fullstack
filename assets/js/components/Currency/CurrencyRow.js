import React from 'react';
import { formatCurrency, getCurrencyName, getCurrencyFlag } from '../../utils/formatters';

/**
 * Single currency row component
 * @param {Object} props - Component props
 * @param {Object} props.currency - Currency data object
 * @param {boolean} props.isLoading - Loading state
 */
function CurrencyRow({ currency, isLoading = false }) {
    if (isLoading) {
        return (
            <tr className="currency-row loading">
                <td><div className="skeleton skeleton-text"></div></td>
                <td><div className="skeleton skeleton-text"></div></td>
                <td><div className="skeleton skeleton-text"></div></td>
                <td><div className="skeleton skeleton-text"></div></td>
                <td><div className="skeleton skeleton-text"></div></td>
            </tr>
        );
    }

    if (!currency) {
        return null;
    }

    const {
        currency: currencyCode,
        name,
        baseRate,
        buyRate,
        sellRate,
        supportsBuying
    } = currency;

    return (
        <tr className="currency-row">
            {/* Currency info */}
            <td className="currency-info">
                <div className="currency-cell">
                    <span className="currency-flag">
                        {getCurrencyFlag(currencyCode)}
                    </span>
                    <div className="currency-details">
                        <span className="currency-code">{currencyCode}</span>
                        <span className="currency-name">{name || getCurrencyName(currencyCode)}</span>
                    </div>
                </div>
            </td>

            {/* NBP Rate */}
            <td className="nbp-rate">
                <span className="rate-value">
                    {formatCurrency(baseRate)} PLN
                </span>
            </td>

            {/* Buy Rate */}
            <td className="buy-rate">
                {supportsBuying && buyRate !== null ? (
                    <span className="rate-value buy">
                        {formatCurrency(buyRate)} PLN
                    </span>
                ) : (
                    <span className="rate-unavailable">
                        Nie skupujemy
                    </span>
                )}
            </td>

            {/* Sell Rate */}
            <td className="sell-rate">
                <span className="rate-value sell">
                    {formatCurrency(sellRate)} PLN
                </span>
            </td>

            {/* Spread */}
            <td className="spread">
                {supportsBuying && buyRate !== null ? (
                    <span className="spread-value">
                        {formatCurrency(sellRate - buyRate)} PLN
                    </span>
                ) : (
                    <span className="spread-unavailable">-</span>
                )}
            </td>
        </tr>
    );
}

export default CurrencyRow; 