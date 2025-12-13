<?php
session_start();
require_once '../config/Database.php';

// --- CONFIGURATION API ---
// Charger les credentials depuis un fichier de config 
if (file_exists(__DIR__ . '/../config/api_credentials.php')) {
    require_once __DIR__ . '/../config/api_credentials.php';
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Configuration API manquante. Veuillez créer le fichier config/api_credentials.php à partir de config/api_credentials.php.example et y configurer votre token GitHub API.'
    ]);
    exit;
}

$apiToken = defined('GITHUB_API_TOKEN') ? GITHUB_API_TOKEN : '';
$endpoint = 'https://models.github.ai/inference/chat/completions';
$modelName = 'openai/gpt-4o'; 

header('Content-Type: application/json');

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID manquant']);
    exit;
}

$idDemande = $_POST['id'];
$pdo = Database::getInstance()->getConnexion();

//Récupération des données complètes (y compris les détails JSON comme le nom de l'hôtel)
$sql = "SELECT el.montant, el.date_depense, el.justificatif_path, el.details_specifiques,
               c.nom_categ, c.plafond_max
        FROM expense_line el
        JOIN categories c ON el.id_categ = c.id_categ
        WHERE el.id_dem = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$idDemande]);
$lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($lines)) {
    echo json_encode(['success' => false, 'message' => 'Aucune donnée.']);
    exit;
}

// le Prompt Système (L'intelligence de l'audit)
$systemPrompt = "Tu es un auditeur financier expert et strict spécialisé sur le marché Marocain.
Ton rôle est de valider des notes de frais en analysant 3 axes critiques. 

RÈGLE ABSOLUE : Pour qu'une dépense soit marquée 'Conforme', TOUS les 3 axes doivent être conformes. Si UN SEUL axe n'est pas conforme, la dépense est 'Non conforme' ou 'Alerte'.

AXE 1 : CONFORMITÉ JUSTIFICATIF (OBLIGATOIRE ET CRITIQUE - Priorité absolue)
- Si une image de justificatif est fournie, tu DOIS analyser le contenu via OCR.
- Compare EXACTEMENT le montant visible sur l'image avec le montant déclaré.
- Compare EXACTEMENT la date visible sur l'image avec la date déclarée.
- Compare le nom de l'établissement/lieu visible sur l'image avec les détails déclarés.
- Si le justificatif (OCR) ne correspond PAS exactement à ce qui est déclaré, c'est une ALERTE CRITIQUE, même si les autres axes (plafond et cohérence marché) sont OK.
- Exemple : Si l'image montre 500 MAD mais que 800 MAD sont déclarés = NON CONFORME.
- Exemple : Si l'image montre une date différente = NON CONFORME.
- Exemple : Si l'image montre un autre établissement = NON CONFORME.
- Si aucune image n'est fournie, marque 'À vérifier' pour cet axe.

AXE 2 : RESPECT DE LA POLITIQUE
- Le montant déclaré dépasse-t-il le 'Plafond Autorisé' ?
- Si oui, c'est NON CONFORME.

AXE 3 : COHÉRENCE MARCHÉ & VÉRACITÉ
- Analyse le lieu/établissement indiqué dans les détails.
- Est-ce que le prix déclaré est cohérent avec le standing de cet établissement au Maroc ?
- Exemple : Si l'employé déclare 2000 MAD pour un 'McDo', c'est une fraude. Si c'est 2000 MAD pour le 'Royal Mansour', c'est cohérent.
- Si l'établissement semble fictif ou le prix exagéré pour la ville, signale-le.

LOGIQUE DE DÉCISION :
1. Si AXE 1 (justificatif) n'est pas conforme → Statut = 'Alerte' (NON CONFORME) - même si AXE 2 et AXE 3 sont OK
2. Si AXE 2 (plafond) est dépassé → Statut = 'Alerte' (NON CONFORME)
3. Si AXE 3 (cohérence marché) est incohérent → Statut = 'Alerte' (NON CONFORME)
4. Si TOUS les 3 axes sont conformes → Statut = 'Conforme'

 OBLIGATION : Tu DOIS analyser TOUTES les dépenses fournies. Ne saute AUCUNE dépense. Si tu reçois 3 dépenses, tu dois analyser les 3. Si tu reçois 5 dépenses, tu dois analyser les 5.

