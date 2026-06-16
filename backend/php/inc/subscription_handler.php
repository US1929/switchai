<?php
/**
 * subscription_handler.php — Gestione sottoscrizioni con double opt-in GDPR.
 *
 * Flusso:
 * 1. submit  → validazione consensi → salva come "pending" → invia email conferma
 * 2. conferma → verifica token → stato "confirmed" → invia a cloud-care (se abilitato)
 */

/**
 * Step 1: riceve la sottoscrizione, la mette in pending, invia email di conferma.
 * NON invia a cloud-care finché l'utente non ha confermato.
 */
function submitPendingSubscription(array $formData, array $offerData, array $meta = []): array {
    // ── Validazione GDPR ──────────────────────────────────────────
    if (empty($formData['gdpr_privacy_accepted']) || $formData['gdpr_privacy_accepted'] !== true) {
        return ['status' => 'error', 'error' => 'Consenso privacy obbligatorio. gdpr_privacy_accepted deve essere true.'];
    }

    $consentSource = $formData['consent_source'] ?? $meta['consent_source'] ?? 'api_direct';
    $consentTimestamp = $formData['consent_timestamp'] ?? date('c');
    $consentSnippet = $formData['conversation_snippet'] ?? '';

    // ── Genera token di conferma ──────────────────────────────────
    $token = bin2hex(random_bytes(32));
    $confirmUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://')
                . ($_SERVER['HTTP_HOST'] ?? 'switchai.it')
                . '/conferma?token=' . $token;

    // ── Salva in pending ──────────────────────────────────────────
    $id = deterministicUuid('sub-' . microtime(true));
    $subscription = [
        'subscription_id'   => $id,
        'status'            => 'pending',
        'confirm_token'     => $token,
        'form_data'         => $formData,
        'offer_data'        => $offerData,
        'consent_source'    => $consentSource,
        'consent_timestamp' => $consentTimestamp,
        'conversation_snippet' => $consentSnippet,
        'created_at'        => date('c'),
        'ip'                => getClientIP(),
        'user_agent'        => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ];
    saveSubscription($subscription);

    // ── Invia email di conferma ───────────────────────────────────
    $emailSent = sendConfirmationEmail($formData, $offerData, $confirmUrl, $id);

    return [
        'status'           => 'pending',
        'subscription_id'  => $id,
        'message'          => 'Sottoscrizione ricevuta. Abbiamo inviato una email di conferma. '
                            . 'Clicca sul link per completare l\'attivazione.',
        'next_step'        => 'Controlla la tua email e clicca sul link di conferma.',
        'confirm_url_hint' => $confirmUrl,
        'email_sent'       => $emailSent,
        'ws_status'        => 'waiting_confirmation',
    ];
}

/**
 * Step 2: conferma la sottoscrizione e (se WS_ENABLED) invia a cloud-care.
 */
