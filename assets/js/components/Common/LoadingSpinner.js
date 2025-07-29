import React from 'react';

/**
 * Loading spinner component
 * @param {Object} props - Component props
 * @param {string} props.message - Loading message
 * @param {string} props.size - Spinner size (small, medium, large)
 * @param {boolean} props.overlay - Whether to show as overlay
 */
function LoadingSpinner({ 
    message = 'Ładowanie...', 
    size = 'medium',
    overlay = false 
}) {
    const sizeClasses = {
        small: 'spinner-small',
        medium: 'spinner-medium',
        large: 'spinner-large'
    };

    const spinnerClass = `loading-spinner ${sizeClasses[size] || sizeClasses.medium}`;

    if (overlay) {
        return (
            <div className="loading-overlay">
                <div className={spinnerClass}>
                    <div className="spinner"></div>
                    {message && <span className="spinner-message">{message}</span>}
                </div>
            </div>
        );
    }

    return (
        <div className={spinnerClass}>
            <div className="spinner"></div>
            {message && <span className="spinner-message">{message}</span>}
        </div>
    );
}

export default LoadingSpinner; 