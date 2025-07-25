import React from 'react';

const ICONS = {
  nbp: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="icon">
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
    </svg>
  ),
  sell: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="icon">
      <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
    </svg>
  ),
  buy: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="icon">
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
    </svg>
  ),
};

const CurrencyCard = ({ currency, buy, sell, mid, onHistoryClick }) => (
  <div className="currency-card">
    <div className="currency-header" style={{ alignItems: 'center' }}>
      <img
        src={getFlagPath(currency)}
        alt={`Flaga ${currency}`}
        className="flag"
        onError={(e) => { e.target.src = '/flags/unknown.png'; }}
      />
      <span className="currency-code" style={{ fontSize: '1.3rem' }}>{currency}</span>
    </div>
    <div className="currency-body">
      <div className="rate-box buy">
        {ICONS.buy} Kupno: <strong>{buy !== null ? buy.toFixed(4) : '-'}</strong>
      </div>
      <div className="rate-box sell">
        {ICONS.sell} Sprzedaż: <strong>{sell !== null ? sell.toFixed(4) : '-'}</strong>
      </div>
      <div className="rate-box nbp">
        {ICONS.nbp} Kurs NBP: <span>{mid !== null ? mid.toFixed(4) : '-'}</span>
      </div>
    </div>
    <button className="btn btn-outline-info btn-sm mt-2 history-btn" onClick={onHistoryClick}>
      Historia
    </button>
  </div>
);

function getFlagPath(currency) {
  const code = currency.toLowerCase();
  return `/flags/${code}.svg`;
}

export default CurrencyCard;