function confirmSubscription(string $token): array {
    // Cerca la sottoscrizione per token
    $all = glob(__DIR__ . '/../../data/subscriptions/*.json');
    $subscription = null;

    foreach ($all as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data) && ($data['confirm_token'] ?? '') === $token) {
            $subscription = $data;
            break;
        }
    }

    if (!$subscription) {
        return ['status' => 'error', 'error' => 'Token non valido o scaduto.'];
    }

    if (($subscription['status'] ?? '') === 'confirmed') {
        return ['status' => 'already_confirmed', 'message' => 'Questa sottoscrizione è già stata confermata.'];
    }

    // ── Aggiorna stato ────────────────────────────────────────────
    $subscription['status'] = 'confirmed';
    $subscription['confirmed_at'] = date('c');
    $subscription['confirm_ip'] = getClientIP();

    $file = __DIR__ . '/../../data/subscriptions/' . $subscription['subscription_id'] . '.json';
    $fp = @fopen($file, 'w');
    if ($fp && flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($subscription, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    // ── Invia a cloud-care se abilitato ────────────────────────────
    $wsResult = ['status' => 'skipped', 'reason' => 'WS_ENABLED=false'];
    if ((getenv('WS_ENABLED') ?: 'false') === 'true') {
        $wsResult = sendRealToWebService(
            $subscription['form_data'],
            $subscription['offer_data'],
            ['source_url' => '', 'user_agent' => $subscription['user_agent'] ?? '']
        );
    }

    // ── Notifica admin con TUTTI i dati (doppio opt-in completato) ─
    $fd = $subscription['form_data'];
    $od = $subscription['offer_data'];
    $to = getenv('ACTIVATION_EMAIL') ?: 'attivazioni@switchai.it';
    $commodityLabel = ($od['commodity'] ?? 'luce') === 'luce' ? '⚡ LUCE' : '🔥 GAS';
    $subject = "[SwitchAI] ✅ CONFERMATA — {$fd['nome']} {$fd['cognome']} — {$od['tariff_name']}";

    $msg = "═══════════════════════════════════════\n";
    $msg .= "  SOTTOSCRIZIONE CONFERMATA (GDPR OK)\n";
    $msg .= "═══════════════════════════════════════\n\n";

    $msg .= "📋 OFFERTA\n";
    $msg .= "  Nome:      {$od['tariff_name']}\n";
    $msg .= "  Fornitore: {$fd['supplier']}\n";
    $msg .= "  Tipo:      $commodityLabel — " . ($od['tipo_offerta'] ?? 'switch') . "\n\n";

    $msg .= "👤 INTESTATARIO\n";
    $msg .= "  Nome:           {$fd['nome']} {$fd['cognome']}\n";
    $msg .= "  Codice Fiscale: {$fd['codice_fiscale']}\n";
    $msg .= "  Email:          {$fd['email']}\n";
    $msg .= "  Cellulare:      {$fd['cellulare']}\n";
    $msg .= "  Titolo Immobile: " . ($fd['titolo_immobile'] ?? 'N/D') . "\n\n";

    $msg .= "📍 FORNITURA\n";
    $msg .= "  Indirizzo: {$fd['indirizzo']} {$fd['civico']}\n";
    $msg .= "  Città:     {$fd['citta']} ({$fd['provincia_sigla']})\n";
    $msg .= "  CAP:       {$fd['cap']}\n";
    if (!empty($fd['codice_pod'])) $msg .= "  POD:       {$fd['codice_pod']}\n";
    if (!empty($fd['codice_pdr'])) $msg .= "  PDR:       {$fd['codice_pdr']}\n";

    if (($fd['indirizzo_coincide'] ?? 'si') === 'no') {
        $msg .= "\n📍 RESIDENZA (diversa)\n";
        $msg .= "  {$fd['indirizzo_residenza']} {$fd['civico_residenza']}\n";
        $msg .= "  {$fd['citta_residenza']} ({$fd['provincia_residenza_sigla']}) {$fd['cap_residenza']}\n";
    }

    $msg .= "\n💳 PAGAMENTO\n";
    $msg .= "  Modalità: {$fd['modalita_pagamento']}\n";
    if (!empty($fd['iban'])) $msg .= "  IBAN:     {$fd['iban']}\n";

    $msg .= "\n📊 CONSUMI\n";
    if (!empty($fd['consumo_kwh'])) $msg .= "  Luce: {$fd['consumo_kwh']} kWh\n";
    if (!empty($fd['potenza'])) $msg .= "  Potenza: {$fd['potenza']} kW\n";
    if (!empty($fd['consumo_smc'])) $msg .= "  Gas: {$fd['consumo_smc']} Smc\n";
    if (!empty($fd['fornitore_attuale'])) $msg .= "  Fornitore uscente: {$fd['fornitore_attuale']}\n";

    $msg .= "\n🔐 CONSENSO\n";
    $msg .= "  Fonte: {$subscription['consent_source']}\n";
    $msg .= "  Data consenso: {$subscription['consent_timestamp']}\n";
    $msg .= "  Data conferma: {$subscription['confirmed_at']}\n";
    $msg .= "  IP conferma: {$subscription['confirm_ip']}\n";
    if (!empty($subscription['conversation_snippet'])) {
        $msg .= "  Snippet: {$subscription['conversation_snippet']}\n";
    }

    $msg .= "\n🔑 ID: {$subscription['subscription_id']}\n";
    $msg .= "───────────────────────────────────────\n";
    $msg .= "WS inviato: " . ($wsResult['status'] ?? 'skipped') . "\n";
    $msg .= "Data: " . date('d/m/Y H:i:s') . "\n";

    @mail($to, $subject, $msg, "From: SwitchAI <attivazioni@switchai.it>\r\nContent-Type: text/plain; charset=UTF-8");

    return [
        'status'          => 'confirmed',
        'subscription_id' => $subscription['subscription_id'],
        'message'         => 'Consenso registrato. La tua richiesta è stata inoltrata.',
        'ws_sent'         => $wsResult['status'] ?? 'skipped',
    ];
}

/**
 * Invia email di conferma all'utente con link univoco.
 */
function sendConfirmationEmail(array $formData, array $offerData, string $confirmUrl, string $id): bool {
    $to = $formData['email'] ?? '';
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("Confirmation email: invalid recipient: $to");
        return false;
    }

    $subject = "[SwitchAI] Conferma la tua richiesta di attivazione";

    $msg = "Ciao {$formData['nome']},\n\n";
    $msg .= "abbiamo ricevuto la tua richiesta di attivazione tramite l'assistente AI di SwitchAI.\n\n";
    $msg .= "Offerta: {$offerData['tariff_name']} — {$formData['supplier']}\n";
    $msg .= "Nome: {$formData['nome']} {$formData['cognome']}\n\n";
    $msg .= "Per completare l'attivazione e autorizzare SwitchAI a inoltrare i tuoi dati, "
          . "clicca sul link qui sotto:\n\n";
    $msg .= "$confirmUrl\n\n";
    $msg .= "Se non hai richiesto tu questa attivazione, ignora questa email.\n\n";
    $msg .= "— SwitchAI\n";
    $msg .= "switchai.it\n";

    $headers = "From: SwitchAI <attivazioni@switchai.it>\r\n";
    $headers .= "Reply-To: attivazioni@switchai.it\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $sent = @mail($to, $subject, $msg, $headers);
    error_log("Confirmation email to $to: " . ($sent ? 'sent' : 'FAILED'));
    return $sent;
}

