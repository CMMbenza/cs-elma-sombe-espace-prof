<?php
// /prof/doc_peda/fiche_detaillee_modele_das.php
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
    'sous_domaine' => '',
    'discipline' => '',
    'titre' => '',
    'sous_titre' => '',
    'code' => '',
    'enseignant' => ($prof['nom'] ?? '') . ' ' . ($prof['postnom'] ?? ''),
    'date_tache' => date('Y-m-d'),
    'duree' => '',
    'reference' => '',
    'madi' => '',
    'competence' => '',
    'exemple_situation' => '',
    'activite_ens_verif' => '',
    'activite_eleve_verif' => '',
    'activite_ens_motivation' => '',
    'activite_eleve_motivation' => '',
    'activite_ens_principal' => '',
    'activite_eleve_principal' => '',
    'activite_ens_synthese' => '',
    'activite_eleve_synthese' => '',
    'activite_ens_eval' => '',
    'activite_eleve_eval' => '',
    'activite_ens_sit_similaire' => '',
    'activite_eleve_sit_similaire' => '',
    'commentaires' => '',
    'piece_jointe' => null
];

$classesSelectionneesActuelles = [];

if ($id > 0) {
    try {
        $stmtFiche = $con->prepare("SELECT * FROM fiches_das WHERE id = ?");
        $stmtFiche->bind_param("i", $id);
        $stmtFiche->execute();
        $resFiche = $stmtFiche->get_result();
        if ($resFiche->num_rows > 0) {
            $fiche = $resFiche->fetch_assoc();
        } else {
            header("Location: fiche_detaillee_modele_das_list.php");
            exit();
        }
        $stmtFiche->close();

        $stmtClassesFiche = $con->prepare("SELECT classe_id FROM fiche_das_classes WHERE fiche_id = ?");
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
}

try {
    $sqlCours = "
        SELECT DISTINCT co.id, co.intitule 
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
        $resAllCours = $con->query("SELECT DISTINCT id, intitule FROM cours ORDER BY intitule ASC");
        $coursAssignes = $resAllCours->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    $coursAssignes = [];
}

if (isset($_GET['get_next_num']) && isset($_GET['classe_id']) && isset($_GET['discipline'])) {
    $cId = (int)$_GET['classe_id'];
    $disc = trim($_GET['discipline']);
    
    $stmtCount = $con->prepare("
        SELECT COUNT(DISTINCT f.id) as total 
        FROM fiches_das f
        JOIN fiche_das_classes fc ON f.id = fc.fiche_id
        WHERE fc.classe_id = ? AND f.discipline = ?
    ");
    $stmtCount->bind_param("is", $cId, $disc);
    $stmtCount->execute();
    $resCount = $stmtCount->get_result()->fetch_assoc();
    $nextNum = ((int)($resCount['total'] ?? 0)) + 1;
    $stmtCount->close();

    echo json_encode(['next_num' => 'DAS-' . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fiche_num                   = trim((string)($_POST['fiche_num'] ?? ''));
    $sous_domaine                = trim((string)($_POST['sous_domaine'] ?? ''));
    $discipline                  = trim((string)($_POST['discipline'] ?? ''));
    $titre                       = trim((string)($_POST['titre'] ?? ''));
    $sous_titre                  = trim((string)($_POST['sous_titre'] ?? ''));
    $code                        = trim((string)($_POST['code'] ?? ''));
    $enseignant                  = trim((string)($_POST['enseignant'] ?? ''));
    $date                        = trim((string)($_POST['date'] ?? ''));
    $duree                       = trim((string)($_POST['duree'] ?? ''));
    $reference                   = trim((string)($_POST['reference'] ?? ''));
    $madi                        = trim((string)($_POST['madi'] ?? ''));
    $competence                  = trim((string)($_POST['competence'] ?? ''));
    $exemple_situation           = trim((string)($_POST['exemple_situation'] ?? ''));
    $activite_ens_verif          = trim((string)($_POST['activite_ens_verif'] ?? ''));
    $activite_eleve_verif        = trim((string)($_POST['activite_eleve_verif'] ?? ''));
    $activite_ens_motivation     = trim((string)($_POST['activite_ens_motivation'] ?? ''));
    $activite_eleve_motivation   = trim((string)($_POST['activite_eleve_motivation'] ?? ''));
    $activite_ens_principal      = trim((string)($_POST['activite_ens_principal'] ?? ''));
    $activite_eleve_principal    = trim((string)($_POST['activite_eleve_principal'] ?? ''));
    $activite_ens_synthese       = trim((string)($_POST['activite_ens_synthese'] ?? ''));
    $activite_eleve_synthese     = trim((string)($_POST['activite_eleve_synthese'] ?? ''));
    $activite_ens_eval           = trim((string)($_POST['activite_ens_eval'] ?? ''));
    $activite_eleve_eval         = trim((string)($_POST['activite_eleve_eval'] ?? ''));
    $activite_ens_sit_similaire  = trim((string)($_POST['activite_ens_sit_similaire'] ?? ''));
    $activite_eleve_sit_similaire= trim((string)($_POST['activite_eleve_sit_similaire'] ?? ''));
    $commentaires                = trim((string)($_POST['commentaires'] ?? ''));
    $classes_selectionnees       = $_POST['classes'] ?? [];

    if ($fiche_num === '' || empty($classes_selectionnees) || $discipline === '') {
        $error = "Le numéro de fiche, la discipline et la sélection d'au moins une classe sont obligatoires.";
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

            if ($id > 0) {
                $stmt = $con->prepare("
                    UPDATE fiches_das SET 
                        fiche_num = ?, sous_domaine = ?, discipline = ?, titre = ?, sous_titre = ?, code = ?, 
                        enseignant = ?, date_tache = ?, duree = ?, reference = ?, madi = ?, competence = ?, 
                        exemple_situation = ?, activite_ens_verif = ?, activite_eleve_verif = ?, activite_ens_motivation = ?, 
                        activite_eleve_motivation = ?, activite_ens_principal = ?, activite_eleve_principal = ?, 
                        activite_ens_synthese = ?, activite_eleve_synthese = ?, activite_ens_eval = ?, activite_eleve_eval = ?, 
                        activite_ens_sit_similaire = ?, activite_eleve_sit_similaire = ?, commentaires = ?, piece_jointe = ?
                    WHERE id = ?
                ");
                $stmt->bind_param(
                    "sssssssssssssssssssssssssssi",
                    $fiche_num, $sous_domaine, $discipline, $titre, $sous_titre, $code,
                    $enseignant, $dateVal, $duree, $reference, $madi, $competence,
                    $exemple_situation, $activite_ens_verif, $activite_eleve_verif, $activite_ens_motivation,
                    $activite_eleve_motivation, $activite_ens_principal, $activite_eleve_principal,
                    $activite_ens_synthese, $activite_eleve_synthese, $activite_ens_eval, $activite_eleve_eval,
                    $activite_ens_sit_similaire, $activite_eleve_sit_similaire, $commentaires, $nomFichierFinal, $id
                );
                $stmt->execute();
                $stmt->close();

                $con->query("DELETE FROM fiche_das_classes WHERE fiche_id = $id");
                $ficheId = $id;
            } else {
                $stmt = $con->prepare("
                    INSERT INTO fiches_das (
                        fiche_num, sous_domaine, discipline, titre, sous_titre, code, 
                        enseignant, date_tache, duree, reference, madi, competence, 
                        exemple_situation, activite_ens_verif, activite_eleve_verif, activite_ens_motivation, 
                        activite_eleve_motivation, activite_ens_principal, activite_eleve_principal, 
                        activite_ens_synthese, activite_eleve_synthese, activite_ens_eval, activite_eleve_eval, 
                        activite_ens_sit_similaire, activite_eleve_sit_similaire, commentaires, piece_jointe
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "sssssssssssssssssssssssssss",
                    $fiche_num, $sous_domaine, $discipline, $titre, $sous_titre, $code,
                    $enseignant, $dateVal, $duree, $reference, $madi, $competence,
                    $exemple_situation, $activite_ens_verif, $activite_eleve_verif, $activite_ens_motivation,
                    $activite_eleve_motivation, $activite_ens_principal, $activite_eleve_principal,
                    $activite_ens_synthese, $activite_eleve_synthese, $activite_ens_eval, $activite_eleve_eval,
                    $activite_ens_sit_similaire, $activite_eleve_sit_similaire, $commentaires, $nomFichierFinal
                );
                $stmt->execute();
                $ficheId = $con->insert_id;
                $stmt->close();
            }

            $stmtClasseLink = $con->prepare("INSERT INTO fiche_das_classes (fiche_id, classe_id) VALUES (?, ?)");
            foreach ($classes_selectionnees as $classeId) {
                $cId = (int)$classeId;
                $stmtClasseLink->bind_param("ii", $ficheId, $cId);
                $stmtClasseLink->execute();
            }
            $stmtClasseLink->close();

            $con->commit();
            header("Location: fiche_detaillee_modele_das_list.php?msg=" . ($id > 0 ? 'updated' : 'created'));
            exit();
        } catch (Throwable $e) {
            $con->rollback();
            $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
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
    <title>Formulaire - Fiche d'Exploitation de Matrice (DAS)</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
    body {
        background-color: #f1f5f9;
        /* padding-left: 1rem;
        padding-right: 1rem; */
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    @media (min-width: 768px) {
        body {
            /* padding-left: 2rem;
            padding-right: 2rem; */
        }
    }

    .sheet {
        background: #ffffff;
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
        font-size: 0.875rem;
        color: #1e293b;
        outline: none;
    }

    .input-standard:focus {
        border-color: #3b82f6;
        background-color: #ffffff;
    }

    .section-box {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background-color: #ffffff;
    }

    /* Impression propre : n'imprime que le formulaire (.sheet) */
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
            <p class="font-medium"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <div
                class="flex flex-col sm:flex-row justify-between items-center mb-6 no-print bg-white p-4 rounded-lg shadow-sm border border-slate-200 gap-3">
                <span
                    class="text-sm font-semibold text-slate-700 text-center sm:text-left"><?= $id > 0 ? 'Modification - Fiche d\'Exploitation de Matrice (DAS)' : 'Saisie - Fiche d\'Exploitation de Matrice (DAS)' ?></span>
                <div class="flex flex-wrap gap-2 justify-center">
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-md text-sm"><?= $id > 0 ? 'Mettre à jour' : 'Enregistrer' ?></button>
                    <a href="fiche_detaillee_modele_das_list.php"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-md text-sm">Historique des fiches</a>
                </div>
            </div>

            <div class="text-center font-bold text-lg uppercase mb-4 text-slate-800">
                FICHE D'EXPLOITATION DE MATRICE N° :
                <input type="text" id="fiche_num" name="fiche_num"
                    class="input-standard inline-block w-36 text-center font-bold bg-slate-100"
                    value="<?= htmlspecialchars($fiche['fiche_num']) ?>" required readonly>
            </div>

            <!-- SECTION MULTI-CLASSES & SÉLECTION COURS / ENSEIGNANT -->
            <div class="section-box p-4 md:p-5 mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-3">
                    <div class="flex flex-col space-y-1">
                        <label class="font-semibold text-xs text-slate-700">Classes & Cycles (Ctrl / Cmd pour choix
                            multiple) * :</label>
                        <select name="classes[]" id="classes_select" multiple class="input-standard h-28" required>
                            <?php foreach ($classesDisponibles as $c): ?>
                            <option value="<?= (int)$c['id']; ?>"
                                <?= in_array((int)$c['id'], $classesSelectionneesActuelles) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nom_classe']); ?> (Cycle:
                                <?= htmlspecialchars($c['nom_cycle'] ?? 'N/A'); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-32 text-xs text-slate-700">Sous-domaine :</span>
                        <input type="text" name="sous_domaine" class="input-standard"
                            value="<?= htmlspecialchars($fiche['sous_domaine'] ?? '') ?>">
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-32 text-xs text-slate-700">Discipline * :</span>
                        <select name="discipline" id="discipline_select" class="input-standard" required>
                            <option value="">-- Sélectionnez une discipline --</option>
                            <?php 
                            $coursFiltres = [];
                            foreach ($coursAssignes as $cours) {
                                $intitule = $cours['intitule'];
                                if (!in_array($intitule, $coursFiltres)) {
                                    $coursFiltres[] = $intitule;
                                    $selected = ($fiche['discipline'] === $intitule) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($intitule) . '" ' . $selected . '>' . htmlspecialchars($intitule) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-32 text-xs text-slate-700">Titre :</span>
                        <input type="text" name="titre" class="input-standard"
                            value="<?= htmlspecialchars($fiche['titre'] ?? '') ?>">
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-32 text-xs text-slate-700">Sous-titre :</span>
                        <input type="text" name="sous_titre" class="input-standard"
                            value="<?= htmlspecialchars($fiche['sous_titre'] ?? '') ?>">
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-32 text-xs text-slate-700">Code :</span>
                        <input type="text" name="code" class="input-standard"
                            value="<?= htmlspecialchars($fiche['code'] ?? '') ?>">
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-32 text-xs text-slate-700">Enseignant :</span>
                        <input type="text" name="enseignant" class="input-standard bg-slate-100"
                            value="<?= htmlspecialchars($fiche['enseignant']) ?>" readonly>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-32 text-xs text-slate-700">Date :</span>
                        <input type="date" name="date" class="input-standard"
                            value="<?= htmlspecialchars($fiche['date_tache'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-32 text-xs text-slate-700">Durée :</span>
                        <input type="text" name="duree" class="input-standard"
                            value="<?= htmlspecialchars($fiche['duree'] ?? '') ?>">
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3">
                        <span class="font-semibold sm:w-32 text-xs text-slate-700">Référence :</span>
                        <input type="text" name="reference" class="input-standard"
                            value="<?= htmlspecialchars($fiche['reference'] ?? '') ?>">
                    </div>
                </div>

                <div
                    class="col-span-1 md:col-span-2 flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-3 pt-2 border-t border-slate-200">
                    <span class="font-semibold sm:w-32 text-xs text-slate-700">MADI :</span>
                    <input type="text" name="madi" class="input-standard"
                        value="<?= htmlspecialchars($fiche['madi'] ?? '') ?>">
                </div>
            </div>

            <!-- Compétence et Exemple de situation -->
            <div class="section-box p-4 mb-4 space-y-3">
                <div>
                    <label class="font-bold text-xs text-slate-800 uppercase block mb-1">COMPÉTENCE :</label>
                    <textarea name="competence" rows="2"
                        class="input-standard resize-none"><?= htmlspecialchars($fiche['competence'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="font-bold text-xs text-slate-800 uppercase block mb-1">Exemple de situation :</label>
                    <textarea name="exemple_situation" rows="4"
                        class="input-standard resize-none"><?= htmlspecialchars($fiche['exemple_situation'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Tableau : Activités (Responsive container) -->
            <div class="overflow-x-auto mb-4 section-box">
                <table class="w-full border-collapse text-sm min-w-[600px]">
                    <thead>
                        <tr class="bg-slate-800 text-white border-b border-slate-300">
                            <th
                                class="p-3 w-1/2 text-left text-xs font-bold uppercase tracking-wider border-r border-slate-600">
                                Activités de l’enseignant</th>
                            <th class="p-3 w-1/2 text-left text-xs font-bold uppercase tracking-wider">Activités de
                                l’élève</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr class="bg-slate-100 font-bold">
                            <td colspan="2" class="p-2 text-center text-xs text-slate-700">I. ACTIVITES INITIALES</td>
                        </tr>
                        <tr>
                            <td class="border-r border-slate-300 p-3 align-top bg-slate-50/50">
                                <p class="font-semibold text-xs text-slate-800 mb-1">1. Vérification de connaissances
                                    précédentes</p>
                                <textarea name="activite_ens_verif" class="input-standard"
                                    rows="2"><?= htmlspecialchars($fiche['activite_ens_verif'] ?? '') ?></textarea>
                            </td>
                            <td class="p-3 align-top">
                                <p class="font-semibold text-xs text-slate-800 mb-1">Réponses aux questions</p>
                                <textarea name="activite_eleve_verif" rows="2"
                                    class="input-standard resize-none"><?= htmlspecialchars($fiche['activite_eleve_verif'] ?? '') ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td class="border-r border-slate-300 p-3 align-top bg-slate-50/50">
                                <p class="font-semibold text-xs text-slate-800 mb-1">2. Motivation (découverte)</p>
                                <textarea name="activite_ens_motivation" class="input-standard"
                                    rows="2"><?= htmlspecialchars($fiche['activite_ens_motivation'] ?? '') ?></textarea>
                            </td>
                            <td class="p-3 align-top">
                                <p class="font-semibold text-xs text-slate-800 mb-1">Compréhension de la situation</p>
                                <textarea name="activite_eleve_motivation" rows="2"
                                    class="input-standard resize-none"><?= htmlspecialchars($fiche['activite_eleve_motivation'] ?? '') ?></textarea>
                            </td>
                        </tr>

                        <tr class="bg-slate-100 font-bold">
                            <td colspan="2" class="p-2 text-center text-xs text-slate-700">II. ACTIVITES PRINCIPALES
                            </td>
                        </tr>
                        <tr>
                            <td class="border-r border-slate-300 p-3 align-top bg-slate-50/50">
                                <p class="font-semibold text-xs text-slate-800 mb-1">Organisation de la classe et
                                    consigne</p>
                                <textarea name="activite_ens_principal" class="input-standard"
                                    rows="4"><?= htmlspecialchars($fiche['activite_ens_principal'] ?? '') ?></textarea>
                            </td>
                            <td class="p-3 align-top">
                                <p class="font-semibold text-xs text-slate-800 mb-1">Activités sur le tableau de
                                    spécification</p>
                                <textarea name="activite_eleve_principal" rows="4"
                                    class="input-standard resize-none"><?= htmlspecialchars($fiche['activite_eleve_principal'] ?? '') ?></textarea>
                            </td>
                        </tr>

                        <tr class="bg-slate-100 font-bold">
                            <td colspan="2" class="p-2 text-center text-xs text-slate-700">III. SYNTHESE</td>
                        </tr>
                        <tr>
                            <td class="border-r border-slate-300 p-3 align-top bg-slate-50/50">
                                <p class="font-semibold text-xs text-slate-800 mb-1">Question de récapitulation</p>
                                <textarea name="activite_ens_synthese" class="input-standard"
                                    rows="4"><?= htmlspecialchars($fiche['activite_ens_synthese'] ?? '') ?></textarea>
                            </td>
                            <td class="p-3 align-top">
                                <p class="font-semibold text-xs text-slate-800 mb-1">Participation des élèves à la
                                    production de la synthèse</p>
                                <textarea name="activite_eleve_synthese" rows="4"
                                    class="input-standard resize-none"><?= htmlspecialchars($fiche['activite_eleve_synthese'] ?? '') ?></textarea>
                            </td>
                        </tr>

                        <tr class="bg-slate-100 font-bold">
                            <td colspan="2" class="p-2 text-center text-xs text-slate-700">IV. EVALUATION</td>
                        </tr>
                        <tr>
                            <td class="border-r border-slate-300 p-3 align-top bg-slate-50/50">
                                <p class="font-semibold text-xs text-slate-800 mb-1">Vérification des acquis sur les
                                    savoirs essentiels</p>
                                <textarea name="activite_ens_eval" class="input-standard"
                                    rows="3"><?= htmlspecialchars($fiche['activite_ens_eval'] ?? '') ?></textarea>
                            </td>
                            <td class="p-3 align-top">
                                <p class="font-semibold text-xs text-slate-800 mb-1">Réponses aux questions</p>
                                <textarea name="activite_eleve_eval" rows="3"
                                    class="input-standard resize-none"><?= htmlspecialchars($fiche['activite_eleve_eval'] ?? '') ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td class="border-r border-slate-300 p-3 align-top bg-slate-50/50">
                                <p class="font-semibold text-xs text-slate-800 mb-1">Situation similaire</p>
                                <textarea name="activite_ens_sit_similaire" class="input-standard"
                                    rows="3"><?= htmlspecialchars($fiche['activite_ens_sit_similaire'] ?? '') ?></textarea>
                            </td>
                            <td class="p-3 align-top">
                                <p class="font-semibold text-xs text-slate-800 mb-1">Traitement de situation par les
                                    élèves</p>
                                <textarea name="activite_eleve_sit_similaire" rows="3"
                                    class="input-standard resize-none"><?= htmlspecialchars($fiche['activite_eleve_sit_similaire'] ?? '') ?></textarea>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Commentaires & Pièces jointes -->
            <div class="section-box p-4 mb-4">
                <label class="font-bold text-xs text-slate-700 uppercase block mb-2">Commentaires / Remarques :</label>
                <textarea name="commentaires" rows="3"
                    class="input-standard resize-none"><?= htmlspecialchars($fiche['commentaires'] ?? '') ?></textarea>
            </div>

            <div class="section-box p-4 mb-4">
                <label class="font-bold text-xs text-slate-700 uppercase block mb-2">Pièce Jointe :</label>
                <input type="file" name="pieces_jointes" class="input-standard bg-white p-1 text-xs">
                <?php if (!empty($fiche['piece_jointe'])): ?>
                <a href="../../uploads/attachement_fiche_detaillee_pedagogique/<?= urlencode($fiche['piece_jointe']) ?>" target="_blank"
                    class="text-blue-600 text-xs underline mt-1 inline-block">Voir le fichier actuel</a>
                <?php endif; ?>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-md text-sm"><?= $id > 0 ? 'Mettre à jour' : 'Enregistrer' ?></button>
                <button type="button" onclick="window.print()"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-md text-sm">🖨️
                    Imprimer la fiche</button>
            </div>
        </form>
    </div>
</div>
    <!-- Script AJAX pour l'incrémentation automatique du numéro de fiche par classe et discipline -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const isEditMode = <?= $id > 0 ? 'true' : 'false' ?>;
        if (!isEditMode) {
            const classesSelect = document.getElementById('classes_select');
            const disciplineSelect = document.getElementById('discipline_select');
            const ficheNumInput = document.getElementById('fiche_num');

            function updateFicheNum() {
                const selectedOptions = Array.from(classesSelect.selectedOptions);
                if (selectedOptions.length > 0 && disciplineSelect.value !== '') {
                    const classeId = selectedOptions[0].value;
                    const disciplineNom = disciplineSelect.value;
                    fetch(
                            `?get_next_num=1&classe_id=${classeId}&discipline=${encodeURIComponent(disciplineNom)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.next_num) ficheNumInput.value = data.next_num;
                        })
                        .catch(err => console.error(err));
                } else {
                    ficheNumInput.value = "";
                }
            }
            classesSelect.addEventListener('change', updateFicheNum);
            disciplineSelect.addEventListener('change', updateFicheNum);
        }
    });
    </script>
    <?php require_once __DIR__.'/../layout/footer.php'; ?>
</body>

</html>