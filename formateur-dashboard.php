<?php
session_start();
require_once 'config.php';

// Vérification si l'utilisateur est connecté et a le rôle de formateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'formateur') {
    header("Location: espace-personnel.php");
    exit();
}

// Initialisation de la base de données
$database = new Database();
$db = $database->getConnection();

// Messages système
$message = '';
$error = '';

// Récupérer l'ID du formateur depuis la table formateur
$formateur_id = 0;
try {
    $query = "SELECT id_formateur FROM formateur WHERE id_user = :id_user";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_user', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $formateur_id = $row['id_formateur'];
    } else {
        // Si le formateur n'existe pas encore dans la table formateur, on peut le créer
        $query = "INSERT INTO formateur (id_user, specialite, disponible) VALUES (:id_user, 'Développement', 1)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_user', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $formateur_id = $db->lastInsertId();
        }
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération/création du formateur: " . $e->getMessage());
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation d'une inscription
    if (isset($_POST['valider_inscription'])) {
        $id_inscription = isset($_POST['id_inscription']) ? intval($_POST['id_inscription']) : 0;
        
        if ($id_inscription > 0) {
            try {
                $query = "UPDATE fiche_inscription SET statut_paiement = 'validé' WHERE id_inscription = :id_inscription";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id_inscription', $id_inscription);
                
                if ($stmt->execute()) {
                    $message = "L'inscription a été validée avec succès.";
                    
                    // Recharger la page pour mettre à jour la liste
                    header("Location: formateur-dashboard.php?success=1");
                    exit();
                } else {
                    $error = "Erreur lors de la validation de l'inscription.";
                }
            } catch (PDOException $e) {
                $error = "Erreur de base de données: " . $e->getMessage();
            }
        }
    }
    
    // Mise à jour du contenu d'un chapitre
    if (isset($_POST['save_chapter'])) {
        $chapitre_id = isset($_POST['chapitre_id']) ? intval($_POST['chapitre_id']) : 0;
        $titre = isset($_POST['titre']) ? htmlspecialchars($_POST['titre']) : '';
        $contenu = isset($_POST['contenu']) ? $_POST['contenu'] : '';
        $duree = isset($_POST['duree_estimee']) ? intval($_POST['duree_estimee']) : 30;
        
        if ($chapitre_id > 0 && !empty($titre)) {
            try {
                // Vérifier que le chapitre appartient à un module d'une formation assignée au formateur
                $check_query = "SELECT c.id_chapitre 
                               FROM chapitre c 
                               JOIN module m ON c.id_module = m.id_module 
                               JOIN formation f ON m.id_formation = f.id_formation
                               JOIN session_formation sf ON f.id_formation = sf.id_formation
                               WHERE c.id_chapitre = :chapitre_id 
                               AND sf.id_formateur = :formateur_id";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':chapitre_id', $chapitre_id);
                $check_stmt->bindParam(':formateur_id', $formateur_id);
                $check_stmt->execute();
                
                if ($check_stmt->rowCount() > 0) {
                    $update_query = "UPDATE chapitre 
                                     SET titre = :titre, contenu = :contenu, duree_estimee = :duree 
                                     WHERE id_chapitre = :chapitre_id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':titre', $titre);
                    $update_stmt->bindParam(':contenu', $contenu);
                    $update_stmt->bindParam(':duree', $duree);
                    $update_stmt->bindParam(':chapitre_id', $chapitre_id);
                    
                    if ($update_stmt->execute()) {
                        $message = "Le chapitre a été mis à jour avec succès.";
                    } else {
                        $error = "Erreur lors de la mise à jour du chapitre.";
                    }
                } else {
                    $error = "Vous n'êtes pas autorisé à modifier ce chapitre.";
                }
            } catch (PDOException $e) {
                $error = "Erreur de base de données: " . $e->getMessage();
            }
        } else {
            $error = "Données invalides pour la mise à jour du chapitre.";
        }
    }
}

