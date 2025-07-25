import React, { useEffect, useState } from 'react';
import axios from 'axios';
import CurrencyCard from './CurrencyCard';
import CurrencyHistoryModal from './CurrencyHistoryModal';

const API_BASE = '';

// CurrencyList.js
// ---------------
// Komponent odpowiada za:
// - Wyświetlanie listy kursów walut.
// - Obsługę historii kursów dla wybranej waluty.
// - Automatyczne odświeżanie kursów po zmianie dnia.
// - Obsługę modala z historią kursów.
const CurrencyList = () => {
  // Stan: lista kursów walut
  const [currencies, setCurrencies] = useState([]);
  // Stan: czy trwa ładowanie kursów
  const [loading, setLoading] = useState(true);
  // Stan: błąd pobierania kursów
  const [error, setError] = useState(null);
  // Stan: modal historii (czy otwarty i dla jakiej waluty)
  const [modal, setModal] = useState({ show: false, currency: null });
  // Stan: historia kursów dla wybranej waluty
  const [history, setHistory] = useState([]);
  // Stan: czy trwa ładowanie historii
  const [historyLoading, setHistoryLoading] = useState(false);
  // Stan: błąd pobierania historii
  const [historyError, setHistoryError] = useState(null);
  // Stan: wybrana data do historii
  const [historyDate, setHistoryDate] = useState(new Date().toISOString().slice(0, 10));

  // Stan: aktualny czas (do zegara na stronie)
  const [currentTime, setCurrentTime] = useState(new Date());
  // Stan: ostatnia data, dla której pobrano kursy (do automatycznego odświeżania)
  const [lastFetchDate, setLastFetchDate] = useState(currentTime.toISOString().slice(0, 10));

  // Pobiera aktualne kursy walut z backendu
  const fetchCurrencies = () => {
    setLoading(true);
    axios.get(`${API_BASE}/api/rates/current`).then(res => {
      setCurrencies(res.data);
      setError(null);
      setLoading(false);
    }).catch(e => {
      setError('Błąd ładowania kursów walut.');
      setLoading(false);
    });
  };

  // useEffect: pobiera kursy przy pierwszym renderze komponentu
  useEffect(() => {
    fetchCurrencies();
  }, []);

  // useEffect: automatyczne odświeżanie kursów po zmianie dnia
  useEffect(() => {
    const interval = setInterval(() => {
      const now = new Date();
      setCurrentTime(now);
      const today = now.toISOString().slice(0, 10);
      if (today !== lastFetchDate) {
        setLastFetchDate(today);
        fetchCurrencies();
      }
    }, 1000);

    return () => clearInterval(interval);
  }, [lastFetchDate]);

  // useEffect: pobieranie historii kursów dla wybranej waluty i daty
  useEffect(() => {
    if (modal.show && modal.currency) {
      setHistory([]);
      setHistoryLoading(true);
      setHistoryError(null);
      const url = `${API_BASE}/api/rates/history?currency=${modal.currency}&date=${historyDate}`;
      axios.get(url)
        .then(res => {
          setHistory(res.data);
          setHistoryLoading(false);
        })
        .catch(e => {
          setHistoryError('Błąd ładowania historii kursów.');
          setHistoryLoading(false);
        });
    }
  }, [modal.show, modal.currency, historyDate]);

  // Otwiera modal historii dla wybranej waluty
  const openHistory = (currency) => {
    setModal({ show: true, currency });
  };

  // Zmienia datę historii (przekazywane do modala)
  const handleDateChange = (date) => {
    setHistoryDate(date);
  };

  // Zamyka modal historii
  const closeModal = () => setModal({ show: false, currency: null });

  // Wyświetla loader lub błąd, jeśli występuje
  if (loading) return <div className="text-center mt-5"><span className="fa fa-spin fa-spinner fa-3x"></span></div>;
  if (error) return <div className="text-danger text-center mt-5">{error}</div>;

  return (
    <>
      {/* Zegar na stronie */}
      <div className="d-flex justify-content-center my-4">
        <div className="live-clock">
          {currentTime.toLocaleDateString()} &nbsp; {currentTime.toLocaleTimeString()}
        </div>
      </div>

      {/* Lista kursów walut */}
      <div className="container mt-4">
      <div className="row">
            {currencies.map(cur => (
              <div className="col-lg-4 col-md-6 col-12 mb-4" key={cur.currency}>
                <CurrencyCard
                  currency={cur.currency}
                  buy={cur.buy}
                  sell={cur.sell}
                  mid={cur.mid}
                  onHistoryClick={() => openHistory(cur.currency)}
                />
              </div>
            ))}
          </div>
        {/* Modal historii kursów */}
        <CurrencyHistoryModal
          show={modal.show}
          onClose={closeModal}
          currency={modal.currency}
          history={history}
          loading={historyLoading}
          error={historyError}
          onDateChange={handleDateChange}
          date={historyDate}
        />
      </div>
    </>
  );
};

export default CurrencyList;
