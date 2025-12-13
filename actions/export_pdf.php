<?php
// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/security.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Location: ../views/auth/login.php');
    exit();
}

// Vérifier que l'ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID de demande manquant');
}

$id_dem = intval($_GET['id']);
$user_id = $_SESSION['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? $_SESSION['user']['role'] ?? null;

if (!$user_id) {
    die('Utilisateur non identifié');
}

$pdo = Database::getInstance()->getConnexion();

// Récupérer la demande
$sql = "
    SELECT d.*, 
           d.date_fin,
           a.montant as montant_avance,
           u.nom, u.prenom, u.email
    FROM demande d
    LEFT JOIN avances a ON d.avance_id = a.id_avance
    LEFT JOIN users u ON d.user_id = u.user_id
    WHERE d.id_dem = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_dem]);
$demande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demande) {
    die('Demande introuvable');
}

// Vérifier les permissions : l'employé peut voir ses propres demandes, le manager peut voir celles de son équipe
if ($user_role === 'employee' && $demande['user_id'] != $user_id) {
    die('Accès refusé');
}

if ($user_role === 'manager') {
    $managerId = $_SESSION['user_id'];
    $stmt_check = $pdo->prepare("SELECT manager_id FROM users WHERE user_id = ?");
    $stmt_check->execute([$demande['user_id']]);
    $employee = $stmt_check->fetch(PDO::FETCH_ASSOC);
    if (!$employee || $employee['manager_id'] != $managerId) {
        die('Accès refusé');
    }
}

// Récupérer les lignes de frais
$sqlLines = "
    SELECT el.*, c.nom_categ, c.plafond_max
    FROM expense_line el
    JOIN categories c ON el.id_categ = c.id_categ
    WHERE el.id_dem = ?
    ORDER BY el.date_depense ASC
";

$stmtLines = $pdo->prepare($sqlLines);
$stmtLines->execute([$id_dem]);
$lignes = $stmtLines->fetchAll(PDO::FETCH_ASSOC);

// Calculer les totaux
$total_frais = 0;
foreach ($lignes as $l) {
    $total_frais += $l['montant'] ?? 0;
}

$montant_avance = $demande['montant_avance'] ?? 0;
$net_a_payer = $total_frais - $montant_avance;

// Fonction pour obtenir le badge de statut
function getStatusLabel($status) {
    $labels = [
        'Valide' => 'Validé',
        'Paye' => 'Remboursé',
        'Rejete' => 'Rejeté',
        'Attente_Manager' => 'En attente de validation manager',
        'Attente_Admin' => 'En attente de validation finance',
        'Brouillon' => 'Brouillon'
    ];
    return $labels[$status] ?? $status;
}

