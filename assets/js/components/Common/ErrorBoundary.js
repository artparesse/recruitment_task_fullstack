import React from 'react';

/**
 * Error boundary component for handling React errors
 */
class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { 
            hasError: false, 
            error: null, 
            errorInfo: null 
        };
    }

    static getDerivedStateFromError(error) {
        // Update state so the next render will show the fallback UI
        return { hasError: true };
    }

    componentDidCatch(error, errorInfo) {
        // Log error details
        console.error('ErrorBoundary caught an error:', error, errorInfo);
        
        this.setState({
            error: error,
            errorInfo: errorInfo
        });

        // Log to external service in production
        if (process.env.NODE_ENV === 'production') {
            // Example: logErrorToService(error, errorInfo);
        }
    }

    handleReload = () => {
        // Clear error state and reload
        this.setState({ 
            hasError: false, 
            error: null, 
            errorInfo: null 
        });
        
        // Reload the page
        window.location.reload();
    };

    render() {
        if (this.state.hasError) {
            return (
                <div className="error-boundary">
                    <div className="container">
                        <div className="error-content">
                            <div className="error-icon">💥</div>
                            
                            <h1 className="error-title">Ups! Coś poszło nie tak</h1>
                            
                            <p className="error-message">
                                Wystąpił nieoczekiwany błąd w aplikacji. 
                                Nasze zespół został powiadomiony o problemie.
                            </p>

                            <div className="error-actions">
                                <button 
                                    className="btn btn-primary"
                                    onClick={this.handleReload}
                                >
                                    Odśwież stronę
                                </button>
                                
                                <button 
                                    className="btn btn-secondary"
                                    onClick={() => window.history.back()}
                                >
                                    Wróć
                                </button>
                            </div>

                            {/* Show error details in development */}
                            {process.env.NODE_ENV === 'development' && (
                                <details className="error-details">
                                    <summary>Szczegóły błędu (development)</summary>
                                    <pre className="error-stack">
                                        {this.state.error && this.state.error.toString()}
                                        <br />
                                        {this.state.errorInfo.componentStack}
                                    </pre>
                                </details>
                            )}
                        </div>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary; 