// Récupérer les formations assignées au formateur
$formations = [];
try {
    $query = "SELECT DISTINCT
                f.id_formation, 
                f.nom_formation, 
                f.description,
                MIN(s.date_debut) as prochain_debut,
                COUNT(DISTINCT s.id_session_formation) as nombre_sessions,
                COUNT(DISTINCT fi.id_inscription) as nombre_inscrits
              FROM formation f
              JOIN session_formation s ON f.id_formation = s.id_formation
              LEFT JOIN fiche_inscription fi ON s.id_session_formation = fi.id_session
              WHERE s.id_formateur = :formateur_id
              GROUP BY f.id_formation
              ORDER BY prochain_debut ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':formateur_id', $formateur_id);
    $stmt->execute();
    
    $formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des formations: " . $e->getMessage());
}

// Récupérer les inscriptions en attente de validation pour les sessions du formateur
// Nous incluons toutes les inscriptions en attente pour que le formateur puisse les voir
$inscriptions_en_attente = [];
try {
    // Version améliorée de la requête pour montrer toutes les inscriptions en attente
    $query = "SELECT fi.id_inscription, fi.date_inscription, fi.statut_paiement,
                    s.id_stagiaire, s.nom as nom_stagiaire, s.prenom as prenom_stagiaire, s.email as email_stagiaire, s.tel,
                    f.id_formation, f.nom_formation, f.prix,
                    sf.id_session_formation, sf.date_debut, sf.date_fin, sf.horaire
              FROM fiche_inscription fi
              JOIN stagiaire s ON fi.id_stagiaire = s.id_stagiaire
              JOIN session_formation sf ON fi.id_session = sf.id_session_formation
              JOIN formation f ON sf.id_formation = f.id_formation
              WHERE fi.statut_paiement = 'en_attente'
              ORDER BY fi.date_inscription DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $inscriptions_en_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log pour débogage
    error_log("Nombre d'inscriptions en attente trouvées: " . count($inscriptions_en_attente));
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des inscriptions en attente: " . $e->getMessage());
}

// Récupérer les modules et chapitres des formations du formateur
$modules_chapitres = [];
try {
    $query = "SELECT DISTINCT
                f.id_formation,
                f.nom_formation,
                m.id_module,
                m.titre as module_titre,
                m.description as module_description,
                m.icone as module_icone,
                c.id_chapitre,
                c.titre as chapitre_titre,
                c.duree_estimee,
                (CHAR_LENGTH(c.contenu) > 0) as has_contenu
              FROM formation f
              JOIN session_formation sf ON f.id_formation = sf.id_formation
              JOIN module m ON f.id_formation = m.id_formation
              JOIN chapitre c ON m.id_module = c.id_module
              WHERE sf.id_formateur = :formateur_id
              ORDER BY f.id_formation, m.ordre, c.ordre";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':formateur_id', $formateur_id);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $formation_id = $row['id_formation'];
        $module_id = $row['id_module'];
        
        if (!isset($modules_chapitres[$formation_id])) {
            $modules_chapitres[$formation_id] = [
                'nom_formation' => $row['nom_formation'],
                'modules' => []
            ];
        }
        
        if (!isset($modules_chapitres[$formation_id]['modules'][$module_id])) {
            $modules_chapitres[$formation_id]['modules'][$module_id] = [
                'id' => $module_id,
                'titre' => $row['module_titre'],
                'description' => $row['module_description'],
                'icone' => $row['module_icone'],
                'chapitres' => []
            ];
        }
        
        $modules_chapitres[$formation_id]['modules'][$module_id]['chapitres'][] = [
            'id' => $row['id_chapitre'],
            'titre' => $row['chapitre_titre'],
            'duree_estimee' => $row['duree_estimee'],
            'has_contenu' => $row['has_contenu']
        ];
    }
    
    // Convertir les tableaux associatifs en tableaux indexés pour les modules
    foreach ($modules_chapitres as $formation_id => $formation_data) {
        $modules_chapitres[$formation_id]['modules'] = array_values($formation_data['modules']);
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des modules et chapitres: " . $e->getMessage());
}