// Vérifier si TCPDF est disponible
$useTCPDF = file_exists(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php');

if ($useTCPDF) {
    try {
        require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
        
        // Créer le PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    $pdf->SetCreator('Rembourse Maroc');
    $pdf->SetAuthor('Rembourse Maroc');
    $pdf->SetTitle('Demande de Remboursement #' . $id_dem);
    $pdf->SetSubject('Demande de Remboursement');
    
    // En-tête et pied de page - DÉSACTIVÉS pour éviter les lignes automatiques
    $pdf->setPrintHeader(false); // Masquer le header automatique
    $pdf->setPrintFooter(false); // Masquer le footer automatique
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(15, 15, 15); // Marge supérieure réduite
    $pdf->SetHeaderMargin(0); // Marge header à 0
    $pdf->SetFooterMargin(0); // Marge footer à 0
    $pdf->SetAutoPageBreak(TRUE, 20);
    
    $pdf->AddPage();
    
    // ===== EN-TÊTE EXACT COMME L'IMAGE =====
    $headerY = 5; // Remonté de 10 à 5
    $infoX = 25;
    
    // Informations de l'entreprise à gauche
    $infoStartY = $headerY;
    
    // Nom de l'entreprise en noir, gras
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetXY($infoX, $infoStartY);
    $pdf->Cell(140, 6, 'REMBOURSE MAROC ', 0, 1);
    
    // Adresse en gris
    $addressY = $infoStartY + 6; // Réduit de 7 à 6 pour rapprocher
    $pdf->SetTextColor(100, 116, 139);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetX($infoX);
    $pdf->Cell(140, 5, 'Tour CFC, Casablanca', 0, 1);
    
    // Email
    $pdf->SetTextColor(100, 116, 139);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetX($infoX);
    $pdf->Cell(140, 5, 'support@remboursemaroc.com', 0, 1);
    
    // Logo à droite - taille réduite
    $logoSize = 12; // Taille réduite (environ 21px)
    $logoX = 8; // Position à droite ajustée
    $logoY = 7; // Aligné avec le début des infos
    
    $logoPath = __DIR__ . '/../assets/img/logo.png';
    $logoPath2 = __DIR__ . '/../assets/img/logo.jpg';
    $logoPath3 = __DIR__ . '/../assets/img/logo.jpeg';
    $logoFound = false;
    
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, $logoX, $logoY, $logoSize, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $logoFound = true;
    } elseif (file_exists($logoPath2)) {
        $pdf->Image($logoPath2, $logoX, $logoY, $logoSize, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $logoFound = true;
    } elseif (file_exists($logoPath3)) {
        $pdf->Image($logoPath3, $logoX, $logoY, $logoSize, '', 'JPEG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $logoFound = true;
    }
    
    if (!$logoFound) {
        // Logo RM carré vert avec coins arrondis
        $pdf->SetDrawColor(5, 150, 105);
        $pdf->SetFillColor(5, 150, 105);
        $pdf->RoundedRect($logoX, $logoY, $logoSize, $logoSize, 1.5, '1111', 'DF');
        
        // Centrer le texte "RM" dans le carré
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 12);
        // Calculer la position pour centrer verticalement et horizontalement
        $textX = $logoX + ($logoSize / 2);
        $textY = $logoY + ($logoSize / 2) - 2; // Ajustement pour centrer verticalement
        $pdf->SetXY($logoX, $textY);
        $pdf->Cell($logoSize, 4, 'RM', 0, 0, 'C');
    }
    
    // Espacement TRÈS GRAND avant le titre "NOTE DE FRAIS"
    $pdf->Ln(35);
    
    // Titre "NOTE DE FRAIS" centré
    $pdf->SetTextColor(5, 150, 105);
    $pdf->SetFont('helvetica', 'B', 22);
    $pdf->Cell(0, 9, 'NOTE DE FRAIS', 0, 1, 'C');
    
    // Référence
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->Cell(0, 6, 'Réf: #' . str_pad($id_dem, 6, '0', STR_PAD_LEFT), 0, 1, 'C');
    $pdf->Ln(8);
    
    // Informations de la demande - Layout en deux colonnes
    $startY = $pdf->GetY();
    
    // Colonne gauche - COLLABORATEUR
    $pdf->SetFillColor(5, 150, 105);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Rect(15, $startY, 85, 7, 'F');
    $pdf->SetXY(15, $startY);
    $pdf->Cell(85, 7, 'COLLABORATEUR', 0, 1, 'L');
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetXY(15, $startY + 8);
    $pdf->Cell(85, 6, 'Nom : ' . htmlspecialchars(trim(($demande['nom'] ?? '') . ' ' . ($demande['prenom'] ?? ''))), 0, 1);
    $pdf->SetX(15);
    $pdf->Cell(85, 6, 'Email : ' . htmlspecialchars($demande['email'] ?? 'N/A'), 0, 1);
    
    // Récupérer l'équipe si disponible
    $teamName = 'N/A';
    try {
        $stmtTeam = $pdo->prepare("SELECT t.nom_team FROM users u LEFT JOIN teams t ON u.team_id = t.team_id WHERE u.user_id = ?");
        $stmtTeam->execute([$demande['user_id']]);
        $team = $stmtTeam->fetch(PDO::FETCH_ASSOC);
        if ($team && !empty($team['nom_team'])) {
            $teamName = $team['nom_team'];
        }
    } catch (Exception $e) {
        // Ignorer l'erreur
    }
    $pdf->SetX(15);
    $pdf->Cell(85, 6, 'Equipe : ' . htmlspecialchars($teamName), 0, 1);
    
    // Colonne droite - DÉTAILS MISSION
    $pdf->SetFillColor(5, 150, 105);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Rect(110, $startY, 85, 7, 'F');
    $pdf->SetXY(110, $startY);
    $pdf->Cell(85, 7, 'DÉTAILS MISSION', 0, 1, 'L');
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetXY(110, $startY + 8);
    $pdf->Cell(85, 6, 'Intitulé : ' . htmlspecialchars($demande['titre_dem'] ?? 'N/A'), 0, 1);
    $pdf->SetX(110);
    $pdf->Cell(85, 6, 'Date : ' . date('d/m/Y', strtotime($demande['date_dep'])), 0, 1);
    if (isset($demande['date_fin']) && !empty($demande['date_fin']) && $demande['date_fin'] != $demande['date_dep']) {
        $pdf->SetX(110);
        $pdf->Cell(85, 6, 'Date fin : ' . date('d/m/Y', strtotime($demande['date_fin'])), 0, 1);
    }
    $pdf->SetX(110);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(85, 6, 'Statut : ' . getStatusLabel($demande['status']), 0, 1);
    
    $pdf->SetY($startY + 35);
    $pdf->Ln(5);
    
    // Détails des lignes de frais
    if (empty($lignes)) {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 10, 'Aucune ligne de frais enregistrée.', 0, 1);
    } else {
        $html = '<table border="0" cellpadding="6" cellspacing="0" style="width:100%;">
            <tr style="background-color:#059669;color:white;">
                <th width="15%" style="border:1px solid #047857;padding:8px;text-align:left;font-weight:bold;font-size:10px;">Date</th>
                <th width="25%" style="border:1px solid #047857;padding:8px;text-align:left;font-weight:bold;font-size:10px;">Catégorie</th>
                <th width="40%" style="border:1px solid #047857;padding:8px;text-align:left;font-weight:bold;font-size:10px;">Description</th>
                <th width="20%" style="border:1px solid #047857;padding:8px;text-align:right;font-weight:bold;font-size:10px;">Montant (DH)</th>
            </tr>';
        
        $rowNum = 0;
        foreach ($lignes as $ligne) {
            $details = '';
            if (!empty($ligne['details_specifiques'])) {
                $detailsJson = json_decode($ligne['details_specifiques'], true);
                if (is_array($detailsJson)) {
                    $details = implode(', ', array_map(function($k, $v) {
                        return "$k: $v";
                    }, array_keys($detailsJson), $detailsJson));
                }
            }
            
            $bgColor = ($rowNum % 2 == 0) ? '#ffffff' : '#f8fafc';
            $html .= '<tr style="background-color:' . $bgColor . ';">
                <td style="border:1px solid #e2e8f0;padding:8px;font-size:9px;">' . date('d/m/Y', strtotime($ligne['date_depense'])) . '</td>
                <td style="border:1px solid #e2e8f0;padding:8px;font-size:9px;">' . htmlspecialchars($ligne['nom_categ']) . '</td>
                <td style="border:1px solid #e2e8f0;padding:8px;font-size:9px;">' . htmlspecialchars($details ?: '-') . '</td>
                <td style="border:1px solid #e2e8f0;padding:8px;text-align:right;font-size:9px;font-weight:bold;">' . number_format($ligne['montant'], 2) . '</td>
            </tr>';
            $rowNum++;
        }
        
        $html .= '</table>';
        $pdf->writeHTML($html, true, false, true, false, '');
    }
    
    $pdf->Ln(8);
    
    // Récapitulatif financier
    $summaryY = $pdf->GetY();
    $html = '<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">
        <tr>
            <td width="70%" align="right" style="padding:6px;font-size:10px;"><strong>Total des frais :</strong></td>
            <td width="30%" align="right" style="padding:6px;font-size:10px;font-weight:bold;">' . number_format($total_frais, 2) . ' DH</td>
        </tr>';
    
    if ($montant_avance > 0) {
        $html .= '<tr>
            <td align="right" style="padding:6px;font-size:10px;"><strong>Avance reçue :</strong></td>
            <td align="right" style="padding:6px;font-size:10px;font-weight:bold;">-' . number_format($montant_avance, 2) . ' DH</td>
        </tr>';
    }
    
    $html .= '<tr style="background-color:#059669;color:white;">
            <td align="right" style="padding:10px;font-size:11px;font-weight:bold;">Net à payer :</td>
            <td align="right" style="padding:10px;font-size:11px;font-weight:bold;">' . number_format($net_a_payer, 2) . ' DH</td>
        </tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Zone de signature
    $pdf->Ln(25);
    $signatureY = $pdf->GetY();
    
    // Signature Employé
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY(15, $signatureY);
    $pdf->Cell(60, 5, 'Signature Employé', 0, 0);
    $pdf->SetLineWidth(0.5);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Line(15, $signatureY + 8, 75, $signatureY + 8);
    
    // Validation Manager
    $pdf->SetXY(80, $signatureY);
    $pdf->Cell(60, 5, 'Validation Manager', 0, 0);
    $pdf->Line(80, $signatureY + 8, 140, $signatureY + 8);
    
    // Service Comptable
    $pdf->SetXY(145, $signatureY);
    $pdf->Cell(50, 5, 'Service Comptable', 0, 0);
    $pdf->Line(145, $signatureY + 8, 195, $signatureY + 8);
    
        // Générer le PDF
        $filename = 'demande_' . str_pad($id_dem, 5, '0', STR_PAD_LEFT) . '_' . date('Y-m-d') . '.pdf';
        $pdf->Output($filename, 'D');
        exit();
    } catch (Exception $e) {
        // En cas d'erreur avec TCPDF, utiliser la solution de secours
        error_log('Erreur TCPDF: ' . $e->getMessage());
    }
}

