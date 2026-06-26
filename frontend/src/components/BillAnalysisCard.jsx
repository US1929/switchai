import { formatEuro } from '../lib/calc.js';

export default function BillAnalysisCard({
  commodity,
  consumption,
  llmData,
  currentAnnualSpend,
  currentFixedMonthly,
  avgMarketSpread,
}) {
  const isLuce = commodity === 'luce';
  const unit = isLuce ? 'kWh' : 'Smc';
  const zona = llmData?.zona || '';
  const spread = llmData?.spread ? parseFloat(llmData.spread) : null;
  const tipo = (llmData?.tipo_tariffa || '').toLowerCase();
  const isVariable = tipo === 'variabile' || tipo === 'variable';
  const qf = currentFixedMonthly || 0;

  const spreadInsight = spread != null && avgMarketSpread != null
    ? spread < avgMarketSpread
      ? `Il tuo spread è sotto la media di mercato (media: ${avgMarketSpread.toFixed(4).replace('.', ',')} €/${unit}) — la tua offerta è già competitiva`
      : `Il tuo spread è sopra la media di mercato (media: ${avgMarketSpread.toFixed(4).replace('.', ',')} €/${unit}) — puoi risparmiare cambiando`
    : null;

  const meseLabel = formatEuro(Math.round(currentAnnualSpend / 12));

  return (
    <div className="glass-card" style={{ padding: '20px 24px', marginBottom: 16 }}>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 6, flexWrap: 'wrap', gap: 6 }}>
        <div style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', letterSpacing: 0.5, fontWeight: 600 }}>
          Analisi bolletta
        </div>
        <div style={{ fontSize: 10, color: '#475569' }}>
          {zona && `${zona} · `}{(consumption || 0).toLocaleString('it-IT')} {unit}/anno
        </div>
      </div>

      <div style={{ fontSize: 13, fontWeight: 600, color: '#94a3b8', marginBottom: 16 }}>
        {isVariable ? 'Indicizzato PUN' : 'Prezzo fisso'}
      </div>

      {/* Cosa paghi adesso */}
      <div style={{ marginBottom: 16 }}>
        <div style={{ fontSize: 9, color: '#64748b', textTransform: 'uppercase', letterSpacing: 0.3, marginBottom: 2 }}>
          Cosa paghi adesso
        </div>
        <div style={{ fontSize: 28, fontWeight: 900, color: '#f1f5f9', fontVariantNumeric: 'tabular-nums' }}>
          {formatEuro(currentAnnualSpend)}
        </div>
        <div style={{ fontSize: 11, color: '#64748b' }}>
          ≈ {meseLabel}/mese · solo energia
        </div>
      </div>

      {/* Come è composta */}
      <div style={{
        display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))',
        gap: 10, marginBottom: 12,
      }}>
        <MiniStat label="Tipo" value={isVariable ? 'Indicizzato PUN' : 'Fisso'} />
        <MiniStat label="Spread" value={spread != null ? `${spread.toFixed(4).replace('.', ',')} €/${unit}` : '—'} />
        <MiniStat label="Quota fissa" value={`${qf.toFixed(2).replace('.', ',')} €/mese`} />
      </div>

      {/* Spread insight */}
      {spreadInsight && (
        <div style={{
          fontSize: 10, color: '#64748b', fontStyle: 'italic',
          padding: '8px 10px', borderRadius: 8,
          background: spread < avgMarketSpread
            ? 'rgba(74,222,128,0.06)'
            : 'rgba(248,113,113,0.06)',
        }}>
          {spread < avgMarketSpread ? '✅ ' : '⚠️ '}
          {spreadInsight}
        </div>
      )}
    </div>
  );
}

function MiniStat({ label, value }) {
  return (
    <div style={{
      background: 'rgba(255,255,255,0.03)', borderRadius: 8,
      padding: '8px 10px', border: '1px solid rgba(255,255,255,0.04)',
    }}>
      <div style={{ fontSize: 9, color: '#64748b', marginBottom: 2 }}>{label}</div>
      <div style={{ fontSize: 12, fontWeight: 700, color: '#e2e8f0' }}>{value}</div>
    </div>
  );
}
