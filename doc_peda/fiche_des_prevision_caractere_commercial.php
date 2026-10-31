<?php
// /prof/doc_peda/fiche_des_prevision_caractere_commercial.php
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
    'branche' => '',
    'classe_id' => 0,
    'fonction' => '',
    'competence_integree' => '',
    'lignes_data' => '',
    'commentaires' => '',
    'piece_jointe' => null
];

// Initialisation des 4 lignes du tableau
$lignesFiche = [];
for ($i = 1; $i <= 4; $i++) {
    $lignesFiche[$i] = [
        'periode' => '', 'mois' => '', 'semaine' => '',
        'competence' => '', 'ressources' => '', 'objectifs' => '',
        'contenu' => '', 'indications' => '', 'observation' => ''
    ];
}

// Chargement en mode modification (?id=val)
if ($id > 0) {
    try {
        $stmt = $con->prepare("SELECT * FROM fiches_previsions_commerciales WHERE id = ? AND agent_id = ?");
        $stmt->bind_param("ii", $id, $agentId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $fiche = $res->fetch_assoc();
            if (!empty($fiche['lignes_data'])) {
                $decoded = json_decode($fiche['lignes_data'], true);
                if (is_array($decoded)) {
                    $lignesFiche = $decoded;
                }
            }
        } else {
            header("Location: fiche_des_prevision_caractere_commercial_list.php");
            exit();
        }
        $stmt->close();
    } catch (Throwable $e) {
        $error = "Erreur lors du chargement : " . $e->getMessage();
    }
}

// Récupération des classes affectées au professeur (sans duplication)[cite: 14]
try {
    $classesDisponibles = classes_of_agent($con, $agentId);
} catch (Throwable $e) {
    $classesDisponibles = [];
    $error = "Erreur lors du chargement des classes : " . $e->getMessage();
}

