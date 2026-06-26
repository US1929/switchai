<?php
/**
 * ARERA Open Data XML → JSON ETL (Switchai internal module)
 *
 * Imports ALL providers from ARERA Open Data (no artificial whitelist).
 * Downloads official ARERA XML offers, parses with XMLReader (stream-based),
 * derives brand from DENOMINAZIONE, extracts pricing components,
 * enriches known brands with logo/vantaggi, saves atomically.
 *
 * Safe for shared hosting (OVH Pro) — memory-efficient streaming parser.
 */

define('ARERA_DATA_DIR', __DIR__ . '/../data/offerte');

$parametri_mercato = [
    'PUN' => 0.1434,
    'PSV' => 0.563775,
    'PUN_label' => 'Pun Giugno 2026',
    'PSV_label' => 'Psv Giugno 2026',
    'consumi_luce' => ['basso' => 1500, 'medio' => 2700, 'alto' => 4000],
    'consumi_gas'  => ['basso' => 400, 'medio' => 1000, 'alto' => 1800],
];

$configFile = ARERA_DATA_DIR . '/config.json';
if (file_exists($configFile)) {
    $saved = json_decode(file_get_contents($configFile), true);
    if (is_array($saved) && !empty($saved['PUN']) && !empty($saved['PSV'])) {
        $parametri_mercato['PUN'] = (float)$saved['PUN'];
        $parametri_mercato['PSV'] = (float)$saved['PSV'];
        if (isset($saved['PUN_label'])) $parametri_mercato['PUN_label'] = $saved['PUN_label'];
        if (isset($saved['PSV_label'])) $parametri_mercato['PSV_label'] = $saved['PSV_label'];
    }
}

/**
 * Metadata fornitori conosciuti, chiave = Partita IVA.
 * Usato per arricchire brand/logo/vantaggi, NON come filtro.
 */
$brand_metadata = [
    '12883420155' => ['brand' => 'A2A ENERGIA', 'logo' => 'loghi/a2a.png', 'vantaggi' => 'Servizio di Assistenza h24 gratuito, energia 100% verde'],
    '06655971007' => ['brand' => 'ENEL ENERGIA', 'logo' => 'loghi/enel.png', 'vantaggi' => 'Bolletta web, addebito diretto e gestione digitale completa'],
    '12300020158' => ['brand' => 'ENI PLENITUDE', 'logo' => 'loghi/plenitude.png', 'vantaggi' => 'Sconto domiciliazione e programma fedeltà Plenitude Insieme'],
    '01178580997' => ['brand' => 'IREN MERCATO', 'logo' => 'loghi/iren.png', 'vantaggi' => 'Prezzo bloccato, bonus benvenuto e energia sostenibile'],
    '08526440154' => ['brand' => 'EDISON ENERGIA', 'logo' => 'loghi/edison.png', 'vantaggi' => 'Servizi casa inclusi, energia 100% green e assistenza dedicata'],
    '00997630322' => ['brand' => 'HERA COMM', 'logo' => 'loghi/hera.png', 'vantaggi' => 'Gruppo Hera: multiutility leader, servizio clienti di prossimità'],
    '02221101203' => ['brand' => 'HERA COMM', 'logo' => 'loghi/hera.png', 'vantaggi' => 'Offerte luce e gas integrate con il territorio'],
    '01771990445' => ['brand' => 'OCTOPUS ENERGY', 'logo' => 'loghi/octopus.png', 'vantaggi' => 'Tariffe trasparenti, app innovativa e servizio clienti premiato'],
    '06874351007' => ['brand' => 'POSTE ENERGIA', 'logo' => 'loghi/poste.png', 'vantaggi' => 'Gestione integrata in Ufficio Postale, offerta semplice e chiara'],
    '17484351006' => ['brand' => 'PULSEE', 'logo' => 'loghi/pulsee.png', 'vantaggi' => '100% digitale, app intuitiva e servizi smart per la casa'],
    '12878470157' => ['brand' => 'FASTWEB ENERGIA', 'logo' => 'loghi/fastweb.png', 'vantaggi' => 'Connettività + energia in un\'unica offerta'],
    '10879560968' => ['brand' => 'NEN', 'logo' => 'loghi/nen.png', 'vantaggi' => 'Canone fisso mensile prevedibile, senza sorprese e 100% green'],
    '12357070965' => ['brand' => 'CALABRIA ENERGIA', 'logo' => 'loghi/calabriaenergia.png', 'vantaggi' => 'Fornitore locale calabrese: servizio di prossimità e supporto dedicato', 'tipo' => 'locale', 'regione_principale' => '18'],
    '01991100189' => ['brand' => 'ASM VENDITA E SERVIZI', 'logo' => 'loghi/asm.png', 'vantaggi' => 'Multiutility locale con offerte luce e gas integrate', 'tipo' => 'locale', 'regione_principale' => '03'],
    '05044850823' => ['brand' => 'AMG ENERGIA', 'logo' => 'loghi/amg.png', 'vantaggi' => 'Azienda municipalizzata: energia a km zero per la tua città', 'tipo' => 'locale', 'regione_principale' => '19'],
    '07383800724' => ['brand' => 'BARI ENERGIA', 'logo' => 'loghi/barienergia.png', 'vantaggi' => 'Fornitore pugliese con offerte competitive per il Sud Italia', 'tipo' => 'locale', 'regione_principale' => '16'],
    '03429130234' => ['brand' => 'E.ON ENERGIA', 'logo' => 'loghi/eon.png', 'vantaggi' => 'Gruppo internazionale leader, soluzioni energetiche complete'],
    '06289781004' => ['brand' => 'ENGIE', 'logo' => 'loghi/engie.png', 'vantaggi' => 'Leader globale energia e servizi, soluzioni green certificate'],
    '01219980529' => ['brand' => 'ESTRA', 'logo' => 'loghi/estra.png', 'vantaggi' => 'Multiutility toscana: offerte Luce, Gas e Fibra integrate'],
    '02356770988' => ['brand' => 'ILLUMIA', 'logo' => 'loghi/illumia.png', 'vantaggi' => 'Energia premium con attenzione alla sostenibilità e al cliente'],
    '14106681001' => ['brand' => 'ITALY GREEN POWER', 'logo' => 'loghi/italygreenpower.png', 'vantaggi' => 'Energia 100% rinnovabile, scelta green per la tua casa'],
];

