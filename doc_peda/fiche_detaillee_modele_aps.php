<?php
// /prof/doc_peda/fiche_detaillee_modele_aps.php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_prof();

require_once __DIR__.'/../get_annee_en_cours.php';

$prof     = current_prof();
$agentId  = (int)($prof['id'] ?? 0);

$message = '';
$error = '';
$id = (int)($_GET['id'] ?? 0);

$fiche = [
    'fiche_num' => '',
    'date_tache' => date('Y-m-d'),
    'heure' => date('H:i'),
    'branche' => '',
    'fonction' => '',
    'competence_integree' => '',
    'competence_visee' => '',
    'sujet_revision' => '',
    'sujet_lecon' => '',
    'objectifs_pedagogiques' => '',
    'materiel_didactique' => '',
    'reference_bibliographique' => '',
    'methode_rappel' => '',
    'matiere_rappel' => '',
    'methode_motivation' => '',
    'matiere_motivation' => '',
    'methode_annonce' => '',
    'matiere_annonce' => '',
    'methode_developpement' => '',
    'matiere_developpement' => '',
    'methode_synthese' => '',
    'matiere_synthese' => '',
    'methode_application' => '',
    'matiere_application' => '',
    'methode_tache' => '',
    'matiere_tache' => '',
    'commentaires' => '',
    'piece_jointe' => null
];

$classesSelectionneesActuelles = [];

if ($id > 0) {
    try {
        $stmtFiche = $con->prepare("SELECT * FROM fiches_aps WHERE id = ?");
        $stmtFiche->bind_param("i", $id);
        $stmtFiche->execute();
        $resFiche = $stmtFiche->get_result();
        if ($resFiche->num_rows > 0) {
            $fiche = $resFiche->fetch_assoc();
        } else {
            header("Location: fiche_detaillee_modele_aps_list.php");
            exit();
        }
        $stmtFiche->close();

        $stmtClassesFiche = $con->prepare("SELECT classe_id FROM fiche_classes WHERE fiche_id = ?");
        $stmtClassesFiche->bind_param("i", $id);
        $stmtClassesFiche->execute();
        $resCf = $stmtClassesFiche->get_result();
        while ($rowCf = $resCf->fetch_assoc()) {
            $classesSelectionneesActuelles[] = (int)$rowCf['classe_id'];
        }
        $stmtClassesFiche->close();
    } catch (Throwable $e) {
        $error = "Erreur lors du chargement de la fiche : " . $e->getMessage();
    }
}

try {
    $sqlClasses = "
        SELECT c.id, c.description AS nom_classe, cy.description AS nom_cycle 
        FROM classe c 
        LEFT JOIN cycle cy ON c.cycle = cy.id 
        ORDER BY cy.description ASC, c.description ASC
    ";
    $resultClasses = $con->query($sqlClasses);
    $classesDisponibles = $resultClasses->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    $classesDisponibles = [];
    $error = "Erreur lors du chargement des classes : " . $e->getMessage();
}