FORMAT DE RÉPONSE (HTML uniquement) :
Utilise une liste <ul style='list-style: none; padding: 0;'>. Pour chaque dépense, fais un <li style='background: white; border-left: 4px solid #10b981; border-radius: 8px; padding: 16px; margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);'>.
- Utilise <span class='text-success' style='font-weight: 600; color: #059669;'><i class='bi bi-check-circle-fill'></i> Conforme</span> UNIQUEMENT si TOUS les 3 axes sont conformes.
- Utilise <span class='text-danger' style='font-weight: 600; color: #dc2626;'><i class='bi bi-exclamation-triangle-fill'></i> Non conforme</span> si AU MOINS UN axe n'est pas conforme (priorité à l'AXE 1 si justificatif fourni).
- Utilise <span class='text-warning' style='font-weight: 600; color: #d97706;'><i class='bi bi-info-circle-fill'></i> À vérifier</span> si justificatif manquant ou doute mineur sur un seul axe.

Pour chaque dépense, détaille explicitement le résultat de CHAQUE axe :
- AXE 1 : [Conforme/Non conforme/À vérifier] - Détails OCR
- AXE 2 : [Conforme/Non conforme] - Respect plafond
- AXE 3 : [Conforme/Non conforme] - Cohérence marché

Sois précis et justifie tes alertes avec des détails (ex: 'Montant sur justificatif : 500 MAD, déclaré : 800 MAD - ÉCART DÉTECTÉ' ou 'Prix moyen constaté pour cet hôtel : 800 MAD, déclaré : 2500 MAD'). Ajoute un <div style='margin-top: 8px; font-size: 0.9rem; color: #64748b;'> pour les détails supplémentaires.

RAPPEL : Analyse TOUTES les dépenses sans exception. Ne termine pas avant d'avoir analysé la dernière dépense.";

//  Préparation des données utilisateur (Texte + Images)
$totalDepenses = count($lines);
$declarations = "═══════════════════════════════════════════════════════════════════════════════\n";
$declarations .= "AUDIT FINANCIER - $totalDepenses DÉPENSE(S) À ANALYSER\n";
$declarations .= "═══════════════════════════════════════════════════════════════════════════════\n";
$declarations .= " OBLIGATION ABSOLUE : Tu DOIS analyser TOUTES les $totalDepenses dépense(s) sans exception.\n";
$declarations .= "Ne saute AUCUNE dépense. Si tu reçois $totalDepenses dépense(s), tu dois analyser les $totalDepenses.\n";
$declarations .= "Pour chaque dépense, tu DOIS comparer EXACTEMENT ce qui est déclaré avec ce qui est visible sur le justificatif (image) via OCR.\n\n";

foreach ($lines as $index => $l) {
    $num = $index + 1;
    
    // On décode le JSON pour donner le nom de l'hôtel/ville à l'IA
    $detailsTxt = "";
    if (!empty($l['details_specifiques'])) {
        $json = json_decode($l['details_specifiques'], true);
        if ($json) {
            foreach ($json as $key => $val) {
                $detailsTxt .= "$key: $val, ";
            }
        }
    }

    $hasJustificatif = !empty($l['justificatif_path']);
    $justificatifInfo = $hasJustificatif ? "OUI - Image fournie ci-dessous" : "NON - Aucun justificatif fourni";

    $declarations .= "═══════════════════════════════════════════════════════\n";
    $declarations .= "DÉPENSE #$num sur $totalDepenses - {$l['nom_categ']}\n";
    $declarations .= "═══════════════════════════════════════════════════════\n";
    $declarations .= "Catégorie : {$l['nom_categ']}\n";
    $declarations .= "MONTANT DÉCLARÉ : {$l['montant']} MAD (à comparer avec l'image)\n";
    $declarations .= "DATE DÉCLARÉE : {$l['date_depense']} (à comparer avec l'image)\n";
    $declarations .= "Plafond Autorisé : {$l['plafond_max']} MAD\n";
    $declarations .= "Détails (Lieu/Établissement) : [$detailsTxt]\n";
    $declarations .= "Justificatif fourni : $justificatifInfo\n";
    if ($hasJustificatif) {
        $declarations .= "⚠️ IMPORTANT : Analyse l'image du justificatif ci-dessous et compare EXACTEMENT :\n";
        $declarations .= "   - Le montant visible sur l'image avec le montant déclaré ({$l['montant']} MAD)\n";
        $declarations .= "   - La date visible sur l'image avec la date déclarée ({$l['date_depense']})\n";
        $declarations .= "   - Le nom de l'établissement visible avec les détails déclarés\n";
        $declarations .= "   Si tu détectes une différence, c'est NON CONFORME même si le plafond et le marché sont OK.\n";
    }
    $declarations .= "═══════════════════════════════════════════════════════\n\n";
}

$userMessageContent = [
    ["type" => "text", "text" => $declarations]
];