/**
 * Deriva brand, logo e vantaggi dalla Partita IVA.
 * Per PIVA sconosciute, usa l'URL del venditore come fallback.
 */
function resolveBrandFromPiva(string $piva, string $urlSito, array $metadata): array {
    if (isset($metadata[$piva])) {
        $meta = $metadata[$piva];
        return [
            'brand'    => $meta['brand'],
            'logo'     => $meta['logo'] ?? null,
            'vantaggi' => $meta['vantaggi'] ?? null,
            'tipo'     => $meta['tipo'] ?? null,
            'regione_principale' => $meta['regione_principale'] ?? null,
        ];
    }

    // Fallback: estrai nome dominio come brand
    $domain = str_replace(['https://', 'http://', 'www.'], '', $urlSito);
    $domain = explode('/', $domain)[0];
    $domain = explode('.', $domain)[0];
    $brand = strtoupper(trim(preg_replace('/[^a-zA-Z0-9]/', ' ', $domain)));

    return [
        'brand'    => $brand ?: ('FORNITORE_' . substr($piva, 0, 6)),
        'logo'     => null,
        'vantaggi' => null,
        'tipo'     => null,
        'regione_principale' => null,
    ];
}

/** Normalizza e valida l'URL offerta ARERA. Ritorna null se non valido. */
function normalizeOfferUrl(?string $url): ?string {
    if ($url === null) return null;
    $url = trim($url);
    if ($url === '') return null;

    // Se manca lo schema, aggiungi https://
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'https://' . $url;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) return null;

    $parts = parse_url($url);
    if (empty($parts['host']) || !str_contains($parts['host'], '.')) return null;

    $scheme = strtolower($parts['scheme'] ?? 'https');
    $host = strtolower($parts['host']);
    $normalized = $scheme . '://' . $host;
    if (!empty($parts['port'])) $normalized .= ':' . $parts['port'];
    if (!empty($parts['path'])) $normalized .= $parts['path'];
    if (!empty($parts['query'])) $normalized .= '?' . $parts['query'];
    if (!empty($parts['fragment'])) $normalized .= '#' . $parts['fragment'];

    return $normalized;
}

