<?php
// /prof/doc_peda/fiche_des_prevision_caractere_commercial_list.php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_prof();

include __DIR__.'/../get_annee_en_cours.php';

$prof     = current_prof();
$agentId  = (int)($prof['id'] ?? 0);

$msg = '';
$error = '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $msg = "Fiche de prévision enregistrée avec succès.";
    if ($_GET['msg'] === 'updated') $msg = "Fiche de prévision modifiée avec succès.";
    if ($_GET['msg'] === 'deleted') $msg = "Fiche de prévision supprimée avec succès.";
}

// Action de suppression
$action = $_GET['action'] ?? '';
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmtFile = $con->prepare("SELECT piece_jointe FROM fiches_previsions_commerciales WHERE id = ? AND agent_id = ?");
            $stmtFile->bind_param("ii", $id, $agentId);
            $stmtFile->execute();
            $fileResult = $stmtFile->get_result()->fetch_assoc();
            $stmtFile->close();

            if (!empty($fileResult['piece_jointe'])) {
                $filePath = __DIR__ . '/../../uploads/attachement_prevision_commercial_pedagogique/' . $fileResult['piece_jointe'];
                if (file_exists($filePath)) { unlink($filePath); }
            }

            $stmtDel = $con->prepare("DELETE FROM fiches_previsions_commerciales WHERE id = ? AND agent_id = ?");
            $stmtDel->bind_param("ii", $id, $agentId);
            $stmtDel->execute();
            $stmtDel->close();

            header("Location: fiche_des_prevision_caractere_commercial_list.php?msg=deleted");
            exit();
        } catch (Throwable $e) {
            $error = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

// Récupération uniquement des classes affectées à l'agent connecté (sans duplication)[cite: 13]
try {
    $classesDisponibles = classes_of_agent($con, $agentId);
} catch (Throwable $e) {
    $classesDisponibles = [];
    $error = "Erreur lors du chargement des classes : " . $e->getMessage();
}

$filtre_classe = (int)($_GET['filtre_classe'] ?? 0);

try {
    $sql = "
        SELECT f.*, c.description AS nom_classe
        FROM fiches_previsions_commerciales f
        LEFT JOIN classe c ON f.classe_id = c.id
        WHERE f.agent_id = ?
    ";
    if ($filtre_classe > 0) {
        $sql .= " AND f.classe_id = ?";
    }
    $sql .= " ORDER BY f.created_at DESC LIMIT 100";
    
    $stmtFiches = $con->prepare($sql);
    if ($filtre_classe > 0) {
        $stmtFiches->bind_param("ii", $agentId, $filtre_classe);
    } else {
        $stmtFiches->bind_param("i", $agentId);
    }
    $stmtFiches->execute();
    $fiches = $stmtFiches->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtFiches->close();
} catch (Throwable $e) {
    $fiches = [];
    $error = "Impossible de charger les fiches : " . $e->getMessage();
}

include __DIR__.'/../layout/header.php';
include __DIR__.'/../layout/navbar.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h3 class="fw-bold mb-1 text-dark">📈 Planification Pédagogique (Caractère Commercial)</h3>
                <p class="text-muted mb-0 small">Consultez, modifiez ou supprimez vos tableaux de prévisions</p>
            </div>
            <a href="fiche_des_prevision_caractere_commercial.php"
                class="btn btn-primary rounded-3 px-4 py-2 fw-semibold shadow-sm text-decoration-none">➕ Nouvelle Fiche
                de Prévision</a>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3"><?= htmlspecialchars($msg) ?><button
            type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3"><?= htmlspecialchars($error) ?><button
            type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary">Filtrer par classe :</label>
                    <select name="filtre_classe" class="form-select rounded-3">
                        <option value="0" selected disabled>Toutes mes classes</option>
                        <?php foreach ($classesDisponibles as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= $filtre_classe === (int)$cl['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cl['description']) ?> <?= htmlspecialchars($cl['cycle_desc']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-3 fw-semibold">Filtrer</button>
                    <?php if ($filtre_classe > 0): ?>
                    <a href="fiche_des_prevision_caractere_commercial_list.php" class="btn btn-secondary rounded-3">Voir
                        tout</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="py-3 ps-4">ID</th>
                        <th class="py-3">Branche (Cours)</th>
                        <th class="py-3">Classe</th>
                        <th class="py-3">Fonction</th>
                        <th class="py-3">Pièce jointe</th>
                        <th class="py-3 text-center" style="width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fiches)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted fw-semibold">Aucune fiche de prévision
                            enregistrée.</td>
                    </tr>
                    <?php else: foreach ($fiches as $f): ?>
                    <tr>
                        <td class="ps-4 fw-bold text-dark">#<?= $f['id'] ?></td>
                        <td><span
                                class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($f['branche']) ?></span>
                        </td>
                        <td class="fw-semibold text-secondary"><?= htmlspecialchars($f['nom_classe'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($f['fonction'] ?? '-') ?></td>
                        <td>
                            <?php if (!empty($f['piece_jointe'])): ?>
                            <a href="../../uploads/attachement_prevision_commercial_pedagogique/<?= urlencode($f['piece_jointe']) ?>"
                                target="_blank" class="btn btn-sm btn-secondary rounded-pill px-3">📎 Fichier</a>
                            <?php else: ?>
                            <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center text-nowrap">
                            <a href="fiche_des_prevision_caractere_commercial.php?id=<?= $f['id'] ?>"
                                class="btn btn-sm btn-primary rounded-3 text-white" title="Modifier">✏️ Modifier</a>
                            <a href="fiche_des_prevision_caractere_commercial_list.php?action=delete&id=<?= $f['id'] ?>"
                                class="btn btn-sm btn-danger rounded-3"
                                onclick="return confirm('Voulez-vous vraiment supprimer cette fiche ?');"
                                title="Supprimer">🗑️</a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../layout/footer.php'; ?>