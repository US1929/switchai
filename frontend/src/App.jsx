import { Suspense, lazy } from 'react';
import { HelmetProvider } from 'react-helmet-async';
import Navbar from './components/Navbar.jsx';
import Footer from './components/Footer.jsx';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import Home from './pages/Home.jsx';
import ComeFunziona from './pages/ComeFunziona.jsx';
import PerLlm from './pages/PerLlm.jsx';
import Privacy from './pages/Privacy.jsx';
import Cookie from './pages/Cookie.jsx';
import Login from './pages/Login.jsx';
import Registrati from './pages/Registrati.jsx';
import ConfermaRegistrazione from './pages/ConfermaRegistrazione.jsx';
import PasswordDimenticata from './pages/PasswordDimenticata.jsx';
import ResetPassword from './pages/ResetPassword.jsx';
import Dashboard from './pages/Dashboard.jsx';
import Fornitori from './pages/Fornitori.jsx';
import Fornitore from './pages/Fornitore.jsx';
import TariffeLuce from './pages/TariffeLuce.jsx';
import ConfrontoGas from './pages/ConfrontoGas.jsx';
import CookieBanner from './components/CookieBanner.jsx';
const Analisi = lazy(() => import('./pages/Analisi.jsx'));
const Admin = lazy(() => import('./pages/Admin.jsx'));
const CalcoloRapido = lazy(() => import('./pages/CalcoloRapido.jsx'));
const Premium = lazy(() => import('./pages/Premium.jsx'));
const Plus = lazy(() => import('./pages/Plus.jsx'));
const ApiDocs = lazy(() => import('./pages/ApiDocs.jsx'));

const PageLoader = () => (
  <div style={{
    minHeight: '100vh', background: 'var(--bg-base)',
    display: 'flex', alignItems: 'center', justifyContent: 'center',
  }}>
    <div style={{ textAlign: 'center' }}>
      <div className="spinner" style={{
        width: 36, height: 36, margin: '0 auto 16px',
        border: '3px solid rgba(255,255,255,0.08)',
        borderTopColor: '#f59e0b', borderRadius: '50%',
      }} />
      <p style={{ color: '#64748b', fontSize: '0.85rem' }}>Caricamento...</p>
    </div>
  </div>
);

export default function App() {
  return (
    <HelmetProvider>
      <Router>
        <Navbar />
        <Suspense fallback={<PageLoader />}>
          <Routes>
            <Route path="/" element={<Home />} />
            <Route path="/come-funziona" element={<ComeFunziona />} />
            <Route path="/per-llm" element={<PerLlm />} />
            <Route path="/privacy" element={<Privacy />} />
            <Route path="/cookie" element={<Cookie />} />
            <Route path="/admin" element={<Admin />} />
            <Route path="/login" element={<Login />} />
            <Route path="/accedi" element={<Login />} />
            <Route path="/registrati" element={<Registrati />} />
            <Route path="/conferma-registrazione" element={<ConfermaRegistrazione />} />
            <Route path="/password-dimenticata" element={<PasswordDimenticata />} />
            <Route path="/reset-password" element={<ResetPassword />} />
            <Route path="/dashboard" element={<Dashboard />} />
            <Route path="/analisi" element={<Analisi />} />
            <Route path="/fornitori" element={<Fornitori />} />
            <Route path="/fornitori/:slug" element={<Fornitore />} />
            <Route path="/calcolo-rapido" element={<CalcoloRapido />} />
            <Route path="/premium" element={<Premium />} />
            <Route path="/plus" element={<Plus />} />
            <Route path="/api-docs" element={<ApiDocs />} />
            <Route path="/tariffe-luce" element={<TariffeLuce />} />
            <Route path="/confronto-gas" element={<ConfrontoGas />} />
          </Routes>
        </Suspense>
        <Footer />
        <CookieBanner />
      </Router>
    </HelmetProvider>
  );
}