function arera_discover_urls(): array {
    $page = 'https://www.ilportaleofferte.it/portaleOfferte/it/open-data.page';
    $urls = ['luce' => null, 'gas' => null];

    $ch = curl_init($page);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SwitchAI-ARERA/1.0)',
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && $html) {
        preg_match_all('/href="([^"]*PO_Offerte_E_MLIBERO[^"]*\.xml)"/i', $html, $lm);
        preg_match_all('/href="([^"]*PO_Offerte_G_MLIBERO[^"]*\.xml)"/i', $html, $gm);
        $base = 'https://www.ilportaleofferte.it';
        if (!empty($lm[1])) {
            $p = $lm[1][0];
            $urls['luce'] = str_starts_with($p, 'http') ? $p : $base . $p;
        }
        if (!empty($gm[1])) {
            $p = $gm[1][0];
            $urls['gas'] = str_starts_with($p, 'http') ? $p : $base . $p;
        }
    }

    $now = new DateTime();
    $y = $now->format('Y');
    $m = $now->format('n');
    $d = $now->format('Ymd');
    $base = 'https://www.ilportaleofferte.it/portaleOfferte/resources/opendata/csv/offerteML';
    if (!$urls['luce']) $urls['luce'] = "{$base}/{$y}_{$m}/PO_Offerte_E_MLIBERO_{$d}.xml";
    if (!$urls['gas'])  $urls['gas']  = "{$base}/{$y}_{$m}/PO_Offerte_G_MLIBERO_{$d}.xml";

    return $urls;
}

function arera_log(string $msg): void {
    if (defined('ARERA_SYNC_SILENT') && ARERA_SYNC_SILENT) return;
    $ts = date('Y-m-d H:i:s');
    if (php_sapi_name() === 'cli') {
        echo "[{$ts}] {$msg}\n";
    } else {
        echo htmlspecialchars("[{$ts}] {$msg}") . "<br>\n";
    }
}

function arera_format(float $n, int $dec = 4): string {
    return number_format($n, $dec, ',', '');
}

function arera_download_xml(string $url, string $dest): void {
    arera_log("Downloading XML from: {$url}");
    $fp = fopen($dest, 'w+');
    if (!$fp) throw new RuntimeException("Cannot write to {$dest}");

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp, CURLOPT_TIMEOUT => 300,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $ok = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    $is_file = str_starts_with(strtolower($url), 'file://');
    if (!$ok || (!$is_file && ($code < 200 || $code >= 300))) {
        @unlink($dest);
        throw new RuntimeException("Download failed (HTTP {$code}): {$err}");
    }
    $mb = round(filesize($dest) / 1048576, 2);
    arera_log("Downloaded {$mb} MB → {$dest}");
}

function arera_xml_find($xml, string $tag): string {
    $l = strtolower($tag);
    $found = '';
    $walk = function($node) use ($l, &$found, &$walk) {
        foreach ($node->children() as $n => $c) {
            if (strtolower($n) === $l) { $found = trim((string)$c); return; }
            $walk($c);
            if ($found !== '') return;
        }
    };
    $walk($xml);
    return $found;
}

/**
 * Estrae tutti i ComponenteImpresa con prezzo, unità e fascia.
 *
 * Per offerte monorarie deduplica i duplicati ARERA (stesso componente ripetuto
 * su più fasce) e li assegna tutti a F1. Per offerte multi-fascia mappa i codici
 * FASCIA_COMPONENTE in ordine di apparizione a F1/F2/F3.
 */
