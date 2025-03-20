<?php
session_start();
require_once 'config.php';

// Vérification si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: espace-personnel.php");
    exit();
}

// Initialisation de la base de données
$database = new Database();
$db = $database->getConnection();
$user = new User($db);

// Récupération des formations de l'utilisateur
$userFormations = $user->getUserFormations($_SESSION['user_id']);

// Récupération des infos du stagiaire (téléphone et adresse)
$user_phone = '';
$user_address = '';
$stagiaire_id = 0;
try {
    $query = "SELECT id_stagiaire, tel, adresse FROM stagiaire WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $_SESSION['user_email']);
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $user_phone = $row['tel'];
        $user_address = $row['adresse'];
        $stagiaire_id = $row['id_stagiaire'];
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des infos du stagiaire: " . $e->getMessage());
}

// Récupération de l'activité récente
$activite_recente = [];
try {
    if ($stagiaire_id > 0) {
        $query = "SELECT ps.id_progression, ps.statut, ps.date_debut, ps.date_fin, ps.updated_at,
                ch.titre as chapitre_titre, m.titre as module_titre, f.nom_formation
                FROM progression_stagiaire ps
                JOIN chapitre ch ON ps.id_chapitre = ch.id_chapitre
                JOIN module m ON ch.id_module = m.id_module
                JOIN formation f ON m.id_formation = f.id_formation
                WHERE ps.id_stagiaire = :stagiaire_id
                ORDER BY ps.updated_at DESC
                LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':stagiaire_id', $stagiaire_id);
        $stmt->execute();
        $activite_recente = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération de l'activité récente: " . $e->getMessage());
}

// Traitement de la mise à jour du profil
$profileUpdateMessage = '';
$profileError = '';
if (isset($_POST['update_profile'])) {
    $fullname = htmlspecialchars($_POST['fullname']);
    $email = htmlspecialchars($_POST['email']);
    $telephone = htmlspecialchars($_POST['telephone'] ?? '');
    $adresse = htmlspecialchars($_POST['adresse'] ?? '');
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Vérification du mot de passe actuel (obligatoire)
    if (empty($current_password)) {
        $profileError = "Le mot de passe actuel est requis pour modifier le profil.";
    }
    // Vérifier si l'email existe déjà (si changé)
    else if ($email !== $_SESSION['user_email'] && $user->emailExists($email)) {
        $profileError = "Cet email est déjà utilisé par un autre compte.";
    }
    // Vérifier que le mot de passe actuel est correct
    else if (!$user->verifyPassword($_SESSION['user_id'], $current_password)) {
        $profileError = "Le mot de passe actuel est incorrect.";
    }
    // Vérifier que les nouveaux mots de passe correspondent
    else if (!empty($new_password) && $new_password !== $confirm_password) {
        $profileError = "Les nouveaux mots de passe ne correspondent pas.";
    }
    // Tout est valide, mettre à jour le profil
    else {
        $updateResult = $user->updateProfile(
            $_SESSION['user_id'],
            $fullname,
            $email,
            !empty($new_password) ? $new_password : null
        );
        
        if ($updateResult) {
            // Mettre à jour la session avec les nouvelles valeurs
            $_SESSION['user_name'] = $fullname;
            $_SESSION['user_email'] = $email;
            
            // Mettre à jour le téléphone et l'adresse dans la table stagiaire
            try {
                $query = "UPDATE stagiaire SET tel = :tel, adresse = :adresse WHERE email = :email";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':tel', $telephone);
                $stmt->bindParam(':adresse', $adresse);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                // Mettre à jour les variables locales
                $user_phone = $telephone;
                $user_address = $adresse;
            } catch (PDOException $e) {
                error_log("Erreur lors de la mise à jour du téléphone et de l'adresse: " . $e->getMessage());
            }
            
            $profileUpdateMessage = 'Profil mis à jour avec succès!';
        } else {
            $profileError = "Une erreur est survenue lors de la mise à jour du profil.";
        }
    }
}