try {
    $sqlCours = "
        SELECT DISTINCT co.id, co.intitule, co.classe_id 
        FROM affectation_prof_classe apc 
        JOIN cours co ON apc.cours_id = co.id 
        WHERE apc.agent_id = ?
        ORDER BY co.intitule ASC
    ";
    $stmtCours = $con->prepare($sqlCours);
    $stmtCours->bind_param("i", $agentId);
    $stmtCours->execute();
    $coursAssignes = $stmtCours->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtCours->close();

    if (empty($coursAssignes)) {
        $resAllCours = $con->query("SELECT DISTINCT id, intitule, classe_id FROM cours ORDER BY intitule ASC");
        $coursAssignes = $resAllCours->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    $coursAssignes = [];
}

if (isset($_GET['get_next_num']) && isset($_GET['classe_id']) && isset($_GET['cours'])) {
    $cId = (int)$_GET['classe_id'];
    $coursNom = trim($_GET['cours']);
    
    $stmtCount = $con->prepare("
        SELECT COUNT(DISTINCT f.id) as total 
        FROM fiches_aps f
        JOIN fiche_classes fc ON f.id = fc.fiche_id
        WHERE fc.classe_id = ? AND f.branche = ?
    ");
    $stmtCount->bind_param("is", $cId, $coursNom);
    $stmtCount->execute();
    $resCount = $stmtCount->get_result()->fetch_assoc();
    $nextNum = ((int)($resCount['total'] ?? 0)) + 1;
    $stmtCount->close();

    echo json_encode(['next_num' => 'FICHE-' . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fiche_num                 = trim((string)($_POST['fiche_num'] ?? ''));
    $date                      = trim((string)($_POST['date'] ?? ''));
    $heure                     = trim((string)($_POST['heure'] ?? ''));
    $classes_selectionnees     = $_POST['classes'] ?? [];
    $noms                      = (string)$agentId;
    $branche                   = trim((string)($_POST['branche'] ?? ''));
    $fonction                  = trim((string)($_POST['fonction'] ?? ''));
    $competence_integree       = trim((string)($_POST['competence_integree'] ?? ''));
    $competence_visee          = trim((string)($_POST['competence_visee'] ?? ''));
    $sujet_revision            = trim((string)($_POST['sujet_revision'] ?? ''));
    $sujet_lecon               = trim((string)($_POST['sujet_lecon'] ?? ''));
    $objectifs_pedagogiques    = trim((string)($_POST['objectifs_pedagogiques'] ?? ''));
    $materiel_didactique       = trim((string)($_POST['materiel_didactique'] ?? ''));
    $reference_bibliographique = trim((string)($_POST['reference_bibliographique'] ?? ''));
    
    $methode_rappel            = trim((string)($_POST['methode_rappel'] ?? ''));
    $matiere_rappel            = trim((string)($_POST['matiere_rappel'] ?? ''));
    $methode_motivation        = trim((string)($_POST['methode_motivation'] ?? ''));
    $matiere_motivation        = trim((string)($_POST['matiere_motivation'] ?? ''));
    $methode_annonce           = trim((string)($_POST['methode_annonce'] ?? ''));
    $matiere_annonce           = trim((string)($_POST['matiere_annonce'] ?? ''));
    $methode_developpement     = trim((string)($_POST['methode_developpement'] ?? ''));
    $matiere_developpement    = trim((string)($_POST['matiere_developpement'] ?? ''));
    $methode_synthese          = trim((string)($_POST['methode_synthese'] ?? ''));
    $matiere_synthese          = trim((string)($_POST['matiere_synthese'] ?? ''));
    $methode_application       = trim((string)($_POST['methode_application'] ?? ''));
    $matiere_application       = trim((string)($_POST['matiere_application'] ?? ''));
    $methode_tache             = trim((string)($_POST['methode_tache'] ?? ''));
    $matiere_tache             = trim((string)($_POST['matiere_tache'] ?? ''));
    $commentaires              = trim((string)($_POST['commentaires'] ?? ''));

    if ($fiche_num === '' || empty($classes_selectionnees) || $branche === '') {
        $error = "Le numéro de fiche, la branche (cours) et la sélection d'au moins une classe sont obligatoires.";
    } else {
        $nomFichierFinal = $fiche['piece_jointe'];
        if (isset($_FILES['pieces_jointes']) && $_FILES['pieces_jointes']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/attachement_fiche_detaillee_pedagogique/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $nomFichierFinal = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', basename($_FILES['pieces_jointes']['name']));
            move_uploaded_file($_FILES['pieces_jointes']['tmp_name'], $uploadDir . $nomFichierFinal);
        }

        try {
            $con->begin_transaction();

            $dateVal = ($date !== '') ? $date : null;
            $heureVal = ($heure !== '') ? $heure : null;

            if ($id > 0) {
                $stmt = $con->prepare("
                    UPDATE fiches_aps SET 
                        fiche_num = ?, date_tache = ?, heure = ?, noms = ?, branche = ?, fonction = ?, 
                        competence_integree = ?, competence_visee = ?, sujet_revision = ?, sujet_lecon = ?, 
                        objectifs_pedagogiques = ?, materiel_didactique = ?, reference_bibliographique = ?, 
                        methode_rappel = ?, matiere_rappel = ?, methode_motivation = ?, matiere_motivation = ?, 
                        methode_annonce = ?, matiere_annonce = ?, methode_developpement = ?, matiere_developpement = ?, 
                        methode_synthese = ?, matiere_synthese = ?, methode_application = ?, matiere_application = ?, 
                        methode_tache = ?, matiere_tache = ?, commentaires = ?, piece_jointe = ?
                    WHERE id = ?
                ");
                $stmt->bind_param(
                    "sssssssssssssssssssssssssssssi",
                    $fiche_num, $dateVal, $heureVal, $noms, $branche, $fonction,
                    $competence_integree, $competence_visee, $sujet_revision, $sujet_lecon,
                    $objectifs_pedagogiques, $materiel_didactique, $reference_bibliographique,
                    $methode_rappel, $matiere_rappel, $methode_motivation, $matiere_motivation,
                    $methode_annonce, $matiere_annonce, $methode_developpement, $matiere_developpement,
                    $methode_synthese, $matiere_synthese, $methode_application, $matiere_application,
                    $methode_tache, $matiere_tache, $commentaires, $nomFichierFinal, $id
                );
                $stmt->execute();
                $stmt->close();

                $con->query("DELETE FROM fiche_classes WHERE fiche_id = $id");
                $ficheId = $id;
            } else {
                // CORRECTION : Le doublon de methode_developpement a été supprimé ici (28 colonnes / 28 's')
                $stmt = $con->prepare("
                    INSERT INTO fiches_aps (
                        fiche_num, date_tache, heure, noms, branche, fonction, 
                        competence_integree, competence_visee, sujet_revision, sujet_lecon, 
                        objectifs_pedagogiques, materiel_didactique, reference_bibliographique, 
                        methode_rappel, matiere_rappel, methode_motivation, matiere_motivation, 
                        methode_annonce, matiere_annonce, methode_developpement, 
                        matiere_developpement, methode_synthese, matiere_synthese, 
                        methode_application, matiere_application, methode_tache, 
                        matiere_tache, commentaires, piece_jointe
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "sssssssssssssssssssssssssssss",
                    $fiche_num, $dateVal, $heureVal, $noms, $branche, $fonction,
                    $competence_integree, $competence_visee, $sujet_revision, $sujet_lecon,
                    $objectifs_pedagogiques, $materiel_didactique, $reference_bibliographique,
                    $methode_rappel, $matiere_rappel, $methode_motivation, $matiere_motivation,
                    $methode_annonce, $matiere_annonce, $methode_developpement, $matiere_developpement,
                    $methode_synthese, $matiere_synthese, $methode_application, $matiere_application,
                    $methode_tache, $matiere_tache, $commentaires, $nomFichierFinal
                );
                $stmt->execute();
                $ficheId = $con->insert_id;
                $stmt->close();
            }

            $stmtClasseLink = $con->prepare("INSERT INTO fiche_classes (fiche_id, classe_id) VALUES (?, ?)");
            foreach ($classes_selectionnees as $classeId) {
                $cId = (int)$classeId;
                $stmtClasseLink->bind_param("ii", $ficheId, $cId);
                $stmtClasseLink->execute();
            }
            $stmtClasseLink->close();

            $con->commit();
            header("Location: fiche_detaillee_modele_aps_list.php?msg=" . ($id > 0 ? 'updated' : 'created'));
            exit();
        } catch (Throwable $e) {
            $con->rollback();
            $error = "Erreur lors de l'enregistrement en base de données : " . $e->getMessage();
        }
    }
}

include __DIR__.'/../layout/header.php';
include __DIR__.'/../layout/navbar.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulaire - Fiche de Préparation Pédagogique</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
    body {
        background-color: #f1f5f9;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        /* padding-left: 1rem;
        padding-right: 1rem; */
    }

    @media (min-width: 768px) {
        body {
            /* padding-left: 2rem;
            padding-right: 2rem; */
        }
    }

    .sheet {
        background: #ffffff;
        width: 100%;
        max-width: 100%;
        margin: 10mm auto;
        padding: 10mm;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        border-radius: 6px;         
    }

    @media (min-width: 768px) {
        .sheet {
            padding: 15mm;
            margin: 15mm auto;
        }
    }

    .input-standard {
        width: 100%;
        padding: 6px 10px;
        border: 1px solid #cbd5e1;
        border-radius: 0.375rem;
        background-color: #f8fafc;
        font-family: inherit;
        font-size: 0.875rem;
        color: #1e293b;
        outline: none;
        transition: all 0.2s ease-in-out;
    }

    .input-standard:focus {
        border-color: #3b82f6;
        background-color: #ffffff;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
    }

    .section-box {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background-color: #ffffff;
    }

    /* Style d'impression propre : n'imprime que le formulaire (.sheet) et masque les menus du site */
    @media print {
        body * {
            visibility: hidden;
        }

        .sheet,
        .sheet * {
            visibility: visible;
        }

        .sheet {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            margin: 0;
            padding: 10mm;
            box-shadow: none;
            background: #ffffff;
            border-radius: 0;
        }

        .no-print {
            display: none !important;
        }

        .input-standard,
        textarea {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            padding: 0 !important;
            resize: none !important;
        }

        .section-box,
        table,
        th,
        td {
            border-color: #94a3b8 !important;
        }
    }
    </style>
</head>

<body class="py-6">
 
<div style="padding-left: 2rem; padding-right: 2rem;">

    <div class="sheet relative">

        <?php if (!empty($error)): ?>
        <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded shadow-sm no-print">
            <p class="font-medium"><?php echo htmlspecialchars($error); ?></p>
        </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">

            <!-- Barre d'actions -->
            <div
                class="flex flex-col sm:flex-row justify-between items-center mb-6 no-print bg-white p-4 rounded-lg shadow-sm border border-slate-200 gap-3">
                <span class="text-sm font-semibold text-slate-700 font-sans text-center sm:text-left">
                    <?= $id > 0 ? 'Modification de la Fiche Pédagogique' : 'Saisie de la Fiche de Préparation - Modèle APS' ?>
                </span>
                <div class="flex flex-wrap gap-2 justify-center">

                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-md shadow-sm transition font-sans text-sm flex items-center">
                        <?= $id > 0 ? 'Mettre à jour la fiche' : 'Enregistrer votre fiche' ?>
                    </button>
                    <a href="fiche_detaillee_modele_aps_list.php"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-md text-sm">
                        Historique
                    </a>
                </div>
            </div>

            <!-- En-tête de la fiche -->
            <div class="section-box p-4 md:p-5 mb-4">
                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 pb-3 border-b border-slate-200 gap-3">
                    <div class="flex items-center space-x-2 w-full md:w-1/2">
                        <label class="font-bold text-base text-slate-800 whitespace-nowrap">FICHE N° :</label>
                        <input type="text" id="fiche_num" name="fiche_num"
                            class="input-standard font-bold w-full bg-slate-100"
                            value="<?= htmlspecialchars($fiche['fiche_num']) ?>" required
                            <?= $id > 0 ? '' : 'readonly' ?>>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 w-full md:w-auto justify-between md:justify-end">
                        <div class="flex items-center space-x-2">
                            <span class="font-semibold text-xs text-slate-600">Date :</span>
                            <input type="date" name="date" class="input-standard w-auto"
                                value="<?= htmlspecialchars($fiche['date_tache'] ?? date('Y-m-d')) ?>">
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="font-semibold text-xs text-slate-600">Heure :</span>
                            <input type="time" name="heure" class="input-standard w-auto"
                                value="<?= htmlspecialchars($fiche['heure'] ?? date('H:i')) ?>">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="flex flex-col space-y-1">
                        <label class="font-semibold text-xs text-slate-600">Classes & Cycles (Ctrl / Cmd pour en choisir
                            plusieurs) * :</label>
                        <select name="classes[]" id="classes_select" multiple class="input-standard h-28" required>
                            <?php if (empty($classesDisponibles)): ?>
                            <option value="" disabled>Aucune classe trouvée</option>
                            <?php else: foreach ($classesDisponibles as $c): ?>
                            <option value="<?= (int)$c['id']; ?>"
                                <?= in_array((int)$c['id'], $classesSelectionneesActuelles) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nom_classe']); ?> (Cycle:
                                <?= htmlspecialchars($c['nom_cycle'] ?? 'N/A'); ?>)
                            </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    <div class="flex flex-col space-y-1">
                        <label class="font-semibold text-xs text-slate-600">Noms du Professeur :</label>
                        <input type="text" name="noms_prof" class="input-standard mt-0 md:mt-5"
                            value="<?= htmlspecialchars(($prof['nom'] ?? '') . ' ' . ($prof['postnom'] ?? '')) ?>"
                            readonly>
                    </div>
                </div>

                <div class="space-y-3 mb-4 bg-slate-50 p-3 rounded border border-slate-200">
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-48 text-xs text-slate-700">Branche (Cours) * :</span>
                        <select name="branche" id="branche_select" class="input-standard" required>
                            <option value="">-- Sélectionnez un cours --</option>
                            <?php 
                            $coursFiltres = [];
                            foreach ($coursAssignes as $cours) {
                                $intitule = $cours['intitule'];
                                if (!in_array($intitule, $coursFiltres)) {
                                    $coursFiltres[] = $intitule;
                                    $selected = ($fiche['branche'] === $intitule) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($intitule) . '" ' . $selected . '>' . htmlspecialchars($intitule) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-48 text-xs text-slate-700">Fonction :</span>
                        <input type="text" name="fonction" class="input-standard"
                            value="<?= htmlspecialchars($fiche['fonction'] ?? '') ?>">
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-48 text-xs text-slate-700">Compétence intégrée :</span>
                        <input type="text" name="competence_integree" class="input-standard"
                            value="<?= htmlspecialchars($fiche['competence_integree'] ?? '') ?>">
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-48 text-xs text-slate-700">Compétence visée :</span>
                        <input type="text" name="competence_visee" class="input-standard"
                            value="<?= htmlspecialchars($fiche['competence_visee'] ?? '') ?>">
                    </div>
                </div>

                <div class="space-y-3 pt-3 border-t border-slate-200">
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-52 text-xs uppercase text-slate-600">Sujet de la Révision
                            :</span>
                        <input type="text" name="sujet_revision" class="input-standard"
                            value="<?= htmlspecialchars($fiche['sujet_revision'] ?? '') ?>">
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-52 text-xs uppercase text-slate-600">Sujet de la Leçon :</span>
                        <input type="text" name="sujet_lecon" class="input-standard"
                            value="<?= htmlspecialchars($fiche['sujet_lecon'] ?? '') ?>">
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-52 text-xs uppercase text-slate-600">Objectifs Pédagogiques
                            :</span>
                        <input type="text" name="objectifs_pedagogiques" class="input-standard"
                            value="<?= htmlspecialchars($fiche['objectifs_pedagogiques'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Matériel et Références -->
            <div class="section-box p-4 mb-4 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                    <span class="font-semibold sm:w-56 text-xs uppercase text-slate-600">Matériel Didactique :</span>
                    <input type="text" name="materiel_didactique" class="input-standard"
                        value="<?= htmlspecialchars($fiche['materiel_didactique'] ?? '') ?>">
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                    <span class="font-semibold sm:w-56 text-xs uppercase text-slate-600">Référence Bibliographique
                        :</span>
                    <input type="text" name="reference_bibliographique" class="input-standard"
                        value="<?= htmlspecialchars($fiche['reference_bibliographique'] ?? '') ?>">
                </div>
            </div>

            <!-- Tableau : Méthodes et Procédés / Matière à enseigner (Responsive table container) -->
            <div class="overflow-x-auto mb-4 section-box">
                <table class="w-full border-collapse text-sm min-w-[600px]">
                    <thead>
                        <tr class="bg-slate-800 text-white border-b border-slate-300">
                            <th
                                class="p-3 w-1/2 text-left text-xs font-bold uppercase tracking-wider border-r border-slate-600">
                                MÉTHODES ET PROCÉDÉS</th>
                            <th class="p-3 w-1/2 text-left text-xs font-bold uppercase tracking-wider">MATIÈRE À
                                ENSEIGNER</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr>
                            <td class="border-r border-slate-300 p-3 align-top bg-slate-50/50">
                                <p class="font-bold text-slate-800 underline mb-3 text-xs">I. INTRODUCTION</p>
                                <label class="font-semibold text-xs text-slate-600 block mb-1">a) Rappel
                                    (Révision)</label>
                                <textarea name="methode_rappel" class="input-standard"
                                    rows="3"><?= htmlspecialchars($fiche['methode_rappel'] ?? '') ?></textarea>
                            </td>
                            <td class="p-3 align-top">
                                <label class="font-semibold text-xs text-transparent block mb-1">&nbsp;</label>
                                <textarea name="matiere_rappel" rows="3"
                                    class="input-standard resize-none"><?= htmlspecialchars($fiche['matiere_rappel'] ?? '') ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td class="border-r border-slate-300 p-3 align-top bg-slate-50/50">
                                <label class="font-semibold text-xs text-slate-600 block mb-1">b) Motivation</label>
                                <textarea name="methode_motivation" class="input-standard"
                                    rows="3"><?= htmlspecialchars($fiche['methode_motivation'] ?? '') ?></textarea>
                            </td>
                            <td class="p-3 align-top">
                                <textarea name="matiere_motivation" rows="3"
                                    class="input-standard resize-none"><?= htmlspecialchars($fiche['matiere_motivation'] ?? '') ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td class="border-r border-slate-300 p-3 align-top bg-slate-50/50">
                                <label class="font-semibold text-xs text-slate-600 block mb-1">c) Annonce du
                                    sujet</label>
                                <textarea name="methode_annonce" class="input-standard"
                                    rows="3"><?= htmlspecialchars($fiche['methode_annonce'] ?? '') ?></textarea>
                            </td>
                            <td class="p-3 align-top">
                                <textarea name="matiere_annonce" rows="3"
                                    class="input-standard resize-none"><?= htmlspecialchars($fiche['matiere_annonce'] ?? '') ?></textarea>
                            </td>
                        </tr>
                        <tr class="border-t border-b border-slate-300">
                            <td class="border-r border-slate-300 p-3 align-top bg-slate-50/50">
                                <p class="font-bold text-slate-800 underline mb-3 text-xs">II. DÉVELOPPEMENT</p>
                                <textarea name="methode_developpement" rows="6"
                                    class="input-standard resize-none"><?= htmlspecialchars($fiche['methode_developpement'] ?? '') ?></textarea>
                            </td>
                            <td class="p-3 align-top">
                                <label class="font-semibold text-xs text-transparent block mb-1">&nbsp;</label>
                                <textarea name="matiere_developpement" rows="7"
                                    class="input-standard resize-none"><?= htmlspecialchars($fiche['matiere_developpement'] ?? '') ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td class="border-r border-slate-300 p-3 align-top bg-slate-50/50">
                                <p class="font-bold text-red-600 underline mb-3 text-xs">III. SYNTHÈSE</p>
                                <textarea name="methode_synthese" class="input-standard"
                                    rows="3"><?= htmlspecialchars($fiche['methode_synthese'] ?? '') ?></textarea>
                            </td>
                            <td class="p-3 align-top">
                                <textarea name="matiere_synthese" rows="3"
                                    class="input-standard resize-none"><?= htmlspecialchars($fiche['matiere_synthese'] ?? '') ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td class="border-r border-slate-300 p-3 align-top bg-slate-50/50">
                                <p class="font-bold text-red-600 underline mb-3 text-xs">IV. APPLICATION</p>
                                <textarea name="methode_application" class="input-standard"
                                    rows="3"><?= htmlspecialchars($fiche['methode_application'] ?? '') ?></textarea>
                            </td>
                            <td class="p-3 align-top">
                                <textarea name="matiere_application" rows="3"
                                    class="input-standard resize-none"><?= htmlspecialchars($fiche['matiere_application'] ?? '') ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td class="border-r border-slate-300 p-3 align-top bg-slate-50/50">
                                <p class="font-bold text-red-600 underline mb-3 text-xs">V. TÂCHE ÉLÈVE</p>
                                <textarea name="methode_tache" class="input-standard"
                                    rows="3"><?= htmlspecialchars($fiche['methode_tache'] ?? '') ?></textarea>
                            </td>
                            <td class="p-3 align-top">
                                <textarea name="matiere_tache" rows="3"
                                    class="input-standard resize-none"><?= htmlspecialchars($fiche['matiere_tache'] ?? '') ?></textarea>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- SECTION : Commentaires / Remarques -->
            <div class="section-box p-4 mb-4">
                <label class="font-bold text-xs text-slate-700 uppercase block mb-2">Commentaires / Remarques du
                    Professeur ou de l'Inspecteur :</label>
                <textarea name="commentaires" rows="3"
                    class="input-standard resize-none"><?= htmlspecialchars($fiche['commentaires'] ?? '') ?></textarea>
            </div>

            <!-- SECTION : Pièces Jointes (PJ) -->
            <div class="section-box p-4 mb-4">
                <label class="font-bold text-xs text-slate-700 uppercase block mb-2">Pièce Jointe (PJ) :</label>
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <input type="file" name="pieces_jointes"
                        class="input-standard bg-white p-1 text-xs text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                    <?php if (!empty($fiche['piece_jointe'])): ?>
                    <a href="../../uploads/attachement_fiche_detaillee_pedagogique/<?= urlencode($fiche['piece_jointe']) ?>"
                        target="_blank" class="text-blue-600 text-xs underline font-semibold">Voir le fichier actuel</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-md shadow-sm transition font-sans text-sm flex items-center">
                    <?= $id > 0 ? 'Mettre à jour la fiche' : 'Enregistrer votre fiche' ?>
                </button>
                <button type="button" onclick="window.print()"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-md text-sm">
                    🖨️ Imprimer la fiche
                </button>
            </div>

        </form>
    </div>

    </div>
    <!-- Script JavaScript pour l'incrémentation automatique (uniquement en mode création) -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const isEditMode = <?= $id > 0 ? 'true' : 'false' ?>;
        if (!isEditMode) {
            const classesSelect = document.getElementById('classes_select');
            const brancheSelect = document.getElementById('branche_select');
            const ficheNumInput = document.getElementById('fiche_num');

            function updateFicheNum() {
                const selectedOptions = Array.from(classesSelect.selectedOptions);
                if (selectedOptions.length > 0 && brancheSelect.value !== '') {
                    const classeId = selectedOptions[0].value;
                    const coursNom = brancheSelect.value;

                    fetch(`?get_next_num=1&classe_id=${classeId}&cours=${encodeURIComponent(coursNom)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.next_num) {
                                ficheNumInput.value = data.next_num;
                            }
                        })
                        .catch(err => console.error("Erreur calcul numéro de fiche:", err));
                } else {
                    ficheNumInput.value = "";
                }
            }

            classesSelect.addEventListener('change', updateFicheNum);
            brancheSelect.addEventListener('change', updateFicheNum);
        }
    });
    </script>
    <?php include __DIR__.'/../layout/footer.php'; ?>
</body>

</html>