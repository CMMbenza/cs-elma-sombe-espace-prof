<?php
// /prof/doc_peda/journal_de_classe.php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_prof();

include __DIR__.'/../get_annee_en_cours.php';

$prof     = current_prof();
$agentId  = (int)($prof['id'] ?? 0);
$classeId = (int)get_current_classe();

$uploadDir = __DIR__.'/../../uploads/attachement_journal_de_class/';

// Récupération de la liste de toutes les classes affectées à l'enseignant
$mesClasses = classes_of_agent($con, $agentId);

// -------------------------------------------------------------------------
// 1) TRAITEMENT : SUPPRESSION D'UNE LIGNE
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete'])) {
    $idDelete = (int)($_POST['id_journal'] ?? 0);

    $stmtCheck = $con->prepare("SELECT piece_jointe, statut FROM journal_classe WHERE id = ? AND prof_id = ?");
    $stmtCheck->bind_param('ii', $idDelete, $agentId);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$resCheck) {
        $_SESSION['msg_error'] = "Fiche introuvable ou accès non autorisé.";
    } elseif ($resCheck['statut'] === 'valider') {
        $_SESSION['msg_error'] = "Impossible de supprimer une fiche déjà validée.";
    } else {
        if (!empty($resCheck['piece_jointe'])) {
            @unlink($uploadDir . $resCheck['piece_jointe']);
        }
        $stmtDel = $con->prepare("DELETE FROM journal_classe WHERE id = ? AND prof_id = ?");
        $stmtDel->bind_param('ii', $idDelete, $agentId);
        if ($stmtDel->execute()) {
            $_SESSION['msg_success'] = "Entrée supprimée avec succès.";
        }
        $stmtDel->close();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// -------------------------------------------------------------------------
// 2) TRAITEMENT : ENREGISTREMENT GROUPÉ MULTI-CLASSES (SAISIE MULTIPLE DU JOUR)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_batch'])) {
    $jourDate          = trim((string)($_POST['jour_date'] ?? ''));
    $targetClasses     = $_POST['target_classes'] ?? [];
    $numeroFiches      = $_POST['numero_fiche'] ?? [];
    $coursIds          = $_POST['cours_id'] ?? [];
    $sujets            = $_POST['sujet'] ?? [];
    $methodes          = $_POST['methode'] ?? [];
    $matieres          = $_POST['matieres'] ?? [];
    $notes             = $_POST['note'] ?? [];
    $observationProfs  = $_POST['observation_prof'] ?? [];
    
    // Filtrage de sécurité : ne garder que les classes effectivement affectées au professeur
    $allowedClasseIds = array_column($mesClasses, 'id');
    $targetClasses    = array_map('intval', (array)$targetClasses);
    $validClasses     = array_intersect($targetClasses, $allowedClasseIds);

    if (empty($jourDate) || empty($coursIds)) {
        $_SESSION['msg_error'] = "Veuillez spécifier la date et au moins une leçon.";
    } elseif (empty($validClasses)) {
        $_SESSION['msg_error'] = "Veuillez sélectionner au moins une classe valide parmi vos classes affectées.";
    } else {
        $successCount = 0;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($coursIds as $index => $cId) {
            $coursId       = (int)$cId;
            $numFicheTxt   = trim((string)($numeroFiches[$index] ?? ''));
            $sujetTxt      = trim((string)($sujets[$index] ?? ''));
            $methodeTxt    = trim((string)($methodes[$index] ?? ''));
            $matiereTxt    = trim((string)($matieres[$index] ?? ''));
            $noteTxt       = trim((string)($notes[$index] ?? ''));
            $obsProfTxt    = trim((string)($observationProfs[$index] ?? ''));

            if ($coursId > 0 && $matiereTxt !== '') {
                $filename = null;

                if (isset($_FILES['piece_jointe']['error'][$index]) && $_FILES['piece_jointe']['error'][$index] === UPLOAD_ERR_OK) {
                    $tmpName   = $_FILES['piece_jointe']['tmp_name'][$index];
                    $extension = pathinfo($_FILES['piece_jointe']['name'][$index], PATHINFO_EXTENSION);
                    $filename  = 'journal_' . uniqid() . '.' . strtolower($extension);
                    move_uploaded_file($tmpName, $uploadDir . $filename);
                }

                // Insertion de la leçon pour CHAQUE classe sélectionnée
                foreach ($validClasses as $cDestId) {
                    $stmt = $con->prepare("
                        INSERT INTO journal_classe (numero_fiche, jour_date, prof_id, classe_id, cours_id, sujet, methode, anneScolaire, matieres, note, observation_prof, piece_jointe, statut)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en attente')
                    ");
                    $stmt->bind_param('ssiiisssssss', $numFicheTxt, $jourDate, $agentId, $cDestId, $coursId, $sujetTxt, $methodeTxt, $anneeEnCours, $matiereTxt, $noteTxt, $obsProfTxt, $filename);
                    if ($stmt->execute()) {
                        $successCount++;
                    }
                    $stmt->close();
                }
            }
        }

        if ($successCount > 0) {
            $_SESSION['msg_success'] = "{$successCount} entrée(s) consignée(s) avec succès pour le " . date('d/m/Y', strtotime($jourDate)) . " dans " . count($validClasses) . " classe(s).";
        } else {
            $_SESSION['msg_error'] = "Aucune matière valide n'a été remplie.";
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// -------------------------------------------------------------------------
// 3) TRAITEMENT : MODIFICATION D'UNE ENTRÉE
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit'])) {
    $idEdit         = (int)($_POST['id_journal'] ?? 0);
    $editNumFiche   = trim((string)($_POST['edit_numero_fiche'] ?? ''));
    $editDate       = trim((string)($_POST['edit_jour_date'] ?? ''));
    $coursId        = (int)($_POST['edit_cours_id'] ?? 0);
    $sujet          = trim((string)($_POST['edit_sujet'] ?? ''));
    $methode        = trim((string)($_POST['edit_methode'] ?? ''));
    $matieres       = trim((string)($_POST['edit_matieres'] ?? ''));
    $note           = trim((string)($_POST['edit_note'] ?? ''));
    $observationProf= trim((string)($_POST['edit_observation_prof'] ?? ''));
    
    $stmtCheck = $con->prepare("SELECT piece_jointe, statut FROM journal_classe WHERE id = ? AND prof_id = ?");
    $stmtCheck->bind_param('ii', $idEdit, $agentId);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$resCheck) {
        $_SESSION['msg_error'] = "Fiche introuvable ou accès non autorisé.";
    } elseif ($resCheck['statut'] === 'valider') {
        $_SESSION['msg_error'] = "Impossible de supprimer ou modifier une fiche déjà validée.";
    } elseif (empty($editDate) || empty($matieres) || $coursId <= 0) {
        $_SESSION['msg_error'] = "La date, le cours et la matière sont obligatoires.";
    } else {
        $filename = $resCheck['piece_jointe'];

        if (isset($_FILES['edit_piece_jointe']['error']) && $_FILES['edit_piece_jointe']['error'] === UPLOAD_ERR_OK) {
            if (!empty($filename)) {
                @unlink($uploadDir . $filename);
            }
            $tmpName   = $_FILES['edit_piece_jointe']['tmp_name'];
            $extension = pathinfo($_FILES['edit_piece_jointe']['name'], PATHINFO_EXTENSION);
            $filename  = 'journal_' . uniqid() . '.' . strtolower($extension);
            move_uploaded_file($tmpName, $uploadDir . $filename);
        }

        $stmtUpd = $con->prepare("UPDATE journal_classe SET numero_fiche = ?, jour_date = ?, cours_id = ?, sujet = ?, methode = ?, matieres = ?, note = ?, observation_prof = ?, piece_jointe = ? WHERE id = ? AND prof_id = ?");
        $stmtUpd->bind_param('ssissssii', $editNumFiche, $editDate, $coursId, $sujet, $methode, $matieres, $note, $observationProf, $filename, $idEdit, $agentId);
        if ($stmtUpd->execute()) {
            $_SESSION['msg_success'] = "Leçon modifiée avec succès.";
        }
        $stmtUpd->close();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Récupération des messages flash
$msgSuccess = $_SESSION['msg_success'] ?? '';
$msgError   = $_SESSION['msg_error'] ?? '';
unset($_SESSION['msg_success'], $_SESSION['msg_error']);

// -------------------------------------------------------------------------
// 4) CHARGEMENT DES COURS ET DU JOURNAL DU JOUR
// -------------------------------------------------------------------------
$coursList = [];
$stmt = $con->prepare("
    SELECT MIN(co.id) AS id, co.intitule
    FROM affectation_prof_classe apc
    INNER JOIN cours co ON co.id = apc.cours_id
    WHERE apc.agent_id = ?
    GROUP BY co.intitule
    ORDER BY co.intitule ASC
");
$stmt->bind_param('i', $agentId);
$stmt->execute();
$coursList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$today = date('Y-m-d');
if ($classeId > 0) {
    $stmt = $con->prepare("
        SELECT jc.*, co.intitule AS cours_nom, c.description AS classe_nom
        FROM journal_classe jc
        INNER JOIN cours co ON co.id = jc.cours_id
        INNER JOIN classe c ON c.id = jc.classe_id
        WHERE jc.prof_id = ? AND jc.classe_id = ? AND jc.jour_date = ?
        ORDER BY jc.id ASC
    ");
    $stmt->bind_param('iis', $agentId, $classeId, $today);
} else {
    $stmt = $con->prepare("
        SELECT jc.*, co.intitule AS cours_nom, c.description AS classe_nom
        FROM journal_classe jc
        INNER JOIN cours co ON co.id = jc.cours_id
        INNER JOIN classe c ON c.id = jc.classe_id
        WHERE jc.prof_id = ? AND jc.jour_date = ?
        ORDER BY jc.id ASC
    ");
    $stmt->bind_param('is', $agentId, $today);
}
$stmt->execute();
$fichesDuJour = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__.'/../layout/header.php';
include __DIR__.'/../layout/navbar.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">📖 Journal de Classe (Saisie Journalière)</h4>
            <small class="text-muted">Consignez toutes vos leçons données aujourd'hui sur un seul formulaire.</small>
        </div>
        <div>
            <a href="historique_journal_de_classe.php" class="btn btn-outline-primary btn-sm fw-bold">
                📜 Historique complet
            </a>
        </div>
    </div>

    <?php if (!empty($msgSuccess)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($msgSuccess) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (!empty($msgError)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e($msgError) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- FORMULAIRE DE SAISIE GROUPÉE MULTI-CLASSES -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
            <span class="fw-bold">✏️ Journal du jour (<?= date('d/m/Y') ?>)</span>
            <button type="button" class="btn btn-light btn-sm fw-bold text-primary" id="add-row-btn">
                ➕ Ajouter une ligne
            </button>
        </div>
        <div class="card-body p-3">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action_add_batch" value="1">

                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="col-md-3 mb-2">
                            <label class="form-label fw-bold">Date du journal <span class="text-danger">*</span></label>
                            <input type="date" name="jour_date" class="form-control" value="<?= date('Y-m-d') ?>"
                                required>
                        </div>

                        <label class="form-label fw-bold">
                            Envoyer aux classe(s) <span class="text-danger">*</span>
                        </label>
                        <div class="d-flex flex-wrap gap-2 p-2 border rounded bg-light">
                            <?php if (!empty($mesClasses)): ?>
                            <?php foreach ($mesClasses as $cls): ?>
                            <div class="form-check form-check-inline m-0 me-3">
                                <input class="form-check-input" type="checkbox" name="target_classes[]"
                                    value="<?= (int)$cls['id'] ?>" id="class_chk_<?= (int)$cls['id'] ?>"
                                    data-cycle="<?= strtolower(e($cls['cycle_desc'] ?? '')) ?>"
                                    <?= ((int)$cls['id'] === $classeId) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-dark"
                                    for="class_chk_<?= (int)$cls['id'] ?>">
                                    <?= e($cls['description']) ?> <?= e($cls['cycle_desc']) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <span class="text-danger small">Aucune classe affectée à votre compte.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="journal-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 11%;" class="col-secondaire">N° Fiche</th>
                                <th style="width: 15%;">Cours / Branche <span class="text-danger">*</span></th>
                                <th class="col-secondaire">Sujet</th>
                                <th class="col-secondaire">Méthode</th>
                                <th>Matière dispensée <span class="text-danger">*</span></th>
                                <th>Remarques / Obs.</th>
                                <th>Obs. Prof</th>
                                <th>Support / PJ</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="journal-tbody">
                            <tr class="journal-row">
                                <td class="col-secondaire"><input type="text" name="numero_fiche[]" class="form-control"
                                        placeholder="N° Fiche"></td>
                                <td>
                                    <select name="cours_id[]" class="form-select" required>
                                        <option value="">-- Choisir --</option>
                                        <?php foreach ($coursList as $co): ?>
                                        <option value="<?= (int)$co['id'] ?>"><?= e($co['intitule']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="col-secondaire"><input type="text" name="sujet[]" class="form-control"
                                        placeholder="Sujet"></td>
                                <td class="col-secondaire"><input type="text" name="methode[]" class="form-control"
                                        placeholder="Méthode"></td>
                                <td><textarea name="matieres[]" class="form-control" rows="2"
                                        placeholder="Contenu de la leçon..." required></textarea></td>
                                <td><input type="text" name="note[]" class="form-control" placeholder="Devoirs..."></td>
                                <td><input type="text" name="observation_prof[]" class="form-control"
                                        placeholder="Obs. prof..."></td>
                                <td><input type="file" name="piece_jointe[]" class="form-control form-control-sm"></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"
                                        title="Supprimer">❌</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="add-row-btn-bottom">
                        ➕ Ajouter une autre ligne
                    </button>
                    <button type="submit" class="btn btn-success fw-bold px-4">
                        💾 Enregistrer tout le journal du jour
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- LEÇONS DÉJÀ CONSIGNÉES AUJOURD'HUI -->
    <?php if (!empty($fichesDuJour)): ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light fw-bold py-3">
            📋 Leçons enregistrées aujourd'hui
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>N° Fiche</th>
                            <th>Cours</th>
                            <th>Sujet / Méthode</th>
                            <th>Matière dispensée</th>
                            <th>Remarques / Obs.</th>
                            <th>Pièce jointe</th>
                            <th class="text-center">Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fichesDuJour as $f): ?>
                        <tr>
                            <td>
                                <?php if (!empty($f['numero_fiche'])): ?>
                                <span class="badge bg-dark"><?= e($f['numero_fiche']) ?></span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-primary"><?= e($f['cours_nom']) ?></td>
                            <td>
                                <strong>Sujet:</strong> <?= e($f['sujet'] ?: '—') ?><br>
                                <small class="text-muted">MÉTHODE: <?= e($f['methode'] ?: '—') ?></small>
                            </td>
                            <td><?= nl2br(e($f['matieres'])) ?></td>
                            <td>
                                <?= e($f['note'] ?: '—') ?>
                                <?php if (!empty($f['observation_prof'])): ?>
                                <br><small class="text-info">Obs: <?= e($f['observation_prof']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($f['piece_jointe'])): ?>
                                <a href="/uploads/attachement_journal_de_class/<?= e($f['piece_jointe']) ?>"
                                    target="_blank" class="btn btn-sm btn-outline-primary">📎 Fichier</a>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (($f['statut'] ?? 'en attente') === 'valider'): ?>
                                <span class="badge bg-success">Validé</span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark">En attente</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if (($f['statut'] ?? 'en attente') !== 'valider'): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary me-1 btn-edit"
                                    data-id="<?= (int)$f['id'] ?>"
                                    data-numerofiche="<?= htmlspecialchars($f['numero_fiche'] ?? '', ENT_QUOTES) ?>"
                                    data-date="<?= htmlspecialchars($f['jour_date'], ENT_QUOTES) ?>"
                                    data-cours="<?= (int)$f['cours_id'] ?>"
                                    data-sujet="<?= htmlspecialchars($f['sujet'] ?? '', ENT_QUOTES) ?>"
                                    data-methode="<?= htmlspecialchars($f['methode'] ?? '', ENT_QUOTES) ?>"
                                    data-matieres="<?= htmlspecialchars($f['matieres'], ENT_QUOTES) ?>"
                                    data-note="<?= htmlspecialchars($f['note'] ?? '', ENT_QUOTES) ?>"
                                    data-observation_prof="<?= htmlspecialchars($f['observation_prof'] ?? '', ENT_QUOTES) ?>"
                                    data-bs-toggle="modal" data-bs-target="#editModal" title="Modifier">
                                    ✏️
                                </button>
                                <form method="post" class="d-inline"
                                    onsubmit="return confirm('Voulez-vous vraiment supprimer cette leçon ?');">
                                    <input type="hidden" name="action_delete" value="1">
                                    <input type="hidden" name="id_journal" value="<?= (int)$f['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                        title="Supprimer">🗑️</button>
                                </form>
                                <?php else: ?>
                                <span class="text-muted small">🔒 Modif. bloquée</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- MODAL DE MODIFICATION -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="action_edit" value="1">
            <input type="hidden" name="id_journal" id="modal_edit_id" value="">

            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold" id="editModalLabel">✏️ Modifier la leçon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">N° Fiche</label>
                        <input type="text" name="edit_numero_fiche" id="modal_edit_numero_fiche" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Date de la leçon</label>
                        <input type="date" name="edit_jour_date" id="modal_edit_date" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Cours / Branche</label>
                    <select name="edit_cours_id" id="modal_edit_cours" class="form-select" required>
                        <option value="">-- Choisir un cours --</option>
                        <?php foreach ($coursList as $co): ?>
                        <option value="<?= (int)$co['id'] ?>"><?= e($co['intitule']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Sujet</label>
                        <input type="text" name="edit_sujet" id="modal_edit_sujet" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Méthode</label>
                        <input type="text" name="edit_methode" id="modal_edit_methode" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Matière dispensée</label>
                    <textarea name="edit_matieres" id="modal_edit_matieres" class="form-control" rows="3"
                        required></textarea>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Remarques / Devoirs</label>
                        <input type="text" name="edit_note" id="modal_edit_note" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Observation Prof</label>
                        <input type="text" name="edit_observation_prof" id="modal_edit_observation_prof"
                            class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Remplacer la pièce jointe (Optionnel)</label>
                    <input type="file" name="edit_piece_jointe" class="form-control">
                    <small class="text-muted">Laissez vide pour conserver le fichier actuel.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-warning fw-bold text-dark">Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('journal-tbody');
    const addBtn = document.getElementById('add-row-btn');
    const addBtnBottom = document.getElementById('add-row-btn-bottom');

    function updateFieldsVisibility() {
        const checkboxes = document.querySelectorAll('input[name="target_classes[]"]:checked');
        let hideSecondaryFields = false;

        if (checkboxes.length > 0) {
            let onlyPrimaryOrMappernal = true;
            checkboxes.forEach(chk => {
                const cycle = chk.getAttribute('data-cycle') || '';
                if (cycle.includes('secondaire') || cycle.includes('humanite') || cycle.includes(
                        'humanités') || cycle.includes('eb')) {
                    onlyPrimaryOrMappernal = false;
                }
            });
            if (onlyPrimaryOrMappernal) {
                checkboxes.forEach(chk => {
                    const cycle = chk.getAttribute('data-cycle') || '';
                    if (!cycle.includes('primaire') && !cycle.includes('maternel')) {
                        onlyPrimaryOrMappernal = false;
                    }
                });
            }
            if (onlyPrimaryOrMappernal) {
                hideSecondaryFields = true;
            }
        }

        document.querySelectorAll('.col-secondaire').forEach(el => {
            el.style.display = hideSecondaryFields ? 'none' : '';
        });
    }

    const classCheckboxes = document.querySelectorAll('input[name="target_classes[]"]');
    classCheckboxes.forEach(chk => {
        chk.addEventListener('change', updateFieldsVisibility);
    });

    updateFieldsVisibility();

    function createNewRow() {
        const firstRow = tbody.querySelector('.journal-row');
        const newRow = firstRow.cloneNode(true);

        newRow.querySelectorAll('input, textarea, select').forEach(input => {
            if (input.tagName === 'SELECT') {
                input.selectedIndex = 0;
            } else {
                input.value = '';
            }
        });
        tbody.appendChild(newRow);
        updateFieldsVisibility();
    }

    if (addBtn) addBtn.addEventListener('click', createNewRow);
    if (addBtnBottom) addBtnBottom.addEventListener('click', createNewRow);

    if (tbody) {
        tbody.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-remove-row')) {
                const rows = tbody.querySelectorAll('.journal-row');
                if (rows.length > 1) e.target.closest('tr').remove();
                else alert("Vous devez conserver au moins une ligne.");
            }
        });
    }

    const editBtns = document.querySelectorAll('.btn-edit');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('modal_edit_id').value = this.getAttribute('data-id');
            document.getElementById('modal_edit_numero_fiche').value = this.getAttribute(
                'data-numerofiche');
            document.getElementById('modal_edit_date').value = this.getAttribute('data-date');
            document.getElementById('modal_edit_cours').value = this.getAttribute('data-cours');
            document.getElementById('modal_edit_sujet').value = this.getAttribute('data-sujet');
            document.getElementById('modal_edit_methode').value = this.getAttribute(
                'data-methode');
            document.getElementById('modal_edit_matieres').value = this.getAttribute(
                'data-matieres');
            document.getElementById('modal_edit_note').value = this.getAttribute('data-note');
            document.getElementById('modal_edit_observation_prof').value = this.getAttribute(
                'data-observation_prof');
        });
    });
});
</script>

<?php include __DIR__.'/../layout/footer.php'; ?>