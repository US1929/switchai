<?php
/**
 * _header.php — Template testata risorse SwitchAI.
 * Include nav, meta tag, CSS design system.
 *
 * @param string $title       Titolo pagina (default: "Risorse")
 * @param string $description Meta description (default: "Guide complete")
 * @param string $canonical   URL canonica (default: current path)
 */

// Previene cache del browser (nav/menu sempre aggiornato)
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

$pageTitle  = $title ?? 'Risorse';
$pageDesc   = $description ?? 'Guide, analisi e approfondimenti su bollette luce e gas, confronto offerte, risparmio energetico.';
$pageCanon  = $canonical ?? 'https://www.switchai.it' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
?><!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?> | SwitchAI</title>
<meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
<link rel="canonical" href="<?= htmlspecialchars($pageCanon) ?>">
<meta name="robots" content="index,follow">
<meta name="theme-color" content="#070a12">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?> | SwitchAI">
<meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
<meta property="og:url" content="<?= htmlspecialchars($pageCanon) ?>">
<meta property="og:site_name" content="SwitchAI">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?> | SwitchAI">
<meta name="twitter:description" content="<?= htmlspecialchars($pageDesc) ?>">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg-base:#070a12;--bg-surface:#0d1120;--bg-card:#131827;
  --border:rgba(255,255,255,0.06);--border-light:rgba(255,255,255,0.10);
  --text-primary:#f1f5f9;--text-secondary:#94a3b8;--text-muted:#64748b;
  --electric:#f59e0b;--gas:#3b82f6;--save:#10b981;--accent:#a78bfa;
  --radius-md:12px;--radius-lg:16px;--radius-xl:20px;
  --ease-out:cubic-bezier(0.16,1,0.3,1);
}
html{font-size:16px}
body{background:var(--bg-base);color:var(--text-primary);font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;line-height:1.6;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
a{text-decoration:none;transition:color .2s}
main a{color:var(--electric)}
main a:hover{color:#fbbf24}
h1,h2,h3{font-weight:800;letter-spacing:-0.02em;line-height:1.2}
h1{font-size:clamp(28px,4vw,40px);margin-bottom:16px}
h2{font-size:clamp(22px,3vw,30px);margin:32px 0 12px;color:var(--text-primary)}
h3{font-size:clamp(18px,2.5vw,22px);margin:24px 0 8px;color:var(--text-primary)}
p{margin-bottom:16px;color:var(--text-secondary);font-size:15px;line-height:1.7}
main.container{padding-top:40px}
.container{max-width:800px;margin:0 auto;padding:0 24px}
.gradient-text{background:linear-gradient(135deg,var(--electric),var(--accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.badge{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:600;padding:4px 10px;border-radius:999px;background:rgba(245,158,11,.12);color:var(--electric)}
.card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;margin-bottom:16px}
.card:hover{border-color:var(--border-light);background:var(--bg-surface)}
.card h3{margin-top:0}
.tag{display:inline-block;font-size:11px;font-weight:600;padding:2px 8px;border-radius:4px;margin-right:6px;margin-bottom:6px;color:var(--text-muted);background:rgba(255,255,255,.04)}
.tag.luce{color:var(--electric);background:rgba(245,158,11,.1)}
.tag.gas{color:var(--gas);background:rgba(59,130,246,.1)}
.tag.guida{color:var(--accent);background:rgba(167,139,250,.1)}
ul,ol{padding-left:20px;margin-bottom:16px;color:var(--text-secondary);font-size:15px}
li{margin-bottom:6px}
strong{color:var(--text-primary)}
code{background:rgba(255,255,255,.05);padding:2px 6px;border-radius:4px;font-size:14px;color:#60a5fa}
table{width:100%;border-collapse:collapse;margin:16px 0;font-size:14px}
th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--border);color:var(--text-secondary)}
th{color:var(--text-primary);font-weight:700;background:rgba(255,255,255,.03)}
tr:last-child td{border-bottom:none}
blockquote{border-left:3px solid var(--electric);padding:12px 16px;margin:16px 0;background:rgba(245,158,11,.04);border-radius:0 var(--radius-md) var(--radius-md) 0;color:var(--text-secondary);font-size:14px}
blockquote strong{color:var(--text-primary)}
.cta{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;font-size:14px;font-weight:700;border-radius:var(--radius-md);border:none;cursor:pointer;transition:all .25s var(--ease-out);text-decoration:none}
.cta-primary{background:linear-gradient(135deg,var(--electric),#d97706);color:#030712}
.cta-primary:hover{transform:translateY(-1px);box-shadow:0 8px 32px rgba(245,158,11,.25)}
.cta-outline{background:transparent;border:1px solid var(--border-light);color:var(--text-primary)}
.cta-outline:hover{background:var(--bg-card);border-color:var(--electric)}
footer{border-top:1px solid var(--border);padding:40px 20px;margin-top:60px;text-align:center;color:var(--text-muted);font-size:13px}
footer a{color:var(--text-secondary)}
footer a:hover{color:var(--text-primary)}
.breadcrumb{font-size:13px;color:var(--text-muted);margin-bottom:24px;padding-top:24px}
.breadcrumb a{color:var(--text-muted)}
.breadcrumb a:hover{color:var(--electric)}
.breadcrumb span{margin:0 6px;color:var(--text-muted)}
hr{border:none;border-top:1px solid var(--border);margin:32px 0}

/* Nav unified con React Navbar */
nav{position:sticky;top:0;z-index:100;background:linear-gradient(135deg,rgba(7,10,18,.92),rgba(13,17,32,.88));backdrop-filter:blur(28px) saturate(1.2);-webkit-backdrop-filter:blur(28px) saturate(1.2);border-bottom:1px solid rgba(255,255,255,.06);font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
nav .container{display:flex;align-items:center;justify-content:space-between;max-width:1040px;margin:0 auto;padding:14px 24px;position:relative}
nav .logo{display:flex;align-items:center;gap:10px;text-decoration:none}
nav .logo:hover{color:var(--electric)}
nav .nav-links{display:flex;gap:4px;align-items:center}
nav .nav-links>a,nav .nav-links .nav-btn{padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;color:var(--text-secondary);text-decoration:none;transition:all .2s ease;background:transparent;border:none;cursor:pointer;font-family:inherit}
nav .nav-links>a:hover,nav .nav-links .nav-btn:hover{color:#e2e8f0}
nav .nav-links>a.active{color:var(--text-primary);background:rgba(255,255,255,.06)}
nav .nav-links a.area-btn{padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#a78bfa,#8b5cf6);border:none}

/* Dropdown Risorse */
.dropdown{position:relative}
.dropdown-menu{display:none;position:absolute;top:100%;right:0;margin-top:8px;min-width:260px;padding:8px;background:rgba(13,17,32,.98);border:1px solid rgba(255,255,255,.08);border-radius:12px;backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);box-shadow:0 12px 40px rgba(0,0,0,.4);z-index:200}
.dropdown.open .dropdown-menu{display:block}
.dropdown-menu .group-label{font-size:9px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;font-weight:700;padding:6px 12px 4px}
.dropdown-menu a{display:block;padding:8px 12px;border-radius:8px;font-size:13px;color:var(--text-secondary);text-decoration:none;transition:all .15s ease}
.dropdown-menu a:hover{background:rgba(255,255,255,.04);color:var(--text-primary)}
.dropdown-menu .divider{height:1px;background:rgba(255,255,255,.06);margin:6px 0}
.dropdown-arrow{font-size:9px;opacity:.6;margin-left:4px}

@media(max-width:640px){
nav .container{padding:12px 16px}
nav .nav-links{gap:2px}
nav .nav-links>a,nav .nav-links .nav-btn{padding:6px 10px;font-size:12px}
nav .area-btn span.label{display:none}
.dropdown-menu{right:auto;left:0;min-width:220px}
}
</style>
</head>
<body>

<nav>
<div id="scroll-progress" style="position:absolute;bottom:-1px;left:0;height:1px;width:0;background:linear-gradient(90deg,#f59e0b,#f97316,#ec4899);transition:width .1s linear;z-index:101"></div>
<div class="container">
<a href="/" class="logo">
<img src="/img/logo-76.png" alt="SwitchAI" width="38" height="38" style="border-radius:10px;box-shadow:0 4px 12px rgba(245,158,11,0.3)">
<span style="font-size:18px;font-weight:800;color:#f1f5f9;letter-spacing:-0.5px">Switch<span style="color:#f59e0b">AI</span><span style="color:#10b981;font-size:12px;font-weight:700;margin-left:2px;vertical-align:super">+</span></span>
</a>
<div class="nav-links">
<a href="/">Confronta</a>
<a href="/come-funziona">Come funziona</a>
<div class="dropdown" id="risorse-dropdown">
<button class="nav-btn" onclick="document.getElementById('risorse-dropdown').classList.toggle('open')">Risorse<span class="dropdown-arrow">▾</span></button>
<div class="dropdown-menu">
<div class="group-label">Guide</div>
<a href="/risorse/come-funziona-bolletta-luce">Bolletta luce</a>
<a href="/risorse/come-funziona-bolletta-gas">Bolletta gas</a>
<a href="/risorse/calcolo-spesa-annua">Calcolo spesa annua</a>
<a href="/risorse/prezzo-fisso-vs-indicizzato">Fisso vs variabile</a>
<a href="/risorse/come-leggere-bolletta">Come leggere la bolletta</a>
<a href="/risorse/glossario-energia">Glossario energia</a>
<div class="divider"></div>
<div class="group-label">Strumenti</div>
<a href="/offerte">Catalogo offerte</a>
<a href="/panoramica">Panoramica mercato</a>
<a href="/mercato">Storico PUN/PSV</a>
</div>
</div>
<a href="/login" class="area-btn">
<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
Area Consulenti
</a>
</div>
</div>
</nav>
<script>document.addEventListener('click',function(e){var d=document.getElementById('risorse-dropdown');if(d&&!d.contains(e.target))d.classList.remove('open')});
window.addEventListener('scroll',function(){var h=document.documentElement.scrollHeight-window.innerHeight;var p=h>0?(window.scrollY/h)*100:0;var b=document.getElementById('scroll-progress');if(b)b.style.width=p+'%'},{passive:true});</script>

<main class="container">

<div class="breadcrumb">
<a href="/">Home</a><?php if ($_SERVER['REQUEST_URI'] !== '/risorse/'): ?><span>›</span><a href="/risorse/">Risorse</a><?php endif; ?>
</div>
