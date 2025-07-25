import React, { useRef, useEffect, useState } from 'react';

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


const baseChartWidth = 900, chartHeight = 340, padding = 60, labelFont = 14, xLabelOffset = 54;

const CurrencyHistoryModal = ({ show, onClose, currency, history, loading, error, onDateChange, date }) => {
  const chartContainerRef = useRef(null);
  const [chartWidth, setChartWidth] = useState(baseChartWidth);

  useEffect(() => {
    if (chartContainerRef.current) {
      setChartWidth(Math.min(chartContainerRef.current.offsetWidth, baseChartWidth));
    }
    const handleResize = () => {
      if (chartContainerRef.current) {
        setChartWidth(Math.min(chartContainerRef.current.offsetWidth, baseChartWidth));
      }
    };
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, [show]);

  if (!show) return null;

  // Wykres SVG (kurs NBP względem czasu)
  let points = '';
  let min = 0, max = 0, minForAxis = 0;
  if (history.length > 1) {
    min = Math.min(...history.map(r => r.mid));
    max = Math.max(...history.map(r => r.mid));
    minForAxis = min - 0.002; // oś Y zaczyna się od najniższej wartości minus 1
    points = history.map((row, i) => {
      const x = padding + i * ((chartWidth - 2 * padding) / (history.length - 1));
      const y = chartHeight - padding - ((row.mid - minForAxis) / (max - minForAxis || 1)) * (chartHeight - 2 * padding);
      return `${x},${y}`;
    }).join(' ');
  }

  // Etykiety Y (kursy, poziomo)
  const yLabels = [];
  if (history.length > 1) {
    const steps = 6;
    for (let i = 0; i <= steps; i++) {
      const value = max - (i * (max - minForAxis) / steps);
      const y = padding + i * ((chartHeight - 2 * padding) / steps);
      yLabels.push({ value: value.toFixed(4), y });
    }
  }

  // Etykiety X (daty, poziomo, niżej pod wykresem)
  const xLabels = history.map((row, i) => {
    const x = padding + i * ((chartWidth - 2 * padding) / (history.length - 1));
    return { date: row.date, x };
  });

  return (
    <div className="modal-backdrop">
      <div className="modal-content" style={{maxWidth: 1200, width: '99vw', position:'relative'}}>
        <h2 style={{marginBottom: 12, marginLeft: 0}}>Historia kursów: {currency}</h2>
        <button className="close" onClick={onClose}>&times;</button>
        <div className="d-flex align-items-center mb-2" style={{gap: 16, justifyContent: 'flex-start'}}>
          <div style={{display: 'flex', alignItems: 'center', gap: 8}}>
            <label htmlFor="history-date" className="mr-2 mb-0">Data rozpoczęcia historii:</label>
            <input
              id="history-date"
              type="date"
              value={date}
              max={new Date().toISOString().slice(0, 10)}
              onChange={e => onDateChange && onDateChange(e.target.value)}
              style={{background: '#23283a', color: '#f3f3f3', border: '1px solid #444', borderRadius: 6, padding: '2px 8px'}}
            />
          </div>
        </div>
        {loading ? (
          <div style={{minHeight: 300, display: 'flex', alignItems: 'center', justifyContent: 'center'}}>
            <span className="fa fa-spin fa-spinner fa-4x" style={{color:'#2196f3'}}></span>
          </div>
        ) : (
        <div className="row">
          <div className="col-md-7">
            {error ? (
              <div className="text-danger">{error}</div>
            ) : (
              <table className="table table-sm table-striped mt-3">
                <thead>
                  <tr>
                    <th>Data</th>
                    <th><span className="th-content">{ICONS.buy} Kupno</span></th>
                    <th> <span className="th-content">{ICONS.sell} Sprzedaż</span></th>
                    <th> <span className="th-content">{ICONS.nbp} Kurs NBP</span></th>
                  </tr>
                </thead>
                <tbody>
                  {history.map(row => (
                    <tr key={row.date}>
                      <td>{row.date}</td>
                      <td>{row.buy !== null ? row.buy.toFixed(4) : '-'}</td>
                      <td>{row.sell !== null ? row.sell.toFixed(4) : '-'}</td>
                      <td>{row.mid !== null ? row.mid.toFixed(4) : '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
          <div className="col-md-5 d-flex align-items-center justify-content-center">
            <div ref={chartContainerRef} style={{width: '100%', minWidth: 200}}>
            {history.length > 1 && !error && (
              <svg
  width="100%"
  height={chartHeight + xLabelOffset}
  viewBox={`0 0 ${chartWidth} ${chartHeight + xLabelOffset}`}
  style={{ background: '#23283a', borderRadius: 12, boxShadow: '0 2px 8px #0003', maxWidth: '100%' }}
  preserveAspectRatio="none"
>
  {/* Siatka pozioma (linie pomocnicze) */}
  {yLabels.map(label => (
    <line
      key={`grid-${label.y}`}
      x1={padding}
      y1={label.y}
      x2={chartWidth - padding}
      y2={label.y}
      stroke="#444"
      strokeWidth={1}
    />
  ))}

  {/* Oś Y */}
  <line x1={padding} y1={padding} x2={padding} y2={chartHeight - padding} stroke="#888" strokeWidth={1.5} />

  {/* Oś X */}
  <line x1={padding} y1={chartHeight - padding} x2={chartWidth - padding} y2={chartHeight - padding} stroke="#888" strokeWidth={1.5} />

  {/* Etykiety Y */}
  {yLabels.map(label => (
    <g key={`y-label-${label.y}`}>
      <text x={padding - 12} y={label.y + 5} fill="#aaa" fontSize={labelFont} textAnchor="end">
        {label.value}
      </text>
      <line x1={padding - 5} y1={label.y} x2={padding} y2={label.y} stroke="#888" strokeWidth={1} />
    </g>
  ))}

{xLabels.map(label => (
  <text
    key={label.x}
    x={label.x}
    y={chartHeight - padding + 50} // jeszcze niżej
    fill="#888"
    fontSize={labelFont - 1}
    textAnchor="middle"
    transform={`rotate(90, ${label.x}, ${chartHeight - padding + 50})`}
  >
    {label.date}
  </text>
))}


  {/* Linia kursu */}
  <polyline fill="none" stroke="#00bcd4" strokeWidth={3} points={points} />

  {/* Punkty */}
  {history.map((row, i) => {
    const x = padding + i * ((chartWidth - 2 * padding) / (history.length - 1));
    const y = chartHeight - padding - ((row.mid - minForAxis) / (max - minForAxis || 1)) * (chartHeight - 2 * padding);
    return (
      <circle key={`pt-${i}`} cx={x} cy={y} r={5} fill="#00bcd4" stroke="#fff" strokeWidth={1.5} />
    );
  })}
</svg>
            )}
            </div>
          </div>
        </div>
        )}
      </div>
    </div>
  );
};

export default CurrencyHistoryModal; 