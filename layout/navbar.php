<?php
// /prof/layout/navbar.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_prof();

$prof        = current_prof();
$agentId     = (int)$prof['id'];
$classeId    = get_current_classe();
$classeMeta  = $classeId ? current_classe_meta($con, $classeId) : null;

// Récupération des classes attribuées à l'enseignant pour le switch
$classesForNav = classes_of_agent($con, $agentId);

// Chemin d'URL courant pour la gestion des liens actifs
$uriPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

function is_active(array $needles, string $haystack): string {
    foreach ($needles as $n) {
        if ($n !== '' && strpos($haystack, $n) !== false) {
            return 'active';
        }
    }
    return '';
}

// Comptage des devoirs/quiz soumis en attente
if ($classeId) {
    $stmt = $con->prepare("
        SELECT COUNT(*) AS n
        FROM quiz_submission qs
        INNER JOIN quiz q ON q.id = qs.quiz_id
        INNER JOIN quiz_classe qc ON qc.quiz_id = q.id
        WHERE q.agent_id = ? AND qc.classe_id = ? AND qs.statut = 'remis'
    ");
    $stmt->bind_param('ii', $agentId, $classeId);
} else {
    $stmt = $con->prepare("
        SELECT COUNT(*) AS n
        FROM quiz_submission qs
        INNER JOIN quiz q ON q.id = qs.quiz_id
        WHERE q.agent_id = ? AND qs.statut = 'remis'
    ");
    $stmt->bind_param('i', $agentId);
}
$stmt->execute();
$pendingCount = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);
$stmt->close();

// Détection des sections actives
$elevesActive = is_active([
    '/prof/eleves.php',
    '/prof/eleves/registre_d_appel.php',
    '/prof/doc_peda/fiches_des_eleves.php',
    '/prof/doc_peda/fiche_de_suivi.php'
], $uriPath);

$evalActive = is_active([
    '/prof/quiz_create.php',
    '/prof/quiz_list.php',
    '/prof/quiz_view.php',
    '/prof/quiz_submissions.php',
    '/prof/submission_view.php',
    '/prof/quiz_list_quiz_soumis.php'
], $uriPath);

$docPedaActive = is_active([
    '/prof/doc_peda/cours_resume.php',
    '/prof/doc_peda/vusialisation_de_mes_cours.php',
    '/prof/doc_peda/fiche_des_prevision.php',
    '/prof/doc_peda/fiche_de_cours.php',
    '/prof/doc_peda/horaire_de_classe.php',
    '/prof/doc_peda/journal_de_classe.php',
    '/prof/doc_peda/cahier_des_cotes.php',
    '/prof/doc_peda/palmares_trimestre.php'
], $uriPath);

$userFullName = trim(($prof['nom'] ?? '') . ' ' . ($prof['postnom'] ?? '') . ' ' . ($prof['prenom'] ?? ''));
?>


<style>
/* Sous-menu latéral */
.dropdown-submenu {
    position: relative;
}

.dropdown-submenu>.dropdown-menu {
    top: 0;
    left: 100%;
    margin-top: -5px;
    margin-left: 2px;
}

/* Ouverture au survol sur ordinateur */
@media (min-width: 992px) {
    .dropdown-submenu:hover>.dropdown-menu {
        display: block;
    }
}

/* Flèche vers la droite */
.dropdown-submenu>.dropdown-toggle::after {
    float: right;
    margin-top: 7px;
    transform: rotate(-90deg);
}
</style>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm mb-4">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-primary" href="/prof/dashboard.php">
            👨‍🏫
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navProf"
            aria-controls="navProf" aria-expanded="false" aria-label="Basculer la navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div id="navProf" class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <!-- Dashboard -->
                <li class="nav-item">
                    <a class="nav-link <?= is_active(['/prof/dashboard.php'], $uriPath) ?>" href="/prof/dashboard.php">
                        Dashboard
                    </a>
                </li>

                <!-- Élèves & Suivi -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $elevesActive ?>" href="#" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Élèves & Suivi
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?= is_active(['/prof/eleves.php'], $uriPath) ?>"
                                href="/prof/eleves.php">Liste des élèves</a></li>
                        <li><a class="dropdown-item <?= is_active(['/prof/eleves/registre_d_appel.php'], $uriPath) ?>"
                                href="/prof/eleves/registre_d_appel.php">Registre d'appel</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item <?= is_active(['/prof/doc_peda/fiches_des_eleves.php'], $uriPath) ?>"
                                href="/prof/doc_peda/fiches_des_eleves.php">Fiches des élèves</a></li>
                        <li><a class="dropdown-item <?= is_active(['/prof/doc_peda/fiche_de_suivi.php'], $uriPath) ?>"
                                href="/prof/doc_peda/fiche_de_suivi.php">Fiches de suivi</a></li>
                    </ul>
                </li>

                <!-- Évaluations & Devoirs -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $evalActive ?>" href="#" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Quiz (Évaluations & Devoirs)
                        <?php if ($pendingCount > 0): ?>
                        <span class="badge rounded-pill bg-danger ms-1"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?= is_active(['/prof/quiz_create.php'], $uriPath) ?>"
                                href="/prof/quiz_create.php">Créer une évaluation</a></li>
                        <li><a class="dropdown-item <?= is_active(['/prof/quiz_list.php'], $uriPath) ?>"
                                href="/prof/quiz_list.php">Mes évaluations / Devoirs</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item <?= is_active(['/prof/quiz_list_quiz_soumis.php', '/prof/submission_view.php'], $uriPath) ?>"
                                href="/prof/quiz_list_quiz_soumis.php">
                                Soumissions révisées
                                <?php if ($pendingCount > 0): ?>
                                <span class="badge rounded-pill bg-danger ms-1"><?= $pendingCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Documents pédagogiques -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $docPedaActive ?>" href="#" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Doc. pédagogiques
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?= is_active(['/prof/doc_peda/cours_resume.php'], $uriPath) ?>"
                                href="/prof/doc_peda/cours_resume.php">Cours/Résumé</a></li>
                        <li><a class="dropdown-item <?= is_active(['/prof/doc_peda/vusialisation_de_mes_cours.php'], $uriPath) ?>"
                                href="/prof/doc_peda/vusialisation_de_mes_cours.php">Statistiques des cours</a></li>

                        <li class="dropdown-submenu">
                            <a href="#" class="text-danger dropdown-item dropdown-toggle <?= is_active([
                                '/prof/doc_peda/fiche_des_prevision.php',
                                '/prof/doc_peda/fiche_des_prevision_caractere_commercial.php'
                            ], $uriPath) ?>" aria-expanded="false">
                                Prévision des matières
                            </a>

                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item <?= is_active(['/prof/doc_peda/fiche_des_prevision.php', '/prof/doc_peda/fiche_des_prevision.php'], $uriPath) ?>"
                                        href="/prof/doc_peda/fiche_des_prevision.php">Classique</a>
                                </li>

                                <li>
                                    <a class="dropdown-item <?= is_active(['/prof/doc_peda/fiche_des_prevision_caractere_commercial.php', '/prof/doc_peda/fiche_des_prevision_caractere_commercial.php'], $uriPath) ?>"
                                        href="/prof/doc_peda/fiche_des_prevision_caractere_commercial.php">Caractère
                                        commercial</a>
                                </li>
                            </ul>
                        </li>

                        <li><a class="dropdown-item <?= is_active(['/prof/doc_peda/horaire_de_classe.php'], $uriPath) ?>"
                                href="/prof/doc_peda/horaire_de_classe.php">Horaire de classe</a></li>
                        <li><a class="dropdown-item <?= is_active(['/prof/doc_peda/journal_de_classe.php'], $uriPath) ?>"
                                href="/prof/doc_peda/journal_de_classe.php">Journal de classe</a></li>

                        <li class="dropdown-submenu">
                            <a href="#" class="text-danger dropdown-item dropdown-toggle <?= is_active([
                                '/prof/doc_peda/fiche_detaillee_model_aps.php',
                                '/prof/doc_peda/fiche_detaillee_model_das.php'
                            ], $uriPath) ?>" aria-expanded="false">
                                Fiche détaillée
                            </a>

                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item <?= is_active([
                                    '/prof/doc_peda/fiche_detaillee_model_aps.php'
                                ], $uriPath) ?>" href="/prof/doc_peda/fiche_detaillee_modele_aps.php">
                                        Modèle APS
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item <?= is_active([
                                    '/prof/doc_peda/fiche_detaillee.php'
                                ], $uriPath) ?>" href="/prof/doc_peda/fiche_detaillee_modele_das.php">
                                        Modèle DAS
                                    </a>
                                </li>
                            </ul>
                        </li>


                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item <?= is_active(['/prof/doc_peda/cahier_des_cotes.php'], $uriPath) ?>"
                                href="/prof/doc_peda/cahier_des_cotes.php">Cahier des côtes</a></li>
                        <li><a class="dropdown-item <?= is_active(['/prof/doc_peda/palmares_trimestre.php'], $uriPath) ?>"
                                href="/prof/doc_peda/palmares_trimestre.php">Palmarès trimestriel</a></li>
                    </ul>
                </li>

                <!-- Communication -->
                <li class="nav-item">
                    <a class="nav-link <?= is_active(['/prof/annonces.php'], $uriPath) ?>"
                        href="/prof/annonces.php">Annonces & Comm.</a>
                </li>

            </ul>

            <!-- Bloc Droite : Sélecteur de Classe & Dropdown Utilisateur -->
            <div class="d-flex align-items-center gap-3">
                <?php if ($classesForNav && count($classesForNav) > 1): ?>
                <form method="post" action="/prof/switch_classe.php" class="d-flex align-items-center mb-0">
                    <select name="classe_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">— Sélectionner classe —</option>
                        <?php foreach ($classesForNav as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= ($classeId === (int)$c['id']) ? 'selected' : '' ?>>
                            <?= e($c['description']) ?><?= !empty($c['cycle_desc']) ? ' — ' . e($c['cycle_desc']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php elseif ($classeMeta): ?>
                <span class="badge rounded-pill text-bg-primary py-2 px-3">
                    <?= e($classeMeta['description']) ?>
                    <?= e($classeMeta['cycle_desc']) ?>
                </span>
                <?php endif; ?>

                <!-- Dropdown Profil Utilisateur -->
                <div class="dropdown">
                    <a href="#"
                        class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle py-1 px-2 rounded border hover-shadow"
                        id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2"
                            style="width: 32px; height: 32px; font-weight: bold;">
                            <?= strtoupper(substr($prof['nom'] ?? 'P', 0, 1)) ?>
                        </div>
                        <div class="text-start d-none d-sm-block me-1">
                            <span class="fw-semibold small d-block leading-tight"><?= e($userFullName) ?></span>
                            <span class="text-muted small" style="font-size: 0.75rem;">Enseignant</span>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow mt-2" aria-labelledby="userMenu">
                        <li class="px-3 py-2 border-bottom">
                            <div class="fw-bold"><?= e($userFullName) ?></div>
                            <div class="text-muted small">ID Agent: #<?= (int)$prof['id'] ?></div>
                        </li>
                        <li><a class="dropdown-item py-2" href="../account/mon_profil.php">👤 Mon Profil</a></li>
                        <li><a class="dropdown-item py-2" href="../account/mes_presence.php">⚙️ Mes présences</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item py-2 text-danger fw-semibold" href="/prof/logout.php">🚪
                                Déconnexion</a></li>
                    </ul>
                </div>

            </div>
        </div>
    </div>
</nav>

<script>
document.querySelectorAll('.dropdown-submenu > .dropdown-toggle')
    .forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const submenu = this.nextElementSibling;
            const isOpen = submenu.classList.contains('show');

            // Fermer les autres sous-menus
            this.closest('.dropdown-menu')
                .querySelectorAll('.dropdown-submenu > .dropdown-menu.show')
                .forEach(function(menu) {
                    menu.classList.remove('show');
                    menu.previousElementSibling.setAttribute(
                        'aria-expanded', 'false'
                    );
                });

            // Ouvrir ou fermer le sous-menu sélectionné
            if (!isOpen) {
                submenu.classList.add('show');
                this.setAttribute('aria-expanded', 'true');
            } else {
                this.setAttribute('aria-expanded', 'false');
            }
        });
    });

// Fermer les sous-menus lorsque le menu principal se ferme
document.querySelectorAll('.navbar .dropdown').forEach(function(dropdown) {
    dropdown.addEventListener('hidden.bs.dropdown', function() {
        this.querySelectorAll('.dropdown-submenu > .dropdown-menu.show')
            .forEach(function(menu) {
                menu.classList.remove('show');
                menu.previousElementSibling.setAttribute(
                    'aria-expanded', 'false'
                );
            });
    });
});
</script>