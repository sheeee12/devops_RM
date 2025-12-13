<?php
session_start();
require_once '../config/Database.php';

if (!isset($_GET['id'])) exit('Erreur ID');

$pdo = Database::getInstance()->getConnexion();

$sql = "SELECT el.*, c.nom_categ, c.plafond_max 
        FROM expense_line el
        JOIN categories c ON el.id_categ = c.id_categ
        WHERE el.id_dem = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_GET['id']]);
$lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($lines)) {
    echo '<div class="alert alert-warning">Aucune ligne de frais trouvée.</div>';
    exit;
}

foreach ($lines as $l) {
    // Vérification plafond simple
    $badgePlafond = '';
    if ($l['montant'] > $l['plafond_max']) {
        $badgePlafond = '<span class="badge bg-danger ms-2" style="font-size: 0.75rem; padding: 4px 8px;">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>Dépassement Plafond
        </span>';
    }
    
    // Parsing JSON des détails
    $detailsHtml = '';
    if ($l['details_specifiques']) {
        $json = json_decode($l['details_specifiques'], true);
        if ($json) {
            $detailsHtml = '<div class="mt-2 p-2 bg-light rounded" style="font-size: 0.85rem;">';
            foreach ($json as $k => $v) {
                $icon = '';
                if (stripos($k, 'hotel') !== false || stripos($k, 'hôtel') !== false) $icon = '<i class="bi bi-building me-1"></i>';
                elseif (stripos($k, 'ville') !== false || stripos($k, 'city') !== false) $icon = '<i class="bi bi-geo-alt me-1"></i>';
                elseif (stripos($k, 'restaurant') !== false) $icon = '<i class="bi bi-egg-fried me-1"></i>';
                else $icon = '<i class="bi bi-info-circle me-1"></i>';
                
                $detailsHtml .= '<span class="d-inline-block me-3 mb-1">
                    '.$icon.'<strong>'.ucfirst($k).':</strong> <span class="text-dark">'.htmlspecialchars($v).'</span>
                </span>';
            }
            $detailsHtml .= '</div>';
        }
    }

    // Gérer le chemin du justificatif
    $proofPath = $l['justificatif_path'] ?? '';
    if (!empty($proofPath)) {
        // Nettoyer le chemin si il contient déjà un préfixe
        if (strpos($proofPath, 'uploads/proofs/') === 0) {
            $proofPath = str_replace('uploads/proofs/', '', $proofPath);
        }
        if (strpos($proofPath, 'uploads/justificatifs/') === 0) {
            $proofPath = str_replace('uploads/justificatifs/', '', $proofPath);
        }
        $proofUrl = '../../uploads/proofs/' . htmlspecialchars($proofPath);
    } else {
        $proofUrl = '#';
    }
    
    $onclickAttr = !empty($l['justificatif_path']) ? 'onclick="window.open(\''.$proofUrl.'\', \'_blank\')"' : '';
    
    echo '
    <div class="card mb-3 border-0 shadow-sm" '.$onclickAttr.' style="cursor:'.(!empty($l['justificatif_path']) ? 'pointer' : 'default').'; border-radius: 12px; border-left: 4px solid #059669; transition: all 0.3s ease;">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="fw-bold text-dark fs-6">'.$l['nom_categ'].'</div>
                        '.$badgePlafond.'
                    </div>
                    <div class="small text-muted mb-2">
                        <i class="bi bi-calendar3 me-1"></i> 
                        '.date('d/m/Y', strtotime($l['date_depense'])).'
                    </div>
                    '.$detailsHtml.'
                    '.(!empty($l['justificatif_path']) ? '<div class="mt-2"><a href="'.$proofUrl.'" target="_blank" class="text-primary small text-decoration-none"><i class="bi bi-paperclip me-1"></i>Voir le justificatif</a></div>' : '').'
                </div>
                <div class="text-end ms-3">
                    <div class="fw-bold fs-4 text-primary mb-1">'.number_format($l['montant'], 2).' <small class="fs-6">DH</small></div>
                    <small class="text-muted d-block">
                        <i class="bi bi-shield-check me-1"></i>
                        Plafond: '.number_format($l['plafond_max'], 2).' DH
                    </small>
                </div>
            </div>
        </div>
    </div>';
}
?>