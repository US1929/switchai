import React, { useState, useEffect, useRef } from 'react';

// Dati fittizi per la demo — non sono dati reali
const DEMO_NAME = 'Mario Rossi';
const DEMO_ADDRESS = 'Via Roma 15, 20121 Milano (MI)';
const DEMO_POD = 'IT001E123456789';
const DEMO_FORNITORE = 'Enel Energia';
const DEMO_CONSUMO = '2.700 kWh/anno';
const DEMO_SPESA = '650 €/anno';

const MESSAGES = [
  {
    role: 'user',
    text: 'Come posso usare SwitchAI per analizzare la mia bolletta?',
    delay: 200,
  },
  {
    role: 'assistant',
    text: 'Ecco come fare:\n\n🔌 **Claude**: Impostazioni → Connettori → Aggiungi connettore personalizzato → incolla `https://www.switchai.it/mcp`\n\n🧩 **ChatGPT**: Esplora GPT → Crea → Azioni → Importa da URL → incolla `https://www.switchai.it/openapi.json`\n\n📋 **Gemini o altri LLM**: carica la bolletta, copia il prompt da switchai.it e incolla la risposta JSON.\n\nDopo aver collegato il connettore, mandami la bolletta e ci penso io!',
    delay: 1200,
  },
  {
    role: 'user',
    text: 'Ottimo! Ecco la mia bolletta Enel. Puoi analizzarla e dirmi quanto posso risparmiare? 📎',
    attachment: true,
    delay: 800,
  },
  {
    role: 'assistant',
    text: 'Certo! Prima di tutto: i tuoi dati personali verranno elaborati da Anthropic (Claude) secondo la loro privacy policy. SwitchAI riceverà solo dati numerici (consumi, spesa, zona) e non conserverà i tuoi dati personali dopo la sessione.',
    delay: 600,
  },
  {
    role: 'user',
    text: 'Ok, va bene. Ecco la bolletta 📎',
    delay: 400,
  },
  {
    role: 'assistant',
    text: 'Perfetto, ho analizzato la bolletta. Ecco cosa ho trovato:\n\n📄 **Dati estratti:**\n• Intestatario: ' + DEMO_NAME + '\n• Indirizzo: ' + DEMO_ADDRESS + '\n• POD: ' + DEMO_POD + '\n• Fornitore: ' + DEMO_FORNITORE + '\n• Consumo annuo: ' + DEMO_CONSUMO + '\n• Spesa annua: ' + DEMO_SPESA + '\n• Zona: NORD\n\nOra chiamo SwitchAI per confrontare le offerte... ⚡',
    delay: 1200,
  },
  {
    role: 'assistant',
    text: 'Ecco il risultato del confronto:\n\n🔍 **Migliore offerta trovata:**\n\n🏆 **Fastweb Energia FIX** — Prezzo Fisso\n• Costo annuo: **457 €/anno**\n• Risparmio: **193 €/anno (30%)**\n• Circa **16 €/mese** in meno\n\n🥈 **Octopus Energy Fissa** — 473 €/anno (risparmio 177 €)\n🥉 **NeN Special 48** — 502 €/anno (risparmio 148 €)\n\n✅ **CONVIENE CAMBIARE** — risparmio >50€/anno e >5%',
    delay: 1500,
  },
  {
    role: 'assistant',
    text: 'Ho i dati della tua bolletta:\n• ' + DEMO_NAME + '\n• ' + DEMO_ADDRESS + '\n• POD ' + DEMO_POD + '\n• ' + DEMO_CONSUMO + ' — ' + DEMO_SPESA + '\n\n**Vuoi che precompili il modulo di attivazione per Fastweb Energia FIX?**\n\nDovrai comunque essere tu a verificare i dati e cliccare su **Invia**. Riceverai una mail di conferma prima dell\'inoltro della richiesta.',
    delay: 1000,
  },
  {
    role: 'user',
    text: 'Sì, precompila il modulo!',
    delay: 300,
  },
  {
    role: 'assistant',
    text: '✅ Fatto! Ecco il link: [Apri modulo precompilato →](https://www.switchai.it/sottoscrizione)\n\nVerifica i dati, completa i campi mancanti e clicca Invia. A presto! 🎉',
    delay: 500,
  },
];

const AVATARS = {
  user: { icon: '👤', bg: 'rgba(59,130,246,0.12)', label: 'Tu' },
  assistant: { icon: '🤖', bg: 'rgba(245,158,11,0.12)', label: 'Claude' },
};

