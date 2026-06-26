import { AreaChart, Area, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts';
import { formatEuro } from '../lib/calc.js';
import { buildMonthlyProjection } from '../lib/seasonal.js';

function AnnualCostTooltip({ active, payload, label, data, commodity }) {
  if (!active || !payload?.length) return null;
  const entry = data.find(d => d.label === label);
  if (!entry) return null;
  return (
    <div style={{
      background: '#131827', border: '1px solid rgba(255,255,255,0.08)',
      borderRadius: 12, padding: '10px 14px', boxShadow: '0 8px 32px rgba(0,0,0,0.5)',
    }}>
      <div style={{ fontSize: 12, fontWeight: 700, color: '#f1f5f9', marginBottom: 6 }}>{label}</div>
      <div style={{ fontSize: 11, color: '#94a3b8', marginBottom: 2 }}>
        Consumo mese: <span style={{ color: '#e2e8f0', fontWeight: 600 }}>{entry.consumoMese.toLocaleString('it-IT')} {commodity === 'luce' ? 'kWh' : 'Smc'}</span>
      </div>
      <div style={{ fontSize: 11, color: '#60a5fa', marginBottom: 2 }}>
        Costo attuale: <span style={{ fontWeight: 700 }}>{formatEuro(entry.costoTua)}</span>
      </div>
      <div style={{ fontSize: 11, color: '#4ade80', marginBottom: 2 }}>
        Media offerte: <span style={{ fontWeight: 700 }}>{formatEuro(entry.costoMedio)}</span>
      </div>
      {entry.risparmio > 0 && (
        <div style={{
          fontSize: 11, color: '#4ade80', marginTop: 4, paddingTop: 4,
          borderTop: '1px solid rgba(255,255,255,0.06)',
        }}>
          Risparmio mese: <span style={{ fontWeight: 800, color: '#34d399' }}>{formatEuro(entry.risparmio)}</span>
        </div>
      )}
    </div>
  );
}

export default function AnnualCostProjection({
  commodity,
  consumption,
  llmData,
  monthlyTrendPct,
  avgMarketSpread,
  avgMarketQuotaFissa,
  avgFixedPricePerUnit,
  billBreakdown,
  currentAnnualSpend,
  currentPun,
  currentPsv,
}) {
  if (!consumption || consumption <= 0 || !currentAnnualSpend) return null;

  const userSpread = llmData?.spread ? parseFloat(llmData.spread) : null;
  const userQF = llmData?.quota_fissa_mensile ? parseFloat(llmData.quota_fissa_mensile) : null;
  const hasFixedPrice = avgFixedPricePerUnit != null && avgFixedPricePerUnit > 0.01;

  const data = buildMonthlyProjection({
    commodity,
    consumption,
    userSpread,
    userQuotaFissa: userQF,
    currentPun,
    currentPsv,
    monthlyTrendPct,
    avgMarketSpread,
    avgMarketQuotaFissa,
    avgFixedPricePerUnit,
    billBreakdown,
    currentAnnualSpend,
  });

  if (!data) return null;

  const totalTuo = data.reduce((s, m) => s + m.costoTua, 0);
  const totalFisso = data.reduce((s, m) => s + m.costoMedio, 0);
  const risparmio = totalTuo - totalFisso;
  const risparmioPct = totalTuo > 0 ? (risparmio / totalTuo) * 100 : 0;

  const maxVal = Math.max(...data.map(m => Math.max(m.costoTua, m.costoMedio)));
  const yMax = Math.ceil(maxVal / 20) * 20 + 20;

  const isVariable = (llmData?.tipo_tariffa || '').toLowerCase() === 'variabile';
  const hasRisparmio = risparmio > 5;

  return (
    <div style={{ marginBottom: 24 }}>
      <div className="glass-card" style={{ padding: '20px 24px' }}>
        {/* Header */}
        <div style={{
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          marginBottom: 16, flexWrap: 'wrap', gap: 8,
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <span style={{ fontSize: 16 }}>📈</span>
            <span style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9' }}>Previsione spesa annuale</span>
            {isVariable && (
              <span style={{
                background: 'rgba(245,158,11,0.1)', border: '1px solid rgba(245,158,11,0.3)',
                borderRadius: 20, padding: '1px 8px', fontSize: 9, fontWeight: 600,
                color: '#fbbf24', textTransform: 'uppercase',
              }}>Variabile ⚡</span>
            )}
          </div>
          {monthlyTrendPct != null && (
            <div style={{
              fontSize: 10, color: monthlyTrendPct > 0 ? '#f87171' : '#4ade80',
              background: monthlyTrendPct > 0 ? 'rgba(248,113,113,0.08)' : 'rgba(74,222,128,0.08)',
              borderRadius: 8, padding: '4px 10px', fontWeight: 600,
            }}>
              {monthlyTrendPct > 0 ? '📈' : '📉'} Trend {monthlyTrendPct > 0 ? '+' : ''}{monthlyTrendPct.toFixed(1)}% (30gg)
            </div>
          )}
        </div>

        {/* KPI cards */}
        <div style={{
          display: 'grid', gridTemplateColumns: hasRisparmio ? 'repeat(3, 1fr)' : 'repeat(2, 1fr)',
          gap: 10, marginBottom: 20,
        }}>
          <div style={{
            background: 'rgba(96,165,250,0.06)', borderRadius: 10, padding: '10px 14px',
            border: '1px solid rgba(96,165,250,0.12)',
          }}>
            <div style={{ fontSize: 9, color: '#64748b', marginBottom: 2, textTransform: 'uppercase', letterSpacing: 0.3 }}>
              Costo variabile stimato
            </div>
            <div style={{ fontSize: 18, fontWeight: 900, color: '#60a5fa' }}>
              {formatEuro(totalTuo)}
            </div>
            <div style={{ fontSize: 9, color: '#64748b' }}>
              ≈ {formatEuro(Math.round(totalTuo / 12))}/mese
            </div>
          </div>

          <div style={{
            background: 'rgba(74,222,128,0.06)', borderRadius: 10, padding: '10px 14px',
            border: '1px solid rgba(74,222,128,0.12)',
          }}>
            <div style={{ fontSize: 9, color: '#64748b', marginBottom: 2, textTransform: 'uppercase', letterSpacing: 0.3 }}>
              Media offerte mercato
            </div>
            <div style={{ fontSize: 18, fontWeight: 900, color: '#4ade80' }}>
              {formatEuro(totalFisso)}
            </div>
            <div style={{ fontSize: 9, color: '#64748b' }}>
              {hasFixedPrice
                ? `Prezzo medio: ${avgFixedPricePerUnit.toFixed(4).replace('.', ',')} €/${commodity === 'luce' ? 'kWh' : 'Smc'}`
                : avgMarketSpread != null
                  ? `Spread medio: ${avgMarketSpread.toFixed(4).replace('.', ',')} €/${commodity === 'luce' ? 'kWh' : 'Smc'}`
                  : `Basato su ${data.length} offerte`}
            </div>
          </div>

          {hasRisparmio && (
            <div style={{
              background: 'rgba(52,211,153,0.08)', borderRadius: 10, padding: '10px 14px',
              border: '1px solid rgba(52,211,153,0.2)',
              position: 'relative', overflow: 'hidden',
            }}>
              <div style={{
                position: 'absolute', top: -10, right: -10,
                fontSize: 40, opacity: 0.06, fontWeight: 900, lineHeight: 1,
                color: '#34d399',
              }}>€</div>
              <div style={{ fontSize: 9, color: '#64748b', marginBottom: 2, textTransform: 'uppercase', letterSpacing: 0.3 }}>
                Risparmio potenziale
              </div>
              <div style={{ fontSize: 18, fontWeight: 900, color: '#34d399' }}>
                -{formatEuro(Math.abs(risparmio))}
              </div>
              <div style={{ fontSize: 9, color: '#64748b' }}>
                {risparmioPct.toFixed(0)}% in meno all'anno
              </div>
            </div>
          )}
        </div>

        {/* Chart */}
        <div style={{ width: '100%', height: 240, marginBottom: 12 }}>
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={data} margin={{ top: 4, right: 4, left: -20, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.03)" vertical={false} />
              <XAxis
                dataKey="label"
                axisLine={false}
                tickLine={false}
                tick={{ fontSize: 10, fill: '#64748b' }}
                interval={0}
              />
              <YAxis
                axisLine={false}
                tickLine={false}
                tick={{ fontSize: 10, fill: '#64748b' }}
                tickFormatter={v => `${v}€`}
                domain={[0, yMax]}
              />
              <Tooltip content={<AnnualCostTooltip data={data} commodity={commodity} />} />
              <Area
                type="monotone"
                dataKey="costoTua"
                stroke="#3b82f6"
                strokeWidth={2}
                fill="#3b82f6"
                fillOpacity={0.1}
                dot={false}
                activeDot={{ r: 4, fill: '#3b82f6', stroke: '#1e293b', strokeWidth: 2 }}
              />
              <Area
                type="monotone"
                dataKey="costoMedio"
                stroke="#4ade80"
                strokeWidth={2}
                strokeDasharray="6 3"
                fill="#4ade80"
                fillOpacity={0.05}
                dot={false}
                activeDot={{ r: 4, fill: '#4ade80', stroke: '#1e293b', strokeWidth: 2 }}
              />
            </AreaChart>
          </ResponsiveContainer>
        </div>

        {/* Legend + insight */}
        <div style={{
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          flexWrap: 'wrap', gap: 8, fontSize: 10, color: '#64748b',
          paddingTop: 10, borderTop: '1px solid rgba(255,255,255,0.04)',
        }}>
          <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap', alignItems: 'center' }}>
              <LegendItem color="#3b82f6" label="Costo attuale" />
            <LegendItem color="#4ade80" label="Media offerte" dashed />
              {isVariable && hasFixedPrice && (
                <span style={{ color: '#52525b' }}>
                  💡 Il costo attuale segue l'andamento del {commodity === 'luce' ? 'PUN' : 'PSV'} (variabile), la media offerte è un prezzo bloccato
                </span>
              )}
              {isVariable && !hasFixedPrice && avgMarketSpread != null && (
                <span style={{ color: '#52525b' }}>
                  💡 Entrambi seguono il {commodity === 'luce' ? 'PUN' : 'PSV'} — la differenza è il tuo spread vs media mercato
                </span>
              )}
          </div>
          {currentAnnualSpend && (
            <span style={{ color: '#52525b' }}>
              Confronto basato su {consumption.toLocaleString('it-IT')} {commodity === 'luce' ? 'kWh' : 'Smc'}/anno
            </span>
          )}
        </div>
      </div>
    </div>
  );
}

function LegendItem({ color, label, dashed }) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
      <div style={{
        width: 14, height: dashed ? 0 : 3,
        borderTop: dashed ? `2px dashed ${color}` : 'none',
        background: dashed ? 'none' : color,
        borderRadius: 2,
      }} />
      {dashed && <div style={{ width: 14, height: 3, borderTop: `2px dashed ${color}`, borderRadius: 2 }} />}
      <span>{label}</span>
    </div>
  );
}