function arera_parse_components($xml, string $tipoFasceCode): array {
    $components = [];
    $unitaLabel = ['01' => '€/anno', '02' => '€/kW', '03' => '€/kWh', '04' => '€/Smc', '05' => '€/mese'];
    $isMonoraria = $tipoFasceCode === '01';

    // Per offerte multi-fascia, mappa i codici ARERA non vuoti in ordine di apparizione a F1/F2/F3
    $rawCodeOrder = [];
    if (!$isMonoraria) {
        foreach ($xml->children() as $comp) {
            if (strtolower($comp->getName()) !== 'componenteimpresa') continue;
            foreach ($comp->children() as $cn => $container) {
                if (strtolower($cn) !== 'intervalloprezzi') continue;
                foreach ($container->children() as $iv) {
                    if (strtolower($iv->getName()) === 'fascia_componente') {
                        $fasciaCod = trim((string)$iv);
                        if ($fasciaCod !== '' && !in_array($fasciaCod, $rawCodeOrder, true)) {
                            $rawCodeOrder[] = $fasciaCod;
                        }
                    }
                }
            }
        }
    }

    foreach ($xml->children() as $comp) {
        if (strtolower($comp->getName()) !== 'componenteimpresa') continue;

        $cNome = ''; $cDesc = ''; $cMacroarea = ''; $cTipologia = '';
        foreach ($comp->children() as $cn => $cv) {
            if (strtolower($cn) === 'nome') $cNome = trim((string)$cv);
            if (strtolower($cn) === 'descrizione') $cDesc = trim((string)$cv);
            if (strtolower($cn) === 'macroarea') $cMacroarea = trim((string)$cv);
            if (strtolower($cn) === 'tipologia') $cTipologia = trim((string)$cv);
        }

        $intervals = [];
        foreach ($comp->children() as $cn => $container) {
            if (strtolower($cn) !== 'intervalloprezzi') continue;
            $prezzoComp = null; $umCod = ''; $fasciaCod = '';
            foreach ($container->children() as $iv) {
                $ivName = strtolower($iv->getName());
                if ($ivName === 'prezzo') {
                    $prezzoComp = (float)str_replace(',', '.', trim((string)$iv));
                }
                if ($ivName === 'unita_misura') {
                    $umCod = trim((string)$iv);
                }
                if ($ivName === 'fascia_componente') {
                    $fasciaCod = trim((string)$iv);
                }
            }
            if ($prezzoComp === null || $prezzoComp <= 0) continue;
            $intervals[] = [
                'nome' => $cNome,
                'descrizione' => $cDesc,
                'macroarea' => $cMacroarea,
                'tipologia' => $cTipologia,
                'fascia_cod' => $fasciaCod,
                'prezzo' => $prezzoComp,
                'unita_cod' => $umCod,
                'unita' => $unitaLabel[$umCod] ?? ('cod_' . $umCod),
            ];
        }

        if ($isMonoraria) {
            // ARERA ripete spesso lo stesso componente per ogni fascia in monoraria;
            // manteniamo una sola istanza per componente.
            $seen = [];
            $deduped = [];
            foreach ($intervals as $int) {
                $key = $int['nome'] . '|' . $int['descrizione'] . '|' . $int['prezzo'] . '|' . $int['unita_cod'];
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $deduped[] = $int;
            }
            $intervals = $deduped;
        }

        // Per offerte multi-fascia senza codici espliciti, assegnamo F1/F2/F3 in ordine
        $hasCode = false;
        foreach ($intervals as $int) {
            if ($int['fascia_cod'] !== '') { $hasCode = true; break; }
        }

        foreach ($intervals as $i => $int) {
            if ($isMonoraria) {
                $fascia = 'F1';
            } elseif ($hasCode) {
                $idx = array_search($int['fascia_cod'], $rawCodeOrder, true);
                $fascia = ['F1', 'F2', 'F3'][$idx] ?? 'F1';
            } else {
                $fascia = ['F1', 'F2', 'F3'][$i] ?? 'F3';
            }
            $components[] = array_merge($int, ['fascia' => $fascia]);
        }
    }
    return $components;
}