// ── Invio reale a cloud-care (solo dopo conferma) ──────────────────

function sendRealToWebService(array $formData, array $offerData, array $meta = []): array {
    $wsUrl = getenv('WS_SUBSCRIPTION_URL');
    $wsToken = getenv('WS_SUBSCRIPTION_TOKEN');

    if (!$wsUrl || !$wsToken) {
        return ['status' => 'error', 'error' => 'Web service non configurato (WS_SUBSCRIPTION_URL o WS_SUBSCRIPTION_TOKEN mancante)'];
    }

    $payload = buildSubscriptionPayload($formData, $offerData, $meta);

    $response = httpPostJSON($wsUrl, $payload, [
        'Authorization: Bearer ' . $wsToken,
        'Content-Type: application/json',
        'Cache-Control: no-cache',
    ]);

    if ($response === false || empty($response)) {
        error_log("Cloud-care WS: HTTP request failed");
        return ['status' => 'error', 'error' => 'Web service unreachable'];
    }

    $decoded = json_decode($response, true);
    error_log("Cloud-care WS response: " . json_encode($decoded));
    return ['status' => 'success', 'ws_response' => $decoded];
}

/**
 * Dry-run: costruisce il payload senza inviare nulla.
 */
function dryRunSubscription(array $formData, array $offerData, array $meta = []): array {
    $wsUrl = getenv('WS_SUBSCRIPTION_URL') ?: '(non configurato)';
    return [
        'status'        => 'dry_run',
        'message'       => 'DRY RUN — payload che verrebbe inviato al web service dopo conferma utente.',
        'would_send_to' => $wsUrl,
        'payload'       => buildCloudCarePayload($formData, $offerData, $meta),
    ];
}

// ── Payload cloud-care ────────────────────────────────────────────

