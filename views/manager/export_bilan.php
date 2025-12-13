<?php
session_start();
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../includes/security.php';

protect_page('manager');

$pdo = Database::getInstance()->getConnexion();
$managerId = $_SESSION['user_id'];
$userName = $_SESSION['user']['nom'] ?? 'Manager';

// Récupérer les informations complètes du manager
try {
    $sqlManager = "SELECT nom, prenom, email, tel FROM users WHERE user_id = ?";
    $stmt = $pdo->prepare($sqlManager);
    $stmt->execute([$managerId]);
    $managerInfo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si le champ tel n'existe pas, essayer sans
    $sqlManager = "SELECT nom, prenom, email FROM users WHERE user_id = ?";
    $stmt = $pdo->prepare($sqlManager);
    $stmt->execute([$managerId]);
    $managerInfo = $stmt->fetch(PDO::FETCH_ASSOC);
}

$managerFullName = trim(($managerInfo['nom'] ?? '') . ' ' . ($managerInfo['prenom'] ?? ''));
$managerEmail = $managerInfo['email'] ?? 'N/A';
$managerTel = $managerInfo['tel'] ?? 'N/A';

// Récupérer les statistiques de l'équipe
$sql = "SELECT 
            u.user_id,
            u.nom,
            u.prenom,
            u.email,
            COUNT(DISTINCT d.id_dem) as nb_demandes,
            COUNT(DISTINCT CASE WHEN d.status = 'Valide' OR d.status = 'Paye' THEN d.id_dem END) as nb_validees,
            COUNT(DISTINCT CASE WHEN d.status = 'Rejete' THEN d.id_dem END) as nb_rejetees,
            COALESCE(SUM(CASE WHEN d.status != 'Rejete' THEN d.montant_total ELSE 0 END), 0) as total_rembourse,
            COUNT(DISTINCT CASE WHEN d.status != 'Rejete' THEN d.id_dem END) as nb_deplacements
        FROM users u
        LEFT JOIN demande d ON u.user_id = d.user_id
        WHERE u.manager_id = ?
        GROUP BY u.user_id, u.nom, u.prenom, u.email
        ORDER BY u.nom ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$managerId]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques globales
$sqlGlobal = "SELECT 
                COUNT(DISTINCT d.id_dem) as total_demandes,
                COUNT(DISTINCT CASE WHEN d.status = 'Valide' OR d.status = 'Paye' THEN d.id_dem END) as total_validees,
                COUNT(DISTINCT CASE WHEN d.status = 'Rejete' THEN d.id_dem END) as total_rejetees,
                COALESCE(SUM(CASE WHEN d.status != 'Rejete' THEN d.montant_total ELSE 0 END), 0) as total_global
              FROM users u
              LEFT JOIN demande d ON u.user_id = d.user_id
              WHERE u.manager_id = ?";

