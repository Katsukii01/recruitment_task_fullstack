import React from 'react';
import CurrencyList from './CurrencyList';

const Home = () => (
  <div>
    <nav className="navbar navbar-expand-lg navbar-dark bg-dark">
      <span className="navbar-brand">Kantor</span>
    </nav>
    <CurrencyList />
  </div>
);

export default Home;
