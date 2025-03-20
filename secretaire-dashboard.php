<?php
session_start();
require_once 'config.php';

// Vérification si l'utilisateur est connecté et a le rôle de secrétaire
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'secretaire') {
    header("Location: espace-personnel.php");
    exit();
}

// Initialisation de la base de données
$database = new Database();
$db = $database->getConnection();

// Messages système
$message = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valider une inscription
    if (isset($_POST['valider_inscription'])) {
        $id_inscription = isset($_POST['id_inscription']) ? intval($_POST['id_inscription']) : 0;
        
        if ($id_inscription > 0) {
            try {
                $query = "UPDATE fiche_inscription SET statut_paiement = 'validé' WHERE id_inscription = :id_inscription";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id_inscription', $id_inscription);
                
                if ($stmt->execute()) {
                    $message = "L'inscription a été validée avec succès.";
                    
                    // Recharger la page pour mettre à jour les listes
                    header("Location: secretaire-dashboard.php?success=1");
                    exit();
                } else {
                    $error = "Erreur lors de la validation de l'inscription.";
                }
            } catch (PDOException $e) {
                $error = "Erreur de base de données: " . $e->getMessage();
            }
        }
    }

    // Annuler une inscription
    if (isset($_POST['annuler_inscription'])) {
        $id_inscription = isset($_POST['id_inscription']) ? intval($_POST['id_inscription']) : 0;
        
        if ($id_inscription > 0) {
            try {
                $query = "UPDATE fiche_inscription SET statut_paiement = 'annulé' WHERE id_inscription = :id_inscription";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id_inscription', $id_inscription);
                
                if ($stmt->execute()) {
                    $message = "L'inscription a été annulée avec succès.";
                    
                    // Recharger la page pour mettre à jour les listes
                    header("Location: secretaire-dashboard.php?success=2");
                    exit();
                } else {
                    $error = "Erreur lors de l'annulation de l'inscription.";
                }
            } catch (PDOException $e) {
                $error = "Erreur de base de données: " . $e->getMessage();
            }
        }
    }
}

// Récupérer les inscriptions en attente de validation
$inscriptions_en_attente = [];
try {
    $query = "SELECT fi.id_inscription, fi.date_inscription, fi.statut_paiement,
                    s.id_stagiaire, s.nom as nom_stagiaire, s.prenom as prenom_stagiaire, s.email as email_stagiaire, s.tel,
                    f.id_formation, f.nom_formation, f.prix,
                    sf.id_session_formation, sf.date_debut, sf.date_fin, sf.horaire
              FROM fiche_inscription fi
              JOIN stagiaire s ON fi.id_stagiaire = s.id_stagiaire
              JOIN session_formation sf ON fi.id_session = sf.id_session_formation
              JOIN formation f ON sf.id_formation = f.id_formation
              WHERE fi.statut_paiement = 'en_attente'
              ORDER BY fi.date_inscription DESC
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $inscriptions_en_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des inscriptions en attente: " . $e->getMessage());
    $error = "Erreur lors de la récupération des inscriptions en attente.";
}

// Récupérer les dernières inscriptions validées
$inscriptions_validees = [];
try {
    $query = "SELECT fi.id_inscription, fi.date_inscription, fi.statut_paiement,
                    s.id_stagiaire, s.nom as nom_stagiaire, s.prenom as prenom_stagiaire, s.email as email_stagiaire,
                    f.id_formation, f.nom_formation, f.prix,
                    sf.id_session_formation, sf.date_debut, sf.date_fin, sf.horaire
              FROM fiche_inscription fi
              JOIN stagiaire s ON fi.id_stagiaire = s.id_stagiaire
              JOIN session_formation sf ON fi.id_session = sf.id_session_formation
              JOIN formation f ON sf.id_formation = f.id_formation
              WHERE fi.statut_paiement = 'validé'
              ORDER BY fi.date_inscription DESC
              LIMIT 5";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $inscriptions_validees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des inscriptions validées: " . $e->getMessage());
}

// Récupérer les statistiques sur les inscriptions et paiements
$stats = [
    'inscriptions_total' => 0,
    'inscriptions_validees' => 0,
    'inscriptions_en_attente' => 0,
    'inscriptions_annulees' => 0,
    'montant_total' => 0,
    'montant_recu' => 0
];

