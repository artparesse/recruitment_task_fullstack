import React from 'react';

/**
 * Date picker component with validation
 * @param {Object} props - Component props
 * @param {string} props.value - Current date value (YYYY-MM-DD)
 * @param {Function} props.onChange - Change handler
 * @param {string} props.max - Maximum allowed date
 * @param {boolean} props.disabled - Disabled state
 */
function DatePicker({ 
    value = '', 
    onChange = () => {}, 
    max = null, 
    disabled = false 
}) {
    // Set max date to today if not provided
    const maxDate = max || new Date().toISOString().split('T')[0];
    
    // Set min date to 1 year back
    const minDate = new Date();
    minDate.setFullYear(minDate.getFullYear() - 1);
    const minDateString = minDate.toISOString().split('T')[0];

    const handleChange = (event) => {
        const newDate = event.target.value;
        onChange(newDate);
    };

    return (
        <div className="date-picker">
            <label htmlFor="date-input" className="picker-label">
                Data referencyjna:
            </label>
            
            <div className="date-input-wrapper">
                <input
                    type="date"
                    id="date-input"
                    value={value}
                    onChange={handleChange}
                    min={minDateString}
                    max={maxDate}
                    disabled={disabled}
                    className="date-input"
                    aria-label="Wybierz datę"
                />
            </div>
            
            <div className="date-help">
                <small>
                    Wybierz datę z ostatniego roku (maksymalnie dzisiaj)
                </small>
            </div>
        </div>
    );
}

export default DatePicker; 