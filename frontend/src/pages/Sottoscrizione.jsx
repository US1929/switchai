import React, { useState, useEffect, useCallback } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { PROVINCE, suggestProvince, TITOLI_IMMOBILE, MODALITA_PAGAMENTO } from '../lib/province.js';
import { isValidCF, isValidPOD, isValidPDR, isValidCAP, isValidIBAN, formatIBAN, isValidPhone, isValidEmail } from '../lib/validators.js';

/** Step del wizard */
const STEPS = [
  { id: 1, label: 'Dati personali' },
  { id: 2, label: 'Indirizzo fornitura' },
  { id: 3, label: 'Dati tecnici' },
  { id: 4, label: 'Riepilogo e invio' },
];

export default function Sottoscrizione() {
  const [params] = useSearchParams();
  const navigate = useNavigate();

  // Dati offerta da URL
  const tariffId = params.get('tariff') || '';
  const supplier = params.get('supplier') || '';
  const tariffName = params.get('name') || '';
  const commodity = params.get('commodity') || 'luce';
  const annualCost = params.get('annualCost') || '';

  // Dati utente da URL (precompilazione via LLM/MCP)
  const prefill = {
    nome: params.get('nome') || '',
    cognome: params.get('cognome') || '',
    cf: params.get('cf') || '',
    email: params.get('email') || '',
    tel: params.get('tel') || '',
    indirizzo: params.get('indirizzo') || '',
    civico: params.get('civico') || '',
    citta: params.get('citta') || '',
    provincia: params.get('provincia') || '',
    provincia_sigla: params.get('provincia_sigla') || '',
    cap: params.get('cap') || '',
    pod: params.get('pod') || '',
    pdr: params.get('pdr') || '',
    consumi: params.get('consumi') || '',
    spesa: params.get('spesa') || '',
  };
  const hasPrefill = Object.values(prefill).some(v => v !== '');

  const isLuce = commodity === 'luce';

  // Wizard state
  const [step, setStep] = useState(1);
  const [sending, setSending] = useState(false);
  const [done, setDone] = useState(false);
  const [resultId, setResultId] = useState(null);
  const [error, setError] = useState(null);

  // Form state (precompilato se dati utente presenti nell'URL)
  const [form, setForm] = useState({
    nome: prefill.nome, cognome: prefill.cognome,
    codice_fiscale: prefill.cf,
    email: prefill.email, cellulare: prefill.tel,
    titolo_immobile: '',
    indirizzo: prefill.indirizzo, civico: prefill.civico,
    citta: prefill.citta, provincia: prefill.provincia,
    provincia_sigla: prefill.provincia_sigla, cap: prefill.cap,
    codice_pod: prefill.pod, codice_pdr: prefill.pdr,
    modalita_pagamento: '', iban: '',
    indirizzo_coincide: 'si',
    indirizzo_residenza: '', civico_residenza: '', citta_residenza: '',
    provincia_residenza: '', provincia_residenza_sigla: '', cap_residenza: '',
    gdpr_privacy_accepted: false,
  });

  // Province autocomplete
  const [provSuggestions, setProvSuggestions] = useState([]);
  const [provResSuggestions, setProvResSuggestions] = useState([]);

  // Errori validazione per campo
  const [fieldErrors, setFieldErrors] = useState({});

  // ── Helpers ──────────────────────────────────────────────────────────
  const update = useCallback((field, value) => {
    setForm(f => ({ ...f, [field]: value }));
    setFieldErrors(e => ({ ...e, [field]: null })); // pulisce errore mentre scrivi
  }, []);

  // Validazione inline onBlur per ogni campo obbligatorio
  const validateField = useCallback((field, value) => {
    let error = null;
    const v = value?.trim?.() ?? value;
    switch (field) {
      case 'nome': if (!v || v.length < 2) error = 'Inserisci il nome'; break;
      case 'cognome': if (!v || v.length < 2) error = 'Inserisci il cognome'; break;
      case 'codice_fiscale': if (!v || !isValidCF(v)) error = 'Codice fiscale non valido'; break;
      case 'email': if (!v || !isValidEmail(v)) error = 'Email non valida'; break;
      case 'cellulare': if (!v || !isValidPhone(v)) error = 'Cellulare non valido (es. +393401234567)'; break;
      case 'titolo_immobile': if (!v) error = 'Seleziona il titolo'; break;
      case 'indirizzo': if (!v) error = 'Inserisci indirizzo'; break;
      case 'civico': if (!v) error = 'Inserisci civico'; break;
      case 'citta': if (!v) error = 'Inserisci città'; break;
      case 'provincia': if (!form.provincia_sigla) error = 'Seleziona provincia dalla lista'; break;
      case 'cap': if (!v || !isValidCAP(v)) error = 'CAP non valido (5 cifre)'; break;
      case 'codice_pod': if (isLuce && !isValidPOD(v)) error = 'POD non valido (IT + 3 cifre + E + 8-9 cifre)'; break;
      case 'codice_pdr': if (!isLuce && !isValidPDR(v)) error = 'PDR non valido (14 cifre)'; break;
      case 'modalita_pagamento': if (!v) error = 'Seleziona modalità'; break;
      case 'iban': if (form.modalita_pagamento === 'SDD' && !isValidIBAN(v)) error = 'IBAN non valido'; break;
    }
    if (error) {
      setFieldErrors(e => ({ ...e, [field]: error }));
    }
    return !error;
  }, [form.provincia_sigla, form.modalita_pagamento, isLuce]);

  const handleProvInput = useCallback((value, isResidenza = false) => {
    const fieldName = isResidenza ? 'provincia_residenza' : 'provincia';
    const siglaField = isResidenza ? 'provincia_residenza_sigla' : 'provincia_sigla';
    update(fieldName, value);
    const suggestions = suggestProvince(value);
    (isResidenza ? setProvResSuggestions : setProvSuggestions)(suggestions);
    // Se match esatto, imposta sigla
    if (PROVINCE[value]) update(siglaField, PROVINCE[value]);
  }, [update]);

  const selectProvince = useCallback((nome, sigla, isResidenza = false) => {
    update(isResidenza ? 'provincia_residenza' : 'provincia', nome);
    update(isResidenza ? 'provincia_residenza_sigla' : 'provincia_sigla', sigla);
    (isResidenza ? setProvResSuggestions : setProvSuggestions)([]);
  }, [update]);

  // ── Validazione step ─────────────────────────────────────────────────
  const validateStep = useCallback((stepNum) => {
    const errors = {};
    switch (stepNum) {
      case 1:
        if (!form.nome?.trim() || form.nome.trim().length < 2) errors.nome = 'Inserisci il nome';
        if (!form.cognome?.trim() || form.cognome.trim().length < 2) errors.cognome = 'Inserisci il cognome';
        if (!form.codice_fiscale?.trim() || !isValidCF(form.codice_fiscale)) errors.codice_fiscale = 'Codice fiscale non valido';
        if (!form.email?.trim() || !isValidEmail(form.email)) errors.email = 'Email non valida';
        if (!form.cellulare?.trim() || !isValidPhone(form.cellulare)) errors.cellulare = 'Cellulare non valido (es. +393401234567)';
        if (!form.titolo_immobile) errors.titolo_immobile = 'Seleziona il titolo';
        break;
      case 2:
        if (!form.indirizzo?.trim()) errors.indirizzo = 'Inserisci indirizzo';
        if (!form.civico?.trim()) errors.civico = 'Inserisci civico';
        if (!form.citta?.trim()) errors.citta = 'Inserisci città';
        if (!form.provincia_sigla) errors.provincia = 'Seleziona provincia';
        if (!form.cap?.trim() || !isValidCAP(form.cap)) errors.cap = 'CAP non valido (5 cifre)';
        if (form.indirizzo_coincide === 'no') {
          if (!form.indirizzo_residenza?.trim()) errors.indirizzo_residenza = 'Inserisci indirizzo';
          if (!form.citta_residenza?.trim()) errors.citta_residenza = 'Inserisci città';
          if (!form.provincia_residenza_sigla) errors.provincia_residenza = 'Seleziona provincia';
          if (!form.cap_residenza?.trim() || !isValidCAP(form.cap_residenza)) errors.cap_residenza = 'CAP non valido';
        }
        break;
      case 3:
        if (isLuce && !isValidPOD(form.codice_pod)) errors.codice_pod = 'POD non valido (IT + 3 cifre + E + 8-9 cifre)';
        if (!isLuce && !isValidPDR(form.codice_pdr)) errors.codice_pdr = 'PDR non valido (14 cifre)';
        if (!form.modalita_pagamento) errors.modalita_pagamento = 'Seleziona modalità';
        if (form.modalita_pagamento === 'SDD' && !isValidIBAN(form.iban)) errors.iban = 'IBAN non valido';
        break;
      case 4:
        if (!form.gdpr_privacy_accepted) errors.gdpr_privacy_accepted = 'Devi accettare l\'informativa privacy per proseguire';
        break;
    }
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }, [form, isLuce]);

  const nextStep = () => { if (validateStep(step)) setStep(s => Math.min(s + 1, 4)); };
  const prevStep = () => setStep(s => Math.max(s - 1, 1));

  // ── Submit ───────────────────────────────────────────────────────────
  const handleSubmit = async () => {
    if (!validateStep(4)) return; // Steps 1-3 are already validated when reaching step 4
    setSending(true); setError(null);
    try {
      const res = await fetch('/api/subscription/submit', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          tariff_id: tariffId,
          tariff_name: tariffName,
          supplier,
          commodity,
          annual_cost: annualCost,
          ...form,
        }),
      });
      if (!res.ok) {
        const e = await res.json().catch(() => ({}));
        throw new Error(e.error || 'Errore durante l\'invio');
      }
      const data = await res.json();
      setResultId(data.subscription_id);
      setDone(true);
    } catch (err) {
      setError(err.message);
    } finally {
      setSending(false);
    }
  };

  // ── Render helpers ────────────────────────────────────────────────────
  const fieldClass = (name) => `input-field${fieldErrors[name] ? ' invalid' : ''}`;
  const fieldError = (name) => fieldErrors[name] ? (
    <div style={{ fontSize: 11, color: '#f87171', marginTop: 4 }}>{fieldErrors[name]}</div>
  ) : null;

  const progressPct = ((step - 1) / (STEPS.length - 1)) * 100;

  // ── DONE state ───────────────────────────────────────────────────────
  if (done) {
    return (
      <main style={{ padding: '80px 24px', textAlign: 'center' }}>
        <div className="glass-card animate-scale-in" style={{ maxWidth: 500, margin: '0 auto', padding: '48px 32px' }}>
          <div style={{ fontSize: 56, marginBottom: 16 }}>🎉</div>
          <h2 style={{ fontSize: 24, fontWeight: 800, marginBottom: 8 }}>Sottoscrizione inviata!</h2>
          <p style={{ color: '#94a3b8', fontSize: 14, lineHeight: 1.7, marginBottom: 8 }}>
            Abbiamo inviato la richiesta per <b style={{ color: '#f1f5f9' }}>{tariffName}</b> di <b style={{ color: '#f1f5f9' }}>{supplier}</b>.
          </p>
          <p style={{ color: '#64748b', fontSize: 13, marginBottom: 24 }}>
            Entro 24 ore ti contatteremo per completare l'attivazione.
          </p>
          {resultId && (
            <div className="badge badge-tag" style={{ marginBottom: 24, fontSize: 11 }}>
              ID: {resultId}
            </div>
          )}
          <button className="btn btn-electric" onClick={() => navigate('/')}>
            ← Torna alla home
          </button>
        </div>
      </main>
    );
  }

  // ── FORM state ───────────────────────────────────────────────────────
  return (
    <main style={{ padding: '50px 24px 80px' }}>
      <div style={{ maxWidth: 660, margin: '0 auto' }}>
        {/* Header */}
        <div style={{ marginBottom: 32 }}>
          <h1 style={{ fontSize: 28, fontWeight: 900, letterSpacing: '-0.5px', marginBottom: 8 }}>
            Attiva {tariffName || 'la tua offerta'}
          </h1>
          {supplier && (
            <div className="glass-card animate-fade-in" style={{ padding: '14px 18px', display: 'flex', alignItems: 'center', gap: 12, fontSize: 14, marginBottom: 8 }}>
              <span>⚡</span>
              <b style={{ color: '#f1f5f9' }}>{supplier}</b>
              <span style={{ color: '#94a3b8' }}>— {tariffName}</span>
              {annualCost && <span style={{ marginLeft: 'auto', color: '#10b981', fontWeight: 700 }}>{annualCost}€/anno</span>}
            </div>
          )}
          {hasPrefill && (
            <div style={{
              padding: '10px 16px', borderRadius: 8,
              background: 'rgba(16,185,129,0.08)', border: '1px solid rgba(16,185,129,0.2)',
              fontSize: 12, color: '#6ee7b7', display: 'flex', alignItems: 'center', gap: 8,
            }}>
              <span>🤖</span>
              <span><b>Dati precompilati dalla tua bolletta</b> — verificali e completa i campi mancanti.</span>
            </div>
          )}
        </div>

        {/* Progress bar */}
        <div style={{ marginBottom: 32 }}>
          <div style={{ height: 4, background: 'var(--gray-800)', borderRadius: 2, overflow: 'hidden' }}>
            <div style={{
              height: '100%', width: `${progressPct}%`,
              background: 'linear-gradient(90deg, #f59e0b, #10b981)',
              borderRadius: 2, transition: 'width 0.3s ease',
            }} />
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 10 }}>
            {STEPS.map(s => (
              <div key={s.id} style={{
                fontSize: 12, fontWeight: step >= s.id ? 700 : 500,
                color: step >= s.id ? '#f1f5f9' : '#64748b',
                textAlign: 'center', flex: 1,
              }}>
                {s.label}
              </div>
            ))}
          </div>
        </div>

        {/* Form card */}
        <div className="glass-card" style={{ padding: '28px 26px' }}>
          {/* ═══ STEP 1: Dati personali ═══ */}
          {step === 1 && (
            <div className="animate-fade-in">
              <h3 style={{ fontSize: 18, fontWeight: 700, marginBottom: 22, color: '#f1f5f9' }}>👤 Dati intestatario</h3>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
                <Row2>
                  <Field label="Nome" error={fieldError('nome')}>
                    <input className={fieldClass('nome')} value={form.nome} onChange={e => update('nome', e.target.value)} onBlur={e => validateField('nome', e.target.value)} placeholder="Mario" />
                  </Field>
                  <Field label="Cognome" error={fieldError('cognome')}>
                    <input className={fieldClass('cognome')} value={form.cognome} onChange={e => update('cognome', e.target.value)} onBlur={e => validateField('cognome', e.target.value)} placeholder="Rossi" />
                  </Field>
                </Row2>
                <Field label="Codice Fiscale" error={fieldError('codice_fiscale')}>
                  <input className={fieldClass('codice_fiscale')} value={form.codice_fiscale}
                    onChange={e => update('codice_fiscale', e.target.value.toUpperCase())}
                    onBlur={e => validateField('codice_fiscale', e.target.value)}
                    placeholder="RSSMRA80A01H501U" maxLength={16} style={{ textTransform: 'uppercase' }} />
                </Field>
                <Row2>
                  <Field label="Email" error={fieldError('email')}>
                    <input type="email" className={fieldClass('email')} value={form.email}
                      onChange={e => update('email', e.target.value)} onBlur={e => validateField('email', e.target.value)} placeholder="mario.rossi@email.com" />
                  </Field>
                  <Field label="Cellulare" error={fieldError('cellulare')}>
                    <input type="tel" className={fieldClass('cellulare')} value={form.cellulare}
                      onChange={e => update('cellulare', e.target.value)} onBlur={e => validateField('cellulare', e.target.value)} placeholder="+39 340 1234567" />
                  </Field>
                </Row2>
                <Field label="Titolo sull'immobile" error={fieldError('titolo_immobile')}>
                  <select className={fieldClass('titolo_immobile')} value={form.titolo_immobile}
                    onChange={e => update('titolo_immobile', e.target.value)} onBlur={e => validateField('titolo_immobile', e.target.value)}>
                    <option value="">Seleziona...</option>
                    {TITOLI_IMMOBILE.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                  </select>
                </Field>
              </div>
            </div>
          )}

          {/* ═══ STEP 2: Indirizzo fornitura ═══ */}
          {step === 2 && (
            <div className="animate-fade-in">
              <h3 style={{ fontSize: 18, fontWeight: 700, marginBottom: 22, color: '#f1f5f9' }}>📍 Indirizzo fornitura</h3>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
                <Row2 w1="65%" w2="35%">
                  <Field label="Indirizzo" error={fieldError('indirizzo')}>
                    <input className={fieldClass('indirizzo')} value={form.indirizzo}
                      onChange={e => update('indirizzo', e.target.value)} onBlur={e => validateField('indirizzo', e.target.value)} placeholder="Via Roma" />
                  </Field>
                  <Field label="Civico" error={fieldError('civico')}>
                    <input className={fieldClass('civico')} value={form.civico}
                      onChange={e => update('civico', e.target.value)} onBlur={e => validateField('civico', e.target.value)} placeholder="15" />
                  </Field>
                </Row2>
                <Field label="Città" error={fieldError('citta')}>
                  <input className={fieldClass('citta')} value={form.citta}
                    onChange={e => update('citta', e.target.value)} onBlur={e => validateField('citta', e.target.value)} placeholder="Milano" />
                </Field>
                <Row2 w1="55%" w2="45%">
                  <Field label="Provincia" error={fieldError('provincia')}>
                    <div style={{ position: 'relative' }}>
                      <input className={fieldClass('provincia')} value={form.provincia}
                        onChange={e => handleProvInput(e.target.value)}
                        onBlur={() => { setTimeout(() => setProvSuggestions([]), 200); validateField('provincia', form.provincia); }}
                        placeholder="Milano" autoComplete="off" />
                      {provSuggestions.length > 0 && (
                        <div style={{
                          position: 'absolute', top: '100%', left: 0, right: 0, zIndex: 10,
                          background: 'var(--bg-surface)', border: '1px solid var(--border)',
                          borderRadius: '0 0 8px 8px', maxHeight: 180, overflowY: 'auto',
                        }}>
                          {provSuggestions.map(([nome, sigla]) => (
                            <div key={sigla} onClick={() => selectProvince(nome, sigla)}
                              style={{ padding: '8px 14px', cursor: 'pointer', fontSize: 13, color: '#94a3b8', borderBottom: '1px solid var(--border)' }}>
                              <b>{sigla}</b> — {nome}
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  </Field>
                  <Field label="CAP" error={fieldError('cap')}>
                    <input className={fieldClass('cap')} value={form.cap}
                      onChange={e => update('cap', e.target.value)} onBlur={e => validateField('cap', e.target.value)} placeholder="20121" maxLength={5} />
                  </Field>
                </Row2>

                {/* Residenza ≠ Fornitura? */}
                <div style={{ marginTop: 8 }}>
                  <label style={{ fontSize: 12, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: 0.8, marginBottom: 10, display: 'block' }}>
                    Residenza e fornitura coincidono?
                  </label>
                  <div style={{ display: 'flex', gap: 12 }}>
                    {['si', 'no'].map(v => (
                      <label key={v} style={{
                        flex: 1, padding: '12px', borderRadius: 10,
                        border: form.indirizzo_coincide === v ? '2px solid #f59e0b' : '1px solid var(--border)',
                        background: form.indirizzo_coincide === v ? 'rgba(245,158,11,0.08)' : 'transparent',
                        cursor: 'pointer', textAlign: 'center', fontSize: 14, fontWeight: 600,
                        color: form.indirizzo_coincide === v ? '#f1f5f9' : '#94a3b8',
                      }}>
                        <input type="radio" name="indirizzo_coincide" value={v}
                          checked={form.indirizzo_coincide === v}
                          onChange={e => update('indirizzo_coincide', e.target.value)}
                          style={{ display: 'none' }} />
                        {v === 'si' ? '✅ Sì, coincidono' : '📍 No, residenza diversa'}
                      </label>
                    ))}
                  </div>
                </div>

                {/* Residenza diversa */}
                {form.indirizzo_coincide === 'no' && (
                  <div style={{ borderLeft: '2px solid rgba(245,158,11,0.3)', paddingLeft: 16, display: 'flex', flexDirection: 'column', gap: 18 }}>
                    <h4 style={{ fontSize: 14, fontWeight: 700, color: '#f59e0b' }}>📍 Indirizzo di residenza</h4>
                    <Row2 w1="65%" w2="35%">
                      <Field label="Indirizzo" error={fieldError('indirizzo_residenza')}>
                        <input className={fieldClass('indirizzo_residenza')} value={form.indirizzo_residenza}
                          onChange={e => update('indirizzo_residenza', e.target.value)} placeholder="Via Dante" />
                      </Field>
                      <Field label="Civico" error={fieldError('civico_residenza')}>
                        <input className={fieldClass('civico_residenza')} value={form.civico_residenza}
                          onChange={e => update('civico_residenza', e.target.value)} placeholder="42" />
                      </Field>
                    </Row2>
                    <Field label="Città" error={fieldError('citta_residenza')}>
                      <input className={fieldClass('citta_residenza')} value={form.citta_residenza}
                        onChange={e => update('citta_residenza', e.target.value)} placeholder="Monza" />
                    </Field>
                    <Row2 w1="55%" w2="45%">
                      <Field label="Provincia" error={fieldError('provincia_residenza')}>
                        <div style={{ position: 'relative' }}>
                          <input className={fieldClass('provincia_residenza')} value={form.provincia_residenza}
                            onChange={e => handleProvInput(e.target.value, true)}
                            onBlur={() => setTimeout(() => setProvResSuggestions([]), 200)}
                            placeholder="Monza e Brianza" autoComplete="off" />
                          {provResSuggestions.length > 0 && (
                            <div style={{ position: 'absolute', top: '100%', left: 0, right: 0, zIndex: 10, background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: '0 0 8px 8px', maxHeight: 180, overflowY: 'auto' }}>
                              {provResSuggestions.map(([nome, sigla]) => (
                                <div key={sigla} onClick={() => selectProvince(nome, sigla, true)}
                                  style={{ padding: '8px 14px', cursor: 'pointer', fontSize: 13, color: '#94a3b8', borderBottom: '1px solid var(--border)' }}>
                                  <b>{sigla}</b> — {nome}
                                </div>
                              ))}
                            </div>
                          )}
                        </div>
                      </Field>
                      <Field label="CAP" error={fieldError('cap_residenza')}>
                        <input className={fieldClass('cap_residenza')} value={form.cap_residenza}
                          onChange={e => update('cap_residenza', e.target.value)} placeholder="20900" maxLength={5} />
                      </Field>
                    </Row2>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* ═══ STEP 3: Dati tecnici ═══ */}
          {step === 3 && (
            <div className="animate-fade-in">
              <h3 style={{ fontSize: 18, fontWeight: 700, marginBottom: 22, color: '#f1f5f9' }}>🔧 Dati tecnici</h3>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
                {isLuce ? (
                  <Field label="Codice POD (14 cifre)" error={fieldError('codice_pod')}>
                    <input className={fieldClass('codice_pod')} value={form.codice_pod}
                      onChange={e => update('codice_pod', e.target.value.toUpperCase())}
                      onBlur={e => validateField('codice_pod', e.target.value)}
                      placeholder="IT012E00550124" style={{ textTransform: 'uppercase' }} />
                  </Field>
                ) : (
                  <Field label="Codice PDR (14 cifre)" error={fieldError('codice_pdr')}>
                    <input className={fieldClass('codice_pdr')} value={form.codice_pdr}
                      onChange={e => update('codice_pdr', e.target.value)}
                      onBlur={e => validateField('codice_pdr', e.target.value)}
                      placeholder="12345678901234" maxLength={14} />
                  </Field>
                )}
                <Field label="Modalità di pagamento" error={fieldError('modalita_pagamento')}>
                  <select className={fieldClass('modalita_pagamento')} value={form.modalita_pagamento}
                    onChange={e => update('modalita_pagamento', e.target.value)} onBlur={e => validateField('modalita_pagamento', e.target.value)}>
                    <option value="">Seleziona...</option>
                    {MODALITA_PAGAMENTO.map(m => <option key={m.value} value={m.value}>{m.label}</option>)}
                  </select>
                </Field>
                {form.modalita_pagamento === 'SDD' && (
                  <Field label="IBAN" error={fieldError('iban')}>
                    <input className={fieldClass('iban')} value={form.iban} onBlur={e => validateField('iban', e.target.value)}
                      onChange={e => update('iban', formatIBAN(e.target.value))}
                      placeholder="IT60 X054 2811 1010 0000 0123 456" />
                  </Field>
                )}
              </div>
            </div>
          )}

          {/* ═══ STEP 4: Riepilogo ═══ */}
          {step === 4 && (
            <div className="animate-fade-in">
              <h3 style={{ fontSize: 18, fontWeight: 700, marginBottom: 22, color: '#f1f5f9' }}>📋 Riepilogo</h3>

              <ReviewSection title="Offerta">
                <ReviewRow label="Fornitore" value={supplier} />
                <ReviewRow label="Tariffa" value={tariffName} />
                <ReviewRow label="Commodity" value={isLuce ? '⚡ Luce' : '🔥 Gas'} />
                {annualCost && <ReviewRow label="Costo annuo" value={`${annualCost} €`} highlight />}
              </ReviewSection>

              <ReviewSection title="Dati personali">
                <ReviewRow label="Nome" value={`${form.nome} ${form.cognome}`} />
                <ReviewRow label="Codice Fiscale" value={form.codice_fiscale} />
                <ReviewRow label="Email" value={form.email} />
                <ReviewRow label="Cellulare" value={form.cellulare} />
                <ReviewRow label="Titolo immobile" value={form.titolo_immobile} />
              </ReviewSection>

              <ReviewSection title="Fornitura">
                <ReviewRow label="Indirizzo" value={`${form.indirizzo} ${form.civico}, ${form.citta} (${form.provincia_sigla}) - ${form.cap}`} />
                {isLuce ? <ReviewRow label="POD" value={form.codice_pod} /> : <ReviewRow label="PDR" value={form.codice_pdr} />}
                <ReviewRow label="Pagamento" value={form.modalita_pagamento} />
                {form.modalita_pagamento === 'SDD' && <ReviewRow label="IBAN" value={form.iban} />}
              </ReviewSection>

              {form.indirizzo_coincide === 'no' && (
                <ReviewSection title="Residenza (diversa da fornitura)">
                  <ReviewRow label="Indirizzo" value={`${form.indirizzo_residenza} ${form.civico_residenza}, ${form.citta_residenza} (${form.provincia_residenza_sigla}) - ${form.cap_residenza}`} />
                </ReviewSection>
              )}

              {/* Privacy */}
              <div style={{
                marginTop: 20, padding: '14px 16px', borderRadius: 10,
                background: 'rgba(255,255,255,0.02)', border: '1px solid var(--border)',
              }}>
                <label style={{ display: 'flex', alignItems: 'flex-start', gap: 12, cursor: 'pointer' }}>
                  <input type="checkbox" checked={form.gdpr_privacy_accepted} 
                    onChange={e => update('gdpr_privacy_accepted', e.target.checked)}
                    style={{ marginTop: 4, width: 16, height: 16, accentColor: '#10b981' }} />
                  <span style={{ fontSize: 13, color: '#94a3b8', lineHeight: 1.6 }}>
                    Ho letto e accetto l'<a href="/privacy" target="_blank" style={{ color: '#f59e0b', textDecoration: 'none' }}>Informativa Privacy</a> e acconsento al trattamento dei miei dati personali per l'attivazione della fornitura, come richiesto dalla normativa GDPR.
                  </span>
                </label>
                {fieldError('gdpr_privacy_accepted')}
              </div>

              {error && (
                <div style={{ marginTop: 16, padding: '14px 18px', borderRadius: 10, background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.25)', color: '#fca5a5', fontSize: 14 }}>
                  ❌ {error}
                </div>
              )}
            </div>
          )}

          {/* Navigation buttons */}
          <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 28, gap: 12 }}>
            {step > 1 ? (
              <button className="btn btn-outline" onClick={prevStep}>← Indietro</button>
            ) : (
              <button className="btn btn-outline" onClick={() => navigate(-1)}>← Annulla</button>
            )}
            {step < 4 ? (
              <button className="btn btn-electric" onClick={nextStep}>Prosegui →</button>
            ) : (
              <button className="btn btn-success btn-lg" onClick={handleSubmit} disabled={sending}
                style={{ padding: '14px 36px' }}>
                {sending ? '⏳ Invio in corso...' : '✅ Conferma e invia'}
              </button>
            )}
          </div>
        </div>
      </div>
    </main>
  );
}

// ── Sub-components ─────────────────────────────────────────────────────

function Field({ label, error, children }) {
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
      <label style={{ fontSize: 12, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: 0.8, marginBottom: 8 }}>
        {label}
      </label>
      {children}
      {error}
    </div>
  );
}

function Row2({ children, w1 = '50%', w2 = '50%' }) {
  return <div style={{ display: 'flex', gap: 16 }}>{children}</div>;
}

function ReviewSection({ title, children }) {
  return (
    <div style={{ marginBottom: 20 }}>
      <h4 style={{ fontSize: 13, fontWeight: 700, color: '#f59e0b', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 10, borderBottom: '1px solid var(--border)', paddingBottom: 6 }}>
        {title}
      </h4>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>{children}</div>
    </div>
  );
}

function ReviewRow({ label, value, highlight }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
      <span style={{ color: '#64748b' }}>{label}</span>
      <span style={{ color: highlight ? '#10b981' : '#f1f5f9', fontWeight: highlight ? 700 : 500, textAlign: 'right', maxWidth: '60%' }}>
        {value || '—'}
      </span>
    </div>
  );
}
