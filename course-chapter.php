<?php
session_start();
require_once 'config.php';

// Vérification si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: espace-personnel.php");
    exit();
}

// Récupération des paramètres
$chapter_id = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;
$module_id = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;
$formation_id = isset($_GET['formation_id']) ? intval($_GET['formation_id']) : 0;

if (!$chapter_id || !$module_id || !$formation_id) {
    header("Location: dashboard.php");
    exit();
}

// Initialisation de la base de données
$database = new Database();
$db = $database->getConnection();
$user = new User($db);

// Récupération des informations du stagiaire
$stagiaire_id = 0;
try {
    $query = "SELECT s.id_stagiaire, s.email, s.tel, s.adresse
              FROM stagiaire s
              JOIN users u ON s.email = u.email
              WHERE u.id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stagiaire_id = $row['id_stagiaire'];
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération du stagiaire: " . $e->getMessage());
}

// Si le chapitre est marqué comme terminé, mettre à jour son statut
if (isset($_POST['mark_completed']) && $stagiaire_id) {
    $user->updateChapterStatus($stagiaire_id, $chapter_id, 'termine');
    // Redirection pour éviter la soumission multiple du formulaire
    header("Location: course-chapter.php?chapter_id=$chapter_id&module_id=$module_id&formation_id=$formation_id&status=completed");
    exit();
}

// Si le chapitre est marqué comme en cours, mettre à jour son statut
if (isset($_GET['start']) && $stagiaire_id) {
    $user->updateChapterStatus($stagiaire_id, $chapter_id, 'en_cours');
}

// Récupération des informations du chapitre
$chapter = null;
$current_status = 'non_commence';
try {
    $query = "SELECT c.*, m.titre as module_titre, m.icone as module_icon, f.nom_formation
              FROM chapitre c
              JOIN module m ON c.id_module = m.id_module
              JOIN formation f ON m.id_formation = f.id_formation
              WHERE c.id_chapitre = :chapter_id AND m.id_module = :module_id AND f.id_formation = :formation_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':chapter_id', $chapter_id);
    $stmt->bindParam(':module_id', $module_id);
    $stmt->bindParam(':formation_id', $formation_id);
    $stmt->execute();
    $chapter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$chapter) {
        header("Location: dashboard.php");
        exit();
    }
    
    // Récupération du statut actuel
    if ($stagiaire_id) {
        $status_query = "SELECT statut FROM progression_stagiaire 
                       WHERE id_stagiaire = :stagiaire_id AND id_chapitre = :chapter_id";
        $status_stmt = $db->prepare($status_query);
        $status_stmt->bindParam(':stagiaire_id', $stagiaire_id);
        $status_stmt->bindParam(':chapter_id', $chapter_id);
        $status_stmt->execute();
        if ($status_row = $status_stmt->fetch(PDO::FETCH_ASSOC)) {
            $current_status = $status_row['statut'];
        }
    }
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération du chapitre: " . $e->getMessage());
    header("Location: dashboard.php");
    exit();
}

// Récupération des chapitres du même module pour la navigation
$chapters = [];
try {
    $query = "SELECT c.id_chapitre, c.titre, c.ordre,
              COALESCE(ps.statut, 'non_commence') as statut
              FROM chapitre c
              LEFT JOIN progression_stagiaire ps ON c.id_chapitre = ps.id_chapitre AND ps.id_stagiaire = :stagiaire_id
              WHERE c.id_module = :module_id
              ORDER BY c.ordre";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':module_id', $module_id);
    $stmt->bindParam(':stagiaire_id', $stagiaire_id);
    $stmt->execute();
    $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des chapitres: " . $e->getMessage());
}

// Trouver le chapitre précédent et suivant pour la navigation
$prev_chapter = null;
$next_chapter = null;
foreach ($chapters as $key => $chap) {
    if ($chap['id_chapitre'] == $chapter_id) {
        if ($key > 0) {
            $prev_chapter = $chapters[$key - 1];
        }
        if ($key < count($chapters) - 1) {
            $next_chapter = $chapters[$key + 1];
        }
        break;
    }
}