// Fonction pour calculer le temps relatif
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    
    $string = array(
        'y' => 'an',
        'm' => 'mois',
        'w' => 'semaine',
        'd' => 'jour',
        'h' => 'heure',
        'i' => 'minute',
        's' => 'seconde',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 && $k != 'm' ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'il y a ' . implode(', ', $string) : 'à l\'instant';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MFC - Tableau de Bord</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style-complementaire.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <style>
        .dashboard-container {
            max-width: 1280px;
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .dashboard-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            transition: transform 0.3s;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
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

        .progress-bar {
            width: 100%;
            height: 0.5rem;
            background: var(--gray-100);
            border-radius: 0.25rem;
            margin: 1rem 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary-color);
            width: 0%;
            transition: width 1s ease-in-out;
        }

        .user-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
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
        }

        .action-btn:hover {
            background: var(--secondary-color);
        }

        .logout-btn {
            background: var(--error);
        }

        .logout-btn:hover {
            background: #dc2626;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Styles pour les modales */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 100;
            overflow-y: auto;
        }
        
        .modal-content {
            background: var(--white);
            margin: 5% auto;
            padding: 2rem;
            border-radius: 1rem;
            max-width: 800px;
            box-shadow: var(--shadow-lg);
            position: relative;
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-50px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--gray-200);
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .modal-header h2 {
            color: var(--secondary-color);
            margin: 0;
        }
        
        .close {
            color: var(--text-light);
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .close:hover {
            color: var(--error);
        }
        
        .modal-body {
            margin-bottom: 1.5rem;
        }
        
        .modal-footer {
            border-top: 1px solid var(--gray-200);
            padding-top: 1rem;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        /* Styles pour l'interface des cours */
        .course-info {
            margin-bottom: 1rem; 
            padding: 1rem; 
            background: var(--gray-50); 
            border-radius: 0.5rem;
        }
        
        .course-module {
            margin-bottom: 2rem;
            background: var(--gray-50);
            border-radius: 0.5rem;
            padding: 1.5rem;
        }
        
        .course-module h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .course-chapter {
            padding: 1rem;
            background: var(--white);
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
            cursor: pointer;
        }
        
        .course-chapter:hover {
            background: var(--gray-100);
        }
        
        .chapter-status {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .status-completed {
            background: var(--success);
            color: var(--white);
        }
        
        .status-in-progress {
            background: var(--primary-color);
            color: var(--white);
        }
        
        .status-not-started {
            background: var(--gray-200);
            color: var(--text-light);
        }
        
        /* Styles pour le formulaire de modification du profil */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-submit {
            background: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .form-submit:hover {
            background: var(--secondary-color);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .alert-info {
            background-color: #e0f2fe;
            color: #075985;
        }

        /* Onglets dans les formations */
        .formation-tabs {
            display: flex;
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 1rem;
        }

        .formation-tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            border-bottom: 2px solid transparent;
        }

        .formation-tab:hover {
            color: var(--primary-color);
        }

        .formation-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .formation-tab-content {
            display: none;
        }

        .formation-tab-content.active {
            display: block;
        }

        /* Calendrier des cours */
        .calendar-container {
            background: var(--white);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .calendar-navigation {
            display: flex;
            gap: 0.5rem;
        }

        .calendar-nav-btn {
            background: var(--gray-100);
            border: none;
            border-radius: 0.25rem;
            padding: 0.5rem;
            cursor: pointer;
            transition: background 0.3s;
        }

        .calendar-nav-btn:hover {
            background: var(--gray-200);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
        }

        .calendar-day-name {
            text-align: center;
            font-weight: 500;
            color: var(--text-light);
            padding-bottom: 0.5rem;
        }

        .calendar-day {
            background: var(--gray-50);
            border-radius: 0.25rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            position: relative;
        }

        .calendar-day:hover {
            background: var(--gray-100);
        }

        .calendar-day.today {
            background: var(--primary-color);
            color: var(--white);
        }

        .calendar-day.has-event::after {
            content: '';
            position: absolute;
            bottom: 0.25rem;
            width: 0.5rem;
            height: 0.5rem;
            background: var(--accent-color);
            border-radius: 50%;
        }

        .calendar-day.other-month {
            color: var(--gray-300);
        }
        
        /* Styles pour l'activité récente */
        .activity-item {
            padding: 0.75rem;
            border-left: 3px solid var(--primary-color);
            background: var(--gray-50);
            margin-bottom: 0.75rem;
            border-radius: 0 0.25rem 0.25rem 0;
        }
        
        .activity-item p {
            margin: 0;
            font-weight: 500;
        }
        
        .activity-item small {
            color: var(--text-light);
            font-size: 0.75rem;
        }
        
        .activity-item.completed {
            border-left-color: var(--success);
        }
        
        .activity-item.in-progress {
            border-left-color: var(--primary-color);
        }
        
        .activity-item.started {
            border-left-color: var(--accent-color);
        }
        
        /* Styles spécifiques pour les formations */
        .formation-progress {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .formation-progress:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .no-formation {
            text-align: center;
            padding: 2rem;
            background: var(--gray-50);
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .welcome-btn {
            display: inline-block;
            background: var(--white);
            color: var(--primary-color);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 9999px;
            text-decoration: none;
            margin-top: 1.5rem;
            transition: all 0.3s;
            border: 2px solid var(--white);
        }
        
        .welcome-btn:hover {
            background: transparent;
            color: var(--white);
        }
        
        /* Styles pour la mise en évidence des champs obligatoires */
        .required-field::after {
            content: " *";
            color: var(--error);
        }
        
        /* Message pour les champs obligatoires */
        .required-message {
            font-size: 0.85rem;
            color: var(--error);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <ul>
                <li class="nav-logo">MFC</li>
                <li><a href="index.php" class="action-btn">
                    <i class="fas fa-home"></i> Accueil
                </a></li>
                <li><a href="formation.php" class="action-btn">
                    <i class="fas fa-graduation-cap"></i> Formations
                </a></li>
                <li><a href="#" class="action-btn logout-btn" onclick="document.getElementById('logout-form').submit();">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a></li>
            </ul>
        </nav>
    </header>

    <form id="logout-form" action="logout.php" method="POST" style="display: none;"></form>

    <main style="margin-top: 5rem;">
        <div class="dashboard-container">
            <div class="welcome-section">
                <h1>Bienvenue, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
                <p>Accédez à vos formations et progressez à votre rythme</p>
                <?php if (empty($userFormations)): ?>
                <a href="formation.php" class="welcome-btn">
                    <i class="fas fa-plus"></i> Découvrir nos formations
                </a>
                <?php endif; ?>
            </div>

            <div class="dashboard-grid">
                <!-- Formations en cours -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="fas fa-graduation-cap"></i>
                        <h2 class="card-title">Mes Formations</h2>
                    </div>
                    
                    <div class="formation-tabs">
                        <div class="formation-tab active" onclick="showTab('in-progress')">En cours</div>
                        <div class="formation-tab" onclick="showTab('upcoming')">À venir</div>
                        <div class="formation-tab" onclick="showTab('completed')">Terminées</div>
                    </div>
                    
                    <div id="in-progress-tab" class="formation-tab-content active">
                        <?php if (empty($userFormations)): ?>
                            <div class="no-formation">
                                <p>Vous n'êtes inscrit à aucune formation.</p>
                                <div class="user-actions">
                                    <a href="formation.php" class="action-btn">
                                        <i class="fas fa-plus"></i> Découvrir nos formations
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php 
                            $inProgressFound = false;
                            foreach($userFormations as $index => $formation): 
                                // Formations en cours (progression entre 1% et 99%)
                                // Vérifiez également si la date de début est passée et la date de fin est future
                                $dateDebut = strtotime($formation['date_debut']);
                                $dateFin = strtotime($formation['date_fin']);
                                $now = time();
                                
                                $dateEnCours = ($dateDebut <= $now && $now <= $dateFin);
                                $progressionEnCours = ($formation['progression'] > 0 && $formation['progression'] < 100);
                                
                                if ($dateEnCours || $progressionEnCours):
                                    $inProgressFound = true;
                            ?>
                                <div class="formation-progress">
                                    <h3 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($formation['nom_formation']); ?></h3>
                                    <p style="font-size: 0.9rem; color: var(--text-light);">
                                        <i class="far fa-clock"></i> <?php echo htmlspecialchars($formation['horaire']); ?>
                                    </p>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo min(100, max(1, $formation['progression'])); ?>%;"></div>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                                        <span>Début: <?php echo date('d/m/Y', strtotime($formation['date_debut'])); ?></span>
                                        <span>Fin: <?php echo date('d/m/Y', strtotime($formation['date_fin'])); ?></span>
                                    </div>
                                    <p style="margin-top: 0.5rem;">
                                        <?php echo round(min(100, max(1, $formation['progression']))); ?>% complété
                                    </p>
                                    <div class="user-actions">
                                        <a href="#" class="action-btn" onclick="openCourseModal('<?php echo htmlspecialchars($formation['nom_formation']); ?>', <?php echo $index; ?>)">
                                            <i class="fas fa-play"></i> Accéder au cours
                                        </a>
                                        <a href="#" class="action-btn" onclick="showCalendar(<?php echo $index; ?>)">
                                            <i class="fas fa-calendar-alt"></i> Planning
                                        </a>
                                    </div>
                                </div>
                            <?php 
                                endif; 
                            endforeach; 
                            
                            if (!$inProgressFound):
                            ?>
                                <div class="no-formation">
                                    <p>Vous n'avez pas de formation en cours actuellement.</p>
                                    <div class="user-actions">
                                        <a href="#" class="action-btn" onclick="switchTab('upcoming')">
                                            <i class="fas fa-calendar-alt"></i> Voir mes formations à venir
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div id="upcoming-tab" class="formation-tab-content">
                        <?php 
                        $upcomingFound = false;
                        foreach($userFormations as $index => $formation): 
                            // Formations à venir (progression = 0%)
                            if ($formation['progression'] == 0):
                                $upcomingFound = true;
                        ?>
                            <div class="formation-progress">
                                <h3 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($formation['nom_formation']); ?></h3>
                                <p style="font-size: 0.9rem; color: var(--text-light);">
                                    <i class="far fa-clock"></i> <?php echo htmlspecialchars($formation['horaire']); ?>
                                </p>
                                <p style="font-size: 0.9rem; margin: 0.5rem 0;">
                                    <i class="fas fa-calendar-alt"></i> Débute le <?php echo date('d/m/Y', strtotime($formation['date_debut'])); ?>
                                </p>
                                <div class="user-actions">
                                    <a href="#" class="action-btn" onclick="showCalendar(<?php echo $index; ?>)">
                                        <i class="fas fa-calendar-alt"></i> Planning
                                    </a>
                                </div>
                            </div>
                        <?php 
                            endif; 
                        endforeach; 
                        
                        if (!$upcomingFound):
                        ?>
                            <div class="no-formation">
                                <p>Vous n'avez pas de formation à venir.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div id="completed-tab" class="formation-tab-content">
                        <?php 
                        $completedFound = false;
                        foreach($userFormations as $index => $formation): 
                            // Formations terminées (progression = 100%)
                            if ($formation['progression'] >= 100):
                                $completedFound = true;
                        ?>
                            <div class="formation-progress">
                                <h3 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($formation['nom_formation']); ?></h3>
                                <p style="font-size: 0.9rem; color: var(--text-light);">
                                    <i class="far fa-clock"></i> <?php echo htmlspecialchars($formation['horaire']); ?>
                                </p>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 100%;"></div>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                                    <span>Début: <?php echo date('d/m/Y', strtotime($formation['date_debut'])); ?></span>
                                    <span>Fin: <?php echo date('d/m/Y', strtotime($formation['date_fin'])); ?></span>
                                </div>
                                <p style="margin-top: 0.5rem;">
                                    <i class="fas fa-check-circle" style="color: var(--success);"></i> Formation terminée
                                </p>
                                <div class="user-actions">
                                    <a href="#" class="action-btn" onclick="openCourseModal('<?php echo htmlspecialchars($formation['nom_formation']); ?>', <?php echo $index; ?>)">
                                        <i class="fas fa-book"></i> Revoir le cours
                                    </a>
                                    <a href="#" class="action-btn">
                                        <i class="fas fa-certificate"></i> Certificat
                                    </a>
                                </div>
                            </div>
                        <?php 
                            endif; 
                        endforeach; 
                        
                        if (!$completedFound):
                        ?>
                            <div class="no-formation">
                                <p>Vous n'avez pas encore terminé de formation.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Profil et informations personnelles -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="fas fa-user"></i>
                        <h2 class="card-title">Mon Profil</h2>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <p><strong>Nom :</strong> <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                        <p><strong>Email :</strong> <?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Non renseigné'); ?></p>
                        <p><strong>Téléphone :</strong> <?php echo htmlspecialchars($user_phone ? $user_phone : 'Non renseigné'); ?></p>
                        <p><strong>Adresse :</strong> <?php echo htmlspecialchars($user_address ? $user_address : 'Non renseignée'); ?></p>
                    </div>
                    <div class="user-actions">
                        <a href="#" class="action-btn" onclick="openProfileModal()">
                            <i class="fas fa-edit"></i> Modifier le profil
                        </a>
                    </div>
                    
                    <h3 style="margin-top: 2rem; margin-bottom: 1rem;">Activité récente</h3>
                    <div id="recent-activity">
                        <?php if (empty($activite_recente)): ?>
                            <p>Aucune activité récente.</p>
                        <?php else: ?>
                            <?php foreach ($activite_recente as $activite): 
                                $activity_class = 'started';
                                $activity_icon = 'fa-book-reader';
                                $activity_text = 'Chapitre commencé';
                                
                                if ($activite['statut'] == 'termine') {
                                    $activity_class = 'completed';
                                    $activity_icon = 'fa-check-circle';
                                    $activity_text = 'Chapitre terminé';
                                } elseif ($activite['statut'] == 'en_cours') {
                                    $activity_class = 'in-progress';
                                    $activity_icon = 'fa-spinner';
                                    $activity_text = 'Chapitre en cours';
                                }
                                
                                $time_ago = time_elapsed_string($activite['updated_at']);
                            ?>
                                <div class="activity-item <?php echo $activity_class; ?>">
                                    <p><i class="fas <?php echo $activity_icon; ?>"></i> <?php echo $activity_text; ?></p>
                                    <small><?php echo htmlspecialchars($activite['chapitre_titre']); ?> - <?php echo htmlspecialchars($activite['nom_formation']); ?> - <?php echo $time_ago; ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal pour accéder au cours -->
    <div id="courseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="courseModalTitle">Contenu du cours</h2>
                <span class="close" onclick="closeModal('courseModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="course-info">
                    <p><strong>Progression globale:</strong> <span id="courseProgressPercent">0</span>%</p>
                    <div class="progress-bar" style="margin: 0.5rem 0;">
                        <div id="courseProgressBar" class="progress-fill" style="width: 0%;"></div>
                    </div>
                    <p><strong>Période:</strong> <span id="courseDuration"></span></p>
                </div>
                
                <div class="course-content">
                    <!-- Le contenu sera chargé dynamiquement en fonction de la formation -->
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p>Chargement du contenu du cours...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closeModal('courseModal')" class="action-btn">Fermer</button>
            </div>
        </div>
    </div>

    <!-- Modal pour modifier le profil -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Modifier mon profil</h2>
                <span class="close" onclick="closeModal('profileModal')">&times;</span>
            </div>
            <div class="modal-body">
                <?php if($profileUpdateMessage): ?>
                    <div class="alert alert-success">
                        <?php echo $profileUpdateMessage; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($profileError): ?>
                    <div class="alert alert-error">
                        <?php echo $profileError; ?>
                    </div>
                <?php endif; ?>
                
                <div class="required-message">Les champs marqués d'un astérisque (*) sont obligatoires</div>
                
                <form method="POST" action="" autocomplete="off" id="profile-form">
                    <div class="form-group">
                        <label for="fullname" class="required-field">Nom complet</label>
                        <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="required-field">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone" value="<?php echo htmlspecialchars($user_phone); ?>" placeholder="Ex: 06 12 34 56 78" autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="adresse">Adresse</label>
                        <input type="text" id="adresse" name="adresse" value="<?php echo htmlspecialchars($user_address); ?>" placeholder="Votre adresse complète" autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="current_password" class="required-field">Mot de passe actuel</label>
                        <input type="password" id="current_password" name="current_password" placeholder="Obligatoire pour confirmer les modifications" autocomplete="new-password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Nouveau mot de passe (optionnel)</label>
                        <input type="password" id="new_password" name="new_password" placeholder="Laissez vide pour conserver le mot de passe actuel" autocomplete="new-password">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirmez votre nouveau mot de passe" autocomplete="new-password">
                    </div>
                    
                    <button type="submit" name="update_profile" class="form-submit">Mettre à jour mon profil</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal pour afficher le calendrier -->
    <div id="calendarModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="calendarTitle">Planning de formation</h2>
                <span class="close" onclick="closeModal('calendarModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="calendar-container">
                    <div class="calendar-header">
                        <h3 id="calendar-month-year">Juin 2023</h3>
                        <div class="calendar-navigation">
                            <button class="calendar-nav-btn" onclick="previousMonth()">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="calendar-nav-btn" onclick="currentMonth()">
                                Aujourd'hui
                            </button>
                            <button class="calendar-nav-btn" onclick="nextMonth()">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    <div class="calendar-grid" id="calendar-days-names">
                        <div class="calendar-day-name">Lun</div>
                        <div class="calendar-day-name">Mar</div>
                        <div class="calendar-day-name">Mer</div>
                        <div class="calendar-day-name">Jeu</div>
                        <div class="calendar-day-name">Ven</div>
                        <div class="calendar-day-name">Sam</div>
                        <div class="calendar-day-name">Dim</div>
                    </div>
                    <div class="calendar-grid" id="calendar-days">
                        <!-- Les jours du calendrier seront ajoutés ici par JavaScript -->
                    </div>
                </div>
                
                <div id="calendar-events" style="margin-top: 1.5rem;">
                    <h3>Événements du jour</h3>
                    <div id="day-events" style="margin-top: 0.5rem;">
                        <!-- Les événements du jour sélectionné seront affichés ici -->
                        <p>Aucun événement prévu pour ce jour.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closeModal('calendarModal')" class="action-btn">Fermer</button>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>&copy; 2024 MFC - Tous droits réservés</p>
        </div>
    </footer>

    <script>
        // Animation des barres de progression au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 300);
            });
            
            // Validation personnalisée pour le formulaire de profil
            const profileForm = document.getElementById('profile-form');
            if (profileForm) {
                profileForm.addEventListener('submit', function(event) {
                    const currentPassword = document.getElementById('current_password').value;
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    // Vérifier que le mot de passe actuel est rempli
                    if (!currentPassword) {
                        event.preventDefault();
                        alert('Le mot de passe actuel est obligatoire pour modifier votre profil.');
                        return false;
                    }
                    
                    // Vérifier que les nouveaux mots de passe correspondent
                    if (newPassword && newPassword !== confirmPassword) {
                        event.preventDefault();
                        alert('Les nouveaux mots de passe ne correspondent pas.');
                        return false;
                    }
                    
                    return true;
                });
            }
        });
        
        // Fonctions pour gérer les onglets des formations
        function showTab(tabName) {
            // Désactiver tous les onglets
            document.querySelectorAll('.formation-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Désactiver tous les contenus d'onglet
            document.querySelectorAll('.formation-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Activer l'onglet et le contenu sélectionnés
            document.querySelector(`.formation-tab[onclick="showTab('${tabName}')"]`).classList.add('active');
            document.getElementById(`${tabName}-tab`).classList.add('active');
        }
        
        // Fonction pour ouvrir la modale du cours
        function openCourseModal(formationName, formationIndex) {
            // Récupérer les données de la formation sélectionnée
            const formations = <?php echo json_encode($userFormations); ?>;
            const formation = formations[formationIndex];
            
            document.getElementById('courseModalTitle').textContent = 'Contenu du cours - ' + formationName;
            
            // Mettre à jour les informations du cours
            document.getElementById('courseProgressPercent').textContent = formation.progression;
            document.getElementById('courseProgressBar').style.width = formation.progression + '%';
            document.getElementById('courseDuration').textContent = `Du ${formatDate(formation.date_debut)} au ${formatDate(formation.date_fin)} - ${formation.horaire}`;
            
            // Mettre à jour le contenu du modal avec les modules spécifiques à cette formation
            loadCourseContent(formation);
            
            document.getElementById('courseModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        // Fonction pour charger le contenu du cours
        function loadCourseContent(formation) {
            const courseContent = document.querySelector('.course-content');
            
            // Afficher l'indicateur de chargement
            courseContent.innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p>Chargement du contenu du cours...</p>
                </div>
            `;
            
            // Récupérer les modules de la formation via AJAX
            fetch(`get-modules.php?formation_id=${formation.id_formation}&stagiaire_id=${formation.id_stagiaire}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        courseContent.innerHTML = `<div class="alert alert-error">${data.error}</div>`;
                        return;
                    }
                    
                    // Construire l'HTML des modules
                    let modulesHTML = '';
                    
                    if (data.modules && data.modules.length > 0) {
                        // Créer un fragment de document pour optimiser les performances
                        const fragment = document.createDocumentFragment();
                        const moduleContainer = document.createElement('div');
                        fragment.appendChild(moduleContainer);
                        
                        data.modules.forEach(module => {
                            const moduleElement = document.createElement('div');
                            moduleElement.className = 'course-module';
                            moduleElement.innerHTML = `
                                <h3><i class="${module.icon}"></i> ${module.title}</h3>
                                <div class="chapters-list" id="module-${module.id}">
                                    <div style="text-align: center; padding: 1rem;">
                                        <i class="fas fa-spinner fa-spin"></i> Chargement des chapitres...
                                    </div>
                                </div>
                            `;
                            moduleContainer.appendChild(moduleElement);
                            
                            // Charger les chapitres de ce module après un court délai
                            setTimeout(() => {
                                loadChapters(module.id, formation.id_formation, formation.id_stagiaire);
                            }, 100);
                        });
                        
                        // Vider et remplir le conteneur de cours
                        courseContent.innerHTML = '';
                        courseContent.appendChild(fragment);
                    } else {
                        courseContent.innerHTML = `<div class="alert alert-info">Aucun module disponible pour cette formation.</div>`;
                    }
                })
                .catch(error => {
                    courseContent.innerHTML = `<div class="alert alert-error">Erreur de chargement des modules: ${error.message}</div>`;
                });
        }
        
        // Fonction pour charger les chapitres d'un module
        function loadChapters(moduleId, formationId, stagiaireId) {
            const chaptersContainer = document.getElementById(`module-${moduleId}`);
            
            if (!chaptersContainer) return;
            
            fetch(`get-chapters.php?module_id=${moduleId}&stagiaire_id=${stagiaireId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        chaptersContainer.innerHTML = `<div class="alert alert-error">${data.error}</div>`;
                        return;
                    }
                    
                    // Construire l'HTML des chapitres
                    if (data.chapters && data.chapters.length > 0) {
                        // Créer un fragment de document pour optimiser les performances
                        const fragment = document.createDocumentFragment();
                        
                        data.chapters.forEach(chapter => {
                            let statusIcon, statusClass, statusText;
                            
                            switch(chapter.status) {
                                case 'completed':
                                    statusIcon = 'fa-check';
                                    statusClass = 'status-completed';
                                    statusText = 'Terminé';
                                    break;
                                case 'in-progress':
                                    statusIcon = 'fa-spinner';
                                    statusClass = 'status-in-progress';
                                    statusText = 'En cours';
                                    break;
                                default:
                                    statusIcon = 'fa-circle';
                                    statusClass = 'status-not-started';
                                    statusText = 'Non commencé';
                            }
                            
                            const chapterElement = document.createElement('div');
                            chapterElement.className = 'course-chapter';
                            chapterElement.onclick = function() { openChapterPage(chapter.id, moduleId, formationId); };
                            chapterElement.innerHTML = `
                                <span>${chapter.title}</span>
                                <span class="chapter-status ${statusClass}">
                                    <i class="fas ${statusIcon}"></i> ${statusText}
                                </span>
                            `;
                            fragment.appendChild(chapterElement);
                        });
                        
                        // Vider et remplir le conteneur de chapitres
                        chaptersContainer.innerHTML = '';
                        chaptersContainer.appendChild(fragment);
                    } else {
                        chaptersContainer.innerHTML = `<p>Aucun chapitre disponible pour ce module.</p>`;
                    }
                })
                .catch(error => {
                    chaptersContainer.innerHTML = `<div class="alert alert-error">Erreur de chargement des chapitres: ${error.message}</div>`;
                });
        }
        
        // Fonction pour ouvrir la page d'un chapitre
        function openChapterPage(chapterId, moduleId, formationId) {
            window.location.href = `course-chapter.php?chapter_id=${chapterId}&module_id=${moduleId}&formation_id=${formationId}`;
        }
        
        // Fonction pour formater une date
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR');
        }
        
        // Fonction pour ouvrir la modale du profil
        function openProfileModal() {
            document.getElementById('profileModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        // Fonctions pour gérer le calendrier
        let currentDate = new Date();
        let selectedFormationIndex = 0;
        
        function showCalendar(formationIndex) {
            selectedFormationIndex = formationIndex;
            const formations = <?php echo json_encode($userFormations); ?>;
            const formation = formations[formationIndex];
            
            document.getElementById('calendarTitle').textContent = `Planning - ${formation.nom_formation}`;
            
            currentDate = new Date();
            updateCalendar();
            
            document.getElementById('calendarModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function updateCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            // Mettre à jour l'en-tête du calendrier
            const monthNames = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
            document.getElementById('calendar-month-year').textContent = `${monthNames[month]} ${year}`;
            
            // Obtenir le premier jour du mois
            const firstDay = new Date(year, month, 1);
            // Obtenir le dernier jour du mois
            const lastDay = new Date(year, month + 1, 0);
            
            // Jour de la semaine du premier jour (0 = Dimanche, 1 = Lundi, etc.)
            let firstDayOfWeek = firstDay.getDay();
            if (firstDayOfWeek === 0) firstDayOfWeek = 7; // Convertir Dimanche (0) en 7
            
            // Nombre total de jours à afficher (jours du mois précédent + jours du mois + jours du mois suivant)
            const totalDays = 42; // 6 semaines x 7 jours
            
            // Générer les cellules du calendrier
            let calendarHTML = '';
            
            // Jours du mois précédent
            const prevMonth = new Date(year, month, 0);
            const prevMonthDays = prevMonth.getDate();
            
            for (let i = firstDayOfWeek - 1; i > 0; i--) {
                const day = prevMonthDays - i + 1;
                calendarHTML += `<div class="calendar-day other-month">${day}</div>`;
            }
            
            // Jours du mois actuel
            const today = new Date();
            const formations = <?php echo json_encode($userFormations); ?>;
            const formation = formations[selectedFormationIndex];
            const formationStart = new Date(formation.date_debut);
            const formationEnd = new Date(formation.date_fin);
            
            for (let i = 1; i <= lastDay.getDate(); i++) {
                const date = new Date(year, month, i);
                let classNames = 'calendar-day';
                
                // Vérifier si c'est aujourd'hui
                if (date.getDate() === today.getDate() && date.getMonth() === today.getMonth() && date.getFullYear() === today.getFullYear()) {
                    classNames += ' today';
                }
                
                // Vérifier si le jour est dans la période de formation
                if (date >= formationStart && date <= formationEnd) {
                    classNames += ' has-event';
                }
                
                calendarHTML += `<div class="${classNames}" onclick="selectDay(${i}, ${month}, ${year})">${i}</div>`;
            }
            
            // Jours du mois suivant
            const daysFromPrevMonth = firstDayOfWeek - 1;
            const daysFromCurrentMonth = lastDay.getDate();
            const remainingCells = totalDays - daysFromPrevMonth - daysFromCurrentMonth;
            
            for (let i = 1; i <= remainingCells; i++) {
                calendarHTML += `<div class="calendar-day other-month">${i}</div>`;
            }
            
            document.getElementById('calendar-days').innerHTML = calendarHTML;
        }
        
        function previousMonth() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            updateCalendar();
        }
        
        function nextMonth() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            updateCalendar();
        }
        
        function currentMonth() {
            currentDate = new Date();
            updateCalendar();
        }
        
        function selectDay(day, month, year) {
            const selectedDate = new Date(year, month, day);
            const dayOfWeek = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
            const formations = <?php echo json_encode($userFormations); ?>;
            const formation = formations[selectedFormationIndex];
            
            // Formater la date en français
            const formattedDate = `${day} ${['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'][month]} ${year}`;
            
            // Vérifier si la date est dans la période de formation
            const formationStart = new Date(formation.date_debut);
            const formationEnd = new Date(formation.date_fin);
            
            let eventsHTML = '';
            
            if (selectedDate >= formationStart && selectedDate <= formationEnd) {
                // Afficher les événements du jour
                const weekday = dayOfWeek[selectedDate.getDay()];
                
                if (formation.horaire === '9h-12h') {
                    eventsHTML = `
                        <div style="background: var(--gray-50); padding: 1rem; border-radius: 0.5rem; margin-bottom: 0.5rem;">
                            <p style="margin: 0; font-weight: 500;">${weekday} ${formattedDate}</p>
                            <p style="margin: 0.5rem 0;">
                                <i class="fas fa-clock"></i> 9h - 12h : Formation ${formation.nom_formation}
                            </p>
                        </div>
                    `;
                } else if (formation.horaire === '14h-17h') {
                    eventsHTML = `
                        <div style="background: var(--gray-50); padding: 1rem; border-radius: 0.5rem; margin-bottom: 0.5rem;">
                            <p style="margin: 0; font-weight: 500;">${weekday} ${formattedDate}</p>
                            <p style="margin: 0.5rem 0;">
                                <i class="fas fa-clock"></i> 14h - 17h : Formation ${formation.nom_formation}
                            </p>
                        </div>
                    `;
                } else {
                    eventsHTML = `
                        <div style="background: var(--gray-50); padding: 1rem; border-radius: 0.5rem; margin-bottom: 0.5rem;">
                            <p style="margin: 0; font-weight: 500;">${weekday} ${formattedDate}</p>
                            <p style="margin: 0.5rem 0;">
                                <i class="fas fa-clock"></i> 9h - 17h : Formation ${formation.nom_formation}
                            </p>
                        </div>
                    `;
                }
            } else {
                eventsHTML = `<p>Aucun événement prévu pour le ${formattedDate}.</p>`;
            }
            
            document.getElementById('day-events').innerHTML = eventsHTML;
        }
        
        // Fonction pour fermer une modale
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Fermer les modales en cliquant en dehors
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // Fermer les modales avec la touche Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
            }
        });
    </script>
</body>
</html>