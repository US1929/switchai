import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';

const API_EXAMPLES = [
  { lang: 'curl', label: 'cURL', code: (key) => `curl -X POST https://www.switchai.it/api/analyze \\\n  -H "Content-Type: application/json" \\\n  -H "x-api-key: ${key}" \\\n  -d '{"commodity":"LUCE","consumo_annuo_kwh":2700,"zona":"NORD"}'` },
  { lang: 'js', label: 'JavaScript (fetch)', code: (key) => `const res = await fetch('https://www.switchai.it/api/analyze', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'x-api-key': '${key}' },
  body: JSON.stringify({ commodity:'LUCE', consumo_annuo_kwh:2700, zona:'NORD' }),
});
const data = await res.json();
console.log(data);` },
  { lang: 'py', label: 'Python', code: (key) => `import requests

res = requests.post(
    'https://www.switchai.it/api/analyze',
    headers={'Content-Type': 'application/json', 'x-api-key': '${key}'},
    json={'commodity': 'LUCE', 'consumo_annuo_kwh': 2700, 'zona': 'NORD'}
)
print(res.json())` },
];

export default function Dashboard() {
  const navigate = useNavigate();
  const token = sessionStorage.getItem('switchai_token');
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [createModal, setCreateModal] = useState(false);
  const [newKey, setNewKey] = useState(null);
  const [keyName, setKeyName] = useState('');
  const [keys, setKeys] = useState([]);
  const [showExample, setShowExample] = useState('curl');

  useEffect(() => {
    if (!token) { navigate('/accedi'); return; }
    fetch('/api/auth/me', { headers: { 'x-auth-token': token } })
      .then(r => r.json())
      .then(d => {
        if (d.error) { sessionStorage.removeItem('switchai_token'); navigate('/accedi'); return; }
        setUser(d);
        if (d.api_keys) setKeys(d.api_keys);
      })
      .catch(() => { sessionStorage.removeItem('switchai_token'); navigate('/accedi'); })
      .finally(() => setLoading(false));
  }, []);

  const createKey = async () => {
    const name = keyName.trim() || 'Default';
    const res = await fetch('/api/auth/api-keys/create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-auth-token': token },
      body: JSON.stringify({ name }),
    });
    const data = await res.json();
    if (data.api_key) {
      setNewKey(data);
      setKeys([...keys, data.key]);
      setKeyName('');
    }
  };

  const revokeKey = async (id) => {
    if (!confirm('Revocare questa chiave? Le integrazioni che la usano smetteranno di funzionare.')) return;
    await fetch(`/api/auth/api-keys/${id}`, {
      method: 'DELETE',
      headers: { 'x-auth-token': token },
    });
    setKeys(keys.filter(k => k.id !== id));
  };

  if (loading) return (
    <main style={{ padding: '100px 24px', textAlign: 'center' }}>
      <div className="spinner" style={{ width: 36, height: 36, margin: '0 auto', border: '3px solid rgba(255,255,255,0.08)', borderTopColor: '#f59e0b', borderRadius: '50%' }} />
    </main>
  );

  if (!user) return null;

  const usagePct = user.daily_quota > 0 ? Math.min(100, (user.usage_today / user.daily_quota) * 100) : 0;
  const isApiPro = user.tier === 'api_pro';
  const exampleKey = keys[0]?.key_prefix || 'sk_xxxxxxxxxxxx';

  return (
    <main style={{ padding: '60px 24px 80px', minHeight: '100vh' }}>
      <div style={{ maxWidth: 720, margin: '0 auto' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 32 }}>
          <div>
            <h1 style={{ fontSize: 24, fontWeight: 900, color: '#f1f5f9' }}>
              👋 Ciao, {user.nome}
            </h1>
            <p style={{ color: '#64748b', fontSize: 14 }}>{user.email}</p>
          </div>
          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <span style={{
              padding: '4px 12px', borderRadius: 20, fontSize: 12, fontWeight: 700,
              background: isApiPro ? 'rgba(245,158,11,0.15)' : 'rgba(16,185,129,0.1)',
              color: isApiPro ? '#f59e0b' : '#6ee7b7',
              border: `1px solid ${isApiPro ? 'rgba(245,158,11,0.2)' : 'rgba(16,185,129,0.2)'}`,
            }}>
              {isApiPro ? 'API Pro ⭐' : 'Free'}
            </span>
          </div>
        </div>

        <div className="glass-card" style={{ padding: '24px', marginBottom: 20 }}>
          <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 16 }}>📊 Utilizzo Oggi</h3>
          <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
            <div style={{ flex: 1 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 8 }}>
                <span style={{ fontSize: 13, color: '#94a3b8' }}>Chiamate API</span>
                <span style={{ fontSize: 13, fontWeight: 700, color: usagePct >= 80 ? '#fca5a5' : '#f1f5f9' }}>
                  {user.usage_today} / {user.daily_quota}
                </span>
              </div>
              <div style={{ height: 8, background: 'rgba(255,255,255,0.05)', borderRadius: 4, overflow: 'hidden' }}>
                <div style={{
                  height: '100%', borderRadius: 4,
                  width: `${usagePct}%`,
                  background: usagePct >= 80 ? 'linear-gradient(90deg, #ef4444, #dc2626)' : 'linear-gradient(90deg, #f59e0b, #d97706)',
                  transition: 'width 0.5s ease',
                }} />
              </div>
            </div>
          </div>
          <p style={{ fontSize: 12, color: '#64748b', marginTop: 12 }}>
            {isApiPro
              ? 'Hai 1.000 chiamate al giorno. Le chiamate via API key consumano lo stesso pool.'
              : 'Hai 10 chiamate al giorno. Passa a API Pro per 1.000 chiamate/giorno.'}
            {' '}<a href="/plus" style={{ color: '#f59e0b' }}>Dettagli piani</a>
          </p>
        </div>

        <div className="glass-card" style={{ padding: '24px', marginBottom: 20 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
            <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9' }}>🔑 API Keys</h3>
            <button className="btn btn-electric" style={{ fontSize: 12, padding: '6px 14px' }}
              onClick={() => setCreateModal(true)}>
              + Crea nuova
            </button>
          </div>
          {keys.length === 0 ? (
            <div style={{ padding: '16px', borderRadius: 10, background: 'rgba(59,130,246,0.06)', border: '1px solid rgba(59,130,246,0.1)' }}>
              <p style={{ fontSize: 13, color: '#94a3b8' }}>
                Crea una API key per integrare SwitchAI nei tuoi script e applicazioni.
              </p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              {keys.map(k => (
                <div key={k.id} style={{
                  display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                  padding: '12px 16px', borderRadius: 10, background: 'rgba(255,255,255,0.03)',
                  border: '1px solid rgba(255,255,255,0.06)',
                }}>
                  <div style={{ flex: 1 }}>
                    <div style={{ fontSize: 13, fontWeight: 600, color: '#e2e8f0' }}>{k.name}</div>
                    <div style={{ fontSize: 11, color: '#64748b', fontFamily: 'monospace' }}>
                      {k.key_prefix}...
                      {k.last_used_at ? ` · Ultimo uso: ${new Date(k.last_used_at).toLocaleDateString('it-IT')}` : ' · Mai usata'}
                    </div>
                  </div>
                  <button className="btn btn-outline" style={{ fontSize: 11, padding: '4px 10px', color: '#ef4444', borderColor: 'rgba(239,68,68,0.2)' }}
                    onClick={() => revokeKey(k.id)}>
                    Elimina
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="glass-card" style={{ padding: '24px' }}>
          <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 16 }}>📖 Esempi di integrazione</h3>
          <p style={{ fontSize: 12, color: '#94a3b8', marginBottom: 12 }}>
            Usa la tua API key per confrontare offerte Luce e Gas da codice. Sostituisci <code style={{ color: '#f59e0b' }}>sk_xxx</code> con la tua chiave.
          </p>
          <div style={{ display: 'flex', gap: 6, marginBottom: 12 }}>
            {API_EXAMPLES.map(ex => (
              <button key={ex.lang} onClick={() => setShowExample(ex.lang)}
                style={{
                  padding: '4px 12px', borderRadius: 6, fontSize: 12, cursor: 'pointer', border: 'none',
                  background: showExample === ex.lang ? 'rgba(245,158,11,0.2)' : 'rgba(255,255,255,0.05)',
                  color: showExample === ex.lang ? '#f59e0b' : '#94a3b8', fontWeight: showExample === ex.lang ? 700 : 400,
                }}>
                {ex.label}
              </button>
            ))}
          </div>
          <pre style={{
            padding: '16px', borderRadius: 8, background: '#0f172a', border: '1px solid rgba(255,255,255,0.06)',
            fontSize: 12, color: '#e2e8f0', overflow: 'auto', lineHeight: 1.6, whiteSpace: 'pre-wrap', wordBreak: 'break-all',
          }}>
            {API_EXAMPLES.find(ex => ex.lang === showExample)?.code(exampleKey)}
          </pre>
          <p style={{ fontSize: 11, color: '#64748b', marginTop: 12 }}>
            Endpoint disponibili: <code style={{ color: '#94a3b8' }}>POST /api/analyze</code> (confronto completo),
            <code style={{ color: '#94a3b8' }}> POST /api/analyze</code> (compatibilità).
            {' '}<Link to="/api-docs" style={{ color: '#60a5fa' }}>Vedi documentazione completa →</Link>
          </p>
        </div>

        {createModal && (
          <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.6)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 200, padding: 20 }}>
            <div className="glass-card" style={{ maxWidth: 420, width: '100%', padding: '28px 24px' }}>
              {newKey ? (
                <>
                  <div style={{ fontSize: 32, textAlign: 'center', marginBottom: 12 }}>🗝️</div>
                  <h3 style={{ fontSize: 16, fontWeight: 800, color: '#f1f5f9', textAlign: 'center', marginBottom: 16 }}>
                    Chiave API creata
                  </h3>
                  <div style={{
                    padding: '12px 16px', borderRadius: 8, background: '#0f172a', border: '1px solid rgba(255,255,255,0.06)',
                    fontFamily: 'monospace', fontSize: 12, color: '#6ee7b7', wordBreak: 'break-all', marginBottom: 12,
                  }}>
                    {newKey.api_key}
                  </div>
                  <div style={{ padding: '10px 14px', borderRadius: 8, background: 'rgba(245,158,11,0.1)', color: '#fbbf24', fontSize: 12, marginBottom: 16 }}>
                    ⚠️ Copiala ora — non verrà più mostrata. Se la perdi, dovrai crearne una nuova.
                  </div>
                  <button className="btn btn-electric" style={{ width: '100%' }} onClick={() => { setCreateModal(false); setNewKey(null); }}>
                    Ho copiato, chiudi
                  </button>
                </>
              ) : (
                <>
                  <h3 style={{ fontSize: 16, fontWeight: 800, color: '#f1f5f9', marginBottom: 16 }}>Nuova API Key</h3>
                  <p style={{ fontSize: 12, color: '#94a3b8', marginBottom: 12 }}>
                    Le chiavi API ti permettono di integrare SwitchAI nei tuoi script. Hai un limite di {user.daily_quota} chiamate/giorno.
                  </p>
                  <input className="input-field" type="text" placeholder="Nome (es: Produzione, Sviluppo)"
                    value={keyName} onChange={e => setKeyName(e.target.value)}
                    onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); createKey(); } }}
                    autoFocus />
                  <div style={{ display: 'flex', gap: 8, marginTop: 16 }}>
                    <button className="btn btn-outline" style={{ flex: 1 }} onClick={() => setCreateModal(false)}>Annulla</button>
                    <button className="btn btn-electric" style={{ flex: 1 }} onClick={createKey}>Genera</button>
                  </div>
                </>
              )}
            </div>
          </div>
        )}
      </div>
    </main>
  );
}