// On ajoute les images pour la vision
foreach ($lines as $l) {
    if (!empty($l['justificatif_path'])) {
        $path = __DIR__ . '/../uploads/proofs/' . $l['justificatif_path'];
        
        if (file_exists($path) && in_array(mime_content_type($path), ['image/jpeg', 'image/png', 'image/webp'])) {
            $data = file_get_contents($path);
            $base64 = base64_encode($data);
            $mime = mime_content_type($path);
            $url = "data:$mime;base64,$base64";

            $userMessageContent[] = [
                "type" => "image_url",
                "image_url" => [
                    "url" => $url,
                    "detail" => "low"
                ]
            ];
        }
    }
}

// Construction de la requête
$payload = [
    'model' => $modelName,
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userMessageContent]
    ],
    'temperature' => 0.3, // Température basse pour être factuel et analytique
    'max_tokens' => 3000 // Augmenté pour permettre l'analyse complète de toutes les dépenses
];

//Vérifier que le token est configuré
if (empty($apiToken) || $apiToken === 'your-github-api-token-here') {
    $configPath = __DIR__ . '/../config/api_credentials.php';
    $instructions = "Le token GitHub API n'a pas été configuré.\n\n";
    $instructions .= "📝 INSTRUCTIONS :\n";
    $instructions .= "1. Ouvrez le fichier : " . realpath($configPath) . "\n";
    $instructions .= "2. Remplacez 'your-github-api-token-here' par votre token GitHub réel\n";
    $instructions .= "3. Pour obtenir un token : https://github.com/settings/tokens\n";
    $instructions .= "4. Générez un nouveau token (classic) avec les permissions nécessaires\n";
    $instructions .= "5. Collez le token dans le fichier api_credentials.php\n\n";
    $instructions .= "Exemple : define('GITHUB_API_TOKEN', 'ghp_votre_token_ici');";
    
    echo json_encode([
        'success' => false,
        'message' => $instructions
    ]);
    exit;
}

// 7Envoi cURL avec gestion d'erreurs améliorée
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiToken
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Timeout de 60 secondes
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Timeout de connexion de 10 secondes

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

//  Gestion des erreurs
if ($response === false) {
    // Erreur cURL (connexion, timeout, etc.)
    $errorMessage = "Erreur de connexion à l'API IA";
    if (!empty($curlError)) {
        $errorMessage .= " : " . $curlError;
    }
    $errorMessage .= ". Vérifiez votre connexion internet et réessayez.";
    
    echo json_encode([
        'success' => false,
        'message' => $errorMessage
    ]);
    exit;
}

// Vérifier le code HTTP
if ($httpCode !== 200) {
    $errorDetails = "Code HTTP : $httpCode";
    
    // Décoder la réponse pour obtenir plus de détails
    $errorResponse = json_decode($response, true);
    if ($errorResponse && isset($errorResponse['error']['message'])) {
        $errorDetails .= " - " . $errorResponse['error']['message'];
    } elseif ($httpCode === 401) {
        $errorDetails .= " - Token API invalide ou expiré. Vérifiez votre configuration.";
    } elseif ($httpCode === 403) {
        $errorDetails .= " - Accès refusé. Vérifiez les permissions de votre token API.";
    } elseif ($httpCode === 429) {
        // Erreur 429 : Rate limiting (trop de requêtes)
        $retryAfter = null;
        if (isset($errorResponse['retry_after'])) {
            $retryAfter = $errorResponse['retry_after'];
        } elseif (isset($errorResponse['error']['retry_after'])) {
            $retryAfter = $errorResponse['error']['retry_after'];
        }
        
        $errorDetails = "Limite de requêtes atteinte (429)";
        if ($retryAfter) {
            $errorDetails .= " - Réessayez dans " . $retryAfter . " secondes";
        } else {
            $errorDetails .= " - Veuillez patienter quelques minutes avant de réessayer";
        }
        $errorDetails .= ". L'API GitHub Models limite le nombre de requêtes par période.";
    } elseif ($httpCode === 500 || $httpCode === 502 || $httpCode === 503) {
        $errorDetails .= " - Le service IA est temporairement indisponible. Réessayez plus tard.";
    } else {
        $errorDetails .= " - Vérifiez votre token API ou la taille des images.";
    }
    
    echo json_encode([
        'success' => false,
        'message' => "Erreur API : $errorDetails",
        'error_code' => $httpCode,
        'error_type' => $httpCode === 429 ? 'rate_limit' : 'api_error'
    ]);
    exit;
}

// Traitement de la réponse réussie
$json = json_decode($response, true);

if (!$json || !isset($json['choices'][0]['message']['content'])) {
    echo json_encode([
        'success' => false,
        'message' => "Erreur : Format de réponse inattendu de l'API IA."
    ]);
    exit;
}

$aiAnalysis = $json['choices'][0]['message']['content'];
$aiAnalysis = str_replace(['```html', '```'], '', $aiAnalysis);

echo json_encode([
    'success' => true,
    'report_html' => $aiAnalysis
]);
?>