$stmt = $pdo->prepare($sqlGlobal);
$stmt->execute([$managerId]);
$globalStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérifier si TCPDF est disponible, sinon utiliser une solution simple
$useTCPDF = file_exists(__DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php');

if ($useTCPDF) {
    require_once __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';
    
    // Créer le PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    $pdf->SetCreator('Rembourse Maroc');
    $pdf->SetAuthor('Rembourse Maroc');
    $pdf->SetTitle('Bilan des Dépenses - Équipe');
    $pdf->SetSubject('Bilan des Dépenses');
    
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
    
    // Logo à gauche - taille réduite
    $logoSize = 12; // Taille réduite
    $logoX = 8; // Position à gauche
    $logoY = 7; // Aligné avec le début des infos
    
    // Informations de l'entreprise à droite du logo
    $infoX = 25; // Position après le logo
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
    
    $logoPath = __DIR__ . '/../../assets/img/logo.png';
    $logoPath2 = __DIR__ . '/../../assets/img/logo.jpg';
    $logoPath3 = __DIR__ . '/../../assets/img/logo.jpeg';
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
    
    // Espacement TRÈS GRAND avant le titre
    $pdf->Ln(35);
    
    // Titre "BILAN DES DÉPENSES DE L'ÉQUIPE" centré
    $pdf->SetTextColor(5, 150, 105);
    $pdf->SetFont('helvetica', 'B', 22);
    $pdf->Cell(0, 9, 'BILAN DES DÉPENSES DE L\'ÉQUIPE', 0, 1, 'C');
    
    // Période
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->Cell(0, 6, 'Période : ' . date('d/m/Y'), 0, 1, 'C');
    $pdf->Ln(8);
    
    // Statistiques globales
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Statistiques Globales', 0, 1);
    $pdf->SetFont('helvetica', '', 11);
    
    $html = '<table border="1" cellpadding="5">
        <tr style="background-color:#059669;color:white;">
            <th width="25%">Total Demandes</th>
            <th width="25%">Validées</th>
            <th width="25%">Rejetées</th>
            <th width="25%">Total Remboursé</th>
        </tr>
        <tr>
            <td align="center">' . ($globalStats['total_demandes'] ?? 0) . '</td>
            <td align="center">' . ($globalStats['total_validees'] ?? 0) . '</td>
            <td align="center">' . ($globalStats['total_rejetees'] ?? 0) . '</td>
            <td align="center">' . number_format($globalStats['total_global'] ?? 0, 2) . ' DH</td>
        </tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(10);
    
    // Détails par employé
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Détails par Employé', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    $html = '<table border="1" cellpadding="5">
        <tr style="background-color:#059669;color:white;">
            <th width="10%">ID</th>
            <th width="25%">Nom & Prénom</th>
            <th width="15%">Déplacements</th>
            <th width="20%">Total Remboursé</th>
            <th width="15%">Validées</th>
            <th width="15%">Rejetées</th>
        </tr>';
    
    foreach ($employees as $emp) {
        $html .= '<tr>
            <td align="center">' . $emp['user_id'] . '</td>
            <td>' . htmlspecialchars($emp['nom'] . ' ' . $emp['prenom']) . '</td>
            <td align="center">' . $emp['nb_deplacements'] . '</td>
            <td align="right">' . number_format($emp['total_rembourse'], 2) . ' DH</td>
            <td align="center">' . $emp['nb_validees'] . '</td>
            <td align="center">' . $emp['nb_rejetees'] . '</td>
        </tr>';
    }
    
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Informations du manager et zone de signature
    $pdf->Ln(30);
    $signatureY = $pdf->GetY();
    
    // Colonne gauche : Informations du manager avec style amélioré
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetXY(15, $signatureY);
    $pdf->Cell(0, 8, 'Informations du Manager', 0, 1);
    
    // Espacement après le titre
    $pdf->Ln(3);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetX(15);
    $pdf->Cell(0, 7, 'Nom : ' . htmlspecialchars($managerFullName), 0, 1);
    
    $pdf->Ln(1); // Espacement entre les lignes
    $pdf->SetX(15);
    $pdf->Cell(0, 7, 'Email : ' . htmlspecialchars($managerEmail), 0, 1);
    
    $pdf->Ln(1); // Espacement entre les lignes
    $pdf->SetX(15);
    $pdf->Cell(0, 7, 'Téléphone : ' . htmlspecialchars($managerTel), 0, 1);
    
    $pdf->Ln(1); // Espacement entre les lignes
    $pdf->SetX(15);
    $pdf->Cell(0, 7, 'Date de création du rapport : ' . date('d/m/Y à H:i'), 0, 1);
    
    // Colonne droite : Zone de signature
    $signatureX = 120;
    $pdf->SetXY($signatureX, $signatureY);
    
    // Cadre pour la signature
    $pdf->SetLineWidth(0.3);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->Rect($signatureX, $signatureY, 75, 40);
    
    // Ligne de signature
    $pdf->SetLineWidth(0.5);
    $pdf->Line($signatureX + 5, $signatureY + 25, $signatureX + 70, $signatureY + 25);
    
    // Texte "Signature"
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetXY($signatureX, $signatureY + 2);
    $pdf->Cell(75, 5, 'Signature', 0, 0, 'C');
    
    // Texte en dessous de la ligne
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY($signatureX, $signatureY + 28);
    $pdf->Cell(75, 5, 'Nom et cachet du manager', 0, 0, 'C');
    
    //$pdf->SetXY($signatureX, $signatureY + 33);
    //$pdf->Cell(75, 5, 'Date : _______________', 0, 0, 'C');
    
    // Générer le PDF
    $pdf->Output('bilan_depenses_' . date('Y-m-d') . '.pdf', 'D');
    
} else {
    // Solution de secours : HTML avec option d'impression
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Bilan des Dépenses</title>
        <style>
            @media print {
                .no-print { display: none; }
                body { margin: 0; }
            }
            body { font-family: Arial, sans-serif; padding: 0; margin: 0; }
            .header { background: white; padding: 20px; margin-bottom: 25px; }
            .header-content { display: flex; justify-content: space-between; align-items: flex-start; position: relative; }
            .logo { width: 32px; height: 32px; background: #059669; color: white; text-align: center; line-height: 32px; font-weight: 700; border-radius: 6px; font-size: 11px; flex-shrink: 0; order: 2; }
            .company-info { text-align: left; order: 1; flex: 1; }
            .company-name { font-size: 14px; font-weight: bold; color: #000000; margin: 0 0 6px 0; }
            .company-address { font-size: 9px; color: #64748b; margin: 3px 0; }
            .company-email { font-size: 9px; color: #64748b; margin: 3px 0; }
            .title-section { text-align: center; padding: 20px 0; margin-bottom: 25px; }
            .main-title { font-size: 22px; font-weight: bold; color: #059669; margin: 0 0 8px 0; }
            .ref-number { font-size: 11px; color: #1e293b; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #059669; color: white; }
            .signature { margin-top: 50px; }
        </style>
    </head>
    <body>
        <div class="no-print" style="margin-bottom: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; background: #059669; color: white; border: none; border-radius: 5px; cursor: pointer;">
                Imprimer / Enregistrer en PDF
            </button>
        </div>
        
        <div class="header">
            <div class="header-content">
                <div class="company-info">
                    <div class="company-name">REMBOURSE MAROC</div>
                    <div class="company-address">Tour CFC, Casablanca</div>
                    <div class="company-email">support@remboursemaroc.com</div>
                </div>
                <div class="logo">RM</div>
            </div>
        </div>
        
        <div class="title-section">
            <h2 class="main-title">BILAN DES DÉPENSES DE L'ÉQUIPE</h2>
            <p class="ref-number">Période : <?= date('d/m/Y') ?></p>
        </div>
        
        <h2>Statistiques Globales</h2>
        <table>
            <tr>
                <th>Total Demandes</th>
                <th>Validées</th>
                <th>Rejetées</th>
                <th>Total Remboursé</th>
            </tr>
            <tr>
                <td><?= $globalStats['total_demandes'] ?? 0 ?></td>
                <td><?= $globalStats['total_validees'] ?? 0 ?></td>
                <td><?= $globalStats['total_rejetees'] ?? 0 ?></td>
                <td><?= number_format($globalStats['total_global'] ?? 0, 2) ?> DH</td>
            </tr>
        </table>
        
        <h2>Détails par Employé</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Nom & Prénom</th>
                <th>Déplacements</th>
                <th>Total Remboursé</th>
                <th>Validées</th>
                <th>Rejetées</th>
            </tr>
            <?php foreach ($employees as $emp): ?>
            <tr>
                <td><?= $emp['user_id'] ?></td>
                <td><?= htmlspecialchars($emp['nom'] . ' ' . $emp['prenom']) ?></td>
                <td><?= $emp['nb_deplacements'] ?></td>
                <td><?= number_format($emp['total_rembourse'], 2) ?> DH</td>
                <td><?= $emp['nb_validees'] ?></td>
                <td><?= $emp['nb_rejetees'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <div style="margin-top: 50px; display: flex; justify-content: space-between; align-items: flex-start;">
            <!-- Colonne gauche : Informations du manager -->
            <div style="flex: 1; padding-right: 30px;">
                <h3 style="font-size: 14px; font-weight: bold; margin-bottom: 15px;">Informations du Manager</h3>
                <p style="margin: 8px 0; font-size: 12px;"><strong>Nom :</strong> <?= htmlspecialchars($managerFullName) ?></p>
                <p style="margin: 8px 0; font-size: 12px;"><strong>Email :</strong> <?= htmlspecialchars($managerEmail) ?></p>
                <p style="margin: 8px 0; font-size: 12px;"><strong>Téléphone :</strong> <?= htmlspecialchars($managerTel) ?></p>
                <p style="margin: 8px 0; font-size: 12px;"><strong>Date de création du rapport :</strong> <?= date('d/m/Y à H:i') ?></p>
            </div>
            
            <!-- Colonne droite : Zone de signature -->
            <div style="width: 250px; border: 2px solid #000; padding: 15px; text-align: center;">
                <p style="font-weight: bold; margin-bottom: 30px; font-size: 13px;">Signature</p>
                <hr style="border: 1px solid #000; margin: 20px 0;">
                <p style="font-size: 10px; margin-top: 20px; color: #666;">Nom et cachet du manager</p>
                <p style="font-size: 10px; margin-top: 10px; color: #666;">Date : _______________</p>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>

