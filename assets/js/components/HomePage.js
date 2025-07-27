import React from 'react';
import CurrencyTable from './Currency/CurrencyTable';
import { useCurrencyRates } from '../hooks/useCurrencyRates';

/**
 * Home page component displaying current currency rates
 */
function HomePage() {
    const { data, loading, error, lastUpdated, refresh, isStale } = useCurrencyRates();

    // Extract currencies array from API response
    const currencies = data?.success && data?.data ? Object.values(data.data) : [];

    return (
        <div className="home-page">
            <div className="container">
                {/* Page header */}
                <div className="page-header">
                    <h1 className="page-title">Kursy Walut</h1>
                    <p className="page-subtitle">
                        Aktualne kursy wymiany walut w oparciu o dane Narodowego Banku Polskiego
                    </p>
                </div>

                {/* Status indicators */}
                <div className="status-indicators">
                    {loading && (
                        <div className="status-indicator loading">
                            <span className="indicator-icon">⏳</span>
                            <span>Ładowanie kursów...</span>
                        </div>
                    )}
                    
                    {isStale && !loading && (
                        <div className="status-indicator stale">
                            <span className="indicator-icon">⚠️</span>
                            <span>Dane mogą być nieaktualne</span>
                        </div>
                    )}
                    
                    {!loading && !error && lastUpdated && (
                        <div className="status-indicator success">
                            <span className="indicator-icon">✅</span>
                            <span>Dane aktualne</span>
                        </div>
                    )}
                </div>

                {/* Main content */}
                <main className="main-content">
                    <CurrencyTable
                        currencies={currencies}
                        loading={loading}
                        error={error}
                        lastUpdated={lastUpdated}
                        onRefresh={refresh}
                    />
                </main>

                {/* Additional info */}
                <div className="page-info">
                    <div className="info-grid">
                        <div className="info-card">
                            <h3 className="info-title">Automatyczne odświeżanie</h3>
                            <p className="info-text">
                                Kursy są automatycznie aktualizowane co 5 minut, 
                                aby zapewnić najświeższe dane z NBP.
                            </p>
                        </div>
                        
                        <div className="info-card">
                            <h3 className="info-title">Kursy kantorowe</h3>
                            <p className="info-text">
                                Wyświetlane kursy kupna i sprzedaży zawierają marże kantorowe
                                zgodnie z aktualnym cennikiem.
                            </p>
                        </div>
                        
                        <div className="info-card">
                            <h3 className="info-title">Źródło danych</h3>
                            <p className="info-text">
                                Wszystkie kursy bazowe pochodzą z oficjalnych tabel
                                Narodowego Banku Polskiego.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default HomePage; 