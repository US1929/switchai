import { useState, useEffect } from 'react';
import TariffTableRow from './TariffTableRow.jsx';
import { isPriceAnomalous } from '../lib/calc.js';

export default function TariffTable({
  items,
  commodity,
  currentSpend,
  hasRealSpend,
  currentPricePerUnit,
  currentFixedMonthly,
  llmData,
  selectedKeys,
  onToggleSelect,
  consumption,
  punEurKwh,
  psvEurSmc,
}) {
  const [isMobile, setIsMobile] = useState(false);

  useEffect(() => {
    const check = () => setIsMobile(window.innerWidth < 640);
    check();
    window.addEventListener('resize', check);
    return () => window.removeEventListener('resize', check);
  }, []);

  const isLuce = commodity === 'luce';

  return (
    <div style={{
      borderRadius: 12,
      background: 'rgba(255,255,255,0.02)',
      border: '1px solid rgba(255,255,255,0.06)',
      overflow: 'hidden',
    }}>
      {!isMobile && (
        <div style={{
          display: 'grid',
          gridTemplateColumns: '32px 100px 1fr 80px 70px 85px 80px 32px',
          gap: 8,
          padding: '8px 16px',
          background: 'rgba(255,255,255,0.025)',
          borderBottom: '1px solid rgba(255,255,255,0.06)',
          fontSize: 10,
          color: '#64748b',
          fontWeight: 600,
          textTransform: 'uppercase',
          letterSpacing: '0.5px',
        }}>
          <div></div>
          <div>Fornitore</div>
          <div>Offerta</div>
          <div style={{ textAlign: 'right' }}>Prezzo</div>
          <div style={{ textAlign: 'right' }}>Quota</div>
          <div style={{ textAlign: 'right' }}>Costo/anno</div>
          <div style={{ textAlign: 'right' }}>Risparmio</div>
          <div></div>
        </div>
      )}

      {items.map((item, i) => (
        <TariffTableRow
          key={`${item.tariff.brand}|${item.tariff.offerta}`}
          item={item}
          rank={i + 1}
          commodity={commodity}
          currentSpend={currentSpend}
          hasRealSpend={hasRealSpend}
          currentPricePerUnit={currentPricePerUnit}
          currentFixedMonthly={currentFixedMonthly}
          llmData={llmData}
          selected={selectedKeys.includes(`${item.tariff.brand}|${item.tariff.offerta}`)}
          onToggleSelect={onToggleSelect}
          consumption={consumption}
          isAnomalous={isPriceAnomalous(
            isLuce ? (item.tariff['prezzo tot kwh']) : (item.tariff['prezzo tot smc']),
            commodity
          )}
          priceWarning={item.priceWarning}
          punEurKwh={punEurKwh}
          psvEurSmc={psvEurSmc}
        />
      ))}
      {items.length > 1 && (
        <div style={{ padding: '10px 16px', fontSize: 11, color: '#64748b', borderTop: '1px solid rgba(255,255,255,0.06)', lineHeight: 1.5 }}>
          💡 <strong style={{ color: '#94a3b8' }}>Scontrino energetico:</strong> la differenza tra le offerte è solo nella <strong style={{ color: '#f1f5f9' }}>vendita energia</strong> (prezzo × consumo + quota fissa). Trasporto, oneri, accise e IVA sono uguali per tutti i fornitori.
        </div>
      )}
    </div>
  );
}
