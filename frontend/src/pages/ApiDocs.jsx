import { useEffect } from 'react';

export default function ApiDocs() {
  useEffect(() => {
    const css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = 'https://unpkg.com/swagger-ui-dist@5/swagger-ui.css';
    document.head.appendChild(css);

    const script = document.createElement('script');
    script.src = 'https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js';
    script.onload = () => {
      window.SwaggerUIBundle({
        url: '/openapi.json',
        dom_id: '#swagger-ui',
        presets: [window.SwaggerUIBundle.presets.apis],
        layout: 'BaseLayout',
        defaultModelsExpandDepth: -1,
        deepLinking: true,
        docExpansion: 'list',
        filter: true,
        tryItOutEnabled: false,
      });
    };
    document.body.appendChild(script);

    return () => {
      document.head.removeChild(css);
      document.body.removeChild(script);
    };
  }, []);

  return (
    <main style={{ minHeight: '100vh', background: '#141b2d' }}>
      <div style={{ maxWidth: 1200, margin: '0 auto', padding: '24px' }}>
        <div style={{ marginBottom: 20 }}>
          <h1 style={{ fontSize: 22, fontWeight: 800, color: '#f1f5f9', margin: 0 }}>API Reference</h1>
          <p style={{ fontSize: 13, color: '#64748b', margin: '4px 0 0' }}>
            SwitchAI API — Confronto tariffe Luce e Gas del mercato libero italiano
          </p>
        </div>
        <div id="swagger-ui" style={{ background: '#fff', borderRadius: 8, overflow: 'hidden' }} />
      </div>
    </main>
  );
}