function arera_process_xml(string $path, string $type, array $brandMetadata, array $params): array {
    arera_log("Processing XML for {$type}...");
    $reader = new XMLReader();
    if (!$reader->open($path)) throw new RuntimeException("Cannot open {$path}");

    $isLuce = $type === 'luce';
    $refKey = $isLuce ? 'PUN' : 'PSV';
    $refVal = $params[$refKey];
    $consumi = $isLuce ? $params['consumi_luce'] : $params['consumi_gas'];

    $results = [];
    $total = 0;
    $imported = 0;
    $offerSeen = [];

    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || strtolower($reader->name) !== 'offerta') continue;
        $total++;
        $xmlStr = $reader->readOuterXml();
        if (!$xmlStr) continue;

        try {
            $xml = new SimpleXMLElement($xmlStr);

            // Identifica il fornitore dalla PIVA (nessun filtro — tutti i fornitori sono importati)
            $piva = arera_xml_find($xml, 'PIVA_UTENTE');
            if ($piva === '') continue;
            $urlSito = arera_xml_find($xml, 'URL_SITO_VENDITORE');
            $meta = resolveBrandFromPiva($piva, $urlSito, $brandMetadata);

            $nome = arera_xml_find($xml, 'NOME_OFFERTA');
            if ($nome === '') continue;

            $tipoCode = arera_xml_find($xml, 'TIPO_OFFERTA');
            $isFisso = $tipoCode === '01';
            $bloccato = $isFisso ? 12 : 0;

            $tipoClienteCode = arera_xml_find($xml, 'TIPO_CLIENTE');
            $tipoCliente = $tipoClienteCode === '01' ? 'residenziale' : 'business';

            $fasceCode = arera_xml_find($xml, 'TIPOLOGIA_FASCE');
            $fasceMap = ['01' => 'Monoraria', '02' => 'Bioraria', '03' => 'Multioraria'];
            $tipoFasce = $fasceMap[$fasceCode] ?? 'Multioraria';

            $spread = null;
            $costoFisso = null;
            $prezzoFisso = null;

            // Parsiamo i componenti una sola volta
            $components = arera_parse_components($xml, $fasceCode);
            $energyByFascia = ['F1' => 0.0, 'F2' => 0.0, 'F3' => 0.0];
            $hasEnergyComponent = false;

            foreach ($components as $c) {
                $um = $c['unita_cod'];
                if ($um === '03' || $um === '04') {
                    // Componenti a consumo (€/kWh o €/Smc)
                    $isSpread = stripos($c['nome'], 'SPREAD') !== false
                        || stripos($c['descrizione'], 'SPREAD') !== false
                        || stripos($c['descrizione'], 'spread') !== false;
                    if ($isSpread) {
                        if ($spread === null || $c['prezzo'] > $spread) {
                            $spread = $c['prezzo'];
                        }
                        continue;
                    }

                    if ($isFisso) {
                        $hasEnergyComponent = true;
                        $fascia = $c['fascia'] ?? 'F1';
                        if (!isset($energyByFascia[$fascia])) $fascia = 'F1';
                        $energyByFascia[$fascia] += $c['prezzo'];
                    } elseif ($spread === null) {
                        // Offerta variabile senza spread esplicito: fallback al primo componente a consumo
                        $spread = $c['prezzo'];
                    }
                } elseif ($um === '01' || $um === '02' || $um === '05') {
                    // Componenti fisse (€/anno, €/kW, €/mese)
                    $isFixedKeyword = stripos($c['nome'], 'COMMERCIALIZZAZIONE') !== false
                        || stripos($c['nome'], 'CCV') !== false
                        || stripos($c['nome'], 'FISSA') !== false
                        || stripos($c['nome'], 'QUOTA') !== false
                        || stripos($c['descrizione'], 'commercializzazione') !== false
                        || stripos($c['descrizione'], 'quota fissa') !== false;
                    if ($isFixedKeyword && $costoFisso === null) {
                        $costoFisso = $c['prezzo'];
                    } elseif ($costoFisso === null && stripos($c['nome'], 'POTENZA') === false) {
                        // Fallback: primo componente non-potenza
                        $costoFisso = $c['prezzo'];
                    }
                }
            }

            // Second pass per spread: cerca macroarea 02 o descrizione "materia prima" per offerte variabili
            if ($spread === null && !$isFisso) {
                foreach ($components as $c) {
                    $um = $c['unita_cod'];
                    if ($um !== '03' && $um !== '04') continue;
                    $isSpread = ($c['macroarea'] === '02' || $c['macroarea'] === '2')
                        || stripos($c['nome'], 'SPREAD') !== false
                        || stripos($c['descrizione'], 'spread') !== false
                        || stripos($c['descrizione'], 'materia prima') !== false;
                    if ($isSpread && ($spread === null || $c['prezzo'] > $spread)) {
                        $spread = $c['prezzo'];
                    }
                }
            }

            if ($costoFisso === null) $costoFisso = 0.0;

            // Prezzo unico per offerte fisse (per ranking/profili): F1 per monoraria, media delle fasce per multi-fascia
            if ($isFisso && $hasEnergyComponent) {
                $nonZeroFasce = array_filter($energyByFascia, fn($v) => $v > 0);
                if (!empty($nonZeroFasce)) {
                    $prezzoFisso = count($nonZeroFasce) === 1
                        ? reset($nonZeroFasce)
                        : (array_sum($nonZeroFasce) / count($nonZeroFasce));
                }
            }

            // ── Validità + filtro scadute ────────────────────────────
            $validita = '';
            foreach ($xml->children() as $cn => $cont) {
                if (strtolower($cn) !== 'validitaofferta') continue;
                foreach ($cont->children() as $vv) {
                    if (strtolower($vv->getName()) === 'data_fine') {
                        $parts = explode('_', trim((string)$vv));
                        $validita = $parts[0];
                    }
                }
            }
            // Salta offerte con validità già scaduta
            if ($validita !== '') {
                $parts = explode('/', $validita);
                if (count($parts) === 3) {
                    $fineTs = mktime(0, 0, 0, (int)$parts[1], (int)$parts[0], (int)$parts[2]);
                    if ($fineTs < time()) continue;
                }
            }

            $regioni = []; $province = [];
            foreach ($xml->children() as $cn => $cont) {
                if (strtolower($cn) !== 'zoneofferta') continue;
                foreach ($cont->children() as $zv) {
                    $code = trim((string)$zv);
                    if ($code === '') continue;
                    if (strtolower($zv->getName()) === 'regione') $regioni[] = $code;
                    if (strtolower($zv->getName()) === 'provincia') $province[] = $code;
                }
            }
            $isNazionale = count($regioni) === 0 && count($province) === 0;

            $modalita = arera_xml_find($xml, 'MODALITA');
            $azioneMap = ['01' => 'Switch', '02' => 'Subentro', '03' => 'Voltura'];
            $azione = $azioneMap[$modalita] ?? 'Switch';

            $tempInfo = '';
            foreach ($xml->children() as $cn => $cont) {
                if (strtolower($cn) !== 'condizionicontrattuali') continue;
                foreach ($cont->children() as $tv) {
                    if (strtolower($tv->getName()) === 'descrizione') $tempInfo = trim((string)$tv);
                }
                if (!$tempInfo) $tempInfo = trim((string)$cont->children()[0]);
            }

            $urlOfferta = normalizeOfferUrl(arera_xml_find($xml, 'URL_OFFERTA'));
            $urlSitoVenditore = normalizeOfferUrl(arera_xml_find($xml, 'URL_SITO_VENDITORE'));
            $codiceOfferta = arera_xml_find($xml, 'COD_OFFERTA');

            // ── Componenti strutturati (per-fascia + voci fisse) ────
            $componenti = array_map(fn($c) => [
                'nome' => $c['nome'],
                'descrizione' => $c['descrizione'],
                'fascia' => $c['fascia'],
                'prezzo' => $c['prezzo'],
                'unita' => $c['unita'],
                'tipologia' => $c['tipologia'],
                'macroarea' => $c['macroarea'],
            ], $components);

            if ($prezzoFisso !== null) {
                $prezzoTot = $prezzoFisso;
            } elseif ($spread !== null) {
                $prezzoTot = $refVal + $spread;
            } else {
                $prezzoTot = $refVal;
            }
            $costoBasso = ($prezzoTot * $consumi['basso']) + $costoFisso;
            $costoMedio = ($prezzoTot * $consumi['medio']) + $costoFisso;
            $costoAlto  = ($prezzoTot * $consumi['alto']) + $costoFisso;

            $prezzoTotStr = arera_format($prezzoTot, 4);
            $spreadStr = arera_format($spread ?? 0, 4);
            $refStr = arera_format($refVal, 5);
            $prezzoMateria = $prezzoFisso !== null
                ? arera_format($prezzoTot, 4) . ' €/' . ($isLuce ? 'kWh' : 'Smc') . ' (bloccato)'
                : ($isLuce ? 'Pun' : 'Psv') . ' + ' . $spreadStr;

            $offer = [
                'brand' => $meta['brand'],
                'azione' => $azione,
                'logo' => $meta['logo'],
                'offerta' => $nome,
                'tariffa' => $isFisso ? 'Fissa' : 'Variabile',
                ($isLuce ? 'prezzo tot kwh' : 'prezzo tot smc') => $prezzoTotStr,
                'prezzo_materia' => $prezzoMateria,
                ($isLuce ? 'Pun' : 'Psv') => $refStr,
                'spread' => $spreadStr,
                'costo_fisso' => arera_format($costoFisso, 2),
                'prezzo_bloccato' => (string)$bloccato,
                'vantaggi' => $meta['vantaggi'],
                'costo_profilo_basso' => arera_format($costoBasso, 2),
                'costo_profilo_medio' => arera_format($costoMedio, 2),
                'costo_profilo_alto'  => arera_format($costoAlto, 2),
                'pagamento' => 'SDD',
                'url_offerta' => $urlOfferta,
                'url_sito_venditore' => $urlSitoVenditore,
                'tempistica' => '30-45 giorni',
                'tempistica_info' => $tempInfo,
                'note_costi' => $isFisso
                    ? "Prezzo Fisso bloccato per {$bloccato} mesi"
                    : 'Prezzo Variabile indicizzato al ' . ($isLuce ? 'PUN' : 'PSV'),
                'validità offerta' => $validita,
                'regioni' => $regioni,
                'province' => $province,
                'nazionale' => $isNazionale,
                'tipo_cliente' => $tipoCliente,
                'tipo_fasce' => $tipoFasce,
                'fornitore_locale' => ($meta['tipo'] ?? '') === 'locale',
                'regione_principale' => $meta['regione_principale'] ?? '',
                'codice_offerta' => $codiceOfferta,
                'componenti' => $componenti,
            ];

            // Dedup: skip if same brand + nome + prezzo + costo_fisso
            $prezzoKey = $isLuce ? 'prezzo tot kwh' : 'prezzo tot smc';
            $dk = $offer['brand'] . '|' . $offer['offerta'] . '|' . ($offer[$prezzoKey] ?? '') . '|' . $offer['costo_fisso'];
            if (isset($offerSeen[$dk])) continue;
            $offerSeen[$dk] = true;

            $results[] = $offer;
            $imported++;
        } catch (Exception $e) {
            arera_log("Warning: skipped malformed offer — " . $e->getMessage());
        }
    }

    $reader->close();
    arera_log("Checked {$total} offers, imported {$imported} (no whitelist filter).");
    return $results;
}

