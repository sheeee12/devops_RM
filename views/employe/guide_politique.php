<?php

// -------------------------------------------------------------------------

// PAGE : GUIDE & POLITIQUE DE REMBOURSEMENT

// -------------------------------------------------------------------------

require_once __DIR__ . '/../../includes/session.php';

requireRole('employee');

require_once __DIR__ . '/../../config/Database.php';

$user_id = $_SESSION['user']['user_id'];

// Récupération des données utilisateur pour le header

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

?>



<!DOCTYPE html>

<html lang="fr">



<head>

    <meta charset="UTF-8">

    <title>Guide & Plafonds | Rembourse Maroc</title>

    <!-- CSS Standard -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">



    <style>

        /* --- 1. VARIABLES & RESET (DESIGN SYSTEM) --- */

        :root {

            --app-bg: #f8fafc;

            /* Gris très pâle (fond application) */

            --header-bg: #ffffff;

            /* Blanc pur (Barre d'outils) */

            --header-border: #e2e8f0;

            /* Bordure subtile */

            --primary: #059669;

            /* Vert Entreprise */

            --text-main: #1e293b;

            /* Texte foncé */

            --text-light: #64748b;

            /* Texte gris */

            --card-border: #e2e8f0;

            /* Bordure des cartes */

            --radius: 8px;

            /* Arrondi "Software" */

        }



        body {

            font-family: 'Inter', sans-serif;

            background-color: var(--app-bg);

            color: var(--text-main);

            font-size: 0.875rem;

            padding-top: 60px;

            /* Espace pour le header fixe */

        }



        /* --- 2. HEADER / NAVIGATION --- */

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

            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);

        }



        .brand-area {

            display: flex;

            align-items: center;

            gap: 12px;

            min-width: 200px;

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

    .app-header .d-flex span,
    .app-header .brand-area span {
        font-weight: 700 !important;
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



        .user-area {

            display: flex;

            align-items: center;

            gap: 12px;

            min-width: 200px;

            justify-content: flex-end;

        }



        .avatar-circle {

            width: 32px;

            height: 32px;

            border-radius: 50%;

            object-fit: cover;

            border: 1px solid #cbd5e1;

        }



        /* --- 3. CONTENEUR PRINCIPAL --- */

        .main-container {

            max-width: 1200px;

            margin: 0 auto;

            padding: 30px 24px;

        }



        .section-title {

            font-weight: 700;

            color: var(--text-main);

            margin-bottom: 20px;

            display: flex;

            align-items: center;

            gap: 10px;

        }



        /* --- 4. STYLES SPÉCIFIQUES GUIDE --- */

        /* Cartes Plafonds */

        .plafond-card {

            background: white;

            border: 1px solid var(--card-border);

            border-radius: 12px;

            padding: 20px;

            transition: transform 0.2s;

            height: 100%;

        }



        .plafond-card:hover {

            transform: translateY(-3px);

            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);

        }



        .icon-box {

            width: 48px;

            height: 48px;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 1.5rem;

            margin-bottom: 15px;

        }



        /* Timeline Virement */

        .timeline-step {

            display: flex;

            gap: 20px;

            margin-bottom: 25px;

            position: relative;

        }



        .timeline-step:last-child {

            margin-bottom: 0;

        }



        .step-marker {

            width: 32px;

            height: 32px;

            background: var(--primary);

            color: white;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: bold;

            flex-shrink: 0;

            z-index: 2;

            font-size: 0.9rem;

        }



        .step-line {

            position: absolute;

            left: 15px;

            top: 32px;

            bottom: -25px;

            width: 2px;

            background: #e2e8f0;

            z-index: 1;

        }



        .timeline-step:last-child .step-line {

            display: none;

        }



        /* Alerts Rules */

        .rule-box {

            border-left: 4px solid;

            padding: 15px;

            background: white;

            border-radius: 0 8px 8px 0;

            margin-bottom: 15px;

            font-size: 0.95rem;

            border: 1px solid var(--card-border);

            border-left-width: 4px;

        }



        .rule-ok {

            border-left-color: #10b981;

            background: #ecfdf5;

        }



        .rule-no {

            border-left-color: #ef4444;

            background: #fef2f2;

        }



        /* Accordion Custom */

        .accordion-button:not(.collapsed) {

            background-color: #f0fdf4;

            color: var(--primary);

            box-shadow: inset 0 -1px 0 rgba(0, 0, 0, .125);

        }

    </style>

</head>



<body>



    <!-- TOP NAVIGATION BAR -->

    <header class="app-header">

        <!-- Logo -->

        <div class="d-flex align-items-center gap-2">
            <div class="brand-logo">RM</div> <span class="fw-bold text-dark">RembourseMaroc</span>
        </div>



        <!-- Menu Onglets -->

        <nav class="app-nav">

            <a href="dashboard.php" class="nav-item-link">

                <i class="bi bi-grid-fill"></i> Tableau de bord

            </a>

            <a href="nouvelle_demande.php" class="nav-item-link">

                <i class="bi bi-plus-circle"></i> Nouvelle demande

            </a>

            <a href="mes_frais.php" class="nav-item-link">

                <i class="bi bi-receipt"></i> Mes frais

            </a>

            <a href="mes_brouillons.php" class="nav-item-link">

                <i class="bi bi-file-earmark"></i> Brouillons

            </a>

            <a href="mes_reclamations.php" class="nav-item-link">

                <i class="bi bi-life-preserver"></i> Support

            </a>

            <a href="mes_avances.php" class="nav-item-link">

                <i class="bi bi-cash-stack"></i> Avances

            </a>

            <a href="guide_politique.php" class="nav-item-link active">

                <i class="bi bi-journal-text fw-bold"></i> Guide

            </a>

        </nav>



        <!-- User Profile -->

        <div class="user-area">
            <?= renderNotificationBell($notifications) ?>
            
            <div class="text-end me-3 d-none d-sm-block">

                <div class="fw-bold small"><?= htmlspecialchars($user_name) ?></div>

                <div class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($role_display) ?></div>

            </div>

            <div class="dropdown">

                <a href="#" class="d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown">

                    <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="avatar-circle">

                </a>

                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">

                    <li><a class="dropdown-item small" href="profil.php"><i class="bi bi-person me-2"></i>Mon Profil</a>

                    </li>

                    <li>

                        <hr class="dropdown-divider">

                    </li>

                    <li><a class="dropdown-item small text-danger" href="../../actions/logout.php"><i

                                class="bi bi-power me-2"></i>Déconnexion</a></li>

                </ul>

            </div>

        </div>

    </header>



    <!-- MAIN CONTENT -->

    <div class="main-container">



        <div class="text-center mb-5 pt-3">

            <h2 class="fw-bold mb-2 text-dark">Politique de Frais & Remboursements</h2>

            <p class="text-secondary">Consultez les règles pour garantir la validation rapide de vos demandes.</p>

        </div>



        <!-- 1. PLAFONDS & CATEGORIES -->

        <h4 class="section-title"><i class="bi bi-speedometer2 text-primary"></i> Plafonds & Règles (2024)</h4>

        <div class="row g-4 mb-5">



            <!-- HÔTEL -->

            <div class="col-md-4">

                <div class="plafond-card">

                    <div class="icon-box bg-primary bg-opacity-10 text-primary"><i class="bi bi-building"></i></div>

                    <h5 class="fw-bold">Hébergement</h5>

                    <div class="mb-2">

                        <span class="fs-4 fw-bold text-dark">800 DH</span> <span class="text-muted small">/ nuit</span>

                    </div>

                    <p class="small text-muted mb-0">

                        Pour les grandes villes (Casablanca, Rabat, Marrakech). <br>

                        <strong>Hors grandes villes :</strong> 600 DH / nuit.<br>

                        <em>Le petit-déjeuner doit être inclus.</em>

                    </p>

                </div>

            </div>



            <!-- REPAS -->

            <div class="col-md-4">

                <div class="plafond-card">

                    <div class="icon-box" style="background: #fff7ed; color: #ea580c;"><i class="bi bi-cup-hot"></i>

                    </div>

                    <h5 class="fw-bold">Restauration</h5>

                    <div class="mb-2">

                        <span class="fs-4 fw-bold text-dark">150 DH</span> <span class="text-muted small">/ repas</span>

                    </div>

                    <p class="small text-muted mb-0">

                        Applicable déjeuner et dîner.<br>

                        <strong>Invités :</strong> Plafond à 350 DH/personne si client invité (Mentionner le nom).

                    </p>

                </div>

            </div>



            <!-- TRANSPORT -->

            <div class="col-md-4">

                <div class="plafond-card">

                    <div class="icon-box" style="background: #eff6ff; color: #2563eb;"><i

                            class="bi bi-airplane-engines"></i></div>

                    <h5 class="fw-bold">Transports</h5>

                    <div class="mb-2">

                        <span class="fs-4 fw-bold text-dark">Frais Réels</span> <span class="text-muted small">(Classe

                            Eco)</span>

                    </div>

                    <p class="small text-muted mb-0">

                        <strong>Avion/Train :</strong> Classe Économique ou 2nde obligatoire.<br>

                        <strong>VTC/Bus :</strong> Uber, Careem, Bus acceptés.<br>

                        <em>Facture et carte d'embarquement requises.</em>

                    </p>

                </div>

            </div>

        </div>



        <div class="row g-5">



            <!-- 2. REGLES D'OR -->

            <div class="col-lg-7">

                <h4 class="section-title"><i class="bi bi-shield-check text-success"></i> Règles de Validation</h4>



                <div class="rule-box rule-ok">

                    <div class="fw-bold text-success mb-1"><i class="bi bi-check-circle-fill me-2"></i>Factures

                        Originales</div>

                    Les scans/photos doivent être lisibles. Pour l'avion et le train, la carte d'embarquement (Boarding

                    Pass) est obligatoire en plus de la facture.

                </div>



                <div class="rule-box rule-no">

                    <div class="fw-bold text-danger mb-1"><i class="bi bi-x-circle-fill me-2"></i>Surclassement</div>

                    Les voyages en Business Class ou 1ère classe ne sont pas remboursés (sauf validation exceptionnelle

                    du Directeur Général avant le départ).

                </div>



                <div class="rule-box rule-ok">

                    <div class="fw-bold text-success mb-1"><i class="bi bi-clock-history me-2"></i>Délai de soumission

                    </div>

                    Les notes de frais doivent être soumises au plus tard <strong>30 jours</strong> après la date de la

                    dépense. Au-delà, elles seront automatiquement rejetées.

                </div>



                <div class="alert alert-warning border-0 bg-warning bg-opacity-10 mt-4 rounded-3">

                    <i class="bi bi-exclamation-triangle-fill me-2"></i>

                    <strong>Astuce :</strong> Pour les VTC (Uber/Careem), téléchargez le PDF depuis l'application. La

                    capture d'écran du trajet n'est pas une pièce comptable valide.

                </div>

            </div>



            <!-- 3. PROCESS DE VIREMENT -->

            <div class="col-lg-5">

                <div class="bg-white p-4 rounded-3 border border-light-subtle h-100 shadow-sm">

                    <h5 class="fw-bold mb-4">Quand suis-je payé ?</h5>



                    <div class="timeline-step">

                        <div class="step-marker">1</div>

                        <div class="step-line"></div>

                        <div>

                            <div class="fw-bold">Soumission</div>

                            <div class="small text-muted">Vous envoyez votre demande via l'application.</div>

                        </div>

                    </div>



                    <div class="timeline-step">

                        <div class="step-marker">2</div>

                        <div class="step-line"></div>

                        <div>

                            <div class="fw-bold">Validation Manager</div>

                            <div class="small text-muted">Sous 48h ouvrées. Il vérifie la pertinence.</div>

                        </div>

                    </div>



                    <div class="timeline-step">

                        <div class="step-marker">3</div>

                        <div class="step-line"></div>

                        <div>

                            <div class="fw-bold">Contrôle Finance</div>

                            <div class="small text-muted">Vérification des justificatifs et TVA (J+3).</div>

                        </div>

                    </div>



                    <div class="timeline-step">

                        <div class="step-marker bg-success"><i class="bi bi-check-lg"></i></div>

                        <div>

                            <div class="fw-bold text-success">Virement</div>

                            <div class="small text-muted">

                                Les ordres de virement sont exécutés <strong>tous les vendredis</strong>.<br>

                                <em>Comptez 24h à 48h selon votre banque.</em>

                            </div>

                        </div>

                    </div>



                    <hr class="text-muted opacity-25">

                    <div class="bg-light p-3 rounded small text-center text-muted">

                        Besoin d'une exception ?<br>

                        Contactez <a href="#"

                            class="text-primary fw-bold text-decoration-none">rh@remboursemaroc.com</a>

                    </div>

                </div>

            </div>



        </div>



        <!-- FAQ -->

        <div class="mt-5 mb-5">

            <h4 class="section-title mb-4">Questions Fréquentes</h4>

            <div class="accordion" id="accordionFAQ">

                <div class="accordion-item border mb-2 shadow-sm rounded overflow-hidden">

                    <h2 class="accordion-header">

                        <button class="accordion-button collapsed fw-medium" type="button" data-bs-toggle="collapse"

                            data-bs-target="#faq1">

                            Que faire si j'ai perdu un ticket ?

                        </button>

                    </h2>

                    <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#accordionFAQ">

                        <div class="accordion-body text-muted small">

                            Pour les montants < 50 DH, une attestation sur l'honneur est possible (max 2/an). Sinon,

                                duplicata commerçant obligatoire. </div>

                        </div>

                    </div>

                    <div class="accordion-item border mb-2 shadow-sm rounded overflow-hidden">

                        <h2 class="accordion-header">

                            <button class="accordion-button collapsed fw-medium" type="button" data-bs-toggle="collapse"

                                data-bs-target="#faq2">

                                Le Taxi compteur est-il remboursé ?

                            </button>

                        </h2>

                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#accordionFAQ">

                            <div class="accordion-body text-muted small">

                                Les petits taxis sans facture ne sont remboursés que s'ils font l'objet d'un "Bon de

                                transport" interne signé. Privilégiez les VTC (Uber/Careem) qui fournissent une facture.

                            </div>

                        </div>

                    </div>

                </div>

            </div>



        </div>



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

</body>



</html>
