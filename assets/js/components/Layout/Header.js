import React from 'react';
import { Link, useLocation } from 'react-router-dom';

/**
 * Header component with navigation
 */
function Header() {
    const location = useLocation();

    const isActive = (path) => {
        if (path === '/' && location.pathname === '/') return true;
        if (path !== '/' && location.pathname.startsWith(path)) return true;
        return false;
    };

    return (
        <header className="header">
            <div className="container">
                <div className="header-content">
                    {/* Logo */}
                    <div className="logo">
                        <Link to="/" className="logo-link">
                            <span className="logo-icon">💱</span>
                            <span className="logo-text">Kantor Walutowy</span>
                        </Link>
                    </div>

                    {/* Navigation */}
                    <nav className="navigation">
                        <ul className="nav-list">
                            <li className="nav-item">
                                <Link 
                                    to="/" 
                                    className={`nav-link ${isActive('/') ? 'active' : ''}`}
                                >
                                    Aktualne Kursy
                                </Link>
                            </li>
                            <li className="nav-item">
                                <Link 
                                    to="/history" 
                                    className={`nav-link ${isActive('/history') ? 'active' : ''}`}
                                >
                                    Historia Kursów
                                </Link>
                            </li>
                        </ul>
                    </nav>

                    {/* Mobile menu button */}
                    <button 
                        className="mobile-menu-button"
                        onClick={() => {
                            const nav = document.querySelector('.navigation');
                            nav.classList.toggle('mobile-open');
                        }}
                        aria-label="Toggle navigation"
                    >
                        <span className="hamburger-line"></span>
                        <span className="hamburger-line"></span>
                        <span className="hamburger-line"></span>
                    </button>
                </div>
            </div>

            {/* Live indicator */}
            <div className="live-indicator">
                <span className="live-dot"></span>
                <span className="live-text">Na żywo</span>
            </div>
        </header>
    );
}

export default Header; 