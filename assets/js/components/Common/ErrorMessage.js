import React from 'react';

/**
 * Generic error message component
 * @param {Object} props - Component props
 * @param {string} props.message - Error message
 * @param {string} props.type - Error type (error, warning, info)
 * @param {Function} props.onRetry - Retry function
 * @param {string} props.retryText - Retry button text
 */
function ErrorMessage({ 
    message = 'Wystąpił błąd', 
    type = 'error',
    onRetry = null,
    retryText = 'Spróbuj ponownie'
}) {
    const typeClasses = {
        error: 'error-message error',
        warning: 'error-message warning',
        info: 'error-message info'
    };

    const icons = {
        error: '⚠️',
        warning: '⚠️',
        info: 'ℹ️'
    };

    return (
        <div className={typeClasses[type] || typeClasses.error}>
            <div className="error-content">
                <span className="error-icon">
                    {icons[type] || icons.error}
                </span>
                
                <div className="error-text">
                    <h3 className="error-title">
                        {type === 'error' ? 'Błąd' : 
                         type === 'warning' ? 'Ostrzeżenie' : 'Informacja'}
                    </h3>
                    <p className="error-description">{message}</p>
                </div>
                
                {onRetry && (
                    <button 
                        className="error-retry-button"
                        onClick={onRetry}
                    >
                        {retryText}
                    </button>
                )}
            </div>
        </div>
    );
}

export default ErrorMessage; 