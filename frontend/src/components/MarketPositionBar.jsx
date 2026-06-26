import { formatEuro } from '../lib/calc.js';

export default function MarketPositionBar({
  bestAnnualCost,
  bestSavings,
  currentAnnualSpend,
  marketAvgAnnualCost,
  totalOffers,
  currentLabel,
}) {
  const hasBest = bestAnnualCost != null && bestAnnualCost > 0;
  const hasAvg = marketAvgAnnualCost != null && marketAvgAnnualCost > 0;

  const diffFromBest = currentAnnualSpend && hasBest ? currentAnnualSpend - bestAnnualCost : null;
  const diffFromAvg = currentAnnualSpend && hasAvg ? marketAvgAnnualCost - currentAnnualSpend : null;

  const gap = diffFromBest > 0
    ? `Puoi risparmiare ${formatEuro(diffFromBest)} all'anno passando alla migliore offerta qui sotto`
    : diffFromAvg != null && diffFromAvg > 0
    ? `Paghi ${formatEuro(diffFromAvg)} in meno della media di mercato — ma le offerte qui sotto possono fare ancora meglio`
    : null;

  const columns = [
    hasBest && { key: 'best', label: 'Migliore offerta', value: bestAnnualCost, color: '#4ade80', sub: bestSavings > 0 ? `${formatEuro(bestSavings)}/anno` : null, highlight: false },
    { key: 'current', label: 'Tu oggi', value: currentAnnualSpend || 0, color: '#60a5fa', sub: currentLabel || '', highlight: true },
    hasAvg && { key: 'avg', label: 'Media mercato', value: marketAvgAnnualCost, color: '#64748b', sub: `${totalOffers || ''} fornitori`, highlight: false },
  ].filter(Boolean).sort((a, b) => a.value - b.value);

  const maxVal = Math.max(...columns.map(c => c.value), 1);

  return (
    <div className="glass-card" style={{ padding: '20px 24px', marginBottom: 16 }}>
      <div style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', letterSpacing: 0.5, fontWeight: 600, marginBottom: 14 }}>
        Dove ti posizioni rispetto al mercato
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: `repeat(${columns.length}, 1fr)`, gap: 10, marginBottom: 14, alignItems: 'stretch' }}>
        {columns.map(col => (
          <PositionColumn
            key={col.key}
            label={col.label}
            value={col.value ? formatEuro(col.value) : '—'}
            sub={col.sub}
            color={col.color}
            ratio={col.value && maxVal > 0 ? col.value / maxVal : 0}
            maxRatio={1}
            highlight={col.highlight}
          />
        ))}
      </div>

      {gap && (
        <div style={{
          fontSize: 10, color: '#64748b',
          padding: '8px 10px', borderRadius: 8,
          background: 'rgba(74,222,128,0.04)',
        }}>
          💡 {gap}
        </div>
      )}
    </div>
  );
}

function PositionColumn({ label, value, sub, color, ratio, maxRatio, highlight }) {
  const pct = maxRatio > 0 ? (ratio / maxRatio) * 100 : 0;
  return (
    <div style={{
      background: highlight ? 'rgba(96,165,250,0.06)' : 'rgba(255,255,255,0.02)',
      border: highlight ? '1px solid rgba(96,165,250,0.15)' : '1px solid rgba(255,255,255,0.04)',
      borderRadius: 10, padding: '12px 14px',
      position: 'relative',
      display: 'flex', flexDirection: 'column', minHeight: 100,
    }}>
      <div style={{
        position: 'absolute', bottom: 0, left: 0,
        width: `${Math.min(pct, 100)}%`, height: 3,
        background: color, borderRadius: '0 2px 0 0',
        opacity: 0.6, transition: 'width 0.6s ease',
      }} />
      <div style={{ fontSize: 9, color: highlight ? '#93c5fd' : '#64748b', marginBottom: 4, fontWeight: 500 }}>
        {label}
      </div>
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', justifyContent: 'center' }}>
        <div style={{ fontSize: 18, fontWeight: 900, color, fontVariantNumeric: 'tabular-nums', marginBottom: 2 }}>
          {value}
        </div>
        {sub && (
          <div style={{ fontSize: 10, color: '#64748b', whiteSpace: 'nowrap' }}>
            {sub}
          </div>
        )}
      </div>
    </div>
  );
}