// Récupérer les statistiques
$stats = [
    'sessions_total' => 0,
    'etudiants_total' => 0,
    'chapitres_total' => 0,
    'chapitres_avec_contenu' => 0
];

try {
    // Compter les sessions
    $query = "SELECT COUNT(*) as count FROM session_formation WHERE id_formateur = :formateur_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':formateur_id', $formateur_id);
    $stmt->execute();
    $stats['sessions_total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Compter les étudiants
    $query = "SELECT COUNT(DISTINCT fi.id_stagiaire) as count 
              FROM fiche_inscription fi
              JOIN session_formation sf ON fi.id_session = sf.id_session_formation
              WHERE sf.id_formateur = :formateur_id AND fi.statut_paiement != 'annulé'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':formateur_id', $formateur_id);
    $stmt->execute();
    $stats['etudiants_total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Compter les chapitres et chapitres avec contenu
    $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN CHAR_LENGTH(c.contenu) > 0 THEN 1 ELSE 0 END) as avec_contenu
              FROM chapitre c
              JOIN module m ON c.id_module = m.id_module
              JOIN formation f ON m.id_formation = f.id_formation
              JOIN session_formation sf ON f.id_formation = sf.id_formation
              WHERE sf.id_formateur = :formateur_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':formateur_id', $formateur_id);
    $stmt->execute();
    $chapitres_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['chapitres_total'] = $chapitres_stats['total'] ?? 0;
    $stats['chapitres_avec_contenu'] = $chapitres_stats['avec_contenu'] ?? 0;
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
}

