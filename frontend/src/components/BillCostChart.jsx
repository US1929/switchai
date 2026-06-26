import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip } from 'recharts'

const COLORS = {
  materia: '#22d3ee',
  trasporto: '#A78BFA',
  oneri: '#E879F9',
  imposte: '#FB7185',
}

function formatEur(n) {
  return n.toFixed(2).replace('.', ',') + '€'
}

function BillCostTooltip({ active, payload, tot }) {
  if (!active || !payload?.length) return null
  const d = payload[0]
  const pct = tot > 0 ? ((d.value / tot) * 100).toFixed(1) : '0'
  return (
    <div style={{
      background: '#131827', border: '1px solid rgba(255,255,255,0.06)',
      borderRadius: 12, padding: '10px 14px', boxShadow: '0 8px 32px rgba(0,0,0,0.4)',
    }}>
      <div style={{ fontSize: 12, color: '#94a3b8', marginBottom: 4 }}>{d.name}</div>
      <div style={{ fontSize: 15, fontWeight: 700, color: '#f1f5f9' }}>
        {formatEur(d.value)}
      </div>
      <div style={{ fontSize: 11, color: '#64748b' }}>{pct}% del totale</div>
    </div>
  )
}

export default function BillCostChart({ data, totale }) {
  if (!data) return null

  const chartData = [
    { name: 'Materia energia', value: Number(data.materia ?? 0), fill: COLORS.materia },
    { name: 'Trasporto e gestione', value: Number(data.trasporto ?? 0), fill: COLORS.trasporto },
    { name: 'Oneri di sistema', value: Number(data.oneri ?? 0), fill: COLORS.oneri },
    { name: 'Imposte, IVA e altro', value: Number(data.imposte ?? 0), fill: COLORS.imposte },
  ].filter(d => d.value > 0)

  if (chartData.length === 0) return null

  const tot = totale || chartData.reduce((s, d) => s + d.value, 0)

  return (
    <div className="glass-card" style={{ padding: '20px 24px' }}>
      <div style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 12 }}>
        Ripartizione spesa bolletta
      </div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
        <div style={{ width: 200, height: 200, flexShrink: 0 }}>
          <ResponsiveContainer width="100%" height="100%">
            <PieChart>
              <Pie
                data={chartData}
                cx="50%"
                cy="50%"
                innerRadius={56}
                outerRadius={90}
                dataKey="value"
                stroke="none"
              >
                {chartData.map((entry, i) => (
                  <Cell
                    key={i}
                    fill={entry.fill}
                    stroke={entry.name === 'Materia energia' ? '#22d3ee' : 'none'}
                    strokeWidth={entry.name === 'Materia energia' ? 2 : 0}
                    style={entry.name === 'Materia energia' ? { filter: 'drop-shadow(0 0 6px rgba(34,211,238,0.5))' } : undefined}
                  />
                ))}
              </Pie>
              <Tooltip content={<BillCostTooltip tot={tot} />} />
            </PieChart>
          </ResponsiveContainer>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8, flex: 1, minWidth: 140 }}>
          {chartData.map(d => {
            const pct = tot > 0 ? ((d.value / tot) * 100).toFixed(1) : '0'
            return (
              <div key={d.name} style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <span style={{ width: 8, height: 8, borderRadius: '50%', background: d.fill, flexShrink: 0,
                  boxShadow: d.name === 'Materia energia' ? '0 0 6px rgba(34,211,238,0.6)' : 'none',
                }} />
                <span style={{ fontSize: 11, color: '#94a3b8', flex: 1 }}>{d.name}</span>
                {d.name === 'Materia energia' && (
                  <span style={{
                    fontSize: 8, fontWeight: 700, color: '#22d3ee',
                    background: 'rgba(34,211,238,0.1)', padding: '1px 6px', borderRadius: 4,
                    letterSpacing: 0.3,
                  }}>
                    CAMBIA
                  </span>
                )}
                <span style={{ fontSize: 12, fontWeight: 600, color: '#e2e8f0' }}>{formatEur(d.value)}</span>
                <span style={{ fontSize: 10, color: '#64748b', minWidth: 32, textAlign: 'right' }}>{pct}%</span>
              </div>
            )
          })}
          <div style={{
            marginTop: 4, paddingTop: 8, borderTop: '1px solid rgba(255,255,255,0.06)',
            display: 'flex', alignItems: 'center', gap: 8,
          }}>
            <span style={{ fontSize: 12, fontWeight: 700, color: '#94a3b8', flex: 1 }}>Totale</span>
            <span style={{ fontSize: 14, fontWeight: 800, color: '#fff' }}>{formatEur(tot)}</span>
          </div>
        </div>
      </div>
    </div>
  )
}