// Solution de secours si TCPDF n'est pas disponible ou en cas d'erreur
if (!$useTCPDF || isset($e)) {
    // Solution de secours : générer une page HTML optimisée pour impression/PDF
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Demande #<?= str_pad($id_dem, 5, '0', STR_PAD_LEFT) ?></title>
        <style>
            @media print {
                @page { margin: 1.5cm; }
                body { margin: 0; }
            }
            body { font-family: Arial, sans-serif; padding: 0; margin: 0; }
            .header { background: white; padding: 20px; margin-bottom: 25px; }
            .header-content { display: flex; justify-content: space-between; align-items: flex-start; position: relative; }
            .logo { width: 3px;

                    height: 3px;

                    background: var(--primary);

                    color: white;

                    border-radius: 8px;

                    display: flex;

                    align-items: center;

                    justify-content: center;

                    font-weight: 700;

                    font-size: 1rem; }
            .company-info { text-align: left; order: 1; flex: 1; }
            .company-name { font-size: 14px; font-weight: bold; color: #000000; margin: 0 0 6px 0; }
            .company-address { font-size: 9px; color: #64748b; margin: 3px 0;  }
            .company-email { font-size: 9px; color: #64748b; margin: 3px 0;  }
            .title-section { text-align: center; padding: 20px 0; margin-bottom: 25px; }
            .main-title { font-size: 22px; font-weight: bold; color: #059669; margin: 0 0 8px 0; }
            .ref-number { font-size: 11px; color: #1e293b; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #059669; color: white; font-weight: bold; padding: 10px; font-size: 11px; }
            td { padding: 8px; font-size: 10px; }
            tr:nth-child(even) { background-color: #f8fafc; }
            .summary { margin-top: 30px; padding: 0; background: transparent; border: none; }
            .summary-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
            .summary-row.total { font-weight: bold; font-size: 12px; background-color: #059669; color: white; margin-top: 10px; padding: 12px 15px; border: none; border-radius: 4px; }
            .summary-row.total span:last-child { color: white; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="header-content">
                <div class="company-info">
                    <div class="company-name">REMBOURSE MAROC </div>
                    <div class="company-address">Tour CFC, Casablanca</div>
                    <div class="company-email">support@remboursemaroc.com</div>
                </div>
                <div class="logo">RM</div>
            </div>
        </div>
        
        <div class="title-section">
            <h2 class="main-title">NOTE DE FRAIS</h2>
            <p class="ref-number">Réf: #<?= str_pad($id_dem, 6, '0', STR_PAD_LEFT) ?></p>
        </div>

        <div style="display: flex; gap: 20px; margin: 20px 0;">
            <div style="flex: 1; border: 1px solid #e2e8f0;">
                <div style="background-color: #059669; color: white; padding: 8px; font-weight: bold; font-size: 11px;">COLLABORATEUR</div>
                <div style="padding: 10px; font-size: 10px;">
                    <div style="margin-bottom: 6px;"><strong>Nom :</strong> <?= htmlspecialchars(trim(($demande['nom'] ?? '') . ' ' . ($demande['prenom'] ?? ''))) ?></div>
                    <div style="margin-bottom: 6px;"><strong>Email :</strong> <?= htmlspecialchars($demande['email'] ?? 'N/A') ?></div>
                    <?php
                    $teamName = 'N/A';
                    try {
                        $stmtTeam = $pdo->prepare("SELECT t.nom_equipe FROM users u LEFT JOIN teams t ON u.team_id = t.id_equipe WHERE u.user_id = ?");
                        $stmtTeam->execute([$demande['user_id']]);
                        $team = $stmtTeam->fetch(PDO::FETCH_ASSOC);
                        if ($team && !empty($team['nom_equipe'])) {
                            $teamName = $team['nom_equipe'];
                        }
                    } catch (Exception $e) {
                        // Ignorer l'erreur
                    }
                    ?>
                    <div><strong>Equipe :</strong> <?= htmlspecialchars($teamName) ?></div>
                </div>
            </div>
            <div style="flex: 1; border: 1px solid #e2e8f0;">
                <div style="background-color: #059669; color: white; padding: 8px; font-weight: bold; font-size: 11px;">DÉTAILS MISSION</div>
                <div style="padding: 10px; font-size: 10px;">
                    <div style="margin-bottom: 6px;"><strong>Intitulé :</strong> <?= htmlspecialchars($demande['titre_dem'] ?? 'N/A') ?></div>
                    <div style="margin-bottom: 6px;"><strong>Date :</strong> <?= date('d/m/Y', strtotime($demande['date_dep'])) ?></div>
                    <?php if (isset($demande['date_fin']) && !empty($demande['date_fin']) && $demande['date_fin'] != $demande['date_dep']): ?>
                    <div style="margin-bottom: 6px;"><strong>Date fin :</strong> <?= date('d/m/Y', strtotime($demande['date_fin'])) ?></div>
                    <?php endif; ?>
                    <div><strong>Statut :</strong> <?= getStatusLabel($demande['status']) ?></div>
                </div>
            </div>
        </div>

        <h3 style="margin-top: 25px; color: #059669; font-size: 14px;">Détail des Frais</h3>
        <?php if (empty($lignes)): ?>
            <p>Aucune ligne de frais enregistrée.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th width="15%">Date</th>
                    <th width="25%">Catégorie</th>
                    <th width="40%">Description</th>
                    <th width="20%" style="text-align: right;">Montant (DH)</th>
                </tr>
                <?php foreach ($lignes as $ligne): 
                    $details = '';
                    if (!empty($ligne['details_specifiques'])) {
                        $detailsJson = json_decode($ligne['details_specifiques'], true);
                        if (is_array($detailsJson)) {
                            $details = implode(', ', array_map(function($k, $v) {
                                return "$k: $v";
                            }, array_keys($detailsJson), $detailsJson));
                        }
                    }
                ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($ligne['date_depense'])) ?></td>
                    <td><?= htmlspecialchars($ligne['nom_categ']) ?></td>
                    <td><?= htmlspecialchars($details ?: '-') ?></td>
                    <td align="right" style="font-weight: bold;"><?= number_format($ligne['montant'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <div class="summary">
            <div class="summary-row">
                <span><strong>Total des frais :</strong></span>
                <span><strong><?= number_format($total_frais, 2) ?> DH</strong></span>
            </div>
            <?php if ($montant_avance > 0): ?>
            <div class="summary-row">
                <span><strong>Avance reçue :</strong></span>
                <span><strong>- <?= number_format($montant_avance, 2) ?> DH</strong></span>
            </div>
            <?php endif; ?>
            <div class="summary-row total">
                <span>Net à payer :</span>
                <span><?= number_format($net_a_payer, 2) ?> DH</span>
            </div>
        </div>

        <div style="margin-top: 40px; display: flex; justify-content: space-between;">
            <div style="width: 30%;">
                <div style="font-size: 10px; margin-bottom: 5px;">Signature Employé</div>
                <div style="border-bottom: 1px solid #ccc; padding-bottom: 20px;"></div>
            </div>
            <div style="width: 30%;">
                <div style="font-size: 10px; margin-bottom: 5px;">Validation Manager</div>
                <div style="border-bottom: 1px solid #ccc; padding-bottom: 20px;"></div>
            </div>
            <div style="width: 30%;">
                <div style="font-size: 10px; margin-bottom: 5px;">Service Comptable</div>
                <div style="border-bottom: 1px solid #ccc; padding-bottom: 20px;"></div>
            </div>
        </div>

        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    </body>
    </html>
    <?php
    exit();
}

