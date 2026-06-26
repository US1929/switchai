const COLORS = [
  { label: 'Materia energia', key: 'materia', color: '#FFEFB4', bar: '#22d3ee' },
  { label: 'Trasporto e gestione', key: 'trasporto', color: '#CCB8FC', bar: '#A78BFA' },
  { label: 'Oneri di sistema', key: 'oneri', color: '#F3D1FB', bar: '#E879F9' },
  { label: 'Imposte, IVA e altro', key: 'imposte', color: '#FFC1C1', bar: '#FB7185' },
]

export default function CostBreakdownCard({ data, totale }) {
  if (!data) return null

  const items = COLORS.map(c => ({
    ...c,
    valore: Number(data[c.key] ?? 0),
  }))
  const tot = totale || items.reduce((s, i) => s + i.valore, 0)

  return (
    <div className="glass-card" style={{ padding: '20px 24px' }}>
      <div style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 14 }}>
        Scomposizione del costo
      </div>
      {items.map(item => {
        if (item.valore <= 0) return null
        const pct = tot > 0 ? (item.valore / tot) * 100 : 0
        return (
          <div key={item.key} style={{ marginBottom: item === items[items.length - 1] ? 0 : 12 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 4 }}>
              <span style={{
                width: 10, height: 10, borderRadius: '50%',
                background: item.bar, flexShrink: 0,
                boxShadow: item.key === 'materia' ? '0 0 6px rgba(34,211,238,0.6)' : 'none',
              }} />
              <span style={{ flex: 1, fontSize: 12, color: '#94a3b8' }}>{item.label}</span>
              {item.key === 'materia' && (
                <span style={{
                  fontSize: 8, fontWeight: 700, color: '#22d3ee',
                  background: 'rgba(34,211,238,0.1)', padding: '1px 6px', borderRadius: 4,
                  letterSpacing: 0.3, marginRight: 4,
                }}>
                  CAMBIA
                </span>
              )}
              <span style={{ fontSize: 13, fontWeight: 700, color: '#f1f5f9' }}>
                {item.valore.toFixed(2).replace('.', ',')}€
              </span>
              <span style={{ fontSize: 11, color: '#64748b', minWidth: 36, textAlign: 'right' }}>
                {pct.toFixed(1)}%
              </span>
            </div>
            <div style={{ height: 4, background: '#1f2937', borderRadius: 4, overflow: 'hidden' }}>
              <div style={{
                height: '100%', width: `${Math.min(pct, 100)}%`,
                background: item.bar, borderRadius: 4, transition: 'width 0.6s ease',
              }} />
            </div>
          </div>
        )
      })}
      <div style={{
        marginTop: 14, paddingTop: 12, borderTop: '1px solid rgba(255,255,255,0.06)',
        display: 'flex', justifyContent: 'space-between', fontSize: 13,
      }}>
        <span style={{ color: '#94a3b8', fontWeight: 600 }}>Totale</span>
        <span style={{ color: '#fff', fontWeight: 800 }}>
          {tot.toFixed(2).replace('.', ',')}€
        </span>
      </div>
    </div>
  )
}
