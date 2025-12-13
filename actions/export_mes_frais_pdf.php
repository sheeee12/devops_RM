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

$user_id = $_SESSION['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
$user_name = $_SESSION['user']['nom'] ?? $_SESSION['user_nom'] ?? 'Employé';
$user_role = $_SESSION['user_role'] ?? $_SESSION['user']['role'] ?? null;

if (!$user_id || $user_role !== 'employee') {
    die('Accès refusé');
}

$pdo = Database::getInstance()->getConnexion();

// Récupérer les mêmes filtres que la page mes_frais.php
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Construction de la requête (identique à mes_frais.php)
$sql = "SELECT * FROM demande WHERE user_id = ?";
$params = [$user_id];

// Filtre par statut
switch ($filter) {
    case 'pending':
        $sql .= " AND status IN ('Attente_Manager', 'Attente_Admin')";
        break;
    case 'approved':
        $sql .= " AND status IN ('Valide', 'Paye')";
        break;
    case 'rejected':
        $sql .= " AND status = 'Rejete'";
        break;
    case 'draft':
        $sql .= " AND status = 'Brouillon'";
        break;
}

// Recherche par texte
if (!empty($search)) {
    $sql .= " AND (titre_dem LIKE ? OR id_dem LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour obtenir le label du statut
function getStatusLabel($status) {
    $labels = [
        'Valide' => 'Validé',
        'Paye' => 'Remboursé',
        'Rejete' => 'Rejeté',
        'Attente_Manager' => 'En attente Manager',
        'Attente_Admin' => 'En attente Finance',
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
        $pdf->SetTitle('Mes Notes de Frais');
        $pdf->SetSubject('Liste des demandes de remboursement');
        
        // En-tête et pied de page
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(15, 30, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 20);
        
        $pdf->AddPage();
        
        // Logo
        $logoPath = __DIR__ . '/../assets/img/logo.png';
        $logoPath2 = __DIR__ . '/../assets/img/logo.jpg';
        $logoPath3 = __DIR__ . '/../assets/img/logo.jpeg';
        
        $logoX = 15;
        $logoY = 10;
        $logoFound = false;
        
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, $logoX, $logoY, 35, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            $logoFound = true;
        } elseif (file_exists($logoPath2)) {
            $pdf->Image($logoPath2, $logoX, $logoY, 35, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            $logoFound = true;
        } elseif (file_exists($logoPath3)) {
            $pdf->Image($logoPath3, $logoX, $logoY, 35, '', 'JPEG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            $logoFound = true;
        }
        
        if (!$logoFound) {
            $logoSize = 28;
            $pdf->SetLineWidth(2);
            $pdf->SetDrawColor(5, 150, 105);
            $pdf->SetFillColor(5, 150, 105);
            $pdf->Rect($logoX, $logoY, $logoSize, $logoSize, 'DF');
            
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetXY($logoX, $logoY + 6);
            $pdf->Cell($logoSize, 16, 'RM', 0, 0, 'C');
        }
        
        // Nom de l'entreprise
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetXY(48, $logoY + 3);
        $pdf->Cell(0, 8, 'Rembourse Maroc', 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetX(48);
        $pdf->Cell(0, 5, 'Gestion des Frais Professionnels', 0, 1);
        
        // Titre
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->SetY(35);
        $pdf->Cell(0, 10, 'MES NOTES DE FRAIS', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 5, 'Employé : ' . htmlspecialchars($user_name), 0, 1, 'C');
        $pdf->Cell(0, 5, 'Date d\'export : ' . date('d/m/Y à H:i'), 0, 1, 'C');
        $pdf->Ln(10);
        
        // Tableau des demandes
        if (empty($demandes)) {
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 10, 'Aucune demande trouvée.', 0, 1);
        } else {
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'Liste des Demandes (' . count($demandes) . ')', 0, 1);
            $pdf->SetFont('helvetica', '', 10);
            
            $html = '<table border="1" cellpadding="5">
                <tr style="background-color:#059669;color:white;">
                    <th width="10%">N°</th>
                    <th width="30%">Intitulé</th>
                    <th width="15%">Date</th>
                    <th width="15%">Montant</th>
                    <th width="20%">Statut</th>
                    <th width="10%">Date création</th>
                </tr>';
            
            foreach ($demandes as $d) {
                $html .= '<tr>
                    <td align="center">#' . str_pad($d['id_dem'], 5, '0', STR_PAD_LEFT) . '</td>
                    <td>' . htmlspecialchars($d['titre_dem'] ?? 'N/A') . '</td>
                    <td>' . date('d/m/Y', strtotime($d['date_dep'])) . '</td>
                    <td align="right">' . number_format($d['montant_total'], 2) . ' DH</td>
                    <td>' . getStatusLabel($d['status']) . '</td>
                    <td>' . date('d/m/Y', strtotime($d['created_at'])) . '</td>
                </tr>';
            }
            
            $html .= '</table>';
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Récapitulatif
            $pdf->Ln(10);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'Récapitulatif', 0, 1);
            $pdf->SetFont('helvetica', '', 10);
            
            $total_validees = 0;
            $total_rejetees = 0;
            $total_en_cours = 0;
            $total_brouillons = 0;
            $montant_total = 0;
            
            foreach ($demandes as $d) {
                $montant_total += $d['montant_total'];
                switch ($d['status']) {
                    case 'Valide':
                    case 'Paye':
                        $total_validees++;
                        break;
                    case 'Rejete':
                        $total_rejetees++;
                        break;
                    case 'Attente_Manager':
                    case 'Attente_Admin':
                        $total_en_cours++;
                        break;
                    case 'Brouillon':
                        $total_brouillons++;
                        break;
                }
            }
            
            $html_recap = '<table border="0" cellpadding="5">
                <tr>
                    <td width="50%" align="right"><strong>Total demandes :</strong></td>
                    <td width="50%">' . count($demandes) . '</td>
                </tr>
                <tr>
                    <td align="right"><strong>Validées/Remboursées :</strong></td>
                    <td>' . $total_validees . '</td>
                </tr>
                <tr>
                    <td align="right"><strong>En cours :</strong></td>
                    <td>' . $total_en_cours . '</td>
                </tr>
                <tr>
                    <td align="right"><strong>Rejetées :</strong></td>
                    <td>' . $total_rejetees . '</td>
                </tr>
                <tr>
                    <td align="right"><strong>Brouillons :</strong></td>
                    <td>' . $total_brouillons . '</td>
                </tr>
                <tr style="background-color:#f0f0f0;">
                    <td align="right"><strong>Montant total :</strong></td>
                    <td><strong>' . number_format($montant_total, 2) . ' DH</strong></td>
                </tr>
            </table>';
            
            $pdf->writeHTML($html_recap, true, false, true, false, '');
        }
        
        // Générer le PDF
        $filename = 'mes_notes_de_frais_' . date('Y-m-d_His') . '.pdf';
        $pdf->Output($filename, 'D'); // 'D' pour téléchargement direct
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
        <title>Mes Notes de Frais</title>
        <style>
            @media print {
                @page { margin: 1.5cm; }
                body { margin: 0; }
            }
            body { font-family: Arial, sans-serif; padding: 20px; }
            .header { text-align: center; margin-bottom: 30px;  padding-bottom: 15px; }
            .logo { display: inline-block; width: 50px; height: 50px; background: #059669; color: white; text-align: center; line-height: 50px; font-weight: bold; border-radius: 8px; }
            .company-name { font-size: 24px; font-weight: bold; color: #059669; margin: 10px 0 5px 0; }
            .company-subtitle { font-size: 12px; color: #64748b; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #059669; color: white; font-weight: bold; }
            .summary { margin-top: 30px; padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; }
            .summary-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="logo">RM</div>
            <div class="company-name">Rembourse Maroc</div>
            <div class="company-subtitle">Gestion des Frais Professionnels</div>
            <h2 style="margin-top: 20px; color: #1e293b;">Mes Notes de Frais</h2>
            <p>Employé : <?= htmlspecialchars($user_name) ?></p>
            <p>Date d'export : <?= date('d/m/Y à H:i') ?></p>
        </div>

        <h3>Liste des Demandes (<?= count($demandes) ?>)</h3>
        <?php if (empty($demandes)): ?>
            <p>Aucune demande trouvée.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>N°</th>
                    <th>Intitulé</th>
                    <th>Date</th>
                    <th>Montant</th>
                    <th>Statut</th>
                    <th>Date création</th>
                </tr>
                <?php 
                $total_validees = 0;
                $total_rejetees = 0;
                $total_en_cours = 0;
                $total_brouillons = 0;
                $montant_total = 0;
                
                foreach ($demandes as $d): 
                    $montant_total += $d['montant_total'];
                    switch ($d['status']) {
                        case 'Valide':
                        case 'Paye':
                            $total_validees++;
                            break;
                        case 'Rejete':
                            $total_rejetees++;
                            break;
                        case 'Attente_Manager':
                        case 'Attente_Admin':
                            $total_en_cours++;
                            break;
                        case 'Brouillon':
                            $total_brouillons++;
                            break;
                    }
                ?>
                <tr>
                    <td align="center">#<?= str_pad($d['id_dem'], 5, '0', STR_PAD_LEFT) ?></td>
                    <td><?= htmlspecialchars($d['titre_dem'] ?? 'N/A') ?></td>
                    <td><?= date('d/m/Y', strtotime($d['date_dep'])) ?></td>
                    <td align="right"><?= number_format($d['montant_total'], 2) ?> DH</td>
                    <td><?= getStatusLabel($d['status']) ?></td>
                    <td><?= date('d/m/Y', strtotime($d['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <div class="summary">
                <h3>Récapitulatif</h3>
                <div class="summary-row">
                    <span>Total demandes :</span>
                    <span><?= count($demandes) ?></span>
                </div>
                <div class="summary-row">
                    <span>Validées/Remboursées :</span>
                    <span><?= $total_validees ?></span>
                </div>
                <div class="summary-row">
                    <span>En cours :</span>
                    <span><?= $total_en_cours ?></span>
                </div>
                <div class="summary-row">
                    <span>Rejetées :</span>
                    <span><?= $total_rejetees ?></span>
                </div>
                <div class="summary-row">
                    <span>Brouillons :</span>
                    <span><?= $total_brouillons ?></span>
                </div>
                <div class="summary-row" style="font-weight: bold; font-size: 16px; border-top: 2px solid #059669; margin-top: 10px; padding: 15px 0;">
                    <span>Montant total :</span>
                    <span><?= number_format($montant_total, 2) ?> DH</span>
                </div>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px; padding: 20px; background: #f0f9ff; border: 1px solid #059669; border-radius: 8px;">
            <p style="margin: 0; color: #059669; font-weight: bold;"> Astuce : Utilisez "Enregistrer en PDF" dans le menu d'impression de votre navigateur</p>
            <p style="margin: 10px 0 0 0; color: #64748b; font-size: 12px;">Ou appuyez sur Ctrl+P puis choisissez "Enregistrer en PDF"</p>
        </div>

        <script>
            // Ouvrir automatiquement la boîte de dialogue d'impression
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            };
        </script>
    </body>
    </html>
    <?php
    exit();
}

