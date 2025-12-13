<?php
// -------------------------------------------------------------------------
// MODIFIER BROUILLON - STYLE DESKTOP APP (CORRIGÉ)
// -------------------------------------------------------------------------
require_once __DIR__ . '/../../includes/session.php';
requireRole('employee');
require_once __DIR__ . '/../../config/Database.php';

// Vérification ID
if (!isset($_GET['id'])) {
    header("Location: mes_brouillons.php");
    exit();
}

$id_dem = $_GET['id'];
$user_id = $_SESSION['user']['user_id'];
$user_name = $_SESSION['user']['nom'];
$user_role_raw = $_SESSION['user']['role'];
$role_display = ($user_role_raw === 'employee') ? 'Collaborateur' : ucfirst($user_role_raw);
$avatar_bdd = $_SESSION['user']['avatar'] ?? 'default.png';
$chemin_physique = __DIR__ . '/../../assets/img/' . $avatar_bdd;
$avatar = (file_exists($chemin_physique) && !empty($avatar_bdd)) ? '../../assets/img/' . $avatar_bdd : '../../assets/img/default.png';

$pdo = Database::getInstance()->getConnexion();

// --- NOTIFICATIONS ---
require_once __DIR__ . '/../../includes/employee_notifications.php';
$notifications = getEmployeeNotifications($pdo, $user_id);
$notificationCount = count($notifications);

// Récupérer le dossier (Sécurité : vérifier user_id)
$stmt = $pdo->prepare("SELECT * FROM demande WHERE id_dem = ? AND user_id = ? AND status = 'Brouillon'");
$stmt->execute([$id_dem, $user_id]);
$demande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demande) {
    $_SESSION['error'] = "Ce brouillon n'existe pas ou ne vous appartient pas.";
    header("Location: mes_brouillons.php");
    exit();
}

