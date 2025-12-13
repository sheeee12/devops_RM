<?php

// -------------------------------------------------------------------------

// VUE : NOUVELLE DEMANDE (AVEC GESTION PLAFONDS & DATES DYNAMIQUES)

// -------------------------------------------------------------------------

require_once __DIR__ . '/../../includes/session.php';

requireRole('employee');

require_once __DIR__ . '/../../config/Database.php';



$user_name = $_SESSION['user']['nom'];

$user_role_raw = $_SESSION['user']['role'];

$role_display = ($user_role_raw === 'employee') ? 'Collaborateur' : ucfirst($user_role_raw);

$avatar_bdd = $_SESSION['user']['avatar'] ?? 'default.png';

$chemin_physique = __DIR__ . '/../../assets/img/' . $avatar_bdd;

$avatar = (file_exists($chemin_physique) && !empty($avatar_bdd)) ? '../../assets/img/' . $avatar_bdd : '../../assets/img/default.png';



$pdo = Database::getInstance()->getConnexion();

// --- NOTIFICATIONS ---
require_once __DIR__ . '/../../includes/employee_notifications.php';
$notifications = getEmployeeNotifications($pdo, $_SESSION['user']['user_id']);
$notificationCount = count($notifications);

// Récupération des catégories AVEC PLAFOND

$stmt = $pdo->query("SELECT * FROM categories ORDER BY nom_categ ASC");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);



// Récupérer les avances

