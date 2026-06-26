import React, { useState, useEffect } from 'react';
import { formatEuro, savingsToHuman } from '../lib/calc.js';

/**
 * TariffCard v5.1 — responsive, tooltip tecnici, accordion mobile.
 *
 * Desktop: confronto per-riga completo con ℹ️ tooltip
 * Mobile (<640px): card compatta, righe tecniche in accordion,
 *   ℹ️ affianco a "Oneri e Imposte" per spiegarne il significato
 */
function InfoTooltip({ text, label }) {
  const [open, setOpen] = useState(false);
  return (
    <span style={{ position: 'relative', display: 'inline-flex', alignItems: 'center' }}>
      <button
        onClick={(e) => { e.stopPropagation(); setOpen(!open); }}
        onMouseEnter={() => setOpen(true)}
        onMouseLeave={() => setOpen(false)}
        aria-label={label || 'Info'}
        style={{
          background: 'none', border: 'none', cursor: 'pointer', padding: '0 3px',
          fontSize: 13, lineHeight: 1, color: '#64748b', verticalAlign: 'middle',
          opacity: 0.7, transition: 'opacity 0.15s',
        }}
        onMouseOver={e => e.currentTarget.style.opacity = '1'}
        onMouseOut={e => e.currentTarget.style.opacity = '0.7'}
      >
        ℹ️
      </button>
      {open && (
        <span style={{
          position: 'absolute', top: 'calc(100% + 8px)', left: '50%', transform: 'translateX(-50%)',
          padding: '12px 16px', borderRadius: 10,
          background: '#0f172a', border: '1px solid rgba(148,163,184,0.25)',
          color: '#e2e8f0', fontSize: 12, lineHeight: 1.65,
          whiteSpace: 'normal', zIndex: 9999, width: 'max-content', maxWidth: 'min(380px, 85vw)',
          boxShadow: '0 8px 32px rgba(0,0,0,0.7), 0 0 0 1px rgba(148,163,184,0.08)',
          pointerEvents: 'auto',
          textAlign: 'left', fontWeight: 400,
        }}>
          {/* Freccetta verso l'alto */}
          <span style={{
            position: 'absolute', top: -6, left: '50%', transform: 'translateX(-50%)',
            width: 0, height: 0,
            borderLeft: '7px solid transparent',
            borderRight: '7px solid transparent',
            borderBottom: '6px solid rgba(148,163,184,0.25)',
          }} />
          <span style={{
            position: 'absolute', top: -5, left: '50%', transform: 'translateX(-50%)',
            width: 0, height: 0,
            borderLeft: '6px solid transparent',
            borderRight: '6px solid transparent',
            borderBottom: '5px solid #0f172a',
          }} />
          {text}
        </span>
      )}
    </span>
  );
}