// Si l'utilisateur n'a pas encore commencé ce chapitre, le marquer comme "en cours"
if ($current_status == 'non_commence' && $stagiaire_id) {
    $user->updateChapterStatus($stagiaire_id, $chapter_id, 'en_cours');
    $current_status = 'en_cours';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($chapter['titre']); ?> - MFC</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style-complementaire.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <style>
        .course-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
        }
        
        .course-sidebar {
            background: var(--white);
            border-radius: 1rem;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .course-sidebar h3 {
            color: var(--secondary-color);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .course-nav {
            list-style: none;
            padding: 0;
        }
        
        .course-nav-item {
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .course-nav-item:hover {
            background: var(--gray-100);
        }
        
        .course-nav-item.active {
            background: var(--primary-color);
            color: var(--white);
        }
        
        .course-nav-item.completed {
            border-left: 3px solid var(--success);
            padding-left: calc(0.75rem - 3px);
        }
        
        .course-nav-item.in-progress {
            border-left: 3px solid var(--primary-color);
            padding-left: calc(0.75rem - 3px);
        }
        
        .course-content {
            background: var(--white);
            border-radius: 1rem;
            box-shadow: var(--shadow);
            padding: 2rem;
        }
        
        .chapter-header {
            margin-bottom: 2rem;
        }
        
        .course-title {
            color: var(--text-color);
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        
        .module-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-light);
            margin-bottom: 1rem;
        }
        
        .chapter-content {
            line-height: 1.8;
            margin-bottom: 2rem;
        }
        
        .chapter-content h2 {
            color: var(--primary-color);
            margin: 1.5rem 0 1rem;
        }
        
        .chapter-content p {
            margin-bottom: 1rem;
        }
        
        .chapter-content ul, .chapter-content ol {
            margin-bottom: 1rem;
            padding-left: 1.5rem;
        }
        
        .chapter-content img {
            max-width: 100%;
            border-radius: 0.5rem;
            margin: 1rem 0;
        }
        
        .chapter-content code {
            background: var(--gray-100);
            padding: 0.2rem 0.4rem;
            border-radius: 0.25rem;
            font-family: monospace;
            color: var(--secondary-color);
        }
        
        .chapter-content pre {
            background: var(--gray-100);
            padding: 1rem;
            border-radius: 0.5rem;
            overflow-x: auto;
            margin-bottom: 1rem;
        }
        
        .chapter-content pre code {
            background: transparent;
            padding: 0;
        }
        
        .chapter-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .nav-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: var(--white);
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .nav-button:hover {
            background: var(--secondary-color);
        }
        
        .nav-button.disabled {
            background: var(--gray-200);
            color: var(--text-light);
            cursor: not-allowed;
        }
        
        .completion-box {
            background: var(--gray-50);
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 2rem;
            text-align: center;
        }
        
        .completion-box p {
            margin-bottom: 1rem;
        }
        
        .complete-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--success);
            color: var(--white);
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .complete-button:hover {
            background: #16a34a;
        }
        
        .complete-button.completed {
            background: var(--gray-200);
            cursor: default;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-badge.completed {
            background: var(--success);
            color: var(--white);
        }
        
        .status-badge.in-progress {
            background: var(--primary-color);
            color: var(--white);
        }
        
        .status-badge.not-started {
            background: var(--gray-200);
            color: var(--text-light);
        }
        
        /* Responsive */
        @media (max-width: 900px) {
            .course-container {
                grid-template-columns: 1fr;
            }
            
            .course-sidebar {
                position: static;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <ul>
                <li class="nav-logo">MFC</li>
                <li><a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Tableau de bord
                </a></li>
                <li><a href="#" class="action-btn logout-btn" onclick="document.getElementById('logout-form').submit();">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a></li>
            </ul>
        </nav>
    </header>

    <form id="logout-form" action="logout.php" method="POST" style="display: none;"></form>

    <main style="margin-top: 5rem;">
        <div class="course-container">
            <!-- Sidebar de navigation du cours -->
            <aside class="course-sidebar">
                <h3><i class="<?php echo htmlspecialchars($chapter['module_icon'] ?? 'fas fa-book'); ?>"></i> <?php echo htmlspecialchars($chapter['module_titre']); ?></h3>
                <ul class="course-nav">
                    <?php foreach ($chapters as $chap): ?>
                        <li class="course-nav-item <?php echo $chap['id_chapitre'] == $chapter_id ? 'active' : ''; ?> <?php echo $chap['statut'] != 'non_commence' ? $chap['statut'] == 'termine' ? 'completed' : 'in-progress' : ''; ?>">
                            <a href="course-chapter.php?chapter_id=<?php echo $chap['id_chapitre']; ?>&module_id=<?php echo $module_id; ?>&formation_id=<?php echo $formation_id; ?>" style="text-decoration: none; color: inherit; display: block;">
                                <?php echo htmlspecialchars($chap['titre']); ?>
                            </a>
                            <?php if ($chap['statut'] == 'termine'): ?>
                                <i class="fas fa-check"></i>
                            <?php elseif ($chap['statut'] == 'en_cours'): ?>
                                <i class="fas fa-spinner"></i>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="dashboard.php" class="nav-button">
                        <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                    </a>
                </div>
            </aside>
            
            <!-- Contenu du chapitre -->
            <section class="course-content">
                <div class="chapter-header">
                    <h1 class="course-title"><?php echo htmlspecialchars($chapter['titre']); ?></h1>
                    <div class="module-info">
                        <i class="<?php echo htmlspecialchars($chapter['module_icon'] ?? 'fas fa-book'); ?>"></i>
                        <span><?php echo htmlspecialchars($chapter['module_titre']); ?></span>
                        <span>&bull;</span>
                        <span><?php echo htmlspecialchars($chapter['nom_formation']); ?></span>
                    </div>
                    <div>
                        <span class="status-badge <?php 
                            if ($current_status == 'termine') echo 'completed';
                            elseif ($current_status == 'en_cours') echo 'in-progress';
                            else echo 'not-started';
                        ?>">
                            <?php 
                                if ($current_status == 'termine') echo '<i class="fas fa-check"></i> Terminé';
                                elseif ($current_status == 'en_cours') echo '<i class="fas fa-spinner"></i> En cours';
                                else echo '<i class="fas fa-circle"></i> Non commencé';
                            ?>
                        </span>
                        <span>&nbsp;&bull;&nbsp;</span>
                        <span><?php echo $chapter['duree_estimee'] ?? 30; ?> min</span>
                    </div>
                </div>
                
                <div class="chapter-content">
                    <?php 
                    // Affichage du contenu du chapitre
                    if (!empty($chapter['contenu'])) {
                        echo nl2br(htmlspecialchars($chapter['contenu']));
                    } else {
                        // Contenu fictif par défaut
                        echo generateDummyContent($chapter['titre'], $chapter['module_titre'], $chapter['nom_formation']);
                    }
                    ?>
                </div>
                
                <!-- Boîte de marquage comme terminé -->
                <?php if ($current_status != 'termine'): ?>
                <div class="completion-box">
                    <p>Avez-vous terminé ce chapitre?</p>
                    <form method="POST" action="">
                        <button type="submit" name="mark_completed" class="complete-button">
                            <i class="fas fa-check"></i> Marquer comme terminé
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div class="completion-box" style="background-color: #d1fae5;">
                    <p><i class="fas fa-check-circle"></i> Vous avez terminé ce chapitre. Félicitations!</p>
                    <button class="complete-button completed" disabled>
                        <i class="fas fa-check"></i> Chapitre terminé
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Navigation entre chapitres -->
                <div class="chapter-navigation">
                    <?php if ($prev_chapter): ?>
                    <a href="course-chapter.php?chapter_id=<?php echo $prev_chapter['id_chapitre']; ?>&module_id=<?php echo $module_id; ?>&formation_id=<?php echo $formation_id; ?>" class="nav-button">
                        <i class="fas fa-arrow-left"></i> Chapitre précédent
                    </a>
                    <?php else: ?>
                    <span class="nav-button disabled">
                        <i class="fas fa-arrow-left"></i> Chapitre précédent
                    </span>
                    <?php endif; ?>
                    
                    <?php if ($next_chapter): ?>
                    <a href="course-chapter.php?chapter_id=<?php echo $next_chapter['id_chapitre']; ?>&module_id=<?php echo $module_id; ?>&formation_id=<?php echo $formation_id; ?>" class="nav-button">
                        Chapitre suivant <i class="fas fa-arrow-right"></i>
                    </a>
                    <?php else: ?>
                    <a href="dashboard.php" class="nav-button">
                        Terminer ce module <i class="fas fa-check"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <footer>
        <div class="footer-content">
            <p>&copy; 2024 MFC - Tous droits réservés</p>
        </div>
    </footer>

    <script>
        // Notification de succès lorsqu'un chapitre est marqué comme terminé
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_GET['status']) && $_GET['status'] == 'completed'): ?>
            alert('Chapitre marqué comme terminé avec succès!');
            <?php endif; ?>
        });
    </script>
