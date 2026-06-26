import { useEffect } from 'react'

function ModalRow({ label, value, highlight }) {
  return (
    <div style={{
      display: 'flex', justifyContent: 'space-between', alignItems: 'center',
      padding: '8px 0', borderBottom: '1px solid rgba(255,255,255,0.05)',
    }}>
      <span style={{ fontSize: 12, color: '#94a3b8' }}>{label}</span>
      <span style={{
        fontSize: 13, fontWeight: highlight ? 700 : 500,
        color: highlight ? '#f1f5f9' : '#cbd5e1',
      }}>{value}</span>
    </div>
  )
}

export default function SavingsBreakdownModal({ consumo, prezzo, costoFisso, annualCost, currentSpend, brand, offerta, tipo, open, onClose }) {
  useEffect(() => {
    if (!open) return
    const handler = (e) => {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', handler)
    return () => document.removeEventListener('keydown', handler)
  }, [open, onClose])

  if (!open) return null

  const isLuce = tipo === 'LUCE'
  const unit = isLuce ? 'kWh' : 'Smc'
  const consumoNum = Number(consumo ?? 0)
  const prezzoNum = Number(prezzo ?? 0)
  const fissoAnnuale = Number(costoFisso ?? 0)
  const annualNum = Number(annualCost ?? 0)
  const currentNum = Number(currentSpend ?? 0)
  const materiaCost = consumoNum * prezzoNum
  const savings = currentNum > 0 ? Math.max(0, currentNum - annualNum) : 0
  const savingsPct = currentNum > 0 ? ((savings / currentNum) * 100).toFixed(1) : '0'

  return (
    <div
      onClick={onClose}
      style={{
        position: 'fixed', inset: 0, zIndex: 999,
        background: 'rgba(0,0,0,0.6)', backdropFilter: 'blur(4px)',
        display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 24,
      }}
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="savings-modal-title"
        onClick={e => e.stopPropagation()}
        style={{
          background: '#0f172a', border: '1px solid rgba(255,255,255,0.08)',
          borderRadius: 20, padding: '24px 28px', maxWidth: 440, width: '100%',
          boxShadow: '0 32px 80px rgba(0,0,0,0.6)',
        }}
      >
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
          <div>
            <div style={{ fontSize: 11, color: '#64748b', marginBottom: 2 }}>{brand}</div>
            <div id="savings-modal-title" style={{ fontSize: 16, fontWeight: 800, color: '#f1f5f9' }}>{offerta}</div>
          </div>
          <button
            onClick={onClose}
            aria-label="Chiudi"
            style={{ background: 'none', border: 'none', color: '#64748b', cursor: 'pointer', fontSize: 20, padding: '4px 8px' }}
          >✕</button>
        </div>

        <ModalRow label={`Consumo annuo`} value={`${consumoNum.toFixed(0).replace('.', ',')} ${unit}`} />
        <ModalRow label={`Prezzo ${brand}`} value={`${prezzoNum.toFixed(4).replace('.', ',')} €/${unit}`} />
        <ModalRow label={`Costo materia`} value={`${materiaCost.toFixed(2).replace('.', ',')} €`} />

        {fissoAnnuale > 0 && (
          <ModalRow
            label={`Quota fissa (${(fissoAnnuale / 12).toFixed(2).replace('.', ',')} €/mese × 12)`}
            value={`${fissoAnnuale.toFixed(2).replace('.', ',')} €`}
          />
        )}

        <div style={{ margin: '12px 0', borderTop: '1px dashed rgba(255,255,255,0.1)' }} />

        <ModalRow label={`Nuova spesa annua`} value={`${annualNum.toFixed(0).replace('.', ',')} €`} highlight />

        {currentNum > 0 && (
          <>
            <ModalRow label={`Spesa attuale`} value={`${currentNum.toFixed(0).replace('.', ',')} €`} />
            <ModalRow
              label={`Risparmio annuo`}
              value={
                savings > 0
                  ? `${savings.toFixed(0).replace('.', ',')} € (-${savingsPct}%)`
                  : `—`
              }
              highlight={savings > 0}
            />
          </>
        )}

        <p style={{ fontSize: 10, color: '#64748b', fontStyle: 'italic', marginTop: 16, lineHeight: 1.5 }}>
          Il calcolo considera solo la componente materia energia (prezzo {unit} + quota fissa).
          Trasporto, oneri di sistema, imposte e IVA restano invariati con qualsiasi fornitore.
        </p>
      </div>
    </div>
  )
}