$stmtAv = $pdo->prepare("

    SELECT * FROM avances 

    WHERE user_id = ? 

    AND status = 'Paye' 

    AND id_avance NOT IN (SELECT COALESCE(avance_id, 0) FROM demande)

");

$stmtAv->execute([$_SESSION['user']['user_id']]);

$mes_avances = $stmtAv->fetchAll(PDO::FETCH_ASSOC);

?>



<!DOCTYPE html>

<html lang="fr">



<head>

    <meta charset="UTF-8">

    <title>Saisie de Frais | Rembourse Maroc</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>

        :root {

            --app-bg: #f8fafc;

            --header-bg: #ffffff;

            --header-border: #e2e8f0;

            --primary: #059669;

            --text-main: #1e293b;

            --text-light: #64748b;

            --card-border: #e2e8f0;

            --radius: 8px;

        }



        body {

            font-family: 'Inter', sans-serif;

            background-color: var(--app-bg);

            color: var(--text-main);

            font-size: 0.875rem;

            padding-top: 60px;

            padding-bottom: 100px;

        }



        /* HEADER */

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



            0%,

            100% {

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



        .app-nav {

            display: flex;

            gap: 4px;

            height: 100%;

        }



        .nav-item-link {

            color: var(--text-light);

            text-decoration: none;

            padding: 0 16px;

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

            background-color: rgba(5, 150, 105, 0.04);

        }



        .avatar-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #cbd5e1;
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



        /* MAIN */

        .main-container {

            max-width: 1000px;

            margin: 0 auto;

            padding: 24px;

        }



        .page-header {

            margin-bottom: 24px;

        }



        .page-title {

            font-size: 1.25rem;

            font-weight: 700;

            color: var(--text-main);

            margin-bottom: 4px;

        }



        .form-section {

            background: white;

            border: 1px solid var(--card-border);

            border-radius: var(--radius);

            padding: 20px;

            margin-bottom: 20px;

        }



        .section-header {

            font-size: 0.9rem;

            font-weight: 600;

            color: var(--text-main);

            margin-bottom: 16px;

            display: flex;

            align-items: center;

            gap: 8px;

            text-transform: uppercase;

        }



        .section-header i {

            color: var(--primary);

            font-size: 1.1rem;

        }



        /* INPUTS */

        .form-label {

            font-size: 0.8rem;

            font-weight: 500;

            color: var(--text-light);

            margin-bottom: 4px;

        }



        .form-control,

        .form-select {

            font-size: 0.9rem;

            padding: 8px 12px;

            border-radius: 6px;

            border: 1px solid var(--card-border);

            transition: 0.2s;

        }



        .form-control:focus,

        .form-select:focus {

            border-color: var(--primary);

            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);

        }



        /* Dynamic Lines */

        .expense-line {

            background: #f8fafc;

            border: 1px solid var(--card-border);

            border-radius: 8px;

            padding: 16px;

            margin-bottom: 12px;

            position: relative;

            transition: all 0.2s;

        }



        .btn-remove {

            position: absolute;

            top: 10px;

            right: 10px;

            color: #ef4444;

            background: transparent;

            border: none;

            width: 28px;

            height: 28px;

            border-radius: 4px;

            display: flex;

            align-items: center;

            justify-content: center;

        }



        .btn-remove:hover {

            background: #fee2e2;

        }



        .btn-add-line {

            width: 100%;

            padding: 12px;

            border: 1px dashed #cbd5e1;

            background: white;

            color: var(--text-light);

            font-weight: 500;

            border-radius: 8px;

            transition: 0.2s;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

        }



        .btn-add-line:hover {

            border-color: var(--primary);

            color: var(--primary);

            background: #ecfdf5;

        }



        /* FOOTER */

        .action-bar {

            position: fixed;

            bottom: 0;

            left: 0;

            right: 0;

            background: white;

            border-top: 1px solid var(--header-border);

            padding: 16px 24px;

            z-index: 900;

            display: flex;

            justify-content: space-between;

            align-items: center;

            box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.02);

        }



        .total-amount {

            font-size: 1.5rem;

            font-weight: 700;

            color: var(--text-main);

            margin-left: 8px;

        }



        .btn-submit {

            background: var(--primary);

            color: white;

            padding: 10px 24px;

            border-radius: 6px;

            border: none;

            font-weight: 600;

            transition: 0.2s;

        }



        /* Style désactivé spécifique */

        .btn-submit:disabled {

            background: #cbd5e1;

            cursor: not-allowed;

            opacity: 0.7;

        }



        .btn-submit:hover:not(:disabled) {

            background: #047857;

        }



        .btn-draft {

            background: white;

            border: 1px solid var(--card-border);

            color: var(--text-main);

            padding: 10px 20px;

            border-radius: 6px;

            font-weight: 500;

            margin-right: 12px;

        }



        /* Feedback Error (Bootstrap custom) */

        .is-invalid {

            border-color: #dc3545 !important;

            background-image: none !important;

            /* Retire l'icone bootstrap par défaut si gênante */

        }



        .invalid-feedback {

            display: none;

            width: 100%;

            margin-top: 0.25rem;

            font-size: 0.8em;

            color: #dc3545;

        }



        .is-invalid~.invalid-feedback {

            display: block;

        }

    </style>

</head>