function buildCloudCarePayload(array $formData, array $offerData, array $meta = []): array {
    $commodity = $offerData['commodity'] ?? 'luce';
    $isLuce = $commodity === 'luce';

    $payload = [
        'privacy1'    => '1',
        'privacy2'    => '1',
        'privacy3'    => '1',
        'privacy4'    => '1',
        'campaign'    => $meta['campaign'] ?? '',
        'ad_group'    => $meta['ad_group'] ?? '',
        'firstname'   => $formData['nome'] ?? '',
        'lastname'    => $formData['cognome'] ?? '',
        'mail'        => $formData['email'] ?? '',
        'phone'       => $formData['cellulare'] ?? '',
        'note'        => 'Lead confermata via double opt-in GDPR da SwitchAI (switchai.it)',
        'city'        => ($formData['citta'] ?? '') . ' (' . ($formData['provincia_sigla'] ?? '') . ')',
        'fiscal_code' => $formData['codice_fiscale'] ?? '',
        'form' => [
            'rp'                   => '5',
            'setRPcookie'          => 'true',
            'tipologia'            => $commodity,
            'gclid'                => null,
            'kwh'                  => $isLuce ? ($formData['consumo_kwh'] ?? '') : '',
            'kw'                   => $formData['potenza'] ?? '3',
            'sm3'                  => !$isLuce ? ($formData['consumo_smc'] ?? '') : '',
            'stato'                => 'step2',
            'mail_registrazione'   => $formData['email'] ?? '',
            'phone_registrazione'  => $formData['cellulare'] ?? '',
            'user_agent'           => $meta['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'source_url'           => $meta['source_url'] ?? '',
            'ip'                   => $meta['ip'] ?? getClientIP(),
        ],
    ];

    $payload['project_fields'] = array_merge($payload['form'], [
        'consumo_presunto_luce_text'     => $isLuce ? ($formData['consumo_kwh'] ?? '') : '',
        'consumo_annuo_gas'              => !$isLuce ? ($formData['consumo_smc'] ?? '') : '',
        'fornitore_vendita'              => $formData['fornitore_attuale'] ?? '',
        'precedente_fornitore_' . $commodity => $formData['fornitore_attuale'] ?? '',
        'fornitore_uscente_' . $commodity    => $formData['fornitore_attuale'] ?? '',
        'nome_offerta'                   => $offerData['tariff_name'] ?? '',
        'azione'                         => $offerData['tipo_offerta'] ?? 'switch',
        'titolo_occupazione_immobile'    => $formData['titolo_immobile'] ?? '',
        'fornitura_indirizzo_text'       => $formData['indirizzo'] ?? '',
        'indirizzo_fornitura_civico'     => $formData['civico'] ?? '',
        'fornitura_comune_text'          => $formData['citta'] ?? '',
        'fornitura_provincia_text'       => $formData['provincia_sigla'] ?? '',
        'fornitura_cap_text'             => $formData['cap'] ?? '',
        'modalita_pagamento_1'           => $formData['modalita_pagamento'] ?? '',
        'pod'                            => $isLuce ? ($formData['codice_pod'] ?? '') : '',
        'pdr'                            => !$isLuce ? ($formData['codice_pdr'] ?? '') : '',
        'stato'                          => 'step2',
    ]);

    if (!empty($formData['iban'])) {
        $payload['project_fields']['iban'] = $formData['iban'];
    }

    if (($formData['indirizzo_coincide'] ?? 'si') === 'no') {
        $payload['project_fields'] = array_merge($payload['project_fields'], [
            'residenza_comune_text'       => $formData['citta_residenza'] ?? '',
            'residenza_provincia_text'    => $formData['provincia_residenza_sigla'] ?? '',
            'residenza_cap_text'          => $formData['cap_residenza'] ?? '',
            'indirizzo_residenza_civico'  => $formData['civico_residenza'] ?? '',
            'residenza_indirizzo_text'    => $formData['indirizzo_residenza'] ?? '',
        ]);
    }

    return array_map(fn($v) => is_array($v) ? array_map(fn($x) => $x ?? '', $v) : ($v ?? ''), $payload);
}

// ── HTTP helpers ────────────────────────────────────────────────────

function httpPostJSON(string $url, array $data, array $headers = []): string|false {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => implode("\r\n", $headers) . "\r\nContent-Length: " . strlen($json),
        'content' => $json,
        'timeout' => 30,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false && function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
    }
    return $response;
}

function getClientIP(): string {
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '127.0.0.1';
}

// ── Storage ─────────────────────────────────────────────────────────

function saveSubscription(array $data): string {
    $dir = __DIR__ . '/../../data/subscriptions';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $id = $data['subscription_id'] ?? deterministicUuid('sub-' . microtime(true));
    $data['subscription_id'] = $id;

    $file = "$dir/$id.json";
    $fp = @fopen($file, 'w');
    if ($fp && flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    return $id;
}

function loadSubscription(string $id): ?array {
    $file = __DIR__ . '/../../data/subscriptions/' . basename($id) . '.json';
    if (!is_file($file)) return null;
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : null;
}
