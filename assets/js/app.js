/*
 * Welcome to your app's main JavaScript file!
 *
 * This file bootstraps the React application and mounts it to the DOM.
 */

// Import CSS styles
import '../css/app.css';

// Import React and ReactDOM
import React from 'react';
import ReactDOM from 'react-dom';

// Import main App component
import App from './components/App';

// Mount React application to the DOM
const rootElement = document.getElementById('app');

if (rootElement) {
    ReactDOM.render(<App />, rootElement);
} else {
    console.error('Could not find element with id "app" to mount React application');
}