export default function ChatDemo({ isOpen, onClose }) {
  const [visibleMsgs, setVisibleMsgs] = useState([]);
  const [done, setDone] = useState(false);
  const containerRef = useRef(null);

  useEffect(() => {
    if (!isOpen) {
      setVisibleMsgs([]);
      setDone(false);
      return;
    }
    let cancelled = false;
    let timeoutIds = [];

    const showMessages = async () => {
      let accDelay = 0;
      for (let i = 0; i < MESSAGES.length; i++) {
        accDelay += MESSAGES[i].delay;
        const idx = i;
        const tid = setTimeout(() => {
          if (!cancelled) {
            setVisibleMsgs(prev => [...prev, idx]);
            if (idx === MESSAGES.length - 1) setDone(true);
          }
        }, accDelay);
        timeoutIds.push(tid);
      }
    };

    // Piccolo ritardo iniziale per l'apertura della modale
    const initTid = setTimeout(showMessages, 300);
    timeoutIds.push(initTid);

    return () => {
      cancelled = true;
      timeoutIds.forEach(clearTimeout);
    };
  }, [isOpen]);

  // Auto-scroll
  useEffect(() => {
    if (containerRef.current) {
      containerRef.current.scrollTop = containerRef.current.scrollHeight;
    }
  }, [visibleMsgs]);

  if (!isOpen) return null;

  return (
    <div
      onClick={onClose}
      style={{
        position: 'fixed', inset: 0, zIndex: 1000,
        background: 'rgba(0,0,0,0.7)', backdropFilter: 'blur(8px)',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        padding: 20,
      }}
    >
      <div
        onClick={e => e.stopPropagation()}
        style={{
          width: '100%', maxWidth: 600, maxHeight: '85vh',
          background: '#0d1117', borderRadius: 16,
          border: '1px solid rgba(255,255,255,0.08)',
          boxShadow: '0 25px 60px rgba(0,0,0,0.5)',
          display: 'flex', flexDirection: 'column',
          overflow: 'hidden',
        }}
      >
        {/* Header */}
        <div style={{
          padding: '14px 20px', borderBottom: '1px solid rgba(255,255,255,0.06)',
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <div style={{
              width: 32, height: 32, borderRadius: 8,
              background: 'linear-gradient(135deg, #f59e0b, #d97706)',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontSize: 16,
            }}>⚡</div>
            <div>
              <div style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9' }}>Demo — Come funziona SwitchAI con Claude</div>
              <div style={{ fontSize: 11, color: '#64748b' }}>Simulazione con dati fittizi</div>
            </div>
          </div>
          <button
            onClick={onClose}
            style={{
              background: 'none', border: 'none', color: '#64748b',
              cursor: 'pointer', fontSize: 20, padding: 4,
            }}
          >
            ✕
          </button>
        </div>

        {/* Messages */}
        <div
          ref={containerRef}
          style={{
            flex: 1, overflowY: 'auto', padding: '16px 20px',
            display: 'flex', flexDirection: 'column', gap: 12,
          }}
        >
          {visibleMsgs.map(idx => {
            const msg = MESSAGES[idx];
            const avatar = AVATARS[msg.role];
            return (
              <div
                key={idx}
                className="animate-fade-in"
                style={{
                  display: 'flex', gap: 10,
                  flexDirection: msg.role === 'user' ? 'row-reverse' : 'row',
                }}
              >
                <div style={{
                  width: 30, height: 30, borderRadius: 8, flexShrink: 0,
                  background: avatar.bg,
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  fontSize: 16,
                }}>
                  {avatar.icon}
                </div>
                <div style={{ maxWidth: '80%' }}>
                  <div style={{
                    fontSize: 10, color: '#64748b', marginBottom: 3,
                    textAlign: msg.role === 'user' ? 'right' : 'left',
                  }}>
                    {avatar.label}
                  </div>
                  <div style={{
                    padding: '10px 14px', borderRadius: 12,
                    background: msg.role === 'user'
                      ? 'rgba(59,130,246,0.15)'
                      : 'rgba(255,255,255,0.04)',
                    border: msg.role === 'user'
                      ? '1px solid rgba(59,130,246,0.2)'
                      : '1px solid rgba(255,255,255,0.06)',
                    fontSize: 12, lineHeight: 1.7, color: '#d1d5db',
                    whiteSpace: 'pre-wrap',
                  }}>
                    <span style={{ color: msg.role === 'user' ? '#93c5fd' : '#d1d5db' }}>
                      {msg.text.split('\n').map((line, i) => (
                        <React.Fragment key={i}>
                          {line}
                          {i < msg.text.split('\n').length - 1 && <br />}
                        </React.Fragment>
                      ))}
                    </span>
                    {msg.attachment && (
                      <div style={{
                        marginTop: 8, padding: '8px 12px', borderRadius: 8,
                        background: 'rgba(0,0,0,0.3)', border: '1px solid rgba(255,255,255,0.08)',
                        display: 'flex', alignItems: 'center', gap: 8, fontSize: 11,
                      }}>
                        <span>📄</span>
                        <span style={{ color: '#94a3b8' }}>bolletta_enel.pdf</span>
                        <span style={{ color: '#64748b', fontSize: 10 }}>124 KB</span>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            );
          })}

          {/* Typing indicator */}
          {!done && visibleMsgs.length > 0 && visibleMsgs.length < MESSAGES.length && (
            <div style={{ display: 'flex', gap: 10 }}>
              <div style={{
                width: 30, height: 30, borderRadius: 8, flexShrink: 0,
                background: AVATARS.assistant.bg,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontSize: 16,
              }}>
                {AVATARS.assistant.icon}
              </div>
              <div style={{
                padding: '10px 14px', borderRadius: 12,
                background: 'rgba(255,255,255,0.04)',
                border: '1px solid rgba(255,255,255,0.06)',
              }}>
                <TypingDots />
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div style={{
          padding: '12px 20px', borderTop: '1px solid rgba(255,255,255,0.06)',
          textAlign: 'center', fontSize: 11, color: '#64748b',
        }}>
          ⚠️ Dati fittizi a scopo dimostrativo — non sono bollette reali
        </div>
      </div>
    </div>
  );
}

function TypingDots() {
  return (
    <div style={{ display: 'flex', gap: 4, padding: '2px 0' }}>
      {[0, 1, 2].map(i => (
        <div
          key={i}
          style={{
            width: 6, height: 6, borderRadius: '50%',
            background: '#94a3b8',
            animation: `pulse 1.4s ease-in-out ${i * 0.2}s infinite`,
          }}
        />
      ))}
      <style>{`@keyframes pulse { 0%,60%,100% { opacity:0.3; transform:scale(1); } 30% { opacity:1; transform:scale(1.3); } }`}</style>
    </div>
  );
}