// Récupérer les lignes existantes
$stmtLines = $pdo->prepare("
    SELECT el.*, c.nom_categ
    FROM expense_line el 
    JOIN categories c ON el.id_categ = c.id_categ 
    WHERE el.id_dem = ?
");
$stmtLines->execute([$id_dem]);
$lignes_existantes = $stmtLines->fetchAll(PDO::FETCH_ASSOC);

// Catégories pour le select
$stmtCats = $pdo->query("SELECT * FROM categories ORDER BY nom_categ ASC");
$categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Éditer Brouillon #<?= $id_dem ?> | Rembourse Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
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
    <style>
        :root {
            --app-bg: #f8fafc;
            --header-bg: #ffffff;
            --header-border: #e2e8f0;
            --primary: #059669;
            --primary-dark: #047857;
            --primary-hover: #047857;
            --text-main: #1e293b;
            --text-light: #64748b;
            --card-border: #e2e8f0;
            --radius: 12px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--app-bg);
            color: var(--text-main);
            font-size: 0.875rem;
            padding-top: 70px;
            overflow-x: hidden;
        }

        /* --- HEADER & LOGO ANIMÉ --- */
        .app-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: var(--header-bg);
            border-bottom: 1px solid var(--header-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            z-index: 1000;
        }

        /* LOGO ANIMATION */
        @keyframes logo-bounce {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
                box-shadow: 0 0 15px rgba(5, 150, 105, 0.4);
            }
        }

        .brand-logo {
            width: 32px;
            height: 32px;
            background: var(--primary);
            color: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .brand-logo:hover {
            animation: logo-bounce 1s infinite;
            background-color: #047857;
        }

        .app-header .d-flex span {
            font-weight: 700 !important;
        }

        .brand-text-wrapper {
            overflow: hidden;
            width: 0;
            opacity: 0;
            white-space: nowrap;
            animation: slideTextOut 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
            animation-delay: 0.3s;
        }

        .brand-text {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }

        @keyframes slideTextOut {
            to {
                width: 160px;
                opacity: 1;
            }
        }

        /* --- NAVIGATION --- */
        .app-nav {
            display: flex;
            gap: 6px;
            height: 100%;
            margin-left: 20px;
        }
        
        .nav-item-link {
            color: var(--text-light);
            text-decoration: none;
            padding: 0 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            height: 100%;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .nav-item-link:hover {
            background-color: #f1f5f9;
            color: var(--primary);
        }

        .nav-item-link.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: linear-gradient(to bottom, transparent 90%, rgba(5, 150, 105, 0.1));
        }
        
        /* --- PROFILE --- */
        .user-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .avatar-circle:hover {
            transform: scale(1.1);
        }

        .notification-bell {
            position: relative;
            color: var(--text-light);
            font-size: 1.25rem;
            text-decoration: none;
            transition: color 0.2s;
        }

        .notification-bell:hover {
            color: var(--primary);
        }

        /* --- CONTENT LAYOUT --- */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 24px;
        }

        .btn-back {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: white;
            border: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            text-decoration: none;
            transition: 0.2s;
        }

        .btn-back:hover {
            background: #f1f5f9;
            color: var(--primary);
        }

        /* --- CARDS --- */
        .card-edit {
            background: white;
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 20px;
        }

        .card-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-main);
        }

        .card-title i {
            color: var(--primary);
        }

        /* --- FORMS --- */
        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 6px;
        }

        .form-control,
        .form-select {
            font-size: 0.9rem;
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid var(--card-border);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        /* --- EXISTING LINES --- */
        .line-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            border: 1px solid var(--card-border);
            border-radius: 6px;
            margin-bottom: 8px;
            background: #fff;
            transition: 0.2s;
        }

        .line-item.deleted {
            background: #f1f5f9;
            border-color: transparent;
            opacity: 0.6;
            text-decoration: line-through;
        }

        .line-icon {
            width: 40px;
            height: 40px;
            background: #ecfdf5;
            color: var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 15px;
        }

        .line-details {
            flex-grow: 1;
        }

        .line-amount {
            font-weight: 700;
            color: var(--text-main);
            font-size: 0.95rem;
            margin-right: 20px;
        }

        .btn-trash {
            width: 32px;
            height: 32px;
            border: 1px solid #fee2e2;
            background: #fff;
            color: #ef4444;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-trash:hover {
            background: #fee2e2;
        }

        .btn-trash.active {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
        }

        /* --- NEW LINES (TEMPLATE) --- */
        .new-line-card {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 12px;
            position: relative;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .btn-add-dashed {
            width: 100%;
            border: 2px dashed #cbd5e1;
            background: transparent;
            color: var(--text-light);
            padding: 12px;
            border-radius: 6px;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-add-dashed:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #f0fdf4;
        }

        /* --- RIGHT SIDEBAR (SUMMARY) --- */
        .summary-card {
            background: white;
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 24px;
            position: sticky;
            top: 80px;
            /* Sticky effect */
        }

        .total-block {
            margin: 20px 0;
            padding: 15px 0;
            border-top: 1px solid var(--card-border);
            border-bottom: 1px solid var(--card-border);
        }

        .total-label {
            font-size: 0.85rem;
            color: var(--text-light);
        }

        .total-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
        }

        .btn-submit-main {
            width: 100%;
            background: var(--primary);
            color: white;
            padding: 12px;
            border-radius: 6px;
            font-weight: 600;
            border: none;
            margin-bottom: 8px;
        }

        .btn-submit-main:hover {
            background: var(--primary-hover);
        }

        .btn-save-draft {
            width: 100%;
            background: white;
            color: var(--text-main);
            padding: 10px;
            border-radius: 6px;
            font-weight: 600;
            border: 1px solid var(--card-border);
        }

        .btn-save-draft:hover {
            background: #f1f5f9;
        }
    </style>
</head>

