import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

const TABS = [
  { id: 'traffic', label: '📊 Traffico', icon: '📊' },
  { id: 'apikeys', label: '🔑 API Keys B2B', icon: '🔑' },
  { id: 'affiliates', label: '💰 Affiliazioni', icon: '💰' },
  { id: 'sync', label: '🔄 Sync ARERA', icon: '🔄' },
];

export default function Admin() {
  const navigate = useNavigate();
  const token = sessionStorage.getItem('switchai_token');
  const [tab, setTab] = useState('traffic');

  useEffect(() => {
    if (!token) { navigate('/login'); return; }
    fetch('/api/auth/verify', { headers: { 'x-auth-token': token } })
      .then(r => r.json())
      .then(d => { if (!d.valid) { sessionStorage.removeItem('switchai_token'); navigate('/login'); } });
  }, []);

  if (!token) return null;

  return (
    <main style={{ padding: '40px 24px 80px', minHeight: '100vh' }}>
      <div style={{ maxWidth: 1000, margin: '0 auto' }}>
        {/* Header */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
          <h1 style={{ fontSize: 24, fontWeight: 900, color: '#f1f5f9' }}>⚙️ Admin</h1>
          <button
            className="btn btn-outline"
            onClick={() => { sessionStorage.removeItem('switchai_token'); navigate('/login'); }}
            style={{ fontSize: 12, padding: '6px 14px' }}
          >
            Logout
          </button>
        </div>

        {/* Tabs */}
        <div style={{ display: 'flex', gap: 8, marginBottom: 28 }}>
          {TABS.map(t => (
            <button
              key={t.id}
              onClick={() => setTab(t.id)}
              style={{
                padding: '10px 20px', borderRadius: 10, border: 'none', cursor: 'pointer',
                background: tab === t.id ? 'rgba(245,158,11,0.12)' : 'rgba(255,255,255,0.04)',
                color: tab === t.id ? '#f59e0b' : '#94a3b8',
                fontSize: 13, fontWeight: 700,
              }}
            >
              {t.label}
            </button>
          ))}
        </div>

        {/* Content */}
        {tab === 'traffic' && <TrafficTab token={token} />}
        {tab === 'apikeys' && <ApiKeysTab token={token} />}
        {tab === 'affiliates' && <AffiliatesTab token={token} />}
        {tab === 'sync' && <SyncTab token={token} />}
      </div>
    </main>
  );
}

// ── Traffic Tab ────────────────────────────────────────────────────

function TrafficTab({ token }) {
  const [data, setData] = useState(null);
  const [hours, setHours] = useState(24);

  useEffect(() => {
    fetch(`/api/stats/traffic?hours=${hours}`, { headers: { 'x-auth-token': token } })
      .then(r => r.json()).then(setData);
  }, [hours, token]);

  return (
    <div>
      <div style={{ display: 'flex', gap: 8, marginBottom: 20 }}>
        {[1, 6, 24, 168, 720].map(h => (
          <button key={h} onClick={() => setHours(h)}
            style={{
              padding: '6px 14px', borderRadius: 8, border: 'none', cursor: 'pointer',
              background: hours === h ? 'rgba(59,130,246,0.15)' : 'rgba(255,255,255,0.04)',
              color: hours === h ? '#60a5fa' : '#64748b', fontSize: 12, fontWeight: 600,
            }}>
            {h === 1 ? '1h' : h === 6 ? '6h' : h === 24 ? '24h' : h === 168 ? '7gg' : '30gg'}
          </button>
        ))}
      </div>

      {!data && <div style={{ color: '#64748b', padding: 20 }}>⏳ Caricamento...</div>}

      {data && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          {/* Total */}
          <div className="glass-card" style={{ padding: '16px 22px', textAlign: 'center' }}>
            <div style={{ fontSize: 36, fontWeight: 900, color: '#f1f5f9' }}>{data.total}</div>
            <div style={{ fontSize: 12, color: '#64748b' }}>chiamate totali</div>
          </div>

          {/* By type */}
          <div className="glass-card" style={{ padding: '16px 22px' }}>
            <h3 style={{ fontSize: 13, fontWeight: 700, color: '#f1f5f9', marginBottom: 10 }}>Per tipo</h3>
            <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
              {[
                { key: 'llm', label: '🤖 LLM', color: '#a78bfa' },
                { key: 'human', label: '👤 Umani', color: '#34d399' },
                { key: 'bot', label: '🔧 Bot', color: '#f59e0b' },
                { key: 'unknown', label: '❓ Altro', color: '#64748b' },
              ].map(t => (
                <div key={t.key} style={{
                  flex: 1, minWidth: 100, padding: 12, borderRadius: 8,
                  background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.05)', textAlign: 'center',
                }}>
                  <div style={{ fontSize: 22, fontWeight: 800, color: t.color }}>{data.by_type[t.key] || 0}</div>
                  <div style={{ fontSize: 11, color: '#64748b', marginTop: 2 }}>{t.label}</div>
                </div>
              ))}
            </div>
          </div>

          {/* By endpoint */}
          {Object.keys(data.by_endpoint).length > 0 && (
            <div className="glass-card" style={{ padding: '16px 22px' }}>
              <h3 style={{ fontSize: 13, fontWeight: 700, color: '#f1f5f9', marginBottom: 10 }}>Per endpoint</h3>
              {Object.entries(data.by_endpoint).sort((a, b) => b[1] - a[1]).map(([ep, count]) => (
                <div key={ep} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, padding: '3px 0' }}>
                  <code style={{ color: '#94a3b8' }}>{ep}</code>
                  <span style={{ color: '#f1f5f9', fontWeight: 700 }}>{count}</span>
                </div>
              ))}
            </div>
          )}

          {/* LLM visitors */}
          {Object.keys(data.llm_visitors).length > 0 && (
            <div className="glass-card best-offer" style={{ padding: '16px 22px' }}>
              <h3 style={{ fontSize: 13, fontWeight: 700, color: '#10b981', marginBottom: 10 }}>🤖 Visitatori LLM</h3>
              {Object.entries(data.llm_visitors).map(([name, info]) => (
                <div key={name} style={{
                  padding: '10px 12px', borderRadius: 8, marginBottom: 6,
                  background: 'rgba(16,185,129,0.05)', border: '1px solid rgba(16,185,129,0.1)',
                }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span style={{ fontSize: 12, fontWeight: 700, color: '#f1f5f9' }}>{name}</span>
                    <span style={{ fontSize: 18, fontWeight: 800, color: '#10b981' }}>{info.calls}</span>
                  </div>
                  <div style={{ fontSize: 10, color: '#64748b', marginTop: 2 }}>
                    Prime: {info.first_seen} · Ultima: {info.last_seen}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

// ── API Keys Tab ────────────────────────────────────────────────────

function ApiKeysTab({ token }) {
  const [clients, setClients] = useState([]);
  const [newName, setNewName] = useState('');
  const [newTier, setNewTier] = useState('basic');
  const [creating, setCreating] = useState(false);
  const [newKey, setNewKey] = useState(null);

  const loadClients = () => {
    fetch('/api/admin/api-keys', { headers: { 'x-auth-token': token } })
      .then(r => r.json()).then(setClients);
  };

  useEffect(() => { loadClients(); }, []);

  const createKey = async () => {
    if (!newName.trim()) return;
    setCreating(true);
    const r = await fetch('/api/admin/api-keys/create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-auth-token': token },
      body: JSON.stringify({ name: newName, tier: newTier }),
    });
    const d = await r.json();
    setNewKey(d.api_key);
    setNewName('');
    setCreating(false);
    loadClients();
  };

  const disableKey = async (hash) => {
    await fetch(`/api/admin/api-keys/${hash}`, { method: 'DELETE', headers: { 'x-auth-token': token } });
    loadClients();
  };

  return (
    <div>
      {/* Create form */}
      <div className="glass-card" style={{ padding: '20px 22px', marginBottom: 20 }}>
        <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 14 }}>➕ Crea nuova chiave API</h3>
        <div style={{ display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
          <div style={{ flex: 1, minWidth: 200 }}>
            <label style={{ fontSize: 11, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, marginBottom: 6, display: 'block' }}>Nome cliente</label>
            <input className="input-field" value={newName} onChange={e => setNewName(e.target.value)} placeholder="Agenzia Energia Srl" />
          </div>
          <div style={{ minWidth: 140 }}>
            <label style={{ fontSize: 11, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, marginBottom: 6, display: 'block' }}>Tier</label>
            <select className="input-field" value={newTier} onChange={e => setNewTier(e.target.value)} style={{ padding: '14px 12px' }}>
              <option value="basic">Basic — 1.000/mese</option>
              <option value="pro">Pro — 5.000/mese</option>
              <option value="premium">Premium — 20.000/mese</option>
            </select>
          </div>
          <button className="btn btn-electric" onClick={createKey} disabled={creating || !newName.trim()}>
            {creating ? '⏳' : 'Crea chiave'}
          </button>
        </div>
        {newKey && (
          <div style={{ marginTop: 14, padding: '14px 16px', borderRadius: 10, background: 'rgba(16,185,129,0.1)', border: '1px solid rgba(16,185,129,0.25)' }}>
            <div style={{ fontSize: 11, color: '#6ee7b7', fontWeight: 700, textTransform: 'uppercase', marginBottom: 4 }}>🔑 Nuova chiave (copiala ora — non verrà più mostrata)</div>
            <code style={{ fontSize: 14, color: '#10b981', fontFamily: 'monospace', wordBreak: 'break-all', userSelect: 'all' }}>{newKey}</code>
          </div>
        )}
      </div>

      {/* Clients list */}
      <div className="glass-card" style={{ padding: '16px 22px' }}>
        <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 14 }}>📋 Clienti API registrati</h3>
        {clients.length === 0 && (
          <p style={{ color: '#64748b', fontSize: 13 }}>Nessun cliente API registrato. Creane uno qui sopra.</p>
        )}
        {clients.map(c => {
          const disabled = c.disabled ?? false;
          return (
            <div key={c.api_key_hash} style={{
              padding: '12px 14px', borderRadius: 8, marginBottom: 8,
              background: disabled ? 'rgba(239,68,68,0.05)' : 'rgba(255,255,255,0.03)',
              border: `1px solid ${disabled ? 'rgba(239,68,68,0.15)' : 'rgba(255,255,255,0.06)'}`,
              opacity: disabled ? 0.6 : 1,
            }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
                <div>
                  <span style={{ fontWeight: 700, color: '#f1f5f9', fontSize: 13 }}>{c.client_name}</span>
                  <span style={{
                    marginLeft: 8, fontSize: 10, fontWeight: 600, padding: '2px 8px', borderRadius: 6,
                    background: c.tier === 'premium' ? 'rgba(245,158,11,0.15)' : c.tier === 'pro' ? 'rgba(59,130,246,0.15)' : 'rgba(255,255,255,0.06)',
                    color: c.tier === 'premium' ? '#f59e0b' : c.tier === 'pro' ? '#60a5fa' : '#94a3b8',
                  }}>
                    {c.tier?.toUpperCase()}
                  </span>
                  {disabled && <span style={{ marginLeft: 8, fontSize: 10, color: '#f87171', fontWeight: 600 }}>DISATTIVATA</span>}
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <span style={{ fontSize: 11, color: '#64748b' }}>{c.calls_current_month || 0} / {c.monthly_quota} chiamate</span>
                  {!disabled && (
                    <button
                      onClick={() => disableKey(c.api_key_hash)}
                      style={{
                        fontSize: 11, padding: '4px 10px', borderRadius: 6,
                        background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.2)',
                        color: '#f87171', cursor: 'pointer', fontWeight: 600,
                      }}
                    >
                      Disattiva
                    </button>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

// ── Affiliates Tab ────────────────────────────────────────────────

function AffiliatesTab({ token }) {
  const [links, setLinks] = useState([]);
  const [offers, setOffers] = useState([]);
  const [search, setSearch] = useState('');
  const [form, setForm] = useState({ tariff_id: '', affiliate_url: '', network: '', supplier: '', tariff_name: '', commodity: '' });
  const [saving, setSaving] = useState(false);
  const [showForm, setShowForm] = useState(false);

  const loadLinks = () => {
    fetch('/api/admin/affiliates', { headers: { 'x-auth-token': token } })
      .then(r => r.json())
      .then(d => setLinks(d.affiliates || []));
  };

  const loadOffers = async () => {
    try {
      const [luce, gas] = await Promise.all([
        fetch('/api/tariffe/luce').then(r => r.json()),
        fetch('/api/tariffe/gas').then(r => r.json()),
      ]);
      const all = [...(luce.offers || []), ...(gas.offers || [])];
      setOffers(all);
    } catch { /* offerte non disponibili */ }
  };

  useEffect(() => { loadLinks(); loadOffers(); }, []);

  const saveLink = async () => {
    if (!form.tariff_id || !form.affiliate_url) return;
    setSaving(true);
    await fetch('/api/admin/affiliates', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-auth-token': token },
      body: JSON.stringify(form),
    });
    setSaving(false);
    setShowForm(false);
    setForm({ tariff_id: '', affiliate_url: '', network: '', supplier: '', tariff_name: '', commodity: '' });
    loadLinks();
  };

  const deleteLink = async (tariffId) => {
    await fetch('/api/admin/affiliates', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json', 'x-auth-token': token },
      body: JSON.stringify({ tariff_id: tariffId }),
    });
    loadLinks();
  };

  const pickOffer = (offer) => {
    setForm({
      tariff_id: offer.id || '',
      affiliate_url: '',
      network: '',
      supplier: offer.supplier_name || '',
      tariff_name: offer.name || '',
      commodity: offer.commodity || '',
    });
    setShowForm(true);
  };

  const linkMap = new Map(links.map(l => [l.tariff_id, l]));
  const filtered = offers.filter(o => {
    if (!search) return true;
    const q = search.toLowerCase();
    return (o.supplier_name || '').toLowerCase().includes(q)
        || (o.name || '').toLowerCase().includes(q)
        || (o.id || '').toLowerCase().includes(q);
  }).slice(0, 100);

  return (
    <div>
      {/* Stats */}
      <div className="glass-card" style={{ padding: '16px 22px', marginBottom: 20, textAlign: 'center' }}>
        <div style={{ fontSize: 36, fontWeight: 900, color: '#f59e0b' }}>{links.length}</div>
        <div style={{ fontSize: 12, color: '#64748b' }}>link di affiliazione attivi</div>
      </div>

      {/* Add form */}
      <div style={{ marginBottom: 20 }}>
        {!showForm ? (
          <button className="btn btn-electric" onClick={() => setShowForm(true)} style={{ fontSize: 13 }}>
            ➕ Nuovo link affiliazione
          </button>
        ) : (
          <div className="glass-card" style={{ padding: '16px 22px' }}>
            <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 14 }}>➕ Aggiungi/Modifica link</h3>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                <div style={{ flex: 1, minWidth: 200 }}>
                  <label style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>Tariff ID</label>
                  <input className="input-field" value={form.tariff_id} onChange={e => setForm({...form, tariff_id: e.target.value})} placeholder="ff96f52a-..." />
                </div>
                <div style={{ flex: 1, minWidth: 120 }}>
                  <label style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>Network</label>
                  <input className="input-field" value={form.network} onChange={e => setForm({...form, network: e.target.value})} placeholder="tradedoubler, awin..." />
                </div>
              </div>
              <div>
                <label style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>URL Affiliazione</label>
                <input className="input-field" value={form.affiliate_url} onChange={e => setForm({...form, affiliate_url: e.target.value})} placeholder="https://tracking.com/redirect?offer=..." style={{ width: '100%' }} />
              </div>
              <div style={{ display: 'flex', gap: 10 }}>
                <button className="btn btn-electric" onClick={saveLink} disabled={saving || !form.tariff_id || !form.affiliate_url} style={{ fontSize: 13 }}>
                  {saving ? '⏳' : '💾 Salva'}
                </button>
                <button className="btn btn-outline" onClick={() => setShowForm(false)} style={{ fontSize: 13 }}>Annulla</button>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Offerte + link */}
      <div className="glass-card" style={{ padding: '16px 22px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14, flexWrap: 'wrap', gap: 10 }}>
          <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9' }}>📋 Offerte e link</h3>
          <input
            className="input-field"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="🔍 Cerca fornitore o tariffa..."
            style={{ fontSize: 12, padding: '8px 12px', width: 250 }}
          />
        </div>

        {/* Lista link esistenti */}
        {links.length > 0 && (
          <div style={{ marginBottom: 20 }}>
            <div style={{ fontSize: 11, color: '#f59e0b', fontWeight: 600, textTransform: 'uppercase', marginBottom: 8 }}>
              💰 {links.length} link di affiliazione
            </div>
            {links.map(l => (
              <div key={l.tariff_id} style={{
                padding: '10px 12px', borderRadius: 8, marginBottom: 6,
                background: 'rgba(245,158,11,0.05)', border: '1px solid rgba(245,158,11,0.15)',
                display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8,
              }}>
                <div style={{ flex: 1, minWidth: 200 }}>
                  <div style={{ fontSize: 12, fontWeight: 700, color: '#f1f5f9' }}>
                    {l.supplier} — {l.tariff_name}
                    {l.network && <span style={{ marginLeft: 6, fontSize: 9, color: '#64748b', background: 'rgba(255,255,255,0.06)', padding: '1px 6px', borderRadius: 4 }}>{l.network}</span>}
                  </div>
                  <a href={l.affiliate_url} target="_blank" rel="noreferrer" style={{ fontSize: 10, color: '#f59e0b', wordBreak: 'break-all' }}>
                    {l.affiliate_url.length > 80 ? l.affiliate_url.slice(0, 80) + '...' : l.affiliate_url}
                  </a>
                </div>
                <button
                  onClick={() => deleteLink(l.tariff_id)}
                  style={{
                    fontSize: 11, padding: '4px 10px', borderRadius: 6,
                    background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.2)',
                    color: '#f87171', cursor: 'pointer', fontWeight: 600, flexShrink: 0,
                  }}
                >
                  Rimuovi
                </button>
              </div>
            ))}
          </div>
        )}

        {/* Offerte disponibili */}
        <div style={{ fontSize: 11, color: '#64748b', fontWeight: 600, textTransform: 'uppercase', marginBottom: 8 }}>
          📦 {offers.length > 0 ? `${offers.length} offerte caricate` : 'Nessuna offerta caricata — esegui ARERA sync'}
        </div>
        {filtered.map(o => {
          const linked = linkMap.get(o.id);
          return (
            <div key={o.id} style={{
              padding: '8px 12px', borderRadius: 8, marginBottom: 4, fontSize: 12,
              background: linked ? 'rgba(16,185,129,0.05)' : 'rgba(255,255,255,0.02)',
              border: `1px solid ${linked ? 'rgba(16,185,129,0.15)' : 'rgba(255,255,255,0.04)'}`,
              display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8,
            }}>
              <div style={{ flex: 1, minWidth: 180 }}>
                <span style={{ fontWeight: 600, color: '#f1f5f9' }}>{o.supplier_name}</span>
                <span style={{ color: '#94a3b8', marginLeft: 4 }}>— {o.name}</span>
                <span style={{
                  marginLeft: 6, fontSize: 9, fontWeight: 600, padding: '1px 6px', borderRadius: 4,
                  background: o.type === 'FISSO' ? 'rgba(59,130,246,0.12)' : 'rgba(168,85,247,0.12)',
                  color: o.type === 'FISSO' ? '#60a5fa' : '#a78bfa',
                }}>
                  {o.type === 'FISSO' ? 'FISSO' : 'VARIABILE'}
                </span>
                <span style={{ marginLeft: 4, fontSize: 9, color: '#64748b' }}>{o.commodity}</span>
                {linked && <span style={{ marginLeft: 6, fontSize: 9, color: '#6ee7b7', fontWeight: 600 }}>💰 AFFILIATO</span>}
              </div>
              <button
                onClick={() => linked ? deleteLink(o.id) : pickOffer(o)}
                style={{
                  fontSize: 11, padding: '4px 10px', borderRadius: 6, cursor: 'pointer', fontWeight: 600, flexShrink: 0,
                  background: linked ? 'rgba(239,68,68,0.1)' : 'rgba(245,158,11,0.1)',
                  border: `1px solid ${linked ? 'rgba(239,68,68,0.2)' : 'rgba(245,158,11,0.2)'}`,
                  color: linked ? '#f87171' : '#f59e0b',
                }}
              >
                {linked ? 'Rimuovi' : '+ Affilia'}
              </button>
            </div>
          );
        })}
      </div>
    </div>
  );
}

// ── Sync ARERA Tab ────────────────────────────────────────────────

function SyncTab({ token }) {
  const [status, setStatus] = useState(null);
  const [syncing, setSyncing] = useState(false);
  const [stats, setStats] = useState(null);

  useEffect(() => {
    fetch('/api/admin/data-stats', { headers: { 'x-auth-token': token } })
      .then(r => r.json()).then(setStats).catch(() => {});
  }, [token]);

  const triggerSync = async () => {
    setSyncing(true);
    setStatus('Avvio sync...');
    try {
      const r = await fetch('/api/admin/sync-arera', {
        method: 'POST',
        headers: { 'x-auth-token': token },
      });
      const d = await r.json();
      setStatus(d.message || 'Sync completato');
    } catch {
      setStatus('Errore: sync fallito');
    }
    setSyncing(false);
  };

  return (
    <div>
      <div className="glass-card" style={{ padding: '20px 24px', marginBottom: 20 }}>
        <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 12 }}>🔄 Sincronizzazione offerte ARERA</h3>
        <p style={{ fontSize: 12, color: '#94a3b8', marginBottom: 16, lineHeight: 1.6 }}>
          Scarica le offerte ufficiali dal Portale Offerte ARERA (ilportaleofferte.it) e le salva in 
          <code style={{ background: 'rgba(255,255,255,0.04)', padding: '1px 4px', borderRadius: 3 }}>data/offerte/db-offerte-luce.json</code> e 
          <code style={{ background: 'rgba(255,255,255,0.04)', padding: '1px 4px', borderRadius: 3 }}>db-offerte-gas.json</code>.
          {' '}Circa 5.000+ offerte da tutti i fornitori italiani. Il processo può richiedere 30-60 secondi.
        </p>
        <button
          className="btn btn-electric"
          onClick={triggerSync}
          disabled={syncing}
          style={{ fontSize: 13 }}
        >
          {syncing ? '⏳ Sync in corso...' : '🔄 Avvia sincronizzazione'}
        </button>
        {status && (
          <div style={{
            marginTop: 12, padding: '10px 14px', borderRadius: 8,
            background: status.includes('Errore') ? 'rgba(239,68,68,0.08)' : 'rgba(16,185,129,0.08)',
            border: `1px solid ${status.includes('Errore') ? 'rgba(239,68,68,0.2)' : 'rgba(16,185,129,0.2)'}`,
            fontSize: 12, color: status.includes('Errore') ? '#f87171' : '#6ee7b7',
          }}>
            {status}
          </div>
        )}
      </div>

      {stats && (
        <div className="glass-card" style={{ padding: '16px 22px' }}>
          <h3 style={{ fontSize: 13, fontWeight: 700, color: '#f1f5f9', marginBottom: 10 }}>📊 Stato sistema</h3>
          <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
            {[
              { label: 'Utenti', value: stats.users, color: '#60a5fa' },
              { label: 'API Keys', value: stats.api_keys, color: '#a78bfa' },
              { label: 'Rate Logs', value: stats.rate_logs, color: '#f59e0b' },
              { label: 'Affiliati', value: stats.affiliates, color: '#10b981' },
              { label: 'MySQL', value: stats.mysql === 'connected' ? '✅' : '❌', color: stats.mysql === 'connected' ? '#10b981' : '#ef4444' },
            ].map(s => (
              <div key={s.label} style={{
                flex: 1, minWidth: 80, padding: 12, borderRadius: 8, textAlign: 'center',
                background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.05)',
              }}>
                <div style={{ fontSize: 20, fontWeight: 800, color: s.color }}>{s.value}</div>
                <div style={{ fontSize: 10, color: '#64748b', marginTop: 2 }}>{s.label}</div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
