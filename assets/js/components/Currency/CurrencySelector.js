import React from 'react';
import { getCurrencyName, getCurrencyFlag } from '../../utils/formatters';

/**
 * Currency selector component
 * @param {Object} props - Component props
 * @param {Array} props.currencies - Available currencies array
 * @param {string} props.selected - Currently selected currency
 * @param {Function} props.onChange - Change handler
 * @param {boolean} props.disabled - Disabled state
 */
function CurrencySelector({ 
    currencies = [], 
    selected = 'EUR', 
    onChange = () => {}, 
    disabled = false 
}) {
    const handleChange = (event) => {
        const newCurrency = event.target.value;
        onChange(newCurrency);
    };

    return (
        <div className="currency-selector">
            <label htmlFor="currency-select" className="selector-label">
                Wybierz walutę:
            </label>
            
            <div className="select-wrapper">
                <select
                    id="currency-select"
                    value={selected}
                    onChange={handleChange}
                    disabled={disabled}
                    className="currency-select"
                    aria-label="Wybierz walutę"
                >
                    {currencies.map(currency => (
                        <option key={currency} value={currency}>
                            {getCurrencyFlag(currency)} {currency} - {getCurrencyName(currency)}
                        </option>
                    ))}
                </select>
                
                <div className="select-arrow">
                    <span>▼</span>
                </div>
            </div>
        </div>
    );
}

export default CurrencySelector; 