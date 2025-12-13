<?php
session_start();
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../includes/security.php';

protect_page('manager');

$pdo = Database::getInstance()->getConnexion();
$managerId = $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: historique.php');
    exit;
}

$employeeId = (int)$_GET['id'];

// Vérifier que l'employé appartient au manager
$sqlCheck = "SELECT u.* FROM users u WHERE u.user_id = ? AND u.manager_id = ?";
$stmt = $pdo->prepare($sqlCheck);
$stmt->execute([$employeeId, $managerId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: historique.php');
    exit;
}

// Récupérer toutes les demandes de l'employé
$sqlDemandes = "SELECT d.*, 
                CASE 
                    WHEN d.status = 'Valide' OR d.status = 'Paye' THEN 'Valide'
                    WHEN d.status = 'Rejete' THEN 'Rejete'
                    ELSE d.status
                END as status_group
                FROM demande d
                WHERE d.user_id = ?
                ORDER BY d.created_at DESC";

$stmt = $pdo->prepare($sqlDemandes);
$stmt->execute([$employeeId]);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = [
    'total' => count($demandes),
    'validees' => 0,
    'rejetees' => 0,
    'total_rembourse' => 0
];

foreach ($demandes as $dem) {
    if ($dem['status_group'] === 'Valide') {
        $stats['validees']++;
        $stats['total_rembourse'] += $dem['montant_total'];
    } elseif ($dem['status_group'] === 'Rejete') {
        $stats['rejetees']++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détails Employé | Rembourse Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/dark-theme.css">
    <?php
    $currentTheme = $_SESSION['theme'] ?? $_COOKIE['app_theme'] ?? 'light';
    ?>
    <script>
        // Appliquer le thème immédiatement pour éviter le flash
        (function() {
            const theme = '<?= $currentTheme ?>';
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>
    <div class="main-container">
        <div class="mb-4">
            <a href="historique.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Retour
            </a>
        </div>
        
        <div class="card-widget mb-4">
            <h5 class="fw-bold mb-3">Employé : <?= htmlspecialchars($employee['nom'] . ' ' . $employee['prenom']) ?></h5>
            <div class="row">
                <div class="col-md-4">
                    <small class="text-muted d-block">ID</small>
                    <strong><?= $employee['user_id'] ?></strong>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">Email</small>
                    <strong><?= htmlspecialchars($employee['email']) ?></strong>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">Total Remboursé</small>
                    <strong class="text-success"><?= number_format($stats['total_rembourse'], 2) ?> DH</strong>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card text-center p-3">
                    <div class="text-muted small mb-2">Total Demandes</div>
                    <div class="fs-3 fw-bold"><?= $stats['total'] ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center p-3">
                    <div class="text-muted small mb-2">Validées</div>
                    <div class="fs-3 fw-bold text-success"><?= $stats['validees'] ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center p-3">
                    <div class="text-muted small mb-2">Rejetées</div>
                    <div class="fs-3 fw-bold text-danger"><?= $stats['rejetees'] ?></div>
                </div>
            </div>
        </div>

        <h6 class="fw-bold mb-3">Historique des Demandes</h6>
        <div class="card-widget p-0">
            <div class="table-responsive">
                <table class="table table-app">
                    <thead class="bg-light">
                        <tr>
                            <th>Date Dépôt</th>
                            <th>Titre</th>
                            <th>Montant</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($demandes)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">Aucune demande</td></tr>
                        <?php else: ?>
                            <?php foreach ($demandes as $dem): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($dem['date_dep'])) ?></td>
                                <td><?= htmlspecialchars($dem['titre_dem']) ?></td>
                                <td><?= number_format($dem['montant_total'], 2) ?> DH</td>
                                <td>
                                    <?php
                                    $badges = [
                                        'Valide' => 'bg-success',
                                        'Rejete' => 'bg-danger',
                                        'Attente_Manager' => 'bg-warning',
                                        'Attente_Admin' => 'bg-info'
                                    ];
                                    $badgeClass = $badges[$dem['status']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= $dem['status'] ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="../../assets/js/theme.js"></script>
</body>
</html>

