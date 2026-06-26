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
  const location = useLocation();

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 10);
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  const currentPath = location.pathname;

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
        {/* Logo */}
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

        {/* Nav links */}
        <div style={{ display: 'flex', gap: 4, alignItems: 'center' }}>
          <Link to="/" style={navLinkStyle(currentPath === '/')}>Confronta</Link>
          <Link to="/come-funziona" style={navLinkStyle(currentPath === '/come-funziona')}>Come funziona</Link>
          <a href="/per-llm" style={navLinkStyle(currentPath === '/per-llm')}>Per LLM</a>
          <a href="/risorse/" style={navLinkStyle(currentPath === '/risorse/')}>Risorse</a>
          <a href="/faq.html" style={navLinkStyle(currentPath === '/faq.html')}>FAQ</a>
        </div>
      </div>
    </nav>
  );
}
