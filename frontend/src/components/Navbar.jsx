import React, { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';

const navLinkStyle = (isActive) => ({
  padding: '8px 16px', borderRadius: 8,
  fontSize: 13, fontWeight: 600,
  color: isActive ? '#f1f5f9' : '#94a3b8',
  background: isActive ? 'rgba(255,255,255,0.06)' : 'transparent',
  textDecoration: 'none',
  transition: 'all 0.15s ease',
});

export default function Navbar() {
  const [scrolled, setScrolled] = useState(false);
  const [user, setUser] = useState(null);
  const [menuOpen, setMenuOpen] = useState(false);
  const location = useLocation();
  const token = sessionStorage.getItem('switchai_token');

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 10);
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  useEffect(() => {
    if (token) {
      fetch('/api/auth/me', { headers: { 'x-auth-token': token } })
        .then(r => r.json())
        .then(d => { if (d.nome) setUser(d); else setUser(null); })
        .catch(() => setUser(null));
    } else {
      setUser(null);
    }
    setMenuOpen(false);
  }, [token, location.pathname]);

  const currentPath = location.pathname;

  const doLogout = () => {
    sessionStorage.removeItem('switchai_token');
    setUser(null);
    setMenuOpen(false);
    window.location.href = '/';
  };

  return (
    <nav style={{
      position: 'sticky', top: 0, zIndex: 100,
      background: scrolled
        ? 'rgba(10,13,20,0.85)'
        : 'transparent',
      backdropFilter: scrolled ? 'blur(20px)' : 'none',
      WebkitBackdropFilter: scrolled ? 'blur(20px)' : 'none',
      borderBottom: scrolled ? '1px solid rgba(255,255,255,0.06)' : '1px solid transparent',
      transition: 'all 0.3s cubic-bezier(0.16,1,0.3,1)',
    }}>
      <div style={{
        maxWidth: 1040, margin: '0 auto',
        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
        padding: '14px 24px',
      }}>
        <Link to="/" style={{
          display: 'flex', alignItems: 'center', gap: 10,
          textDecoration: 'none',
        }}>
          <img src="/img/logo-76.png" alt="SwitchAI" width="38" height="38" style={{
            borderRadius: 10,
            boxShadow: '0 4px 12px rgba(245,158,11,0.3)',
          }} />
          <span style={{ fontSize: 18, fontWeight: 800, color: '#f1f5f9', letterSpacing: '-0.5px' }}>
            Switch<span style={{ color: '#f59e0b' }}>AI</span>
          </span>
        </Link>

        <div style={{ display: 'flex', gap: 4, alignItems: 'center' }}>
          <Link to="/" style={navLinkStyle(currentPath === '/')}>Confronta</Link>
          <Link to="/come-funziona" style={navLinkStyle(currentPath === '/come-funziona')}>Come funziona</Link>
          <Link to="/plus" style={navLinkStyle(currentPath === '/plus')}>SwitchAI+</Link>
          <Link to="/api-docs" style={navLinkStyle(currentPath === '/api-docs')}>API</Link>
          <a href="/risorse/" style={navLinkStyle(currentPath === '/risorse/')}>Risorse</a>
          <a href="/faq.html" style={navLinkStyle(currentPath === '/faq.html')}>FAQ</a>

          {user ? (
            <div style={{ position: 'relative', marginLeft: 8 }}>
              <button onClick={() => setMenuOpen(!menuOpen)} style={{
                display: 'flex', alignItems: 'center', gap: 6,
                padding: '6px 14px', borderRadius: 8, border: '1px solid rgba(255,255,255,0.1)',
                background: 'rgba(255,255,255,0.04)', color: '#e2e8f0', cursor: 'pointer',
                fontSize: 13, fontWeight: 600,
              }}>
                <span>👤</span>
                <span>{user.nome}</span>
                <span style={{ fontSize: 10, color: '#64748b', marginLeft: 2 }}>▼</span>
              </button>
              {menuOpen && (
                <div style={{
                  position: 'absolute', top: '100%', right: 0, marginTop: 6,
                  minWidth: 160, padding: 6, borderRadius: 10,
                  background: '#1e293b', border: '1px solid rgba(255,255,255,0.08)',
                  boxShadow: '0 12px 32px rgba(0,0,0,0.4)',
                }}>
                  <Link to="/dashboard" style={{
                    display: 'block', padding: '8px 14px', borderRadius: 6,
                    fontSize: 13, color: '#e2e8f0', textDecoration: 'none',
                  }}
                    onClick={() => setMenuOpen(false)}>
                    📊 Dashboard
                  </Link>
                  {user.admin && (
                    <Link to="/admin" style={{
                      display: 'block', padding: '8px 14px', borderRadius: 6,
                      fontSize: 13, color: '#e2e8f0', textDecoration: 'none',
                    }}
                      onClick={() => setMenuOpen(false)}>
                      ⚙️ Admin
                    </Link>
                  )}
                  <div style={{ height: 1, background: 'rgba(255,255,255,0.06)', margin: '4px 8px' }} />
                  <button onClick={doLogout} style={{
                    display: 'block', width: '100%', textAlign: 'left',
                    padding: '8px 14px', borderRadius: 6,
                    fontSize: 13, color: '#fca5a5', textDecoration: 'none',
                    background: 'none', border: 'none', cursor: 'pointer',
                  }}>
                    Esci
                  </button>
                </div>
              )}
            </div>
          ) : (
            <div style={{ display: 'flex', gap: 6, marginLeft: 8 }}>
              <Link to="/accedi" className="btn btn-outline"
                style={{ textDecoration: 'none', padding: '6px 14px', fontSize: 12 }}>
                Accedi
              </Link>
              <Link to="/registrati" className="btn btn-electric"
                style={{ textDecoration: 'none', padding: '6px 14px', fontSize: 12 }}>
                Registrati
              </Link>
            </div>
          )}
        </div>
      </div>
    </nav>
  );
}
