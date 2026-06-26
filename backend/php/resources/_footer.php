</main>

<?php if (isset($schema) && $schema): ?>
<script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>

<footer>
<div style="max-width:800px;margin:0 auto">
<div style="display:flex;justify-content:center;flex-wrap:wrap;gap:6px 24px;padding:20px 0;border-top:1px solid var(--border);border-bottom:1px solid var(--border);margin-bottom:20px">
<a href="/come-funziona" style="color:var(--text-secondary);text-decoration:none;font-size:13px">Come funziona</a>
<a href="/risorse/" style="color:var(--text-secondary);text-decoration:none;font-size:13px">Risorse</a>
<a href="/offerte" style="color:var(--text-secondary);text-decoration:none;font-size:13px">Catalogo offerte</a>
<a href="/panoramica" style="color:var(--text-secondary);text-decoration:none;font-size:13px">Panoramica mercato</a>
<a href="/mercato" style="color:var(--text-secondary);text-decoration:none;font-size:13px">Storico PUN/PSV</a>
<a href="/per-llm" style="color:var(--text-muted);text-decoration:none;font-size:13px">Documentazione LLM</a>
</div>
<div style="display:flex;justify-content:center;flex-wrap:wrap;gap:4px 20px;font-size:12px">
<a href="/privacy" style="color:var(--text-secondary);text-decoration:none;font-weight:600">Privacy Policy</a>
<a href="/cookie" style="color:var(--text-secondary);text-decoration:none;font-weight:600">Cookie Policy</a>
<span style="color:var(--text-muted)">© <?= date('Y') ?> SwitchAI+</span>
<span style="color:var(--text-muted)">switchai.it</span>
</div>
</div>
</footer>

</body>
</html>