</body>
</html>

<?php
// Fonction pour générer un contenu fictif pour les chapitres sans contenu réel
function generateDummyContent($chapter_title, $module_title, $formation_title) {
    ob_start();
    ?>
    <h2>Introduction à <?php echo htmlspecialchars($chapter_title); ?></h2>
    
    <p>Bienvenue dans ce chapitre consacré à <strong><?php echo htmlspecialchars($chapter_title); ?></strong>, 
    une partie essentielle du module <strong><?php echo htmlspecialchars($module_title); ?></strong> 
    de notre formation <strong><?php echo htmlspecialchars($formation_title); ?></strong>.</p>
    
    <p>Dans ce chapitre, vous allez découvrir les concepts fondamentaux et acquérir des compétences pratiques 
    qui vous seront utiles tout au long de votre parcours d'apprentissage.</p>
    
    <h2>Objectifs d'apprentissage</h2>
    
    <p>À la fin de ce chapitre, vous serez capable de :</p>
    
    <ul>
        <li>Comprendre les principes fondamentaux liés à <?php echo htmlspecialchars($chapter_title); ?></li>
        <li>Appliquer ces concepts dans des situations pratiques</li>
        <li>Analyser et résoudre des problèmes courants</li>
        <li>Évaluer différentes approches et choisir la plus adaptée</li>
    </ul>
    
    <h2>Contenu principal</h2>
    
    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla facilisi. Phasellus auctor, nisl eget ultricies tincidunt, 
    nisl nisl aliquam nisl, eget aliquam nisl nisl eget nisl. Nulla facilisi. Phasellus auctor, nisl eget ultricies tincidunt, 
    nisl nisl aliquam nisl, eget aliquam nisl nisl eget nisl.</p>
    
    <p>Voici un exemple de code :</p>
    
    <pre><code>// Exemple de code
function example() {
    console.log("Ceci est un exemple de code");
    return "Bonjour le monde";
}</code></pre>
    
    <h2>Points clés à retenir</h2>
    
    <ol>
        <li>Point important numéro 1 sur <?php echo htmlspecialchars($chapter_title); ?></li>
        <li>Point important numéro 2 qui développe les concepts vus précédemment</li>
        <li>Point important numéro 3 pour une compréhension globale</li>
        <li>Point important numéro 4 concernant les applications pratiques</li>
    </ol>
    
    <h2>Activités pratiques</h2>
    
    <p>Pour consolider vos connaissances, nous vous recommandons de réaliser les exercices suivants :</p>
    
    <ol>
        <li>Exercice pratique 1 : application directe des concepts de base</li>
        <li>Exercice pratique 2 : résolution d'un problème plus complexe</li>
        <li>Projet personnel : création d'un mini-projet utilisant les compétences acquises</li>
    </ol>
    
    <h2>Conclusion</h2>
    
    <p>Dans ce chapitre, vous avez découvert les bases de <?php echo htmlspecialchars($chapter_title); ?>. 
    Ces connaissances vous serviront de fondation pour la suite de votre apprentissage. 
    N'hésitez pas à revenir sur ce chapitre si certains concepts ne sont pas parfaitement clairs.</p>
    
    <p>Dans le prochain chapitre, nous explorerons des concepts plus avancés qui s'appuieront sur ce que vous venez d'apprendre.</p>
    <?php
    return ob_get_clean();
}
?>