// Récupération des cours/branches assignés au professeur connecté
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

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branche             = trim((string)($_POST['branche'] ?? ''));
    $classe_id           = (int)($_POST['classe_id'] ?? 0);
    $fonction            = trim((string)($_POST['fonction'] ?? ''));
    $competence_integree = trim((string)($_POST['competence_integree'] ?? ''));
    $commentaires        = trim((string)($_POST['commentaires'] ?? ''));

    // Collecte des 4 lignes du tableau
    $lignesPost = [];
    for ($i = 1; $i <= 4; $i++) {
        $lignesPost[$i] = [
            'periode'     => trim((string)($_POST['periode_' . $i] ?? '')),
            'mois'        => trim((string)($_POST['mois_' . $i] ?? '')),
            'semaine'     => trim((string)($_POST['semaine_' . $i] ?? '')),
            'competence'  => trim((string)($_POST['competence_' . $i] ?? '')),
            'ressources'  => trim((string)($_POST['ressources_' . $i] ?? '')),
            'objectifs'   => trim((string)($_POST['objectifs_' . $i] ?? '')),
            'contenu'     => trim((string)($_POST['contenu_' . $i] ?? '')),
            'indications' => trim((string)($_POST['indications_' . $i] ?? '')),
            'observation' => trim((string)($_POST['observation_' . $i] ?? ''))
        ];
    }
    $lignesJson = json_encode($lignesPost, JSON_UNESCAPED_UNICODE);

    if ($branche === '' || $classe_id === 0) {
        $error = "La branche et la sélection d'une classe sont obligatoires.";
    } else {
        $nomFichierFinal = $fiche['piece_jointe'];
        if (isset($_FILES['pieces_jointes']) && $_FILES['pieces_jointes']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/attachement_prevision_commercial_pedagogique/';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
            $nomFichierFinal = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', basename($_FILES['pieces_jointes']['name']));
            move_uploaded_file($_FILES['pieces_jointes']['tmp_name'], $uploadDir . $nomFichierFinal);
        }

        try {
            if ($id > 0) {
                $stmt = $con->prepare("
                    UPDATE fiches_previsions_commerciales 
                    SET branche = ?, classe_id = ?, fonction = ?, competence_integree = ?, lignes_data = ?, commentaires = ?, piece_jointe = ?
                    WHERE id = ? AND agent_id = ?
                ");
                $stmt->bind_param("sisssssii", $branche, $classe_id, $fonction, $competence_integree, $lignesJson, $commentaires, $nomFichierFinal, $id, $agentId);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $con->prepare("
                    INSERT INTO fiches_previsions_commerciales (agent_id, branche, classe_id, fonction, competence_integree, lignes_data, commentaires, piece_jointe)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("isisssss", $agentId, $branche, $classe_id, $fonction, $competence_integree, $lignesJson, $commentaires, $nomFichierFinal);
                $stmt->execute();
                $stmt->close();
            }

            header("Location: fiche_des_prevision_caractere_commercial_list.php?msg=" . ($id > 0 ? 'updated' : 'created'));
            exit();
        } catch (Throwable $e) {
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
    <title>Formulaire - Planification Pédagogique / Fiche de Matière</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
    body {
        background-color: #f1f5f9;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .sheet {
        background: #ffffff;
        width: 100%;
        max-width: 100%;
        min-height: 210mm;
        margin: 15mm auto;
        padding: 10mm;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        border-radius: 6px;
    }

    .input-standard {
        width: 100%;
        padding: 6px 10px;
        border: 1px solid #cbd5e1;
        border-radius: 0.375rem;
        background-color: #f8fafc;
        font-size: 0.8rem;
        color: #1e293b;
        outline: none;
    }

    .input-standard:focus {
        border-color: #3b82f6;
        background-color: #ffffff;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
    }

    .table-grid th,
    .table-grid td {
        border: 1px solid #94a3b8;
        padding: 8px;
        vertical-align: middle;
    }

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
            margin: 0;
            box-shadow: none;
            width: 100%;
            padding: 5mm;
        }

        .no-print {
            display: none !important;
        }

        .input-standard,
        select,
        textarea {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            resize: none !important;
        }
    }
    </style>
</head>

<body class="py-6">
    <div class="sheet relative">
        <?php if (!empty($error)): ?>
        <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded shadow-sm no-print">
            <p class="font-medium"><?php echo htmlspecialchars($error); ?></p>
        </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <div
                class="flex justify-between items-center mb-6 no-print bg-white p-4 rounded-lg shadow-sm border border-slate-200">
                <span class="text-sm font-semibold text-slate-700 font-sans">
                    <?= $id > 0 ? 'Modification - Planification Pédagogique' : 'Saisie - Tableau de Planification Pédagogique' ?>
                </span>
                <div class="flex space-x-3">
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-md text-sm">
                        <?= $id > 0 ? 'Mettre à jour' : 'Enregistrer' ?>
                    </button>
                    <a href="fiche_des_prevision_caractere_commercial_list.php"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-md text-sm">Historique</a>
                    <!-- <button type="button" onclick="window.print()"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-md text-sm">Imprimer</button> -->
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse table-grid text-xs bg-white min-w-[1100px]">
                    <thead>
                        <tr>
                            <td colspan="4" class="p-3 bg-slate-50">
                                <div class="font-bold text-slate-700 mb-1">Branche * :</div>
                                <select name="branche" class="input-standard" required>
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
                            </td>
                            <td colspan="5" class="p-3 bg-slate-50">
                                <div class="font-bold text-slate-700 mb-1">Classe * :</div>
                                <select name="classe_id" class="input-standard" required>
                                    <option value="">-- Sélectionnez une classe --</option>
                                    <?php foreach ($classesDisponibles as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"
                                        <?= ((int)$fiche['classe_id'] === (int)$c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['description']) ?> (Cycle:
                                        <?= htmlspecialchars($c['cycle_desc'] ?? 'N/A') ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="9" class="p-3 bg-slate-50">
                                <div class="font-bold text-slate-700 mb-1">FONCTION :</div>
                                <input type="text" name="fonction" class="input-standard"
                                    value="<?= htmlspecialchars($fiche['fonction']) ?>">
                            </td>
                        </tr>
                        <tr>
                            <td colspan="9" class="p-3 bg-slate-50">
                                <div class="font-bold text-slate-700 mb-1">Compétence intégrée :</div>
                                <input type="text" name="competence_integree" class="input-standard"
                                    value="<?= htmlspecialchars($fiche['competence_integree']) ?>">
                            </td>
                        </tr>
                        <tr class="bg-slate-900 text-white text-center font-bold tracking-wide">
                            <td class="w-28 p-3 text-white">PERIODE</td>
                            <td class="w-28 p-3 text-white">MOIS</td>
                            <td class="w-36 p-3 text-white">Semaine</td>
                            <td class="w-36 p-3 text-white">Compétence</td>
                            <td class="w-36 p-3 text-white">Ressources</td>
                            <td class="w-36 p-3 text-white">Objectifs Spécifiques</td>
                            <td class="w-44 p-3 text-white">Contenu Matière</td>
                            <td class="w-40 p-3 text-white">Indications Méthodologiques</td>
                            <td class="w-32 p-3 text-white">Observation</td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 1; $i <= 4; $i++): 
                            $ligne = $lignesFiche[$i];
                        ?>
                        <tr>
                            <td class="text-center align-middle">
                                <select name="periode_<?php echo $i; ?>" class="input-standard py-2">
                                    <option value="">Sélectionner</option>
                                    <?php foreach(['1ère Période', '2ème Période', '3ème Période', '4ème Période', '5ème Période', '6ème Période'] as $p): ?>
                                    <option value="<?= $p ?>" <?= ($ligne['periode'] === $p) ? 'selected' : '' ?>>
                                        <?= $p ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="text-center align-middle">
                                <select name="mois_<?php echo $i; ?>" class="input-standard py-2">
                                    <option value="">Sélectionner</option>
                                    <?php foreach(['Septembre', 'Octobre', 'Novembre', 'Décembre', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août'] as $m): ?>
                                    <option value="<?= $m ?>" <?= ($ligne['mois'] === $m) ? 'selected' : '' ?>><?= $m ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="align-middle">
                                <input type="text" name="semaine_<?php echo $i; ?>" class="input-standard py-2"
                                    placeholder="Du ... au ..." value="<?= htmlspecialchars($ligne['semaine']) ?>">
                            </td>
                            <td><textarea name="competence_<?php echo $i; ?>" rows="6"
                                    class="input-standard resize-none"
                                    placeholder="Saisir la compétence..."><?= htmlspecialchars($ligne['competence']) ?></textarea>
                            </td>
                            <td><textarea name="ressources_<?php echo $i; ?>" rows="6"
                                    class="input-standard resize-none"
                                    placeholder="Saisir les ressources..."><?= htmlspecialchars($ligne['ressources']) ?></textarea>
                            </td>
                            <td><textarea name="objectifs_<?php echo $i; ?>" rows="6" class="input-standard resize-none"
                                    placeholder="Saisir les objectifs..."><?= htmlspecialchars($ligne['objectifs']) ?></textarea>
                            </td>
                            <td><textarea name="contenu_<?php echo $i; ?>" rows="6" class="input-standard resize-none"
                                    placeholder="Saisir le contenu..."><?= htmlspecialchars($ligne['contenu']) ?></textarea>
                            </td>
                            <td><textarea name="indications_<?php echo $i; ?>" rows="6"
                                    class="input-standard resize-none"
                                    placeholder="Saisir les indications..."><?= htmlspecialchars($ligne['indications']) ?></textarea>
                            </td>
                            <td><textarea name="observation_<?php echo $i; ?>" rows="6"
                                    class="input-standard resize-none"
                                    placeholder="Observations..."><?= htmlspecialchars($ligne['observation']) ?></textarea>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 p-4 bg-white border border-slate-300 rounded-md">
                <label class="font-bold text-xs text-slate-700 uppercase block mb-2">Commentaires / Remarques :</label>
                <textarea name="commentaires" rows="3" class="input-standard resize-none"
                    placeholder="Ajouter des observations..."><?= htmlspecialchars($fiche['commentaires']) ?></textarea>
            </div>

            <div class="mt-4 p-4 bg-white border border-slate-300 rounded-md">
                <label class="font-bold text-xs text-slate-700 uppercase block mb-2">Pièce Jointe (PJ) :</label>
                <div class="flex items-center space-x-3">
                    <input type="file" name="pieces_jointes" class="input-standard bg-white p-1 text-xs text-slate-500">
                    <?php if (!empty($fiche['piece_jointe'])): ?>
                    <a href="../../uploads/attachement_prevision_commercial_pedagogique/<?= urlencode($fiche['piece_jointe']) ?>"
                        target="_blank" class="text-blue-600 text-xs underline font-semibold">Voir le fichier actuel</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
    <?php require_once __DIR__.'/../layout/footer.php'; ?>
</body>

</html>