function arera_save_json(array $data, string $target, string $tmp): void {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) throw new RuntimeException('JSON encoding failed: ' . json_last_error_msg());
    file_put_contents($tmp, $json, LOCK_EX);
    rename($tmp, $target);
    arera_log("Saved atomically → {$target}");
}

function arera_run_sync(string $type, array $brandMetadata, array $params, ?string $filterRegione = null): array {
    $dir = ARERA_DATA_DIR;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $urls = arera_discover_urls();
    if (!isset($urls[$type]) || !$urls[$type]) throw new RuntimeException("No URL discovered for {$type}");

    $tmpXml = $dir . "/arera_{$type}.xml";
    $targetJson = $dir . "/db-offerte-{$type}.json";
    $tmpJson = $dir . "/db-offerte-{$type}-tmp.json";

    if ($filterRegione) {
        $targetJson = $dir . "/db-offerte-{$type}-regione{$filterRegione}.json";
        $tmpJson = $dir . "/db-offerte-{$type}-regione{$filterRegione}-tmp.json";
    }

    $start = microtime(true);
    arera_log("=== Syncing {$type} ===");
    arera_log("URL: {$urls[$type]}");

    try {
        arera_download_xml($urls[$type], $tmpXml);
        $data = arera_process_xml($tmpXml, $type, $brandMetadata, $params);

        if ($filterRegione) {
            $data = array_values(array_filter($data, fn($o) => !empty($o['nazionale']) || in_array($filterRegione, $o['regioni'])));
            arera_log("Region filter: " . count($data) . " offers retained.");
        }

        arera_save_json($data, $targetJson, $tmpJson);
        @unlink($tmpXml);

        $elapsed = round(microtime(true) - $start, 2);
        arera_log("Done ({$elapsed}s) — " . count($data) . " offers → {$targetJson}");

        return ['type' => $type, 'count' => count($data), 'elapsed' => $elapsed, 'success' => true];
    } catch (Exception $e) {
        @unlink($tmpXml);
        @unlink($tmpJson);
        arera_log("ERROR: " . $e->getMessage());
        return ['type' => $type, 'success' => false, 'error' => $e->getMessage()];
    }
}