<body>

    <!-- TOP NAVIGATION -->
    <header class="app-header">
        <div class="d-flex align-items-center gap-2">
            <div class="brand-logo">RM</div> <span class="fw-bold text-dark">RembourseMaroc</span>
        </div>

        <nav class="app-nav">
            <a href="dashboard.php" class="nav-item-link"><i class="bi bi-grid-fill"></i> Tableau de bord</a>
            <a href="nouvelle_demande.php" class="nav-item-link"><i class="bi bi-plus-circle"></i> Nouvelle demande</a>
            <a href="mes_frais.php" class="nav-item-link"><i class="bi bi-receipt"></i> Mes frais</a>
            <a href="mes_brouillons.php" class="nav-item-link active"><i class="bi bi-file-earmark"></i> Brouillons</a>
            <a href="mes_reclamations.php" class="nav-item-link"><i class="bi bi-life-preserver"></i> Support</a>
            <a href="mes_avances.php" class="nav-item-link"><i class="bi bi-cash-stack"></i> Avances</a>
            <a href="guide_politique.php" class="nav-item-link"><i class="bi bi-journal-text fw-bold"></i> Guide</a>
        </nav>

        <div class="d-flex align-items-center gap-3">
            <?= renderNotificationBell($notifications) ?>
            
            <div class="text-end d-none d-sm-block">
                <div class="fw-bold small"><?= htmlspecialchars($user_name) ?></div>
                <div class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($role_display) ?></div>
            </div>
            <div class="dropdown">
                <a href="#" data-bs-toggle="dropdown"><img src="<?= htmlspecialchars($avatar) ?>" class="avatar-circle"></a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                    <li><a class="dropdown-item small" href="profil.php">Mon Profil</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item small text-danger" href="../../actions/logout.php">Déconnexion</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <div class="main-container">

        <!-- HEADER -->
        <div class="page-header">
            <a href="mes_brouillons.php" class="btn-back"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h4 class="fw-bold m-0 text-dark">Modification du brouillon
                    #<?= str_pad($id_dem, 4, '0', STR_PAD_LEFT) ?></h4>
                <div class="text-muted small">Modifiez, ajoutez ou supprimez des frais avant soumission.</div>
            </div>
        </div>

        <form action="../../actions/update_demande.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_dem" value="<?= $id_dem ?>">

            <div class="row g-4">

                <!-- GAUCHE : FORMULAIRE -->
                <div class="col-lg-8">

                    <!-- 1. INFO GÉNÉRALE -->
                    <div class="card-edit">
                        <div class="card-title"><i class="bi bi-info-circle-fill"></i> Informations de la mission</div>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Titre de la mission / Objet</label>
                                <input type="text" name="titre_dem" class="form-control"
                                    value="<?= htmlspecialchars($demande['titre_dem']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date de début</label>
                                <input type="date" name="date_mission" class="form-control"
                                    value="<?= $demande['date_dep'] ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- 2. LIGNES EXISTANTES -->
                    <?php if (!empty($lignes_existantes)): ?>
                        <div class="card-edit">
                            <div class="card-title">
                                <i class="bi bi-list-check"></i> Frais déjà enregistrés
                                <span
                                    class="badge bg-light text-secondary ms-2 small"><?= count($lignes_existantes) ?></span>
                            </div>

                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($lignes_existantes as $line): ?>
                                    <div class="line-item" id="line_<?= $line['id'] ?>">
                                        <div class="d-flex align-items-center flex-grow-1">
                                            <div class="line-icon">
                                                <!-- Icône générique corrigée -->
                                                <i class="bi bi-receipt"></i>
                                            </div>
                                            <div class="line-details">
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($line['nom_categ']) ?></div>
                                                <div class="text-muted small">
                                                    <?= date('d/m/Y', strtotime($line['date_depense'])) ?>
                                                    <?php if (isset($line['description']) && !empty($line['description'])): ?>
                                                        - <span
                                                            class="fst-italic"><?= htmlspecialchars($line['description']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($line['justificatif_path'])): 
                                                    // Gérer les deux formats : avec ou sans préfixe uploads/proofs/
                                                    $proofPath = $line['justificatif_path'];
                                                    if (strpos($proofPath, 'uploads/proofs/') === 0) {
                                                        $proofPath = str_replace('uploads/proofs/', '', $proofPath);
                                                    }
                                                ?>
                                                    <a href="../../uploads/proofs/<?= htmlspecialchars($proofPath) ?>" target="_blank"
                                                        class="text-primary small text-decoration-none">
                                                        <i class="bi bi-paperclip me-1"></i>Preuve jointe
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="line-amount" data-val="<?= $line['montant'] ?>">
                                            <?= number_format($line['montant'], 2) ?> DH
                                        </div>

                                        <!-- Checkbox cachée pour suppression -->
                                        <input type="checkbox" name="delete_lines[]" value="<?= $line['id'] ?>"
                                            id="del_input_<?= $line['id'] ?>" class="d-none delete-checker">

                                        <!-- Bouton visuel -->
                                        <button type="button" class="btn-trash" onclick="toggleDelete(<?= $line['id'] ?>)"
                                            title="Supprimer cette ligne">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-2 text-muted small fst-italic">
                                <i class="bi bi-info-circle me-1"></i> Les éléments barrés seront supprimés lors de la
                                sauvegarde.
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 3. AJOUTER DES LIGNES -->
                    <div class="card-edit">
                        <div class="card-title"><i class="bi bi-plus-circle-fill"></i> Ajouter de nouveaux frais</div>

                        <div id="new-lines-container"></div>

                        <button type="button" class="btn-add-dashed" onclick="addNewLine()">
                            <i class="bi bi-plus-lg me-1"></i> Ajouter une ligne de dépense
                        </button>
                    </div>

                </div>

                <!-- DROITE : RÉSUMÉ (STICKY) -->
                <div class="col-lg-4">
                    <div class="summary-card">
                        <h6 class="fw-bold text-dark mb-1">Résumé financier</h6>
                        <div class="text-muted small">Mise à jour en temps réel</div>

                        <div class="total-block">
                            <div class="total-label">Total Estimé</div>
                            <div class="total-value"><span
                                    id="totalDisplay"><?= number_format($demande['montant_total'], 2) ?></span> <small
                                    class="fs-6 text-muted">DH</small></div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="action" value="submit" class="btn-submit-main">
                                <i class="bi bi-send-fill me-2"></i> Soumettre la demande
                            </button>
                            <button type="submit" name="action" value="draft" class="btn-save-draft">
                                <i class="bi bi-save me-2"></i> Sauvegarder
                            </button>
                        </div>

                        <div class="mt-3 p-2 bg-light rounded border text-center small text-muted">
                            <i class="bi bi-lock-fill me-1"></i> Validation sécurisée
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <!-- TEMPLATE JS (Caché) -->
    <template id="tpl-line">
        <div class="new-line-card">
            <div class="d-flex justify-content-between mb-3">
                <span class="badge bg-primary bg-opacity-10 text-primary">Nouvelle dépense</span>
                <button type="button" class="btn-close btn-sm"
                    onclick="this.closest('.new-line-card').remove(); updateCalc();"></button>
            </div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label">Type</label>
                    <select name="categs[]" class="form-select" required>
                        <option value="" disabled selected>Choisir...</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id_categ'] ?>"><?= htmlspecialchars($c['nom_categ']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Montant (DH)</label>
                    <input type="number" step="0.01" name="montants[]" class="form-control input-montant"
                        placeholder="0.00" oninput="updateCalc()" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date</label>
                    <input type="date" name="dates[]" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Justificatif</label>
                    <input type="file" name="justificatifs[]" class="form-control" accept="image/*,.pdf" required>
                </div>
                <div class="col-12">
                    <input type="text" name="descriptions[]" class="form-control"
                        placeholder="Description optionnelle (ex: Taxi aller-retour)">
                </div>
            </div>
        </div>
    </template>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script pour gérer les notifications -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.notification-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    const notificationId = this.getAttribute('data-notification-id');
                    const notificationType = this.getAttribute('data-notification-type');
                    const demandId = this.getAttribute('data-demand-id');
                    
                    if (notificationId) {
                        fetch('../../actions/mark_notification_read_employee.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({id: notificationId, type: notificationType})
                        }).then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.style.opacity = '0.5';
                                if (demandId) {
                                    setTimeout(function() {
                                        window.location.href = 'details_demande.php?id=' + demandId;
                                    }, 300);
                                }
                            }
                        });
                    } else if (demandId) {
                        window.location.href = 'details_demande.php?id=' + demandId;
                    }
                });
            });
        });
    </script>
    <script>
        // --- GESTION SUPPRESSION LIGNES EXISTANTES ---
        function toggleDelete(id) {
            const row = document.getElementById('line_' + id);
            const input = document.getElementById('del_input_' + id);
            const btn = row.querySelector('.btn-trash');

            if (input.checked) {
                // On annule la suppression
                input.checked = false;
                row.classList.remove('deleted');
                btn.classList.remove('active');
            } else {
                // On marque pour suppression
                input.checked = true;
                row.classList.add('deleted');
                btn.classList.add('active');
            }
            updateCalc();
        }

        // --- AJOUT NOUVELLE LIGNE ---
        function addNewLine() {
            const tpl = document.getElementById('tpl-line');
            const clone = tpl.content.cloneNode(true);
            document.getElementById('new-lines-container').appendChild(clone);
        }

        // --- CALCULATRICE TEMPS RÉEL ---
        function updateCalc() {
            let total = 0;

            // Somme des existants (non barrés)
            const existingItems = document.querySelectorAll('.line-item');
            existingItems.forEach(item => {
                if (!item.classList.contains('deleted')) {
                    const amount = parseFloat(item.querySelector('.line-amount').getAttribute('data-val'));
                    if (!isNaN(amount)) total += amount;
                }
            });

            // Somme des nouveaux inputs
            const newInputs = document.querySelectorAll('.input-montant');
            newInputs.forEach(inp => {
                const val = parseFloat(inp.value);
                if (!isNaN(val)) total += val;
            });

            // Mise à jour DOM
            document.getElementById('totalDisplay').textContent = total.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Init au chargement
        document.addEventListener('DOMContentLoaded', updateCalc);
    </script>
    
    <!-- Script pour gérer les notifications -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.notification-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    const notificationId = this.getAttribute('data-notification-id');
                    const notificationType = this.getAttribute('data-notification-type');
                    const demandId = this.getAttribute('data-demand-id');
                    
                    if (notificationId) {
                        fetch('../../actions/mark_notification_read_employee.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({id: notificationId, type: notificationType})
                        }).then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.style.opacity = '0.5';
                                if (demandId) {
                                    setTimeout(function() {
                                        window.location.href = 'details_demande.php?id=' + demandId;
                                    }, 300);
                                }
                            }
                        });
                    } else if (demandId) {
                        window.location.href = 'details_demande.php?id=' + demandId;
                    }
                });
            });
        });
    </script>
    <script src="../../assets/js/theme.js"></script>
</body>

</html>