// Récupérer les prochaines sessions
$prochaines_sessions = [];
try {
    $query = "SELECT sf.id_session_formation, f.nom_formation, sf.date_debut, sf.date_fin, sf.horaire,
                    COUNT(fi.id_inscription) as nombre_inscrits, sf.places_disponibles
              FROM session_formation sf
              JOIN formation f ON sf.id_formation = f.id_formation
              LEFT JOIN fiche_inscription fi ON sf.id_session_formation = fi.id_session AND fi.statut_paiement != 'annulé'
              WHERE sf.id_formateur = :formateur_id AND sf.date_debut > CURRENT_DATE
              GROUP BY sf.id_session_formation
              ORDER BY sf.date_debut ASC
              LIMIT 5";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':formateur_id', $formateur_id);
    $stmt->execute();
    
    $prochaines_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des prochaines sessions: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MFC - Espace Formateur</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style-complementaire.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .welcome-section {
            background: linear-gradient(120deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
            padding: 2.5rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            text-align: center;
        }
        
        .welcome-section h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .welcome-section p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .dashboard-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .card-header i {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--gray-50);
            padding: 1.5rem;
            border-radius: 0.5rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-light);
        }

        .inscription-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .inscription-table th,
        .inscription-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-100);
        }

        .inscription-table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--text-color);
        }

        .inscription-table tr:hover {
            background: var(--gray-50);
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            background: var(--primary-color);
            color: var(--white);
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        button.action-btn {
            font-family: inherit;
            font-size: inherit;
        }

        .action-btn:hover {
            background: var(--secondary-color);
        }

        .action-btn.success {
            background: var(--success);
        }

        .action-btn.success:hover {
            background: #15803d;
        }

        .action-btn.secondary {
            background: var(--gray-200);
            color: var(--text-color);
        }

        .action-btn.secondary:hover {
            background: var(--gray-300);
        }

        .logout-btn {
            background: var(--error);
        }

        .logout-btn:hover {
            background: #dc2626;
        }

        .empty-message {
            padding: 2rem;
            text-align: center;
            background: var(--gray-50);
            border-radius: 0.5rem;
        }

        .session-list {
            margin-top: 1rem;
        }

        .session-item {
            padding: 1rem;
            background: var(--gray-50);
            border-radius: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .session-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .session-details {
            font-size: 0.875rem;
            color: var(--text-light);
        }

        .session-capacity {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 0.5rem;
        }

        .capacity-bar {
            flex-grow: 1;
            height: 0.5rem;
            background: var(--gray-200);
            border-radius: 9999px;
            margin: 0 0.5rem;
            overflow: hidden;
        }

        .capacity-fill {
            height: 100%;
            background: var(--primary-color);
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 1rem;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            border-bottom: 2px solid transparent;
        }

        .tab:hover {
            color: var(--primary-color);
        }

        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .accordion {
            margin-bottom: 1rem;
        }

        .accordion-header {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
        }

        .accordion-header:hover {
            background: var(--gray-100);
        }

        .accordion-header h3 {
            margin: 0;
            font-size: 1.125rem;
        }

        .accordion-content {
            display: none;
            padding: 1rem;
            background: var(--white);
            border: 1px solid var(--gray-100);
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: var(--gray-100);
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
        }

        .module-header h4 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .module-content {
            display: none;
            padding: 0.5rem;
        }

        .chapitre-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-radius: 0.5rem;
            background: var(--white);
            margin-bottom: 0.5rem;
            border: 1px solid var(--gray-100);
        }

        .chapitre-info {
            flex: 1;
        }

        .chapitre-title {
            font-weight: 500;
        }

        .chapitre-duration {
            font-size: 0.75rem;
            color: var(--text-light);
        }

        .chapitre-status {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            background: var(--gray-100);
            color: var(--text-light);
        }

        .status-complete {
            background: #d1fae5;
            color: #065f46;
        }

        .status-incomplete {
            background: #fee2e2;
            color: #991b1b;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 80%;
            max-width: 800px;
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
            font-size: 1rem;
        }

        textarea.form-control {
            min-height: 200px;
            resize: vertical;
        }

        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <ul>
                <li class="nav-logo">MFC Formateur</li>
                <li><a href="formateur-dashboard.php" class="active">
                    <i class="fas fa-tachometer-alt"></i> Tableau de bord
                </a></li>
                <li><a href="#" class="action-btn logout-btn" onclick="document.getElementById('logout-form').submit();">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a></li>
            </ul>
        </nav>
    </header>

    <form id="logout-form" action="logout.php" method="POST" style="display: none;"></form>

    <main>
        <div class="dashboard-container">
            <div class="welcome-section">
                <h1>Bienvenue, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
                <p>Gérez le contenu de vos formations et les inscriptions de vos stagiaires</p>
            </div>

            <?php if(isset($_GET['success']) && $_GET['success'] == '1'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> L'inscription a été validée avec succès.
                </div>
            <?php elseif($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['sessions_total']; ?></div>
                    <div class="stat-label">Sessions assignées</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['etudiants_total']; ?></div>
                    <div class="stat-label">Étudiants</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['chapitres_avec_contenu']; ?>/<?php echo $stats['chapitres_total']; ?></div>
                    <div class="stat-label">Chapitres complétés</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($inscriptions_en_attente); ?></div>
                    <div class="stat-label">Inscriptions en attente</div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="main-content">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-book"></i>
                            <h2 class="card-title">Gestion du contenu</h2>
                        </div>
                        
                        <div class="tabs">
                            <div class="tab active" onclick="showTab('content-tab')">Contenus de formation</div>
                            <div class="tab" onclick="showTab('inscriptions-tab')">Inscriptions en attente</div>
                        </div>
                        
                        <div id="content-tab" class="tab-content active">
                            <?php if (empty($modules_chapitres)): ?>
                                <div class="empty-message">
                                    <i class="fas fa-info-circle fa-2x" style="color: var(--primary-color); margin-bottom: 1rem;"></i>
                                    <h3>Aucune formation assignée</h3>
                                    <p>Vous n'avez pas encore de formations assignées pour lesquelles gérer le contenu.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($modules_chapitres as $formation_id => $formation_data): ?>
                                    <div class="accordion">
                                        <div class="accordion-header" onclick="toggleAccordion('formation-<?php echo $formation_id; ?>')">
                                            <h3><?php echo htmlspecialchars($formation_data['nom_formation']); ?></h3>
                                            <i class="fas fa-chevron-down"></i>
                                        </div>
                                        <div id="formation-<?php echo $formation_id; ?>" class="accordion-content">
                                            <?php foreach ($formation_data['modules'] as $module): ?>
                                                <div class="module">
                                                    <div class="module-header" onclick="toggleModule('module-<?php echo $module['id']; ?>')">
                                                        <h4>
                                                            <i class="<?php echo htmlspecialchars($module['icone'] ?: 'fas fa-book'); ?>"></i>
                                                            <?php echo htmlspecialchars($module['titre']); ?>
                                                        </h4>
                                                        <i class="fas fa-chevron-down"></i>
                                                    </div>
                                                    <div id="module-<?php echo $module['id']; ?>" class="module-content">
                                                        <?php if (!empty($module['chapitres'])): ?>
                                                            <?php foreach ($module['chapitres'] as $chapitre): ?>
                                                                <div class="chapitre-item">
                                                                    <div class="chapitre-info">
                                                                        <div class="chapitre-title"><?php echo htmlspecialchars($chapitre['titre']); ?></div>
                                                                        <div class="chapitre-duration"><i class="far fa-clock"></i> <?php echo $chapitre['duree_estimee']; ?> min</div>
                                                                    </div>
                                                                    <div class="chapitre-actions">
                                                                        <span class="chapitre-status <?php echo $chapitre['has_contenu'] ? 'status-complete' : 'status-incomplete'; ?>">
                                                                            <?php echo $chapitre['has_contenu'] ? '<i class="fas fa-check"></i> Complet' : '<i class="fas fa-times"></i> Incomplet'; ?>
                                                                        </span>
                                                                        <button class="action-btn" onclick="editChapter(<?php echo $chapitre['id']; ?>)">
                                                                            <i class="fas fa-edit"></i> Éditer
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <p>Aucun chapitre disponible pour ce module.</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div id="inscriptions-tab" class="tab-content">
                            <?php if (empty($inscriptions_en_attente)): ?>
                                <div class="empty-message">
                                    <i class="fas fa-check-circle fa-2x" style="color: var(--success); margin-bottom: 1rem;"></i>
                                    <h3>Aucune inscription en attente</h3>
                                    <p>Toutes les inscriptions ont été traitées.</p>
                                </div>
                            <?php else: ?>
                                <table class="inscription-table">
                                    <thead>
                                        <tr>
                                            <th>Stagiaire</th>
                                            <th>Formation</th>
                                            <th>Dates</th>
                                            <th>Prix</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inscriptions_en_attente as $inscription): ?>
                                            <tr>
                                                <td>
                                                    <div><?php echo htmlspecialchars($inscription['prenom_stagiaire'] . ' ' . $inscription['nom_stagiaire']); ?></div>
                                                    <div style="font-size: 0.75rem; color: var(--text-light);"><?php echo htmlspecialchars($inscription['email_stagiaire']); ?></div>
                                                    <?php if (!empty($inscription['tel'])): ?>
                                                        <div style="font-size: 0.75rem; color: var(--text-light);"><?php echo htmlspecialchars($inscription['tel']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($inscription['nom_formation']); ?></td>
                                                <td>
                                                    <div>Du <?php echo date('d/m/Y', strtotime($inscription['date_debut'])); ?></div>
                                                    <div>au <?php echo date('d/m/Y', strtotime($inscription['date_fin'])); ?></div>
                                                    <div style="font-size: 0.75rem; color: var(--text-light);"><?php echo htmlspecialchars($inscription['horaire']); ?></div>
                                                </td>
                                                <td><?php echo number_format($inscription['prix'], 2, ',', ' '); ?> €</td>
                                                <td>
                                                    <div class="action-btns">
                                                        <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir valider cette inscription ?');">
                                                            <input type="hidden" name="id_inscription" value="<?php echo $inscription['id_inscription']; ?>">
                                                            <button type="submit" name="valider_inscription" class="action-btn success">
                                                                <i class="fas fa-check"></i> Valider
                                                            </button>
                                                        </form>
                                                        <a href="#" class="action-btn secondary" onclick="showStudentDetails(<?php echo $inscription['id_stagiaire']; ?>)">
                                                            <i class="fas fa-user"></i> Profil
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <div style="text-align: center; margin-top: 1.5rem;">
                                    <button type="button" class="action-btn" onclick="refreshInscriptions()">
                                        <i class="fas fa-sync-alt"></i> Rafraîchir la liste
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="side-content">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-calendar-alt"></i>
                            <h2 class="card-title">Prochaines sessions</h2>
                        </div>
                        
                        <?php if (empty($prochaines_sessions)): ?>
                            <div class="empty-message">
                                <p>Aucune session à venir.</p>
                            </div>
                        <?php else: ?>
                            <div class="session-list">
                                <?php foreach ($prochaines_sessions as $session): ?>
                                    <?php 
                                        $places_totales = $session['places_disponibles'] ?? 20;
                                        $places_prises = $session['nombre_inscrits'] ?? 0;
                                        $pourcentage = $places_totales > 0 ? round(($places_prises / $places_totales) * 100) : 0;
                                    ?>
                                    <div class="session-item">
                                        <div class="session-title"><?php echo htmlspecialchars($session['nom_formation']); ?></div>
                                        <div class="session-details">
                                            <div>Du <?php echo date('d/m/Y', strtotime($session['date_debut'])); ?> au <?php echo date('d/m/Y', strtotime($session['date_fin'])); ?></div>
                                            <div><?php echo htmlspecialchars($session['horaire']); ?></div>
                                        </div>
                                        <div class="session-capacity">
                                            <span><?php echo $places_prises; ?></span>
                                            <div class="capacity-bar">
                                                <div class="capacity-fill" style="width: <?php echo $pourcentage; ?>%;"></div>
                                            </div>
                                            <span><?php echo $places_totales; ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-tasks"></i>
                            <h2 class="card-title">Actions rapides</h2>
                        </div>
                        
                        <div style="display: grid; gap: 1rem;">
                            <button class="action-btn" onclick="showTab('content-tab')">
                                <i class="fas fa-book"></i> Gérer les contenus
                            </button>
                            <button class="action-btn" onclick="showTab('inscriptions-tab')">
                                <i class="fas fa-user-check"></i> Valider les inscriptions
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal pour éditer un chapitre -->
    <div id="chapterModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('chapterModal')">&times;</span>
            <h2>Éditer le chapitre</h2>
            
            <form method="POST" action="" id="chapter-form">
                <input type="hidden" name="chapitre_id" id="chapitre_id" value="">
                
                <div class="form-group">
                    <label for="titre">Titre du chapitre*</label>
                    <input type="text" id="titre" name="titre" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="duree_estimee">Durée estimée (minutes)*</label>
                    <input type="number" id="duree_estimee" name="duree_estimee" class="form-control" min="1" value="30" required>
                </div>
                
                <div class="form-group">
                    <label for="contenu">Contenu du chapitre</label>
                    <textarea id="contenu" name="contenu" class="form-control"></textarea>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                    <button type="button" class="action-btn secondary" onclick="closeModal('chapterModal')">Annuler</button>
                    <button type="submit" name="save_chapter" class="action-btn">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>&copy; 2025 MFC - Tous droits réservés</p>
        </div>
    </footer>

    <script>
        // Gestion des onglets
        function showTab(tabId) {
            // Cacher tous les contenus d'onglet
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Désactiver tous les onglets
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Activer l'onglet et le contenu sélectionnés
            document.getElementById(tabId).classList.add('active');
            document.querySelector(`.tab[onclick="showTab('${tabId}')"]`).classList.add('active');
        }
        
        // Gestion des accordéons
        function toggleAccordion(id) {
            const content = document.getElementById(id);
            if (content.style.display === 'block') {
                content.style.display = 'none';
            } else {
                content.style.display = 'block';
            }
        }
        
        // Gestion des modules
        function toggleModule(id) {
            const content = document.getElementById(id);
            if (content.style.display === 'block') {
                content.style.display = 'none';
            } else {
                content.style.display = 'block';
            }
        }
        
        // Gestion des modales
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Édition d'un chapitre
        function editChapter(chapitreId) {
            // Requête AJAX pour obtenir les détails du chapitre
            fetch(`get-chapter-details.php?id=${chapitreId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    
                    const chapitre = data.chapitre;
                    
                    // Remplir le formulaire
                    document.getElementById('chapitre_id').value = chapitre.id_chapitre;
                    document.getElementById('titre').value = chapitre.titre;
                    document.getElementById('duree_estimee').value = chapitre.duree_estimee;
                    document.getElementById('contenu').value = chapitre.contenu || '';
                    
                    // Afficher la modale
                    document.getElementById('chapterModal').style.display = 'block';
                })
                .catch(error => {
                    alert('Erreur lors de la récupération des détails du chapitre: ' + error);
                });
        }
        
        // Affichage des détails d'un stagiaire
        function showStudentDetails(stagiaireId) {
            // Rediriger vers une page avec les détails du stagiaire (à implémenter plus tard)
            alert('Fonction de profil stagiaire à implémenter');
            
            // Idéalement, vous pourriez ouvrir une modale ou rediriger vers une page de détails du stagiaire
            // window.location.href = `formateur-detail-stagiaire.php?id=${stagiaireId}`;
        }
        
        // Rafraîchir la liste des inscriptions
        function refreshInscriptions() {
            window.location.reload();
        }
        
        // Fermer la modale si on clique en dehors
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        };
        
        // Créer le fichier get-chapter-details.php s'il n'existe pas
        <?php
        if (!file_exists('get-chapter-details.php')) {
            $chapter_details_handler = '<?php
session_start();
require_once "config.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "formateur") {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Non autorisé"]);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$chapitre_id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

if ($chapitre_id <= 0) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "ID de chapitre invalide"]);
    exit();
}

try {
    // Récupérer l\'ID du formateur
    $query = "SELECT id_formateur FROM formateur WHERE id_user = :id_user";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id_user", $_SESSION["user_id"]);
    $stmt->execute();
    $formateur_id = $stmt->fetch(PDO::FETCH_ASSOC)["id_formateur"] ?? 0;
    
    if ($formateur_id <= 0) {
        header("Content-Type: application/json");
        echo json_encode(["error" => "Formateur non trouvé"]);
        exit();
    }
    
    // Vérifier que le chapitre appartient à une formation assignée au formateur
    $check_query = "SELECT c.* 
                   FROM chapitre c 
                   JOIN module m ON c.id_module = m.id_module 
                   JOIN formation f ON m.id_formation = f.id_formation
                   JOIN session_formation sf ON f.id_formation = sf.id_formation
                   WHERE c.id_chapitre = :chapitre_id 
                   AND sf.id_formateur = :formateur_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":chapitre_id", $chapitre_id);
    $check_stmt->bindParam(":formateur_id", $formateur_id);
    $check_stmt->execute();
    
    if ($chapitre = $check_stmt->fetch(PDO::FETCH_ASSOC)) {
        header("Content-Type: application/json");
        echo json_encode(["chapitre" => $chapitre]);
    } else {
        header("Content-Type: application/json");
        echo json_encode(["error" => "Chapitre non trouvé ou non autorisé"]);
    }
} catch (PDOException $e) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Erreur de base de données: " . $e->getMessage()]);
}
?>';
            file_put_contents('get-chapter-details.php', $chapter_details_handler);
            echo "console.log('Fichier get-chapter-details.php créé avec succès.');";
        }
        ?>
    </script>
</body>
</html>