import React from 'react';
import { BrowserRouter as Router, Route, Switch } from 'react-router-dom';
import Header from './Layout/Header';
import Footer from './Layout/Footer';
import HomePage from './HomePage';
import HistoryPage from './HistoryPage';
import ErrorBoundary from './Common/ErrorBoundary';

/**
 * Main App component with routing
 */
function App() {
    return (
        <ErrorBoundary>
            <Router>
                <div className="app">
                    <Header />
                    
                    <main className="main-content">
                        <Switch>
                            <Route exact path="/" component={HomePage} />
                            <Route path="/history/:currency?/:date?" component={HistoryPage} />
                            <Route render={() => (
                                <div className="not-found">
                                    <div className="container">
                                        <h1>404 - Strona nie znaleziona</h1>
                                        <p>Przepraszamy, strona której szukasz nie istnieje.</p>
                                    </div>
                                </div>
                            )} />
                        </Switch>
                    </main>
                    
                    <Footer />
                </div>
            </Router>
        </ErrorBoundary>
    );
}

export default App; 