export default function TariffCard({
  rank,
  tariff,
  commodity,
  annualCost,
  savings,
  savingsPct,
  breakdown,
  currentSpend,
  hasRealSpend,
  rankBadge,
  rankLabel,
  currentPricePerUnit,
  currentFixedMonthly,
  llmData,
  isAnomalous,
  priceWarning,
}) {
  const [showDetails, setShowDetails] = useState(false);
  const [showTechDetails, setShowTechDetails] = useState(false);
  const [isMobile, setIsMobile] = useState(false);

  useEffect(() => {
    const check = () => setIsMobile(window.innerWidth < 640);
    check();
    window.addEventListener('resize', check);
    return () => window.removeEventListener('resize', check);
  }, []);

  const isLuce = commodity === 'luce';
  const accentColor = isLuce ? '#f59e0b' : '#3b82f6';
  const unit = isLuce ? 'kWh' : 'Smc';
  const indice = isLuce ? 'PUN' : 'PSV';
  const showSavings = hasRealSpend && savings !== null;
  const isPositive = showSavings && savings > 0;
  const monthlyCost = Math.round(annualCost / 12);
  const humanSaves = isPositive ? savingsToHuman(savings) : null;

  // ── Prezzo unità offerta ──────────────────────────────────────
  let newPriceValue, newPriceRaw;
  if (tariff.tariffa === 'Variabile') {
    const spreadVal = tariff.spread != null && tariff.spread > 0
      ? ` + ${tariff.spread.toFixed(3).replace('.', ',')}€`
      : '';
    newPriceValue = `${indice}${spreadVal}`;
    newPriceRaw = null;
  } else {
    const p = isLuce ? tariff['prezzo tot kwh'] : tariff['prezzo tot smc'];
    newPriceRaw = p;
    newPriceValue = p != null ? `${p.toFixed(4).replace('.', ',')}€` : '—';
  }

  const newFixedMonthly = tariff.fixed_fee_monthly
    || (tariff.costo_fisso ? Math.round(tariff.costo_fisso / 12 * 100) / 100 : null);

  // Vantaggi / note / penali
  const vantaggi = tariff.vantaggi || tariff.extra?.vantaggi;
  const note = tariff.note_costi || tariff.extra?.note;
  const penaleRecesso = tariff.extra?.penale_recesso || tariff.penale_recesso;
  const prezzoBloccato = tariff.prezzo_bloccato || tariff.extra?.prezzo_bloccato_mesi;
  const validitaOfferta = tariff.validita_offerta || tariff.extra?.validita_offerta;

  // ── Confronto per-riga ────────────────────────────────────────
  const comparisonRows = [];
  if (hasRealSpend) {
    if (currentPricePerUnit && currentPricePerUnit > 0) {
      const currPriceStr = currentPricePerUnit.toFixed(4).replace('.', ',');
      const newPriceStr = newPriceRaw != null ? newPriceRaw.toFixed(4).replace('.', ',') : newPriceValue;
      const priceIsBetter = newPriceRaw != null && newPriceRaw < currentPricePerUnit;
      comparisonRows.push({
        label: `Prezzo ${unit}`,
        current: `${currPriceStr} €`,
        new: `${newPriceStr} €`,
        better: priceIsBetter,
        detail: isLuce
          ? 'Componente materia energia (PE) — l\'unica voce che cambia tra fornitori'
          : 'Componente materia gas (CMEM)',
        info: `Prezzo della sola energia (${unit}), senza trasporto, oneri o imposte. È l'unica parte su cui i fornitori competono.`,
      });
    }

    if (currentFixedMonthly && currentFixedMonthly > 0 && newFixedMonthly != null) {
      const fixedBetter = newFixedMonthly < currentFixedMonthly;
      comparisonRows.push({
        label: 'Quota fissa',
        current: `${currentFixedMonthly.toFixed(2).replace('.', ',')} €/mese`,
        new: `${newFixedMonthly.toFixed(2).replace('.', ',')} €/mese`,
        better: fixedBetter,
        detail: 'Canone commercializzazione (PCV)',
        info: 'Importo fisso mensile per la gestione del contratto. Anche questa voce cambia tra fornitori.',
      });
    }

    comparisonRows.push({
      label: 'Totale anno',
      current: formatEuro(currentSpend),
      new: formatEuro(annualCost),
      better: isPositive,
      detail: 'Include IVA 10%, trasporto, oneri di sistema, accise',
      info: isLuce
        ? 'Include: materia energia + trasporto (0,0089 €/kWh) + oneri sistema Asos/Arim (0,038 €/kWh) + accise (0,0227 €/kWh) + quota potenza (21,48 €/kW) + quota fissa reti (24 €/anno) + IVA 10%. Questi costi regolati ARERA sono UGUALI per ogni fornitore.'
        : 'Include: materia gas + trasporto (0,15 €/Smc) + oneri sistema (0,03 €/Smc) + accise (0,15 €/Smc) + quota fissa reti (23 €/anno) + IVA (10% fino a 480 Smc, 22% oltre). I costi regolati ARERA sono UGUALI per ogni fornitore.',
      highlight: true,
    });
  }

  // ── Barra proporzionale ───────────────────────────────────────
  const maxBar = Math.max(currentSpend || 0, annualCost || 0);
  const barPctNew = maxBar > 0 ? (annualCost / maxBar) * 100 : 100;

  // ── Oneri regolati info (per tooltip rapido) ──────────────────
  const regulatedInfo = isLuce
    ? 'Oneri e Imposte includono: trasporto gestione contatore, oneri di sistema (Asos per rinnovabili + Arim), accise erariali, IVA 10% e Canone RAI (~90€/anno). Sono stabiliti da ARERA e sono identici per qualsiasi fornitore. Cambia solo la materia energia (prezzo kWh + quota fissa).'
    : 'Oneri e Imposte includono: trasporto e distribuzione, oneri di sistema, accise e IVA (10% sotto 480 Smc/anno, 22% oltre). Sono stabiliti da ARERA e identici per ogni fornitore. Cambia solo la materia gas (prezzo Smc + quota fissa).';

  return (
    <div
      className={`glass-card${rankBadge === '🥇' ? ' best-offer' : ''}`}
      style={{ padding: isMobile ? '14px 16px' : '20px 24px' }}
    >
      {/* ── Header: brand + rank badge ───────────────────────────── */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 14 }}>
        {tariff.logo ? (
          <img src={tariff.logo} alt={tariff.brand}
            style={{ width: 36, height: 28, objectFit: 'contain', borderRadius: 4, flexShrink: 0 }}
            onError={e => { e.target.style.display = 'none'; }}
          />
        ) : (
          <div style={{
            width: 36, height: 28, borderRadius: 4, flexShrink: 0,
            background: `${accentColor}18`, display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: 14, fontWeight: 900, color: accentColor,
          }}>
            {tariff.brand?.charAt(0) || '?'}
          </div>
        )}
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 14, fontWeight: 800, color: '#f1f5f9', lineHeight: 1.3, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
            {tariff.brand} {tariff.offerta}
          </div>
          {tariff.tariffa && (
            <span style={{
              fontSize: 10, fontWeight: 600, padding: '1px 8px', borderRadius: 4,
              background: tariff.tariffa === 'Variabile' ? 'rgba(251,191,36,0.12)' : 'rgba(16,185,129,0.12)',
              color: tariff.tariffa === 'Variabile' ? '#fbbf24' : '#6ee7b7',
            }}>
              {tariff.tariffa === 'Variabile' ? 'Variabile' : 'Prezzo Fisso'}
            </span>
          )}
        </div>
        {rankBadge && (
          <div style={{ textAlign: 'center', flexShrink: 0 }}>
            <div style={{ fontSize: 22, lineHeight: 1 }}>{rankBadge}</div>
            {rankLabel && (
              <div style={{ fontSize: 9, color: '#94a3b8', fontWeight: 600, marginTop: 2 }}>{rankLabel}</div>
            )}
          </div>
        )}
      </div>

      {/* ── Warning prezzo anomalo ───────────────────────────────── */}
      {(isAnomalous || priceWarning) && (
        <div style={{
          padding: '8px 12px', borderRadius: 8, marginBottom: 14,
          background: 'rgba(251,191,36,0.08)', border: '1px solid rgba(251,191,36,0.2)',
          fontSize: 11, color: '#fbbf24', lineHeight: 1.5,
        }}>
          ⚠️ {priceWarning || 'Prezzo molto inferiore alla media di mercato. Potrebbe essere un\'offerta promozionale o contenere condizioni particolari.'}
          {validitaOfferta ? <b> Valida fino al {validitaOfferta}.</b> : ' Verifica sempre il contratto prima di sottoscrivere.'}
        </div>
      )}

      {/* ── Barra proporzionale ──────────────────────────────────── */}
      {hasRealSpend && currentSpend > 0 && (
        <div style={{ marginBottom: 16 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6, fontSize: 10 }}>
            <span style={{ color: '#64748b' }}>Ora</span>
            <span style={{ color: '#64748b' }}>Con questa offerta</span>
          </div>
          <div style={{
            position: 'relative', height: 10, borderRadius: 5,
            background: 'rgba(248,113,113,0.3)', overflow: 'hidden',
          }}>
            <div style={{
              position: 'absolute', left: 0, top: 0, height: '100%',
              width: `${Math.min(barPctNew, 100)}%`,
              background: isPositive
                ? 'linear-gradient(90deg, #10b981, #34d399)'
                : 'linear-gradient(90deg, #f59e0b, #fbbf24)',
              borderRadius: 5, transition: 'width 0.5s ease',
            }} />
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 6, fontSize: 13 }}>
            <span style={{ fontWeight: 800, color: '#f87171' }}>{formatEuro(currentSpend)}</span>
            <span style={{ fontWeight: 800, color: isPositive ? '#10b981' : '#f1f5f9' }}>{formatEuro(annualCost)}</span>
          </div>
          {isPositive && (
            <div style={{ textAlign: 'center', marginTop: 4 }}>
              <span style={{
                fontSize: 13, fontWeight: 700, color: '#10b981',
                background: 'rgba(16,185,129,0.1)', padding: '3px 14px', borderRadius: 20,
              }}>
                -{formatEuro(savings)} ({savingsPct?.toFixed(0)}%)
              </span>
            </div>
          )}
        </div>
      )}

      {/* ── Confronto per-riga (desktop sempre visibile; mobile in accordion) ── */}
      {comparisonRows.length > 0 && (
        <>
          {isMobile ? (
            /* MOBILE: mostra solo totale + accordion per i dettagli */
            <div style={{ marginBottom: 14 }}>
              <div style={{
                display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                padding: '10px 14px', borderRadius: 8,
                background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.05)',
              }}>
                <div>
                  <span style={{ fontSize: 13, color: '#f1f5f9', fontWeight: 700 }}>
                    Totale anno
                  </span>
                  <div style={{ fontSize: 9, color: '#64748b', marginTop: 2 }}>
                    Include IVA, trasporto, oneri, imposte{' '}
                    <InfoTooltip text={regulatedInfo} label="Cosa include il totale?" />
                  </div>
                </div>
                <div style={{ textAlign: 'right' }}>
                  <div style={{ fontSize: 14, fontWeight: 800, color: isPositive ? '#10b981' : '#f1f5f9' }}>
                    {formatEuro(annualCost)}
                  </div>
                  {isPositive && (
                    <div style={{ fontSize: 11, color: '#6ee7b7' }}>
                      -{formatEuro(savings)}/anno
                    </div>
                  )}
                </div>
              </div>

              {/* Bottone per espandere i dettagli tecnici */}
              <button
                onClick={() => setShowTechDetails(!showTechDetails)}
                style={{
                  width: '100%', marginTop: 6, padding: '8px 12px', borderRadius: 8,
                  background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)',
                  color: '#64748b', cursor: 'pointer', fontSize: 11,
                  display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 4,
                }}
              >
                <span style={{ transform: showTechDetails ? 'rotate(90deg)' : 'none', transition: 'transform 0.15s', display: 'inline-block' }}>▶</span>
                {showTechDetails ? 'Nascondi dettagli tecnici' : 'Confronto per-riga e dettagli'}
              </button>

              {showTechDetails && (
                <div style={{
                  marginTop: 6, borderRadius: 8,
                  background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)',
                }}>
                  <div style={{
                    display: 'grid', gridTemplateColumns: '1fr auto auto',
                    gap: 6, padding: '6px 12px',
                    background: 'rgba(255,255,255,0.03)', borderBottom: '1px solid rgba(255,255,255,0.04)',
                    fontSize: 9, color: '#64748b', textTransform: 'uppercase', fontWeight: 600,
                  }}>
                    <div></div>
                    <div style={{ textAlign: 'right' }}>Ora</div>
                    <div style={{ textAlign: 'right' }}>Offerta</div>
                  </div>
                  {comparisonRows.map((row, i) => (
                    <div key={i} style={{
                      display: 'grid', gridTemplateColumns: '1fr auto auto',
                      gap: 6, padding: row.highlight ? '8px 12px' : '6px 12px',
                      alignItems: 'center',
                      borderBottom: i < comparisonRows.length - 1 ? '1px solid rgba(255,255,255,0.03)' : 'none',
                      background: row.highlight ? 'rgba(255,255,255,0.03)' : 'transparent',
                    }}>
                      <div>
                        <span style={{
                          fontSize: 11, color: row.highlight ? '#f1f5f9' : '#94a3b8',
                          fontWeight: row.highlight ? 700 : 500,
                          display: 'flex', alignItems: 'center', gap: 4,
                        }}>
                          {row.label}
                          {row.info && <InfoTooltip text={row.info} label={row.label} />}
                        </span>
                      </div>
                      <div style={{ fontSize: 11, fontWeight: 600, color: '#94a3b8', textAlign: 'right' }}>
                        {row.current}
                      </div>
                      <div style={{
                        fontSize: 11, fontWeight: row.highlight ? 700 : 600,
                        color: row.highlight ? (isPositive ? '#10b981' : '#f1f5f9') : '#f1f5f9',
                        textAlign: 'right',
                      }}>
                        {row.new}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          ) : (
            /* DESKTOP: tabella completa */
            <div style={{
              marginBottom: 16, borderRadius: 10,
              background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)',
            }}>
              <div style={{
                display: 'grid', gridTemplateColumns: '1fr auto auto',
                gap: 8, padding: '8px 14px',
                background: 'rgba(255,255,255,0.03)', borderBottom: '1px solid rgba(255,255,255,0.04)',
                fontSize: 10, color: '#64748b', textTransform: 'uppercase', fontWeight: 600,
              }}>
                <div></div>
                <div style={{ textAlign: 'right' }}>Ora</div>
                <div style={{ textAlign: 'right' }}>Con questa offerta</div>
              </div>
              {comparisonRows.map((row, i) => (
                <div key={i} style={{
                  display: 'grid', gridTemplateColumns: '1fr auto auto',
                  gap: 8, padding: row.highlight ? '10px 14px' : '8px 14px',
                  alignItems: 'center',
                  borderBottom: i < comparisonRows.length - 1 ? '1px solid rgba(255,255,255,0.03)' : 'none',
                  background: row.highlight ? 'rgba(255,255,255,0.03)' : 'transparent',
                }}>
                  <div>
                    <span style={{
                      fontSize: row.highlight ? 13 : 12,
                      color: row.highlight ? '#f1f5f9' : '#94a3b8',
                      fontWeight: row.highlight ? 700 : 500,
                      display: 'inline-flex', alignItems: 'center', gap: 4,
                    }}>
                      {row.label}
                      {row.info && <InfoTooltip text={row.info} label={row.label} />}
                    </span>
                    {row.detail && (
                      <div style={{ fontSize: 9, color: '#64748b', lineHeight: 1.4 }}>{row.detail}</div>
                    )}
                  </div>
                  <div style={{
                    fontSize: row.highlight ? 14 : 12,
                    fontWeight: row.highlight ? 700 : 600,
                    color: row.highlight ? '#f87171' : '#94a3b8',
                    textAlign: 'right', fontVariantNumeric: 'tabular-nums',
                  }}>
                    {row.current}
                  </div>
                  <div style={{
                    fontSize: row.highlight ? 14 : 12,
                    fontWeight: row.highlight ? 800 : 600,
                    color: row.highlight ? (isPositive ? '#10b981' : '#f1f5f9') : '#f1f5f9',
                    textAlign: 'right', fontVariantNumeric: 'tabular-nums',
                    display: 'flex', alignItems: 'center', gap: 4, justifyContent: 'flex-end',
                  }}>
                    {row.new}
                    {row.better !== undefined && (
                      <span style={{ fontSize: 12, flexShrink: 0 }}>
                        {row.better ? '✅' : '↗'}
                      </span>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </>
      )}

      {/* ── Risparmio mensile + CTA ──────────────────────────────── */}
      <div style={{
        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
        flexWrap: 'wrap', gap: 10,
      }}>
        <div>
          <div style={{ fontSize: 22, fontWeight: 900, color: rankBadge === '🥇' ? '#10b981' : '#f1f5f9', fontVariantNumeric: 'tabular-nums' }}>
            {formatEuro(monthlyCost)}<span style={{ fontSize: 11, color: '#64748b' }}>/mese</span>
          </div>
          {isPositive && humanSaves && (
            <div style={{ fontSize: 11, color: '#6ee7b7', marginTop: 2 }}>
              ≈ {formatEuro(humanSaves.mese)}/mese in meno
              <span style={{ color: '#64748b' }}> · {humanSaves.giorno}€/giorno</span>
            </div>
          )}
          {!hasRealSpend && (
            <div style={{ fontSize: 11, color: '#64748b', fontStyle: 'italic' }}>
              Inserisci la tua spesa annua per vedere il risparmio
            </div>
          )}
        </div>
        <a
          href={tariff.affiliate_url || tariff.subscription_url || `/sottoscrizione?tariff=${encodeURIComponent(tariff.id)}&supplier=${encodeURIComponent(tariff.brand)}&name=${encodeURIComponent(tariff.offerta)}&commodity=${commodity}&annualCost=${annualCost}`}
          target={tariff.affiliate_url ? "_blank" : undefined}
          rel={tariff.affiliate_url ? "nofollow noopener" : undefined}
          className="btn btn-electric"
          style={{ fontSize: 12, padding: '9px 22px', flexShrink: 0 }}
        >
          {tariff.affiliate_url ? '🔗 Attiva Online' : 'Attiva Online'}
        </a>
      </div>

      {/* ── Tag vincoli offerta ───────────────────────────────────── */}
      {(prezzoBloccato || penaleRecesso || validitaOfferta) && (
        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 12, paddingTop: 12, borderTop: '1px solid rgba(255,255,255,0.05)' }}>
          {prezzoBloccato && (
            <span style={{
              fontSize: 10, padding: '2px 8px', borderRadius: 4,
              background: 'rgba(16,185,129,0.08)', color: '#6ee7b7',
              border: '1px solid rgba(16,185,129,0.15)',
            }}>
              🔒 Prezzo bloccato {prezzoBloccato} mesi
            </span>
          )}
          {validitaOfferta && !prezzoBloccato && (
            <span style={{
              fontSize: 10, padding: '2px 8px', borderRadius: 4,
              background: 'rgba(148,163,184,0.06)', color: '#94a3b8',
              border: '1px solid rgba(148,163,184,0.1)',
            }}>
              📅 Valida fino al {validitaOfferta}
            </span>
          )}
          {penaleRecesso && (
            <span style={{
              fontSize: 10, padding: '2px 8px', borderRadius: 4,
              background: 'rgba(251,191,36,0.06)', color: '#fbbf24',
              border: '1px solid rgba(251,191,36,0.12)',
            }}>
              ⚠️ Penale recesso: {typeof penaleRecesso === 'object' ? JSON.stringify(penaleRecesso) : penaleRecesso}
            </span>
          )}
        </div>
      )}

      {/* ── Accordion dettagli tariffa ────────────────────────────── */}
      <div style={{ marginTop: 10 }}>
        <button
          onClick={() => setShowDetails(!showDetails)}
          style={{
            background: 'none', border: 'none', color: '#64748b', cursor: 'pointer',
            fontSize: 11, padding: '4px 0', display: 'flex', alignItems: 'center', gap: 4,
          }}
        >
          <span style={{ transform: showDetails ? 'rotate(90deg)' : 'none', transition: 'transform 0.15s', display: 'inline-block' }}>▶</span>
          {showDetails ? 'Nascondi dettagli tariffa' : 'Dettagli tariffa e calcolo'}
        </button>
        {showDetails && (
          <div style={{
            marginTop: 8, padding: '14px 16px', borderRadius: 8,
            background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)',
            fontSize: 12, lineHeight: 1.8,
          }}>
            {[
              { label: 'Tipologia contratto', value: tariff.tariffa === 'Variabile' ? 'Prezzo Variabile' : 'Prezzo Fisso' },
              { label: `Prezzo ${unit}`, value: newPriceValue },
              newFixedMonthly != null ? { label: 'Quota fissa', value: `${newFixedMonthly.toFixed(2).replace('.', ',')} €/mese` } : null,
              tariff.pagamento ? { label: 'Modalità pagamento', value: tariff.pagamento } : null,
              prezzoBloccato && tariff.tariffa === 'Fissa' ? { label: 'Prezzo bloccato', value: `${prezzoBloccato} mesi` } : null,
              vantaggi ? { label: 'Vantaggi', value: vantaggi, highlight: true } : null,
              note ? { label: 'Note', value: note, warn: true } : null,
              // Riga riepilogo costi regolati
              hasRealSpend ? {
                label: 'Oneri e Imposte',
                value: 'Stabiliti da ARERA',
                info: regulatedInfo,
              } : null,
            ].filter(Boolean).map((row, i) => (
              <div key={i} style={{
                display: 'flex', justifyContent: 'space-between', alignItems: 'baseline',
                padding: '4px 0', borderBottom: '1px solid rgba(255,255,255,0.03)',
              }}>
                <span style={{ color: '#64748b', flexShrink: 0, marginRight: 16, display: 'flex', alignItems: 'center', gap: 4 }}>
                  {row.label}
                  {row.info && <InfoTooltip text={row.info} label={row.label} />}
                </span>
                <span style={{
                  color: row.highlight ? '#6ee7b7' : row.warn ? '#fbbf24' : '#94a3b8',
                  fontWeight: 500, textAlign: 'right',
                }}>
                  {row.value}
                </span>
              </div>
            ))}

            {/* Breakdown testuale */}
            {breakdown && breakdown.length > 0 && (
              <div style={{ marginTop: 10, paddingTop: 10, borderTop: '1px solid rgba(255,255,255,0.05)' }}>
                {breakdown.slice(0, 4).map((b, i) => (
                  <div key={i} style={{ fontSize: 11, color: '#94a3b8', lineHeight: 1.7 }}>
                    • {b}
                  </div>
                ))}
              </div>
            )}

            {/* Nota costi regolati */}
            {hasRealSpend && (
              <div style={{
                marginTop: 10, paddingTop: 10,
                borderTop: '1px solid rgba(255,255,255,0.05)',
                fontSize: 10, color: '#64748b', fontStyle: 'italic', lineHeight: 1.6,
              }}>
                💡 <b style={{ color: '#94a3b8' }}>Trasporto, oneri di sistema e imposte</b> restano uguali con qualsiasi fornitore.
                La componente che cambia è la <b style={{ color: '#94a3b8' }}>materia energia</b> (prezzo {unit} + quota fissa commercializzazione).
                {' '}<InfoTooltip text={regulatedInfo} label="Costi regolati ARERA" />
                {llmData?.tipo_tariffa === 'variabile' && (
                  <span style={{ display: 'block', marginTop: 4, color: '#fbbf24' }}>
                    ⚡ La tua tariffa attuale è variabile: la spesa mostrata è una proiezione basata sul {indice} attuale e può cambiare nel tempo.
                    {isLuce && (
                      <span style={{ display: 'block', marginTop: 2, color: '#64748b', fontSize: 10 }}>
                        Prezzo = (PUN + spread) × 1,102 (perdite rete BT ~10,2% ARERA)
                      </span>
                    )}
                  </span>
                )}
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
