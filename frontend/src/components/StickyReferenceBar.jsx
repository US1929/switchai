import React from 'react';
import { formatEuro } from '../lib/calc.js';

/**
 * Barra sticky sempre visibile durante lo scroll delle offerte.
 * Mostra i 3 numeri chiave della bolletta attuale:
 * - Prezzo energia (€/kWh o €/Smc)
 * - Quota fissa (€/mese)
 * - Spesa annua (€)
 *
 * Per tariffe variabili, il prezzo energia è calcolato dinamicamente
 * con PUN/PSV live + spread dell'utente.
 * Per la Luce si applica il fattore perdite di rete ARERA (~10,2%).
 */
export default function StickyReferenceBar({
  commodity,
  currentPricePerUnit,
  currentFixedMonthly,
  currentAnnualSpend,
  llmData,
  punDisplay,
  psvDisplay,
}) {
  if (!currentAnnualSpend || currentAnnualSpend <= 0) return null;

  const isLuce = commodity === 'luce';
  const unit = isLuce ? 'kWh' : 'Smc';
  const accentColor = isLuce ? '#f59e0b' : '#3b82f6';
  const isVariable = (llmData?.tipo_tariffa || '').toLowerCase() === 'variabile';
  const spread = llmData?.spread ? parseFloat(llmData.spread) : null;

  return (
    <div
      style={{
        position: 'sticky', top: 0, zIndex: 50,
        background: 'rgba(15,23,42,0.95)', backdropFilter: 'blur(16px)',
        borderBottom: `2px solid ${accentColor}30`,
        padding: '12px 24px', marginBottom: 20, borderRadius: '0 0 14px 14px',
      }}
    >
      <div style={{
        maxWidth: 880, margin: '0 auto',
        display: 'flex', alignItems: 'center', gap: 24, flexWrap: 'wrap',
      }}>
        {/* Label */}
        <div style={{ flexShrink: 0 }}>
          <div style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 2 }}>
            La tua bolletta attuale
          </div>
          <div style={{ fontSize: 11, color: '#94a3b8' }}>
            {isVariable ? 'Tariffa variabile' : 'Tariffa attuale'}
            {isVariable && spread ? (
              <span style={{ color: '#64748b' }}>
                {' '}({isLuce ? 'PUN' : 'PSV'} + {spread.toFixed(3).replace('.', ',')} €)
              </span>
            ) : null}
          </div>
        </div>

        {/* Separator */}
        <div style={{ width: 1, height: 36, background: 'rgba(255,255,255,0.06)', flexShrink: 0 }} />

        {/* Prezzo energia */}
        <div style={{ textAlign: 'center', flexShrink: 0 }}>
          <div style={{ fontSize: 10, color: '#64748b', marginBottom: 2 }}>Prezzo {unit}</div>
          <div style={{ fontSize: 15, fontWeight: 800, color: '#f1f5f9' }}>
            {currentPricePerUnit ? currentPricePerUnit.toFixed(4).replace('.', ',') : '—'} €
          </div>
          <div style={{ fontSize: 10, color: '#64748b' }}>
            /{unit}
            {isVariable && punDisplay ? (
              <span style={{ color: '#f59e0b' }}> · {isLuce ? 'PUN' : 'PSV'} {punDisplay || psvDisplay}</span>
            ) : null}
            {isVariable && isLuce ? (
              <span style={{ display: 'block', color: '#64748b', fontSize: 9, marginTop: 1 }}>
                (PUN + spread) × perdite rete ~10,2% (ARERA)
              </span>
            ) : null}
            {isVariable && !isLuce ? (
              <span style={{ display: 'block', color: '#64748b', fontSize: 9, marginTop: 1 }}>
                PSV + spread (nessuna perdita rete su gas)
              </span>
            ) : null}
          </div>
        </div>

        {/* Quota fissa */}
        <div style={{ textAlign: 'center', flexShrink: 0 }}>
          <div style={{ fontSize: 10, color: '#64748b', marginBottom: 2 }}>Quota fissa</div>
          <div style={{ fontSize: 15, fontWeight: 800, color: '#f1f5f9' }}>
            {currentFixedMonthly.toFixed(2).replace('.', ',')} €
          </div>
          <div style={{ fontSize: 10, color: '#64748b' }}>/mese</div>
        </div>

        {/* Spesa annua */}
        <div style={{ textAlign: 'center', flexShrink: 0, marginLeft: 'auto' }}>
          <div style={{ fontSize: 10, color: '#64748b', marginBottom: 2, textTransform: 'uppercase' }}>Spesa annua</div>
          <div style={{ fontSize: 20, fontWeight: 900, color: '#f87171', fontVariantNumeric: 'tabular-nums' }}>
            {formatEuro(currentAnnualSpend)}
          </div>
          <div style={{ fontSize: 10, color: '#64748b' }}>
            ≈ {formatEuro(Math.round(currentAnnualSpend / 12))}/mese
          </div>
        </div>

        {/* Variable tariff note */}
        {isVariable && (
          <div style={{
            flexBasis: '100%', fontSize: 10, color: '#64748b',
            fontStyle: 'italic', textAlign: 'center',
            paddingTop: 4, borderTop: '1px solid rgba(255,255,255,0.04)',
          }}>
            ⚡ La tua tariffa è variabile: la spesa mostrata è una proiezione basata sul {isLuce ? 'PUN' : 'PSV'} attuale ({punEurKwh ? (punEurKwh * 1000).toFixed(1) + ' €/MWh' : '...'}).
            Il confronto con le offerte usa lo STESSO {isLuce ? 'PUN' : 'PSV'} per entrambi i lati (metodo ARERA).
            Il risparmio mostrato riflette solo le differenze contrattuali (spread + quota fissa), non le oscillazioni di mercato.
          </div>
        )}
      </div>
    </div>
  );
}