<body>



    <!-- TOP NAVIGATION -->

    <header class="app-header">

        <div class="d-flex align-items-center gap-2">

            <!-- Logo Animé ici -->

            <div class="brand-logo">RM</div> <span class="fw-bold text-dark">RembourseMaroc</span>

        </div>

        <nav class="app-nav">

            <a href="dashboard.php" class="nav-item-link"><i class="bi bi-grid-fill"></i> Tableau de bord</a>

            <a href="nouvelle_demande.php" class="nav-item-link active"><i class="bi bi-plus-circle"></i> Nouvelle

                demande</a>

            <a href="mes_frais.php" class="nav-item-link"><i class="bi bi-receipt"></i> Mes frais</a>

            <a href="mes_brouillons.php" class="nav-item-link"><i class="bi bi-file-earmark"></i> Brouillons</a>

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
                <a href="#" data-bs-toggle="dropdown"><img src="<?= htmlspecialchars($avatar) ?>"
                        class="avatar-circle"></a>
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



    <!-- MAIN FORM -->

    <div class="main-container">

        <div class="page-header">

            <h1 class="page-title">Déclarer des frais</h1>

            <p class="text-secondary small mb-0">Remplissez les informations. Les plafonds sont vérifiés

                automatiquement.</p>

        </div>



        <?php if (isset($_SESSION['error'])): ?>

            <div class="alert alert-danger shadow-sm border-0 mb-4 small">

                <i class="bi bi-exclamation-octagon me-2"></i> <?= $_SESSION['error'];

                                                                unset($_SESSION['error']); ?>

            </div>

        <?php endif; ?>



        <form action="../../actions/submit_demande.php" method="POST" enctype="multipart/form-data" id="expenseForm">



            <!-- CONTEXTE -->

            <div class="form-section">

                <div class="section-header justify-content-between">

                    <span><i class="bi bi-geo-alt"></i> Contexte</span>

                </div>



                <div class="row g-3">

                    <div class="col-12">

                        <div class="form-check form-switch">

                            <input class="form-check-input" type="checkbox" id="toggleMultiDays">

                            <label class="form-check-label small fw-bold text-primary" for="toggleMultiDays">Mission sur

                                plusieurs jours ?</label>

                        </div>

                    </div>



                    <div class="col-md-6">

                        <label class="form-label">Objet <span class="text-danger">*</span></label>

                        <input type="text" name="titre_dem" class="form-control" placeholder="Ex: Déplacement Client..."

                            required>

                    </div>



                    <!-- Date de début (toujours visible) -->

                    <div class="col-md-3">

                        <label class="form-label" id="labelDateStart">Date de la mission <span

                                class="text-danger">*</span></label>

                        <input type="date" name="date_mission" id="dateStart" class="form-control"

                            value="<?= date('Y-m-d') ?>" required>

                    </div>



                    <!-- Date de fin (masquée par défaut) -->

                    <div class="col-md-3" id="containerDateEnd" style="display: none;">

                        <label class="form-label">Date de fin <span class="text-danger">*</span></label>

                        <input type="date" name="date_fin" id="dateEnd" class="form-control">

                    </div>

                </div>

            </div>



            <!-- LIGNES DE FRAIS -->

            <div class="form-section">

                <div class="section-header d-flex justify-content-between">

                    <span><i class="bi bi-list-check"></i> Dépenses</span>

                    <span class="badge bg-light text-secondary border fw-normal"><span id="countLines">0</span>

                        ligne(s)</span>

                </div>

                <div id="linesContainer"></div>

                <button type="button" class="btn-add-line" onclick="addExpenseLine()">

                    <i class="bi bi-plus-lg"></i> Ajouter une dépense

                </button>

            </div>



            <!-- AVANCE -->

            <?php if (!empty($mes_avances)): ?>

                <div class="form-section" style="background-color: #fffbeb; border-color: #fcd34d;">

                    <div class="section-header" style="color: #92400e;">

                        <i class="bi bi-cash-coin"></i> Régularisation d'avance

                    </div>

                    <div class="row align-items-center">

                        <div class="col-md-7">

                            <p class="small mb-0 text-secondary">Avez-vous reçu une avance pour cette mission ?</p>

                        </div>

                        <div class="col-md-5">

                            <select name="avance_id" id="avanceSelect" class="form-select fw-bold text-dark"

                                onchange="calculateTotal()">

                                <option value="" data-amount="0">-- Non --</option>

                                <?php foreach ($mes_avances as $av): ?>

                                    <option value="<?= $av['id_avance'] ?>" data-amount="<?= $av['montant_demande'] ?>">

                                        Oui : <?= number_format($av['montant_demande'], 2) ?> DH

                                        (<?= date('d/m', strtotime($av['created_at'])) ?>)

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>

                </div>

            <?php endif; ?>



            <!-- FOOTER -->

            <div class="action-bar">

                <div class="container-fluid d-flex justify-content-between align-items-center p-0"

                    style="max-width: 1000px; margin: 0 auto;">

                    <div class="total-box">

                        Total estimé : <span class="total-amount" id="displayTotal">0.00</span> <small

                            class="text-muted">DH</small>

                    </div>

                    <div>

                        <button type="submit" name="action" value="draft" class="btn-draft" formnovalidate>

                            <i class="bi bi-save me-2"></i>Brouillon

                        </button>

                        <button type="submit" name="action" value="submit" class="btn-submit" id="btnSubmit">

                            <i class="bi bi-send-fill me-2"></i>Soumettre

                        </button>

                    </div>

                </div>

            </div>

        </form>

    </div>



    <!-- TEMPLATE JS (avec data-plafond) -->

    <template id="lineTemplate">

        <div class="expense-line">

            <button type="button" class="btn-remove" onclick="removeLine(this)" title="Supprimer">

                <i class="bi bi-trash"></i>

            </button>

            <div class="row g-3">

                <div class="col-md-3">

                    <label class="form-label">Type <span class="text-danger">*</span></label>

                    <select name="categs[]" class="form-select category-select" required onchange="validateLine(this)">

                        <option value="" selected disabled>Choisir...</option>

                        <?php foreach ($categories as $cat): ?>

                            <option value="<?= $cat['id_categ'] ?>" data-plafond="<?= $cat['plafond_max'] ?>">

                                <?= htmlspecialchars($cat['nom_categ']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div class="form-text text-muted small ceiling-info"></div>

                </div>

                <div class="col-md-2">

                    <label class="form-label">Date <span class="text-danger">*</span></label>

                    <input type="date" name="dates[]" class="form-control" required>

                </div>

                <div class="col-md-2">

                    <label class="form-label">Montant <span class="text-danger">*</span></label>

                    <input type="number" step="0.01" name="montants[]"

                        class="form-control text-end fw-bold amount-input" placeholder="0.00"

                        oninput="validateLine(this)" required>

                    <div class="invalid-feedback">Plafond dépassé !</div>

                </div>

                <div class="col-md-5">

                    <label class="form-label">Justificatif <span class="text-danger">*</span></label>

                    <input type="file" name="justificatifs[]" class="form-control" accept="image/*,application/pdf"

                        required>

                </div>

                <div class="col-12">

                    <input type="text" name="descriptions[]" class="form-control form-control-sm text-secondary"

                        placeholder="Description facultative...">

                </div>

            </div>

        </div>

    </template>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>

        document.addEventListener('DOMContentLoaded', function() {

            addExpenseLine();

            initDateLogic();

        });



        // --- GESTION LOGIQUE DATE (TOGGLE) ---

        function initDateLogic() {

            const toggle = document.getElementById('toggleMultiDays');

            const containerEnd = document.getElementById('containerDateEnd');

            const dateStart = document.getElementById('dateStart');

            const dateEnd = document.getElementById('dateEnd');

            const labelStart = document.getElementById('labelDateStart');



            toggle.addEventListener('change', function() {

                if (this.checked) {

                    // Mode Plusieurs jours

                    containerEnd.style.display = 'block';

                    labelStart.textContent = "Date de début";

                    dateEnd.setAttribute('required', 'required');



                    // Initialiser la date de fin min = date début

                    dateEnd.min = dateStart.value;

                    if (!dateEnd.value) dateEnd.value = dateStart.value;



                } else {

                    // Mode Jour unique

                    containerEnd.style.display = 'none';

                    labelStart.textContent = "Date de la mission";

                    dateEnd.removeAttribute('required');

                    dateEnd.value = dateStart.value; // Synchro pour l'envoi

                }

            });



            // Si on change la date de début, on met à jour la contrainte de la date de fin

            dateStart.addEventListener('change', function() {

                dateEnd.min = this.value;

                if (!toggle.checked) {

                    dateEnd.value = this.value;

                } else if (dateEnd.value < this.value) {

                    dateEnd.value = this.value;

                }

            });

        }





        // --- GESTION DES LIGNES ---

        function addExpenseLine() {

            const container = document.getElementById('linesContainer');

            const template = document.getElementById('lineTemplate');

            const clone = template.content.cloneNode(true);



            const dateInput = clone.querySelector('input[type="date"]');

            if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];



            container.appendChild(clone);

            updateCount();

            calculateTotal();

            checkGlobalValidity(); // Vérifier l'état du bouton

        }



        function removeLine(btn) {

            const container = document.getElementById('linesContainer');

            if (container.children.length > 1) {

                const row = btn.closest('.expense-line');

                row.style.opacity = '0';

                setTimeout(() => {

                    row.remove();

                    updateCount();

                    calculateTotal();

                    checkGlobalValidity(); // Re-vérifier après suppression

                }, 200);

            } else {

                alert("Une demande doit contenir au moins une ligne.");

            }

        }



        function updateCount() {

            document.getElementById('countLines').textContent = document.getElementById('linesContainer').children.length;

        }



        // --- VALIDATION PLAFOND JS ---

        function validateLine(element) {

            // Trouver la ligne parente

            const row = element.closest('.expense-line');

            const select = row.querySelector('.category-select');

            const inputAmount = row.querySelector('.amount-input');

            const ceilingInfo = row.querySelector('.ceiling-info');

            const feedback = row.querySelector('.invalid-feedback');



            // Récupérer le plafond

            const selectedOption = select.options[select.selectedIndex];

            const plafond = selectedOption ? parseFloat(selectedOption.getAttribute('data-plafond')) : 0;

            const amount = parseFloat(inputAmount.value) || 0;



            // Afficher l'info du plafond sous le select

            if (plafond > 0) {

                ceilingInfo.textContent = `Max: ${plafond.toFixed(2)} DH`;

            } else {

                ceilingInfo.textContent = '';

            }



            // Vérifier dépassement local

            if (plafond > 0 && amount > plafond) {

                inputAmount.classList.add('is-invalid');

                feedback.textContent = `Le plafond est de ${plafond.toFixed(2)} DH. Soumission bloquée.`;

            } else {

                inputAmount.classList.remove('is-invalid');

            }



            calculateTotal();

            checkGlobalValidity(); // Vérifier tout le formulaire pour le bouton

        }



        // Fonction globale pour activer/désactiver le bouton soumettre

        function checkGlobalValidity() {

            const btnSubmit = document.getElementById('btnSubmit');

            const errors = document.querySelectorAll('.is-invalid');



            if (errors.length > 0) {

                btnSubmit.disabled = true;

                btnSubmit.title = "Corrigez les erreurs (plafonds dépassés) avant de soumettre.";

            } else {

                btnSubmit.disabled = false;

                btnSubmit.title = "";

            }

        }



        // --- CALCULS ---

        function calculateTotal() {

            let totalFrais = 0;

            document.querySelectorAll('.amount-input').forEach(input => {

                const val = parseFloat(input.value);

                if (!isNaN(val)) totalFrais += val;

            });



            let montantAvance = 0;

            const selectAvance = document.getElementById('avanceSelect');

            if (selectAvance) {

                montantAvance = parseFloat(selectAvance.options[selectAvance.selectedIndex].getAttribute('data-amount')) ||

                    0;

            }



            const netA_Payer = totalFrais - montantAvance;

            const totalBox = document.querySelector('.total-box');



            if (netA_Payer < 0) {

                totalBox.innerHTML =

                    `Reste à rendre : <span class="total-amount text-danger">${Math.abs(netA_Payer).toFixed(2)}</span> <small class="text-danger">DH</small>`;

            } else {

                totalBox.innerHTML =

                    `Net à payer : <span class="total-amount text-success">${netA_Payer.toFixed(2)}</span> <small class="text-success">DH</small>`;

            }

        }

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
</body>



</html>
