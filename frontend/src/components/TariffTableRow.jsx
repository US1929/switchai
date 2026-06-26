import { useState, useEffect, useRef } from 'react';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';
import { formatEuro, savingsToHuman } from '../lib/calc.js';
import { LUCE, GAS } from '../lib/constants.js';
import { MESI, CONSUMO_LUCE, CONSUMO_GAS } from '../lib/seasonal.js';
import SavingsBreakdownModal from './SavingsBreakdownModal.jsx';

function parsePrice(v) {
  if (v == null) return null;
  if (typeof v === 'number') return v;
  return parseFloat(v.replace(',', '.'));
}

function InfoTooltip({ text, label }) {
  const [open, setOpen] = useState(false);
  const btnRef = useRef(null);

  useEffect(() => {
    if (!open) return;
    const handler = (e) => {
      if (btnRef.current && !btnRef.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  return (
    <span style={{ position: 'relative', display: 'inline-flex', alignItems: 'center' }}>
      <button
        ref={btnRef}
        onClick={(e) => { e.stopPropagation(); setOpen(!open); }}
        aria-label={label || 'Info'}
        style={{
          background: 'none', border: 'none', cursor: 'pointer', padding: '0 3px',
          fontSize: 12, lineHeight: 1, color: '#64748b', opacity: 0.7,
        }}
      >ℹ️</button>
      {open && <TooltipPopup text={text} btnRef={btnRef} onClose={() => setOpen(false)} />}
    </span>
  );
}

function TooltipPopup({ text, btnRef }) {
  const [pos, setPos] = useState({ top: 0, left: 0 });

  useEffect(() => {
    if (!btnRef.current) return;
    const rect = btnRef.current.getBoundingClientRect();
    setPos({
      top: rect.bottom + 6,
      left: Math.min(rect.left + rect.width / 2, window.innerWidth - 300),
    });
  }, [btnRef]);

  return (
    <span style={{
      position: 'fixed', top: pos.top, left: pos.left,
      transform: 'translateX(-50%)',
      padding: '10px 14px', borderRadius: 8, zIndex: 99999,
      background: '#0f172a', border: '1px solid rgba(148,163,184,0.25)',
      color: '#e2e8f0', fontSize: 11, lineHeight: 1.6,
      whiteSpace: 'normal', width: 280, maxWidth: '85vw',
      boxShadow: '0 8px 32px rgba(0,0,0,0.7)',
      pointerEvents: 'auto', textAlign: 'left', fontWeight: 400,
    }}>
      {text}
    </span>
  );
}

export default function TariffTableRow({
  item,
  rank,
  commodity,
  currentSpend,
  hasRealSpend,
  currentPricePerUnit,
  currentFixedMonthly,
  llmData,
  selected,
  onToggleSelect,
  consumption,
  isAnomalous,
  priceWarning,
}) {
  const [expanded, setExpanded] = useState(false);
  const [showCalc, setShowCalc] = useState(false);
  const [isMobile, setIsMobile] = useState(false);

  useEffect(() => {
    const check = () => setIsMobile(window.innerWidth < 640);
    check();
    window.addEventListener('resize', check);
    return () => window.removeEventListener('resize', check);
  }, []);

  const tariff = item.tariff;
  const annualCost = Math.round(item.annualCost || 0);
  const savings = item.savings !== null ? Math.round(item.savings) : null;
  const savingsPct = item.savingsPct;
  const isLuce = commodity === 'luce';
  const accentColor = isLuce ? '#f59e0b' : '#3b82f6';
  const unit = isLuce ? 'kWh' : 'Smc';
  const indice = isLuce ? 'PUN' : 'PSV';
  const isPositive = hasRealSpend && savings !== null && savings > 0;
  const humanSaves = isPositive ? savingsToHuman(savings) : null;

  let newPriceValue, newPriceRaw, priceTooltip;
  const pTot = parsePrice(isLuce ? tariff['prezzo tot kwh'] : tariff['prezzo tot smc']);
  const pSpread = parsePrice(tariff.spread);
  if (tariff.tariffa === 'Variabile') {
    newPriceRaw = pTot;
    newPriceValue = pTot != null ? `${pTot.toFixed(4).replace('.', ',')}€` : '—';
    const spreadStr = pSpread != null && pSpread > 0
      ? `${pSpread.toFixed(4)} €/${unit}`
      : '0 € (PUN puro)';
    priceTooltip = {
      lines: [
        { label: 'Prezzo oggi (ARERA)', value: pTot != null ? `${pTot.toFixed(4)} €/${unit}` : '—', bold: true },
        { label: 'Spread fornitore', value: spreadStr },
      ],
      note: 'Prezzo indicizzato al PUN/PSV: cambia ogni mese. Lo spread è l\'unica componente fissa. Il prezzo mostrato è l\'ultimo pubblicato da ARERA; quello effettivo al momento della bolletta potrebbe essere diverso.',
    };
  } else {
    newPriceRaw = pTot;
    newPriceValue = pTot != null ? `${pTot.toFixed(4).replace('.', ',')}€` : '—';
    priceTooltip = pTot != null ? {
      lines: [
        { label: 'Prezzo bloccato', value: `${pTot.toFixed(4)} €/${unit}`, bold: true },
      ],
      note: 'Prezzo fisso per tutta la durata contrattuale, non cambia col mercato.',
    } : null;
  }

  const newFixedMonthly = parsePrice(tariff.fixed_fee_monthly)
    || (parsePrice(tariff.costo_fisso) ? Math.round(parsePrice(tariff.costo_fisso) / 12 * 100) / 100 : null);

  const prezzoBloccato = tariff.prezzo_bloccato || tariff.extra?.prezzo_bloccato_mesi;
  const penaleRecesso = tariff.extra?.penale_recesso || tariff.penale_recesso;
  const validitaOfferta = tariff.validita_offerta || tariff.extra?.validita_offerta;

  const monthlyData = (consumption > 0 && hasRealSpend && currentPricePerUnit > 0 && newPriceRaw != null && newPriceRaw > 0)
    ? (() => {
        const cf = isLuce ? CONSUMO_LUCE : CONSUMO_GAS;
        const varCurrent = consumption * currentPricePerUnit;
        const fixCurrent = Math.max(0, currentSpend - varCurrent);
        const varOffer = consumption * newPriceRaw;
        const fixOffer = Math.max(0, annualCost - varOffer);
        return MESI.map((label, m) => {
          const curMonth = Math.round(cf[m] * varCurrent + fixCurrent / 12);
          const offMonth = Math.round(cf[m] * varOffer + fixOffer / 12);
          return { label, current: curMonth, offer: offMonth };
        });
      })()
    : null;

  const comparisonRows = [];
  if (hasRealSpend) {
    if (currentPricePerUnit && currentPricePerUnit > 0) {
      comparisonRows.push({
        label: `Prezzo ${unit}`,
        current: `${currentPricePerUnit.toFixed(4).replace('.', ',')} €`,
        new: newPriceRaw != null ? `${newPriceRaw.toFixed(4).replace('.', ',')} €` : newPriceValue,
        better: newPriceRaw != null && newPriceRaw < currentPricePerUnit,
        info: isLuce
          ? 'Prezzo della sola energia (kWh), senza trasporto, oneri o imposte. È l\'unica parte su cui i fornitori competono.'
          : 'Prezzo del solo gas (Smc), senza trasporto, oneri o imposte.',
      });
    }
    if (currentFixedMonthly && currentFixedMonthly > 0 && newFixedMonthly != null) {
      comparisonRows.push({
        label: 'Quota fissa',
        current: `${currentFixedMonthly.toFixed(2).replace('.', ',')} €/mese`,
        new: `${newFixedMonthly.toFixed(2).replace('.', ',')} €/mese`,
        better: newFixedMonthly < currentFixedMonthly,
      });
    }
    comparisonRows.push({
      label: 'Totale anno',
      current: formatEuro(currentSpend),
      new: formatEuro(annualCost),
      better: isPositive,
      highlight: true,
      info: isLuce
        ? `Include: materia energia + trasporto (${LUCE.TRASPORTO_VAR.toFixed(4).replace('.', ',')} €/kWh) + oneri sistema Asos/Arim (${LUCE.ONERI_SISTEMA.toFixed(4).replace('.', ',')} €/kWh) + accise (${LUCE.ACCISE.toFixed(4).replace('.', ',')} €/kWh) + quota potenza (${LUCE.COSTO_POTENZA_KW.toFixed(2).replace('.', ',')} €/kW) + quota fissa reti (${LUCE.QUOTA_FISSA_RETI.toFixed(2).replace('.', ',')} €/anno) + IVA 10%. Questi costi regolati ARERA sono UGUALI per ogni fornitore.`
        : `Include: materia gas + trasporto (${GAS.TRASPORTO_VAR.toFixed(2).replace('.', ',')} €/Smc) + oneri sistema (${GAS.ONERI_SISTEMA.toFixed(2).replace('.', ',')} €/Smc) + accise (${GAS.ACCISE.toFixed(3).replace('.', ',')} €/Smc) + addizionale regionale (${GAS.ADDIZIONALE_REGIONALE.toFixed(4).replace('.', ',')} €/Smc) + quota fissa reti (${GAS.QUOTA_FISSA_RETI.toFixed(0).replace('.', ',')} €/anno) + IVA (10% fino a 480 Smc, 22% oltre). I costi regolati ARERA sono UGUALI per ogni fornitore.`,
    });
  }

  const maxBar = Math.max(currentSpend || 0, annualCost || 0);
  const barPctNew = maxBar > 0 ? (annualCost / maxBar) * 100 : 100;

  const rankDisplay = item.rankBadge || (rank <= 3 ? ['🥇', '🥈', '🥉'][rank - 1] : `#${rank}`);

  let savingsDisplay;
  if (!hasRealSpend || savings === null || savings === undefined) {
    savingsDisplay = { text: '—', color: '#64748b' };
  } else if (savings > 100) {
    savingsDisplay = { text: `-${formatEuro(savings)}`, color: '#10b981' };
  } else if (savings > 15) {
    savingsDisplay = { text: `-${savingsPct?.toFixed(0)}%`, color: '#10b981' };
  } else if (savings > 0) {
    savingsDisplay = { text: 'minimo', color: '#64748b' };
  } else {
    savingsDisplay = { text: formatEuro(annualCost), color: '#64748b' };
  }

  const highlightColors = ['#10b981', '#3b82f6', '#f59e0b'];
  const borderLeft = rank <= 3 ? `3px solid ${highlightColors[rank - 1]}` : '3px solid transparent';

  const gridCols = '32px 100px 1fr 80px 70px 85px 80px 32px';

  return (
    <div style={{
      borderLeft,
      background: rank <= 3 ? 'rgba(255,255,255,0.015)' : 'transparent',
    }}>
      <div
        role="button"
        tabIndex={0}
        aria-expanded={expanded}
        onClick={() => setExpanded(prev => !prev)}
        onKeyDown={e => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            setExpanded(prev => !prev);
          }
        }}
        style={{
          display: 'grid',
          gridTemplateColumns: isMobile ? '28px 1fr auto 28px' : gridCols,
          alignItems: 'center',
          gap: isMobile ? 6 : 8,
          padding: isMobile ? '10px 12px' : '10px 16px',
          cursor: 'pointer',
          borderBottom: '1px solid rgba(255,255,255,0.04)',
          transition: 'background 0.15s',
          userSelect: 'none',
        }}
        onMouseOver={e => e.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
        onMouseOut={e => e.currentTarget.style.background = 'transparent'}
      >
        <div style={{ textAlign: 'center', fontSize: isMobile ? 15 : 18, lineHeight: 1 }}>
          {rankDisplay}
        </div>

        {!isMobile && (
          <div style={{ display: 'flex', alignItems: 'center', gap: 6, overflow: 'hidden' }}>
            {tariff.logo ? (
              <img src={tariff.logo} alt={tariff.brand}
                style={{ width: 18, height: 14, objectFit: 'contain', flexShrink: 0 }}
                onError={e => { e.target.style.display = 'none'; }}
              />
            ) : (
              <div style={{
                width: 18, height: 14, borderRadius: 2, flexShrink: 0,
                background: `${accentColor}18`, display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontSize: 9, fontWeight: 900, color: accentColor,
              }}>
                {tariff.brand?.charAt(0) || '?'}
              </div>
            )}
            <span style={{
              fontSize: 12, fontWeight: 600, color: '#e2e8f0',
              overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
            }}>
              {tariff.brand}
            </span>
          </div>
        )}

        <div style={{ overflow: 'hidden' }}>
          <div style={{
            fontSize: isMobile ? 11 : 12, fontWeight: 600, color: '#f1f5f9',
            overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
          }}>
            {isMobile ? `${tariff.brand} ${tariff.offerta}` : tariff.offerta}
          </div>
          {tariff.tariffa && (
            <div style={{
              fontSize: 9, fontWeight: 600, padding: '1px 6px', borderRadius: 3, marginTop: 3,
              background: tariff.tariffa === 'Variabile' ? 'rgba(251,191,36,0.12)' : 'rgba(16,185,129,0.12)',
              color: tariff.tariffa === 'Variabile' ? '#fbbf24' : '#6ee7b7',
              display: 'inline-block',
            }}>
              {tariff.tariffa === 'Variabile' ? 'Variabile' : 'Fisso'}
            </div>
          )}
        </div>

        {!isMobile && (
          <div style={{
            fontSize: 11, fontWeight: 500, color: '#94a3b8',
            textAlign: 'right', fontVariantNumeric: 'tabular-nums',
            display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: 2,
          }}>
            {newPriceValue}
            {priceTooltip && <InfoTooltip text={
              <div>
                {priceTooltip.lines.map((l, i) => (
                  <div key={i} style={{
                    display: 'flex', justifyContent: 'space-between', gap: 16,
                    fontWeight: l.bold ? 700 : 400,
                    color: l.bold ? '#f1f5f9' : '#94a3b8',
                    marginBottom: 2,
                  }}>
                    <span>{l.label}</span>
                    <span>{l.value}</span>
                  </div>
                ))}
                {priceTooltip.note && (
                  <div style={{ marginTop: 6, fontSize: 10, color: '#64748b', fontStyle: 'italic' }}>
                    {priceTooltip.note}
                  </div>
                )}
              </div>
            } label="Dettaglio prezzo" />}
          </div>
        )}

        {!isMobile && (
          <div style={{
            fontSize: 11, fontWeight: 500, color: '#94a3b8', textAlign: 'right',
          }}>
            {newFixedMonthly != null ? `${newFixedMonthly.toFixed(2).replace('.', ',')} €/mese` : '—'}
          </div>
        )}

        <div style={{
          fontSize: isMobile ? 12 : 13, fontWeight: 700, color: '#f1f5f9',
          textAlign: 'right', fontVariantNumeric: 'tabular-nums',
        }}>
          {formatEuro(annualCost)}
        </div>

        <div style={{
          fontSize: isMobile ? 10 : 11, fontWeight: 600, color: savingsDisplay.color,
          textAlign: 'right', fontVariantNumeric: 'tabular-nums',
        }}>
          {savingsDisplay.text}
        </div>

        {onToggleSelect && (
          <div
            role="checkbox"
            aria-checked={selected}
            tabIndex={0}
            onClick={e => { e.stopPropagation(); onToggleSelect(`${tariff.brand}|${tariff.offerta}`); }}
            onKeyDown={e => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                onToggleSelect(`${tariff.brand}|${tariff.offerta}`);
              }
            }}
            style={{
              width: isMobile ? 16 : 18, height: isMobile ? 16 : 18, borderRadius: 4,
              border: selected ? '2px solid #3b82f6' : '2px solid rgba(255,255,255,0.15)',
              background: selected ? 'rgba(59,130,246,0.25)' : 'rgba(0,0,0,0.2)',
              cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center',
              flexShrink: 0, transition: 'all 0.15s',
            }}
          >
            {selected && (
              <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                <path d="M20 6L9 17l-5-5" />
              </svg>
            )}
          </div>
        )}
      </div>

      {expanded && (
        <div style={{
          padding: isMobile ? '12px 14px' : '16px 20px',
          background: 'rgba(255,255,255,0.012)',
          borderBottom: '1px solid rgba(255,255,255,0.04)',
        }}>
          {(isAnomalous || priceWarning) && (
            <div style={{
              padding: '6px 12px', borderRadius: 6, marginBottom: 12,
              background: 'rgba(251,191,36,0.08)', border: '1px solid rgba(251,191,36,0.2)',
              fontSize: 10, color: '#fbbf24', lineHeight: 1.5,
            }}>
              ⚠️ {priceWarning || 'Prezzo molto inferiore alla media di mercato. Verifica sempre le condizioni contrattuali.'}
            </div>
          )}

          {comparisonRows.length > 0 && (
            <div style={{
              borderRadius: 8, marginBottom: 12,
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
                      fontSize: row.highlight ? 12 : 11,
                      color: row.highlight ? '#f1f5f9' : '#94a3b8',
                      fontWeight: row.highlight ? 700 : 500,
                      display: 'inline-flex', alignItems: 'center', gap: 4,
                    }}>
                      {row.label}
                      {row.info && <InfoTooltip text={row.info} label={row.label} />}
                    </span>
                  </div>
                  <div style={{ fontSize: 11, fontWeight: 600, color: '#94a3b8', textAlign: 'right' }}>
                    {row.current}
                  </div>
                  <div style={{
                    fontSize: row.highlight ? 13 : 11, fontWeight: row.highlight ? 700 : 600,
                    color: row.highlight ? (isPositive ? '#10b981' : '#f1f5f9') : '#f1f5f9',
                    textAlign: 'right', display: 'flex', alignItems: 'center', gap: 4, justifyContent: 'flex-end',
                  }}>
                    {row.new}
                    {row.better !== undefined && (
                      <span style={{ fontSize: 10 }}>{row.better ? '✅' : '↗'}</span>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}

          {/* ═══ PREZZI PER FASCIA ═══ */}
          {(tariff.componenti || tariff.extra?.componenti) && (
            (() => {
              const comps = tariff.componenti || tariff.extra?.componenti || [];
              const fascie = comps.filter(c => c.fascia);
              const fissi = comps.filter(c => !c.fascia);
              if (fascie.length === 0) return null;
              const unit = fascie[0]?.unita || '€/kWh';
              return (
                <div style={{
                  borderRadius: 8, marginBottom: 12,
                  background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)',
                  overflow: 'hidden',
                }}>
                  <div style={{
                    padding: '6px 12px',
                    background: 'rgba(255,255,255,0.03)', borderBottom: '1px solid rgba(255,255,255,0.04)',
                    fontSize: 9, color: '#64748b', textTransform: 'uppercase', fontWeight: 600,
                  }}>
                    Prezzi per fascia
                  </div>
                  {fascie.map((c, i) => (
                    <div key={i} style={{
                      display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                      padding: '5px 12px',
                      borderBottom: i < fascie.length - 1 ? '1px solid rgba(255,255,255,0.03)' : 'none',
                    }}>
                      <span style={{ fontSize: 11, color: '#94a3b8', fontWeight: 500 }}>
                        Fascia {c.fascia}
                        <span style={{ color: '#64748b', fontSize: 9, marginLeft: 4 }}>
                          {(c.nome || '').toLowerCase().includes('prezzo') ? '' : c.nome}
                        </span>
                      </span>
                      <span style={{ fontSize: 11, fontWeight: 600, color: '#f1f5f9', fontVariantNumeric: 'tabular-nums' }}>
                        {c.prezzo.toFixed(4).replace('.',',')} {unit}
                      </span>
                    </div>
                  ))}
                  {/* Componenti fisse */}
                  {fissi.map((c, i) => (
                    <div key={`fix-${i}`} style={{
                      display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                      padding: '5px 12px', borderTop: '1px solid rgba(255,255,255,0.04)',
                    }}>
                      <span style={{ fontSize: 11, color: '#94a3b8', fontWeight: 500 }}>
                        {c.nome}
                      </span>
                      <span style={{ fontSize: 11, fontWeight: 600, color: '#f1f5f9' }}>
                        {c.prezzo.toFixed(2).replace('.',',')} {c.unita || '€/anno'}
                      </span>
                    </div>
                  ))}
                </div>
              );
            })()
          )}

          {/* ═══ COMPOSIZIONE PREZZO (costi regolati) ═══ */}
          {isLuce && (tariff.componenti || tariff.extra?.componenti) && (
            (() => {
              const comps = tariff.componenti || tariff.extra?.componenti || [];
              const firstEnergia = comps.find(c => c.fascia && c.fascia === 'F1');
              if (!firstEnergia) return null;
              const regRates = [
                { label: 'Energia elettrica', value: firstEnergia.prezzo, unit: unit },
                { label: 'Oneri di sistema (ASOS+ARIM)', value: LUCE.ONERI_SISTEMA, unit: unit },
                { label: 'Trasporto e dispacciamento', value: LUCE.TRASPORTO_VAR, unit: unit },
              ];
              const total = regRates.reduce((s, r) => s + r.value, 0);
              return (
                <div style={{
                  borderRadius: 8, marginBottom: 12,
                  background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)',
                  overflow: 'hidden',
                }}>
                  <div style={{
                    padding: '6px 12px',
                    background: 'rgba(255,255,255,0.03)', borderBottom: '1px solid rgba(255,255,255,0.04)',
                    fontSize: 9, color: '#64748b', textTransform: 'uppercase', fontWeight: 600,
                  }}>
                    Composizione prezzo (Fascia F1)
                  </div>
                  {regRates.map((r, i) => (
                    <div key={i} style={{
                      display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                      padding: '4px 12px',
                      borderBottom: i < regRates.length - 1 ? '1px solid rgba(255,255,255,0.03)' : 'none',
                    }}>
                      <span style={{ fontSize: 10, color: '#94a3b8' }}>{r.label}</span>
                      <span style={{ fontSize: 10, fontWeight: 600, color: '#e2e8f0', fontVariantNumeric: 'tabular-nums' }}>
                        {r.value.toFixed(4).replace('.',',')} {r.unit}
                      </span>
                    </div>
                  ))}
                  <div style={{
                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                    padding: '6px 12px', background: 'rgba(255,255,255,0.03)',
                    borderTop: '1px solid rgba(255,255,255,0.05)',
                  }}>
                    <span style={{ fontSize: 10, color: '#f1f5f9', fontWeight: 700 }}>Totale F1</span>
                    <span style={{ fontSize: 10, fontWeight: 800, color: '#f1f5f9', fontVariantNumeric: 'tabular-nums' }}>
                      {total.toFixed(4).replace('.',',')} {unit}
                    </span>
                  </div>
                </div>
              );
            })()
          )}

          {/* ═══ MONTHLY PROJECTION CHART ═══ */}
          {monthlyData && (
            <div style={{ marginBottom: 12 }}>
              <div style={{ fontSize: 10, color: '#64748b', fontWeight: 600, marginBottom: 6 }}>
                Andamento mensile ipotetico
              </div>
              <div style={{ width: '100%', height: 140 }}>
                <ResponsiveContainer width="100%" height="100%">
                  <LineChart data={monthlyData} margin={{ top: 2, right: 0, left: -20, bottom: 0 }}>
                    <XAxis dataKey="label" axisLine={false} tickLine={false} tick={{ fontSize: 8, fill: '#475569' }} interval={1} />
                    <YAxis axisLine={false} tickLine={false} tick={{ fontSize: 8, fill: '#475569' }} tickFormatter={v => `${v}€`} domain={[0, 'auto']} width={30} />
                    <Tooltip content={({ active, payload, label }) => {
                      if (!active || !payload?.length) return null;
                      const d = monthlyData.find(m => m.label === label);
                      if (!d) return null;
                      return (
                        <div style={{
                          background: '#131827', border: '1px solid rgba(255,255,255,0.08)',
                          borderRadius: 8, padding: '8px 12px', boxShadow: '0 8px 32px rgba(0,0,0,0.5)',
                        }}>
                          <div style={{ fontSize: 11, fontWeight: 700, color: '#f1f5f9', marginBottom: 4 }}>{label}</div>
                          <div style={{ fontSize: 10, color: '#60a5fa' }}>Ora: <b>{formatEuro(d.current)}</b></div>
                          <div style={{ fontSize: 10, color: '#4ade80' }}>Offerta: <b>{formatEuro(d.offer)}</b></div>
                        </div>
                      );
                    }} />
                    <Line type="monotone" dataKey="current" stroke="#60a5fa" strokeWidth={2} dot={false} activeDot={{ r: 3, fill: '#60a5fa' }} />
                    <Line type="monotone" dataKey="offer" stroke="#4ade80" strokeWidth={2} dot={false} activeDot={{ r: 3, fill: '#4ade80' }} />
                  </LineChart>
                </ResponsiveContainer>
              </div>
              <div style={{ display: 'flex', gap: 12, fontSize: 9, color: '#64748b', marginTop: 4 }}>
                <span><span style={{ color: '#60a5fa' }}>━</span> Costo attuale</span>
                <span><span style={{ color: '#4ade80' }}>━</span> Questa offerta</span>
              </div>
            </div>
          )}

          {hasRealSpend && currentSpend > 0 && (
            <div style={{ marginBottom: 12 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4, fontSize: 9 }}>
                <span style={{ color: '#64748b' }}>Spesa attuale</span>
                <span style={{ color: '#64748b' }}>Nuova spesa</span>
              </div>
              <div style={{
                position: 'relative', height: 8, borderRadius: 4,
                background: 'rgba(248,113,113,0.3)', overflow: 'hidden',
              }}>
                <div style={{
                  position: 'absolute', left: 0, top: 0, height: '100%',
                  width: `${Math.min(barPctNew, 100)}%`,
                  background: isPositive
                    ? 'linear-gradient(90deg, #10b981, #34d399)'
                    : 'linear-gradient(90deg, #f59e0b, #fbbf24)',
                  borderRadius: 4, transition: 'width 0.5s ease',
                }} />
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 4, fontSize: 12 }}>
                <span style={{ fontWeight: 700, color: '#f87171' }}>{formatEuro(currentSpend)}</span>
                <span style={{ fontWeight: 700, color: isPositive ? '#10b981' : '#f1f5f9' }}>{formatEuro(annualCost)}</span>
              </div>
              {isPositive && humanSaves && (
                <div style={{ textAlign: 'center', marginTop: 4 }}>
                  {savings > 100 ? (
                    <span style={{
                      fontSize: 12, fontWeight: 700, color: '#10b981',
                      background: 'rgba(16,185,129,0.1)', padding: '2px 14px', borderRadius: 20,
                    }}>
                      -{formatEuro(savings)} ({savingsPct?.toFixed(0)}%)
                    </span>
                  ) : savings > 15 ? (
                    <span style={{
                      fontSize: 12, fontWeight: 700, color: '#10b981',
                      background: 'rgba(16,185,129,0.1)', padding: '2px 14px', borderRadius: 20,
                    }}>
                      -{savingsPct?.toFixed(0)}% ({formatEuro(savings)})
                    </span>
                  ) : (
                    <span style={{
                      fontSize: 11, fontWeight: 500, color: '#94a3b8',
                      background: 'rgba(148,163,184,0.06)', padding: '2px 14px', borderRadius: 20,
                    }}>
                      Risparmio minimo ({formatEuro(savings)})
                    </span>
                  )}
                  <div style={{ fontSize: 10, color: '#6ee7b7', marginTop: 2 }}>
                    ≈ {formatEuro(humanSaves.mese)}/mese · {humanSaves.giorno}€/giorno
                  </div>
                </div>
              )}
            </div>
          )}

          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <a
              href={`/sottoscrizione?tariff=${encodeURIComponent(tariff.id || '')}&supplier=${encodeURIComponent(tariff.brand || '')}&name=${encodeURIComponent(tariff.offerta || '')}&commodity=${commodity}&annualCost=${annualCost}`}
              className="btn btn-electric"
              style={{ fontSize: 11, padding: '8px 18px', flexShrink: 0 }}
            >
              Sottoscrivi
            </a>
            <button
              onClick={e => { e.stopPropagation(); setShowCalc(true); }}
              className="btn btn-outline"
              style={{ fontSize: 11, padding: '8px 18px' }}
            >
              ℹ️ Calcolo Risparmio
            </button>
          </div>

            {(tariff.codice_offerta || tariff.extra?.codice_offerta || tariff.url_offerta || tariff.extra?.url_offerta || prezzoBloccato || penaleRecesso || validitaOfferta) && (
            <div style={{
              display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 10,
              paddingTop: 10, borderTop: '1px solid rgba(255,255,255,0.04)',
            }}>
              {prezzoBloccato && (
                <span style={{
                  fontSize: 9, padding: '2px 8px', borderRadius: 4,
                  background: 'rgba(16,185,129,0.08)', color: '#6ee7b7',
                  border: '1px solid rgba(16,185,129,0.15)',
                }}>
                  🔒 Prezzo bloccato {prezzoBloccato} mesi
                </span>
              )}
              {validitaOfferta && !prezzoBloccato && (
                <span style={{
                  fontSize: 9, padding: '2px 8px', borderRadius: 4,
                  background: 'rgba(148,163,184,0.06)', color: '#94a3b8',
                  border: '1px solid rgba(148,163,184,0.1)',
                }}>
                  📅 Valida fino al {validitaOfferta}
                </span>
              )}
              {penaleRecesso && (
                <span style={{
                  fontSize: 9, padding: '2px 8px', borderRadius: 4,
                  background: 'rgba(251,191,36,0.06)', color: '#fbbf24',
                  border: '1px solid rgba(251,191,36,0.12)',
                }}>
                  ⚠️ Penale recesso: {typeof penaleRecesso === 'object' ? JSON.stringify(penaleRecesso) : penaleRecesso}
                </span>
              )}
              {(tariff.codice_offerta || tariff.extra?.codice_offerta) && (
                <span style={{
                  fontSize: 9, padding: '2px 8px', borderRadius: 4,
                  background: 'rgba(148,163,184,0.06)', color: '#94a3b8',
                  border: '1px solid rgba(148,163,184,0.1)',
                }}>
                  📋 Cod.: {tariff.codice_offerta || tariff.extra?.codice_offerta}
                </span>
              )}
              {(tariff.url_offerta || tariff.extra?.url_offerta) && (
                <a
                  href={tariff.url_offerta || tariff.extra?.url_offerta}
                  target="_blank" rel="noopener noreferrer"
                  style={{
                    fontSize: 9, padding: '2px 8px', borderRadius: 4,
                    background: 'rgba(59,130,246,0.08)', color: '#60a5fa',
                    border: '1px solid rgba(59,130,246,0.15)',
                    textDecoration: 'none',
                    display: 'inline-flex', alignItems: 'center', gap: 3,
                  }}
                >
                  🔗 Vedi su ARERA
                </a>
              )}
            </div>
          )}

          {hasRealSpend && (
            <div style={{
              marginTop: 10, paddingTop: 10, borderTop: '1px solid rgba(255,255,255,0.04)',
              fontSize: 9, color: '#64748b', fontStyle: 'italic', lineHeight: 1.5,
            }}>
              💡 Trasporto, oneri di sistema e imposte restano uguali con qualsiasi fornitore.
              La componente che cambia è la materia energia (prezzo {unit} + quota fissa).
              {llmData?.tipo_tariffa === 'variabile' && (
                <span style={{ display: 'block', marginTop: 2, color: '#fbbf24' }}>
                  ⚡ La tua tariffa attuale è variabile: la spesa è una proiezione basata sul {indice} attuale.
                </span>
              )}
            </div>
          )}
        </div>
      )}

      <SavingsBreakdownModal
        consumo={consumption || 0}
        prezzo={isLuce ? (tariff?.['prezzo tot kwh'] || 0) : (tariff?.['prezzo tot smc'] || 0)}
        costoFisso={tariff.costo_fisso || 0}
        annualCost={annualCost}
        currentSpend={currentSpend}
        brand={tariff.brand || ''}
        offerta={tariff.offerta || ''}
        tipo={isLuce ? 'LUCE' : 'GAS'}
        open={showCalc}
        onClose={() => setShowCalc(false)}
      />
    </div>
  );
}
