import React, { useState } from 'react';

export default function BollettaGuide({ tipo = 'luce', focus = 'consumo' }) {
  const [open, setOpen] = useState(false);
  const isGas = tipo === 'gas';
  const focusLabel = focus === 'spesa' ? 'Spesa annuale (€)' : (isGas ? 'Consumo annuale (Smc)' : 'Consumo annuale (kWh)');

  return (
    <>
      {/* Bottone "?" accanto al label */}
      <button
        onClick={() => setOpen(true)}
        title={`Dove trovo ${focusLabel} nella bolletta?`}
        style={{
          width: 20, height: 20, borderRadius: '50%',
          background: 'rgba(255,255,255,0.06)', border: '1px solid rgba(255,255,255,0.12)',
          color: '#94a3b8', fontSize: 12, fontWeight: 700, cursor: 'pointer',
          display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
          flexShrink: 0, lineHeight: 1, marginLeft: 6,
        }}
      >
        ?
      </button>

      {/* Modale */}
      {open && (
        <div
          onClick={() => setOpen(false)}
          style={{
            position: 'fixed', inset: 0, zIndex: 1000,
            background: 'rgba(0,0,0,0.7)', backdropFilter: 'blur(4px)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            padding: 20,
          }}
        >
          <div
            onClick={e => e.stopPropagation()}
            style={{
              background: '#111620', border: '1px solid rgba(255,255,255,0.08)',
              borderRadius: 16, maxWidth: 780, width: '100%', maxHeight: '90vh',
              overflow: 'auto', padding: 24, position: 'relative',
            }}
          >
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
              <h3 style={{ fontSize: 16, fontWeight: 700, color: '#f1f5f9' }}>
                📄 Dove trovo "{focusLabel}" nella bolletta ARERA 2.0
              </h3>
              <button
                onClick={() => setOpen(false)}
                style={{
                  width: 32, height: 32, borderRadius: '50%',
                  background: 'rgba(255,255,255,0.06)', border: '1px solid rgba(255,255,255,0.12)',
                  color: '#94a3b8', fontSize: 18, cursor: 'pointer',
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                }}
              >
                ✕
              </button>
            </div>

            {/* Contenuto a due colonne */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
              <BollettaMock isGas={isGas} highlight={focus} />
              <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                <Callout
                  numero="1" color="amber" active={focus === 'spesa'}
                  titolo="Spesa annuale (€)"
                  testo={<>Cerca <strong>«Spesa totale»</strong> nel frontespizio e moltiplicala per il numero di bollette all'anno:<br/>• Trimestrale → ×4<br/>• Bimestrale → ×6<br/>• Mensile → ×12<br/><br/>Alcuni fornitori riportano già la <strong>«Spesa annua stimata»</strong> — usala direttamente.</>}
                />
                <Callout
                  numero="2" color="blue" active={focus === 'consumo'}
                  titolo={isGas ? 'Consumo annuale (Smc)' : 'Consumo annuale (kWh)'}
                  testo={<>Il campo <strong>«Consumo annuo stimato»</strong> è obbligatorio per legge ARERA nella bolletta 2.0.<br/><br/>Lo trovi nella sezione <strong>Consumi</strong>, già normalizzato su 12 mesi — usalo direttamente senza fare calcoli.{isGas && <><br/><br/>Per il gas l'unità è <strong>Smc</strong> (metri cubi standard).</>}</>}
                />
                <Tip testo={isGas
                  ? "Hai una fornitura dual (luce + gas)? Ripeti la lettura per entrambe le sezioni della bolletta — sono sempre separate."
                  : "Bolletta bimestrale? Non usare il «consumo nel periodo» — prendi solo il campo «consumo annuo stimato» che è già su 12 mesi."}
                />
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

function BollettaMock({ isGas, highlight }) {
  return (
    <div style={{ background: 'rgba(255,255,255,0.04)', border: '1px solid rgba(241,245,249,0.08)', borderRadius: 10, overflow: 'hidden', fontSize: 12 }}>
      <div style={{ background: 'rgba(255,255,255,0.05)', padding: '10px 14px', borderBottom: '1px solid rgba(241,245,249,0.07)', display: 'flex', justifyContent: 'space-between' }}>
        <div>
          <div style={{ fontWeight: 600, color: '#f1f5f9', fontSize: 13 }}>{isGas ? 'GasPrime S.p.A.' : 'LuceVera S.p.A.'}</div>
          <div style={{ color: '#64748b', fontSize: 11 }}>Fornitura: {isGas ? 'Gas · PDR: IT-xxx' : 'Luce · POD: IT001E000'}</div>
        </div>
        <div style={{ textAlign: 'right' }}>
          <div style={{ color: '#64748b', fontSize: 11 }}>Periodo</div>
          <div style={{ color: '#f1f5f9', fontWeight: 500 }}>01/01 – 31/03/2026</div>
        </div>
      </div>

      {/* Sezione Spesa */}
      <div style={{ padding: '10px 14px', borderBottom: '1px solid rgba(241,245,249,0.07)', opacity: highlight === 'spesa' ? 1 : 0.25, background: highlight === 'spesa' ? 'rgba(245,158,11,0.05)' : 'transparent', transition: 'all 0.3s' }}>
        <div style={labelStyle}>Spesa per la fornitura</div>
        <BillRow label="Materia energia" value={isGas ? '€ 112,40' : '€ 98,20'} />
        <BillRow label="Trasporto e gestione" value="€ 42,10" />
        <BillRow label="Oneri di sistema" value="€ 18,80" />
        <BillRow label="Imposte" value="€ 27,30" />
        <div style={{ marginTop: 6, paddingTop: 6, borderTop: '1px solid rgba(241,245,249,0.07)' }}>
          <HighlightRow numero="1" color="amber" label="Spesa totale trimestre" value={isGas ? '€ 200,60' : '€ 186,40'} pulse={highlight === 'spesa'} />
        </div>
      </div>

      {/* Sezione Consumi */}
      <div style={{ padding: '10px 14px', opacity: highlight === 'consumo' ? 1 : 0.25, background: highlight === 'consumo' ? 'rgba(59,130,246,0.05)' : 'transparent', transition: 'all 0.3s' }}>
        <div style={labelStyle}>Consumi <span style={{ fontSize: 10, background: 'rgba(255,255,255,0.05)', borderRadius: 4, padding: '1px 5px', color: '#64748b', marginLeft: 4 }}>Dati ARERA</span></div>
        <BillRow label="Consumo nel periodo" value={isGas ? '180 Smc' : '820 kWh'} />
        <BillRow label="Lettura" value="Effettiva" />
        <div style={{ marginTop: 6, paddingTop: 6, borderTop: '1px solid rgba(241,245,249,0.07)' }}>
          <HighlightRow numero="2" color="blue" label="Consumo annuo stimato" value={isGas ? '720 Smc' : '3.280 kWh'} pulse={highlight === 'consumo'} />
        </div>
      </div>
    </div>
  );
}

const labelStyle = { fontSize: 10, color: '#475569', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: 6 };

function BillRow({ label, value }) {
  return <div style={{ display: 'flex', justifyContent: 'space-between', padding: '2px 0', color: '#94a3b8', fontSize: 12 }}>
    <span>{label}</span>
    <span style={{ fontWeight: 500, color: '#cbd5e1' }}>{value}</span>
  </div>;
}

function HighlightRow({ numero, color, label, value, pulse }) {
  const c = color === 'amber'
    ? { bg: 'rgba(186,117,23,0.25)', border: 'rgba(245,158,11,0.7)', badge: '#f59e0b', text: '#fef3c7', glow: '0 0 12px rgba(245,158,11,0.3)' }
    : { bg: 'rgba(24,95,165,0.25)', border: 'rgba(59,130,246,0.7)', badge: '#3b82f6', text: '#dbeafe', glow: '0 0 12px rgba(59,130,246,0.3)' };
  return (
    <div style={{
      display: 'flex', justifyContent: 'space-between', alignItems: 'center',
      background: c.bg, border: `2px solid ${c.border}`,
      borderRadius: 6, padding: '8px 10px',
      boxShadow: pulse ? c.glow : 'none',
      animation: pulse ? 'pulse 2s cubic-bezier(.4,0,.6,1) infinite' : 'none',
      transform: pulse ? 'scale(1.02)' : 'none',
    }}>
      <span style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
        <span style={{ width: 18, height: 18, borderRadius: '50%', background: c.badge, color: '#fff', fontSize: 10, fontWeight: 600, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>{numero}</span>
        <strong style={{ color: c.text, fontSize: 12 }}>{label}</strong>
      </span>
      <span style={{ color: c.text, fontWeight: 600, fontSize: 12 }}>{value}</span>
    </div>
  );
}

function Callout({ numero, color, active, titolo, testo }) {
  const c = color === 'amber'
    ? { bg: 'rgba(186,117,23,0.08)', border: 'rgba(186,117,23,0.2)', badge: '#BA7517', title: '#fcd34d', body: '#d97706' }
    : { bg: 'rgba(24,95,165,0.08)', border: 'rgba(24,95,165,0.2)', badge: '#185FA5', title: '#93c5fd', body: '#60a5fa' };
  return (
    <div style={{
      background: c.bg, border: `2px solid ${active ? c.border : 'transparent'}`,
      borderRadius: 10, padding: '12px 14px',
      transition: 'border-color 0.3s',
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
        <span style={{ width: 20, height: 20, borderRadius: '50%', background: c.badge, color: '#fff', fontSize: 11, fontWeight: 600, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>{numero}</span>
        <strong style={{ color: c.title, fontSize: 13 }}>{titolo}</strong>
        {active && <span style={{ fontSize: 10, background: c.badge, color: '#fff', borderRadius: 4, padding: '2px 6px', marginLeft: 4 }}>Stai cercando questo</span>}
      </div>
      <div style={{ fontSize: 12, color: c.body, lineHeight: 1.6 }}>{testo}</div>
    </div>
  );
}

function Tip({ testo }) {
  return (
    <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(241,245,249,0.07)', borderRadius: 8, padding: '10px 12px', fontSize: 12, color: '#64748b', lineHeight: 1.5 }}>
      💡 {testo}
    </div>
  );
}
