import React from 'react';
import { formatDate } from '../../utils/formatters';

/**
 * Footer component with NBP information
 */
function Footer() {
    const currentYear = new Date().getFullYear();
    const today = formatDate(new Date());

    return (
        <footer className="footer">
            <div className="container">
                <div className="footer-content">
                    {/* Data source info */}
                    <div className="footer-section">
                        <h4 className="footer-title">Źródło danych</h4>
                        <p className="footer-text">
                            Kursy walut pochodzą z{' '}
                            <a 
                                href="https://nbp.pl" 
                                target="_blank" 
                                rel="noopener noreferrer"
                                className="footer-link"
                            >
                                Narodowego Banku Polskiego (NBP)
                            </a>
                        </p>
                        <p className="footer-text small">
                            Dane na dzień: {today}
                        </p>
                    </div>

                    {/* App info */}
                    <div className="footer-section">
                        <h4 className="footer-title">Informacje</h4>
                        <p className="footer-text">
                            Aplikacja kantorowa - kursy walut dla profesjonalistów
                        </p>
                        <p className="footer-text small">
                            Automatyczne odświeżanie co 5 minut
                        </p>
                    </div>

                    {/* Disclaimer */}
                    <div className="footer-section">
                        <h4 className="footer-title">Zastrzeżenia</h4>
                        <p className="footer-text small">
                            Kursy są orientacyjne i mogą różnić się od aktualnych kursów kantoru.
                            Ostateczne kursy wymiany ustala kantor w dniu transakcji.
                        </p>
                    </div>
                </div>

                {/* Bottom bar */}
                <div className="footer-bottom">
                    <div className="footer-bottom-content">
                        <p className="copyright">
                            © {currentYear} Kantor Walutowy. Wszystkie prawa zastrzeżone.
                        </p>
                        
                        <div className="footer-links">
                            <a 
                                href="https://api.nbp.pl" 
                                target="_blank" 
                                rel="noopener noreferrer"
                                className="footer-link"
                            >
                                NBP API
                            </a>
                            <span className="separator">|</span>
                            <span className="version">v1.0</span>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    );
}

export default Footer; 