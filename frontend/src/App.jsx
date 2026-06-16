import React from 'react';
import Navbar from './components/Navbar.jsx';
import Footer from './components/Footer.jsx';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import Activate from './pages/Activate.jsx';
import Home from './pages/Home.jsx';
import Analisi from './pages/Analisi.jsx';
import Sottoscrizione from './pages/Sottoscrizione.jsx';
import ComeFunziona from './pages/ComeFunziona.jsx';
import PerLlm from './pages/PerLlm.jsx';
import Conferma from './pages/Conferma.jsx';
import Privacy from './pages/Privacy.jsx';
import Cookie from './pages/Cookie.jsx';
import Admin from './pages/Admin.jsx';
import Login from './pages/Login.jsx';
import CookieBanner from './components/CookieBanner.jsx';

export default function App() {
  return (
    <Router>
      <Navbar />
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/come-funziona" element={<ComeFunziona />} />
        <Route path="/per-llm" element={<PerLlm />} />
        <Route path="/conferma" element={<Conferma />} />
        <Route path="/privacy" element={<Privacy />} />
        <Route path="/cookie" element={<Cookie />} />
        <Route path="/admin" element={<Admin />} />
        <Route path="/login" element={<Login />} />
        <Route path="/sottoscrizione" element={<Sottoscrizione />} />
        <Route path="/analisi" element={<Analisi />} />
        <Route path="/activate" element={<Activate />} />
      </Routes>
      <Footer />
      <CookieBanner />
    </Router>
  );
}