try {
    // Total des inscriptions
    $query = "SELECT COUNT(*) as total FROM fiche_inscription";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['inscriptions_total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Inscriptions validées
    $query = "SELECT COUNT(*) as total FROM fiche_inscription WHERE statut_paiement = 'validé'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['inscriptions_validees'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Inscriptions en attente
    $query = "SELECT COUNT(*) as total FROM fiche_inscription WHERE statut_paiement = 'en_attente'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['inscriptions_en_attente'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Inscriptions annulées
    $query = "SELECT COUNT(*) as total FROM fiche_inscription WHERE statut_paiement = 'annulé'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['inscriptions_annulees'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Montant total et reçu
    $query = "SELECT 
                SUM(f.prix) as montant_total,
                SUM(CASE WHEN fi.statut_paiement = 'validé' THEN f.prix ELSE 0 END) as montant_recu
              FROM fiche_inscription fi
              JOIN session_formation sf ON fi.id_session = sf.id_session_formation
              JOIN formation f ON sf.id_formation = f.id_formation
              WHERE fi.statut_paiement != 'annulé'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['montant_total'] = $result['montant_total'] ?? 0;
    $stats['montant_recu'] = $result['montant_recu'] ?? 0;
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
}

// Afficher les messages de succès si redirection
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $message = "L'inscription a été validée avec succès.";
            break;
        case 2:
            $message = "L'inscription a été annulée avec succès.";
            break;
        case 3:
            $message = "La nouvelle session a été créée avec succès.";
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MFC - Espace Secrétariat</title>
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
            grid-template-columns: 1fr;
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

        .inscription-status {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-valide {
            background: #d1fae5;
            color: #065f46;
        }

        .status-attente {
            background: #fef3c7;
            color: #92400e;
        }

        .status-annule {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-btns {
            display: flex;
            gap: 0.5rem;
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

        .action-btn.secondary {
            background: var(--gray-200);
            color: var(--text-color);
        }

        .action-btn.secondary:hover {
            background: var(--gray-300);
        }

        .action-btn.danger {
            background: var(--error);
        }

        .action-btn.danger:hover {
            background: #dc2626;
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

        @media (max-width: 992px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <ul>
                <li class="nav-logo">MFC Secrétariat</li>
                <li><a href="secretaire-dashboard.php" class="active">
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
                <p>Gérez les inscriptions, les paiements et les dossiers des stagiaires</p>
            </div>

            <?php if($message): ?>
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
                    <div class="stat-value"><?php echo $stats['inscriptions_total']; ?></div>
                    <div class="stat-label">Inscriptions totales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['inscriptions_en_attente']; ?></div>
                    <div class="stat-label">En attente</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['inscriptions_validees']; ?></div>
                    <div class="stat-label">Validées</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['montant_recu'], 0, ',', ' '); ?> €</div>
                    <div class="stat-label">Montant encaissé</div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="main-content">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-clipboard-list"></i>
                            <h2 class="card-title">Gestion des inscriptions</h2>
                        </div>
                        
                        <div class="tabs">
                            <div class="tab active" onclick="showTab('en-attente-tab')">En attente (<?php echo count($inscriptions_en_attente); ?>)</div>
                            <div class="tab" onclick="showTab('validees-tab')">Dernières validées</div>
                        </div>
                        
                        <div id="en-attente-tab" class="tab-content active">
                            <?php if (empty($inscriptions_en_attente)): ?>
                                <div class="empty-message">
                                    <i class="fas fa-check-circle fa-2x" style="color: var(--primary-color); margin-bottom: 1rem;"></i>
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
                                                            <button type="submit" name="valider_inscription" class="action-btn">
                                                                <i class="fas fa-check"></i> Valider
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler cette inscription ?');">
                                                            <input type="hidden" name="id_inscription" value="<?php echo $inscription['id_inscription']; ?>">
                                                            <button type="submit" name="annuler_inscription" class="action-btn danger">
                                                                <i class="fas fa-times"></i> Annuler
                                                            </button>
                                                        </form>
                                                        <a href="secretaire-detail-stagiaire.php?id=<?php echo $inscription['id_stagiaire']; ?>" class="action-btn secondary">
                                                            <i class="fas fa-user"></i> Profil
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <div style="text-align: center; margin-top: 1.5rem;">
                                    <a href="secretaire-inscriptions.php" class="action-btn">
                                        <i class="fas fa-clipboard-list"></i> Voir toutes les inscriptions
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div id="validees-tab" class="tab-content">
                            <?php if (empty($inscriptions_validees)): ?>
                                <div class="empty-message">
                                    <p>Aucune inscription validée.</p>
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
                                        <?php foreach ($inscriptions_validees as $inscription): ?>
                                            <tr>
                                                <td>
                                                    <div><?php echo htmlspecialchars($inscription['prenom_stagiaire'] . ' ' . $inscription['nom_stagiaire']); ?></div>
                                                    <div style="font-size: 0.75rem; color: var(--text-light);"><?php echo htmlspecialchars($inscription['email_stagiaire']); ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars($inscription['nom_formation']); ?></td>
                                                <td>
                                                    <div>Du <?php echo date('d/m/Y', strtotime($inscription['date_debut'])); ?></div>
                                                    <div>au <?php echo date('d/m/Y', strtotime($inscription['date_fin'])); ?></div>
                                                </td>
                                                <td><?php echo number_format($inscription['prix'], 2, ',', ' '); ?> €</td>
                                                <td>
                                                    <div class="action-btns">
                                                        <span class="inscription-status status-valide">
                                                            <i class="fas fa-check"></i> Validée
                                                        </span>
                                                        <a href="secretaire-detail-stagiaire.php?id=<?php echo $inscription['id_stagiaire']; ?>" class="action-btn secondary">
                                                            <i class="fas fa-user"></i> Profil
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <div style="text-align: center; margin-top: 1.5rem;">
                                    <a href="secretaire-paiements.php" class="action-btn">
                                        <i class="fas fa-money-bill-wave"></i> Gérer les paiements
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

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
    </script>
</body>
</html>