<?php
session_start();
require_once 'config.php';

// Vérification si l'utilisateur est connecté et a le rôle d'administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: espace-personnel.php");
    exit();
}

// Initialisation de la base de données
$database = new Database();
$db = $database->getConnection();

// Messages d'alerte
$alert = ['type' => '', 'message' => ''];

// --------------- GESTION DES CONTACTS ---------------
if (isset($_POST['update_status'])) {
    $contact_id = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
    $statut = isset($_POST['statut']) ? $_POST['statut'] : '';
    $notes = isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '';
    
    if ($contact_id > 0 && !empty($statut)) {
        try {
            $query = "UPDATE prisedecontact SET statut = :statut, notes_admin = :notes WHERE id_contact = :id_contact";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':statut', $statut);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':id_contact', $contact_id);
            
            if ($stmt->execute()) {
                $alert = ['type' => 'success', 'message' => "Statut du contact mis à jour avec succès."];
            } else {
                $alert = ['type' => 'error', 'message' => "Erreur lors de la mise à jour du statut."];
            }
        } catch (PDOException $e) {
            $alert = ['type' => 'error', 'message' => "Erreur de base de données: " . $e->getMessage()];
        }
    }
}

// --------------- GESTION DES UTILISATEURS ---------------
// Traitement de l'ajout/modification d'utilisateur
if (isset($_POST['save_user'])) {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $fullname = isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : '';
    $email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '';
    $role = isset($_POST['role']) ? htmlspecialchars($_POST['role']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    try {
        if ($user_id > 0) { // Modification
            $query = "UPDATE users SET fullname = :fullname, email = :email, role = :role, is_active = :is_active";
            if (!empty($password)) {
                $query .= ", password = :password";
            }
            $query .= " WHERE id = :user_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':fullname', $fullname);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':is_active', $is_active);
            $stmt->bindParam(':user_id', $user_id);
            
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt->bindParam(':password', $hashed_password);
            }
        } else { // Ajout
            if (empty($password)) {
                $alert = ['type' => 'error', 'message' => "Le mot de passe est obligatoire pour créer un utilisateur."];
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $query = "INSERT INTO users (fullname, email, role, password, is_active) VALUES (:fullname, :email, :role, :password, :is_active)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':fullname', $fullname);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':role', $role);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':is_active', $is_active);
            }
        }
        
        if (isset($stmt) && $stmt->execute()) {
            // Si c'est un formateur, créer une entrée dans la table formateur si elle n'existe pas
            if ($role === 'formateur') {
                $new_user_id = $user_id > 0 ? $user_id : $db->lastInsertId();
                $specialite = isset($_POST['specialite']) ? htmlspecialchars($_POST['specialite']) : 'Non spécifié';
                
                // Vérifier si le formateur existe déjà
                $check_query = "SELECT id_formateur FROM formateur WHERE id_user = :id_user";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':id_user', $new_user_id);
                $check_stmt->execute();
                
                if ($check_stmt->rowCount() === 0) {
                    // Créer une entrée dans la table formateur
                    $formateur_query = "INSERT INTO formateur (id_user, specialite, disponible) VALUES (:id_user, :specialite, 1)";
                    $formateur_stmt = $db->prepare($formateur_query);
                    $formateur_stmt->bindParam(':id_user', $new_user_id);
                    $formateur_stmt->bindParam(':specialite', $specialite);
                    $formateur_stmt->execute();
                } else if ($user_id > 0) {
                    // Mettre à jour la spécialité si l'utilisateur existe déjà
                    $update_query = "UPDATE formateur SET specialite = :specialite WHERE id_user = :id_user";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':specialite', $specialite);
                    $update_stmt->bindParam(':id_user', $user_id);
                    $update_stmt->execute();
                }
            }
            
            // De même pour un stagiaire
            if ($role === 'stagiaire') {
                $new_user_id = $user_id > 0 ? $user_id : $db->lastInsertId();
                
                // Vérifier si le stagiaire existe déjà
                $check_query = "SELECT id_stagiaire FROM stagiaire WHERE id_user = :id_user";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':id_user', $new_user_id);
                $check_stmt->execute();
                
                if ($check_stmt->rowCount() === 0) {
                    // Créer une entrée dans la table stagiaire
                    $parts = explode(' ', $fullname, 2);
                    $prenom = $parts[0];
                    $nom = isset($parts[1]) ? $parts[1] : '';
                    
                    $stagiaire_query = "INSERT INTO stagiaire (id_user, nom, prenom, email) VALUES (:id_user, :nom, :prenom, :email)";
                    $stagiaire_stmt = $db->prepare($stagiaire_query);
                    $stagiaire_stmt->bindParam(':id_user', $new_user_id);
                    $stagiaire_stmt->bindParam(':nom', $nom);
                    $stagiaire_stmt->bindParam(':prenom', $prenom);
                    $stagiaire_stmt->bindParam(':email', $email);
                    $stagiaire_stmt->execute();
                }
            }
            
            $action = $user_id > 0 ? "modifié" : "ajouté";
            $alert = ['type' => 'success', 'message' => "Utilisateur $action avec succès."];
        }
    } catch (PDOException $e) {
        $alert = ['type' => 'error', 'message' => "Erreur de base de données: " . $e->getMessage()];
    }
}

// Traitement de la suppression d'utilisateur
if (isset($_POST['delete_user'])) {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if ($user_id > 0) {
        try {
            // Vérifier si l'utilisateur est le dernier administrateur
            if ($_POST['user_role'] === 'admin') {
                $check_query = "SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND id != :user_id";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':user_id', $user_id);
                $check_stmt->execute();
                $admin_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($admin_count === 0) {
                    $alert = ['type' => 'error', 'message' => "Impossible de supprimer le dernier administrateur."];
                    // Sortir de la fonction
                    goto skip_delete;
                }
            }
            
            // Supprimer l'utilisateur
            $query = "DELETE FROM users WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                $alert = ['type' => 'success', 'message' => "Utilisateur supprimé avec succès."];
            } else {
                $alert = ['type' => 'error', 'message' => "Erreur lors de la suppression de l'utilisateur."];
            }
        } catch (PDOException $e) {
            // Si erreur de contrainte d'intégrité (clé étrangère)
            if ($e->getCode() == 23000) {
                $alert = ['type' => 'error', 'message' => "Impossible de supprimer cet utilisateur car il est référencé dans d'autres tables."];
            } else {
                $alert = ['type' => 'error', 'message' => "Erreur de base de données: " . $e->getMessage()];
            }
        }
    }
    skip_delete:
}

// --------------- GESTION DES FORMATIONS ---------------
// Traitement de l'ajout/modification de formation
if (isset($_POST['save_formation'])) {
    $formation_id = isset($_POST['formation_id']) ? intval($_POST['formation_id']) : 0;
    $nom_formation = isset($_POST['nom_formation']) ? htmlspecialchars($_POST['nom_formation']) : '';
    $description = isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '';
    $duree_formation = isset($_POST['duree_formation']) ? intval($_POST['duree_formation']) : 0;
    $prix = isset($_POST['prix']) ? floatval(str_replace(',', '.', $_POST['prix'])) : 0;
    $niveau_requis = isset($_POST['niveau_requis']) ? htmlspecialchars($_POST['niveau_requis']) : '';
    
    try {
        if ($formation_id > 0) { // Modification
            $query = "UPDATE formation SET nom_formation = :nom_formation, description = :description, 
                      duree_formation = :duree_formation, prix = :prix, niveau_requis = :niveau_requis 
                      WHERE id_formation = :formation_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':formation_id', $formation_id);
        } else { // Ajout
            $query = "INSERT INTO formation (nom_formation, description, duree_formation, prix, niveau_requis) 
                      VALUES (:nom_formation, :description, :duree_formation, :prix, :niveau_requis)";
            
            $stmt = $db->prepare($query);
        }
        
        $stmt->bindParam(':nom_formation', $nom_formation);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':duree_formation', $duree_formation);
        $stmt->bindParam(':prix', $prix);
        $stmt->bindParam(':niveau_requis', $niveau_requis);
        
        if ($stmt->execute()) {
            $action = $formation_id > 0 ? "modifiée" : "ajoutée";
            $alert = ['type' => 'success', 'message' => "Formation $action avec succès."];
        } else {
            $alert = ['type' => 'error', 'message' => "Erreur lors de l'enregistrement de la formation."];
        }
    } catch (PDOException $e) {
        $alert = ['type' => 'error', 'message' => "Erreur de base de données: " . $e->getMessage()];
    }
}

// Traitement de la suppression de formation
if (isset($_POST['delete_formation'])) {
    $formation_id = isset($_POST['formation_id']) ? intval($_POST['formation_id']) : 0;
    
    if ($formation_id > 0) {
        try {
            // Vérifier si la formation est utilisée dans des sessions
            $check_query = "SELECT COUNT(*) as count FROM session_formation WHERE id_formation = :formation_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':formation_id', $formation_id);
            $check_stmt->execute();
            $session_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($session_count > 0) {
                $alert = ['type' => 'error', 'message' => "Impossible de supprimer cette formation car elle est associée à des sessions."];
            } else {
                // Supprimer la formation
                $query = "DELETE FROM formation WHERE id_formation = :formation_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':formation_id', $formation_id);
                
                if ($stmt->execute()) {
                    $alert = ['type' => 'success', 'message' => "Formation supprimée avec succès."];
                } else {
                    $alert = ['type' => 'error', 'message' => "Erreur lors de la suppression de la formation."];
                }
            }
        } catch (PDOException $e) {
            $alert = ['type' => 'error', 'message' => "Erreur de base de données: " . $e->getMessage()];
        }
    }
}

// --------------- GESTION DU PLANNING ---------------
// Traitement de l'ajout/modification de session
if (isset($_POST['save_session'])) {
    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    $formation_id = isset($_POST['formation_id']) ? intval($_POST['formation_id']) : 0;
    $date_debut = isset($_POST['date_debut']) ? $_POST['date_debut'] : null;
    $formateur_id = isset($_POST['formateur_id']) ? intval($_POST['formateur_id']) : null;
    $horaire = isset($_POST['horaire']) ? $_POST['horaire'] : '';
    $places_disponibles = isset($_POST['places_disponibles']) ? intval($_POST['places_disponibles']) : 20;
    
    try {
        // Récupérer la durée de la formation pour calculer la date de fin
        $query_formation = "SELECT duree_formation FROM formation WHERE id_formation = :formation_id";
        $stmt_formation = $db->prepare($query_formation);
        $stmt_formation->bindParam(':formation_id', $formation_id);
        $stmt_formation->execute();
        $formation = $stmt_formation->fetch(PDO::FETCH_ASSOC);
        
        if (!$formation) {
            $alert = ['type' => 'error', 'message' => "Formation non trouvée."];
        } else {
            $duree_mois = $formation['duree_formation'];
            $date_fin = date('Y-m-d', strtotime($date_debut . " + $duree_mois months"));
            
            if ($session_id > 0) { // Modification
                $query = "UPDATE session_formation SET id_formation = :formation_id, date_debut = :date_debut, 
                          date_fin = :date_fin, horaire = :horaire, places_disponibles = :places_disponibles, 
                          id_formateur = :formateur_id WHERE id_session_formation = :session_id";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':session_id', $session_id);
            } else { // Ajout
                $query = "INSERT INTO session_formation (id_formation, date_debut, date_fin, horaire, 
                          places_disponibles, id_formateur) 
                          VALUES (:formation_id, :date_debut, :date_fin, :horaire, :places_disponibles, :formateur_id)";
                
                $stmt = $db->prepare($query);
            }
            
            $stmt->bindParam(':formation_id', $formation_id);
            $stmt->bindParam(':date_debut', $date_debut);
            $stmt->bindParam(':date_fin', $date_fin);
            $stmt->bindParam(':horaire', $horaire);
            $stmt->bindParam(':places_disponibles', $places_disponibles);
            
            if ($formateur_id > 0) {
                $stmt->bindParam(':formateur_id', $formateur_id);
            } else {
                $stmt->bindValue(':formateur_id', null, PDO::PARAM_NULL);
            }
            
            if ($stmt->execute()) {
                $action = $session_id > 0 ? "modifiée" : "ajoutée";
                $alert = ['type' => 'success', 'message' => "Session $action avec succès."];
            } else {
                $alert = ['type' => 'error', 'message' => "Erreur lors de l'enregistrement de la session."];
            }
        }
    } catch (PDOException $e) {
        $alert = ['type' => 'error', 'message' => "Erreur de base de données: " . $e->getMessage()];
    }
}

// Traitement de la suppression de session
if (isset($_POST['delete_session'])) {
    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    
    if ($session_id > 0) {
        try {
            // Vérifier si des stagiaires sont inscrits à cette session
            $check_query = "SELECT COUNT(*) as count FROM fiche_inscription WHERE id_session = :session_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':session_id', $session_id);
            $check_stmt->execute();
            $inscriptions_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($inscriptions_count > 0) {
                $alert = ['type' => 'error', 'message' => "Impossible de supprimer cette session car des stagiaires y sont inscrits."];
            } else {
                // Supprimer la session
                $query = "DELETE FROM session_formation WHERE id_session_formation = :session_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':session_id', $session_id);
                
                if ($stmt->execute()) {
                    $alert = ['type' => 'success', 'message' => "Session supprimée avec succès."];
                } else {
                    $alert = ['type' => 'error', 'message' => "Erreur lors de la suppression de la session."];
                }
            }
        } catch (PDOException $e) {
            $alert = ['type' => 'error', 'message' => "Erreur de base de données: " . $e->getMessage()];
        }
    }
}

// --------------- RÉCUPÉRATION DES DONNÉES ---------------
// Récupération des statistiques
try {
    // Nombre total d'utilisateurs par rôle
    $user_stats_query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
    $user_stats_stmt = $db->prepare($user_stats_query);
    $user_stats_stmt->execute();
    $user_stats = $user_stats_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiques des formations
    $formation_stats_query = "SELECT nom_formation, COUNT(sf.id_session_formation) as total_sessions 
                              FROM formation f
                              LEFT JOIN session_formation sf ON f.id_formation = sf.id_formation
                              GROUP BY f.id_formation";
    $formation_stats_stmt = $db->prepare($formation_stats_query);
    $formation_stats_stmt->execute();
    $formation_stats = $formation_stats_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Progression globale des stagiaires 
    $progression_query = "SELECT AVG(CASE 
                          WHEN ps.statut = 'termine' THEN 100
                          WHEN ps.statut = 'en_cours' THEN 50
                          ELSE 0
                        END) as progression_moyenne
                        FROM progression_stagiaire ps";
    $progression_stmt = $db->prepare($progression_query);
    $progression_stmt->execute();
    $progression_stats = $progression_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Nombre de contacts non traités
    $pending_contacts_query = "SELECT COUNT(*) as count FROM prisedecontact WHERE statut = 'non_traite'";
    $pending_contacts_stmt = $db->prepare($pending_contacts_query);
    $pending_contacts_stmt->execute();
    $pending_contacts = $pending_contacts_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats_error = "Erreur lors de la récupération des statistiques: " . $e->getMessage();
}

// Pagination pour les contacts
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$records_per_page = 5;
$offset = ($page - 1) * $records_per_page;

// Filtres pour les contacts
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Construction de la requête avec filtres pour les contacts
$where_clauses = [];
$params = [];

if (!empty($status_filter)) {
    $where_clauses[] = "statut = :statut";
    $params[':statut'] = $status_filter;
}

if (!empty($search)) {
    $where_clauses[] = "(nom LIKE :search OR email LIKE :search OR sujet LIKE :search OR message LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Récupération des contacts
try {
    // Requête pour le nombre total de demandes
    $count_query = "SELECT COUNT(*) as total FROM prisedecontact $where_sql";
    $count_stmt = $db->prepare($count_query);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Requête pour les demandes paginées
    $query = "SELECT * FROM prisedecontact $where_sql ORDER BY date_contact DESC LIMIT :offset, :limit";
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $contacts_error = "Erreur de récupération des contacts: " . $e->getMessage();
}

// Récupération des sessions pour la gestion du planning
// Récupération des sessions pour la gestion du planning
try {
    $sessions_query = "SELECT sf.id_session_formation, f.nom_formation, sf.date_debut, sf.date_fin, 
                       sf.horaire, sf.places_disponibles, sf.id_formateur,
                       (SELECT fullname FROM users u JOIN formateur fo ON u.id = fo.id_user
                        WHERE fo.id_formateur = sf.id_formateur) as formateur_nom,
                       (SELECT COUNT(*) FROM fiche_inscription fi 
                        WHERE fi.id_session = sf.id_session_formation) as nombre_inscrits
                       FROM session_formation sf
                       JOIN formation f ON sf.id_formation = f.id_formation
                       ORDER BY sf.date_debut DESC";
    $sessions_stmt = $db->prepare($sessions_query);
    $sessions_stmt->execute();
    $sessions = $sessions_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sessions_error = "Erreur de récupération des sessions: " . $e->getMessage();
}
// Récupération des formateurs disponibles
try {
    $formateurs_query = "SELECT f.id_formateur, u.fullname, f.specialite
                        FROM formateur f
                        JOIN users u ON f.id_user = u.id
                        WHERE f.disponible = 1";
    $formateurs_stmt = $db->prepare($formateurs_query);
    $formateurs_stmt->execute();
    $formateurs = $formateurs_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $formateurs_error = "Erreur de récupération des formateurs: " . $e->getMessage();
}

// Récupération des formations
try {
    $formations_query = "SELECT * FROM formation ORDER BY nom_formation";
    $formations_stmt = $db->prepare($formations_query);
    $formations_stmt->execute();
    $formations = $formations_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $formations_error = "Erreur de récupération des formations: " . $e->getMessage();
}

// Récupération des utilisateurs
$user_filter = isset($_GET['user_role']) ? $_GET['user_role'] : '';
$user_search = isset($_GET['user_search']) ? $_GET['user_search'] : '';

$user_where = [];
$user_params = [];

if (!empty($user_filter)) {
    $user_where[] = "role = :role";
    $user_params[':role'] = $user_filter;
}

if (!empty($user_search)) {
    $user_where[] = "(fullname LIKE :search OR email LIKE :search)";
    $user_params[':search'] = "%$user_search%";
}

$user_where_sql = !empty($user_where) ? "WHERE " . implode(" AND ", $user_where) : "";

try {
    $users_query = "SELECT * FROM users $user_where_sql ORDER BY fullname";
    $users_stmt = $db->prepare($users_query);
    foreach ($user_params as $key => $value) {
        $users_stmt->bindValue($key, $value);
    }
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users_error = "Erreur de récupération des utilisateurs: " . $e->getMessage();
}

// Vérifier si le fichier get-contact-details.php existe et le créer si nécessaire
function createContactDetailsHandler() {
    return file_exists('get-contact-details.php') || file_put_contents('get-contact-details.php', '<?php
session_start();
require_once "config.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Non autorisé"]);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$contact_id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

if ($contact_id <= 0) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "ID de contact invalide"]);
    exit();
}

try {
    $query = "SELECT * FROM prisedecontact WHERE id_contact = :id_contact";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id_contact", $contact_id);
    $stmt->execute();
    
    if ($contact = $stmt->fetch(PDO::FETCH_ASSOC)) {
        header("Content-Type: application/json");
        echo json_encode(["contact" => $contact]);
    } else {
        header("Content-Type: application/json");
        echo json_encode(["error" => "Contact non trouvé"]);
    }
} catch (PDOException $e) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Erreur de base de données: " . $e->getMessage()]);
}
?>');
}

$contact_details_created = createContactDetailsHandler();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MFC Admin - Tableau de bord</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style-complementaire.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .admin-welcome {
            margin-bottom: 2rem;
            padding: 2rem;
            background: linear-gradient(120deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: var(--shadow);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .tab-navigation {
            display: flex;
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 1.5rem;
            overflow-x: auto;
        }
        
        .tab-button {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            font-weight: 500;
            color: var(--text-light);
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .tab-button.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .filter-bar {
            background: white;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .data-table th, .data-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .data-table th {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
        }
        
        .data-table tr:hover {
            background: var(--gray-50);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 100;
            overflow-y: auto;
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 600px;
            position: relative;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            color: white;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary-color);
        }
        
        .btn-danger {
            background: var(--error);
        }
        
        .btn-warning {
            background: #f59e0b;
        }
        
        .btn-success {
            background: var(--success);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
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
            border-radius: 0.25rem;
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
        
        .alert-warning {
            background-color: #ffe7a3;
            color: #854d0e;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .dependencies-modal-content {
            max-width: 800px !important;
        }
        
        .dependencies-list {
            margin-top: 1.5rem;
        }
        
        .dependencies-list h3 {
            font-size: 1.1rem;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            color: var(--text-color);
        }
        
        .dependencies-list ul {
            list-style-type: disc;
            padding-left: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .dependencies-list li {
            margin-bottom: 0.5rem;
        }
        
        .form-hint {
            font-size: 0.875rem;
            color: var(--text-light);
            margin-top: 0.5rem;
        }
        
        .modal-header, .modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header {
            border-bottom: 1px solid var(--gray-200);
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .modal-footer {
            border-top: 1px solid var(--gray-200);
            padding-top: 1.5rem;
            margin-top: 1.5rem;
            justify-content: flex-end;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <ul>
                <li class="nav-logo">MFC Admin</li>
                <li><a href="#" class="action-btn logout-btn" onclick="document.getElementById('logout-form').submit();">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a></li>
            </ul>
        </nav>
    </header>

    <form id="logout-form" action="logout.php" method="POST" style="display: none;"></form>

    <main>
        <div class="admin-container">
            <div class="admin-welcome">
                <h2><i class="fas fa-chart-line"></i> Tableau de bord administrateur</h2>
                <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['user_name']); ?>. Gérez votre centre de formation depuis cette interface.</p>
            </div>
            
            <?php if (!empty($alert['message'])): ?>
                <div class="alert alert-<?php echo $alert['type']; ?>">
                    <?php echo $alert['message']; ?>
                </div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <?php foreach ($user_stats as $stat): ?>
                <div class="stat-card">
                    <div class="stat-icon">
                        <?php 
                            $icon = 'fas fa-user';
                            switch($stat['role']) {
                                case 'admin': $icon = 'fas fa-user-shield'; break;
                                case 'formateur': $icon = 'fas fa-chalkboard-teacher'; break;
                                case 'stagiaire': $icon = 'fas fa-user-graduate'; break;
                                case 'secretaire': $icon = 'fas fa-user-tie'; break;
                            }
                            echo "<i class=\"$icon\"></i>";
                        ?>
                    </div>
                    <div class="stat-value"><?php echo $stat['count']; ?></div>
                    <div class="stat-label">
                        <?php 
                            switch($stat['role']) {
                                case 'admin': echo 'Administrateurs'; break;
                                case 'formateur': echo 'Formateurs'; break;
                                case 'stagiaire': echo 'Stagiaires'; break;
                                case 'secretaire': echo 'Secrétaires'; break;
                                default: echo ucfirst($stat['role']) . 's';
                            }
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                    <div class="stat-value"><?php echo count($formations ?? []); ?></div>
                    <div class="stat-label">Formations</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-value"><?php echo count($sessions ?? []); ?></div>
                    <div class="stat-label">Sessions</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                    <div class="stat-value"><?php echo $pending_contacts['count'] ?? '0'; ?></div>
                    <div class="stat-label">Contacts non traités</div>
                </div>
            </div>
            
            <div class="tab-container">
                <div class="tab-navigation">
                    <button class="tab-button active" data-tab="dashboard-tab">Tableau de bord</button>
                    <button class="tab-button" data-tab="users-tab">Gestion des utilisateurs</button>
                    <button class="tab-button" data-tab="formations-tab">Gestion des formations</button>
                    <button class="tab-button" data-tab="planning-tab">Gestion du planning</button>
                    <button class="tab-button" data-tab="contacts-tab">Gestion des contacts</button>
                </div>
                
                <!-- Tab Dashboard -->
                <div id="dashboard-tab" class="tab-content active">
                    <div class="row">
                        <div class="col-12">
                            <div class="dashboard-summary">
                                <h3><i class="fas fa-tachometer-alt"></i> Aperçu de l'activité</h3>
                                <p>Bienvenue sur votre tableau de bord d'administration. Vous pouvez gérer toutes les ressources de votre centre de formation à partir des onglets ci-dessus.</p>
                                
                                <div class="dashboard-actions" style="margin-top: 2rem;">
                                    <h4>Actions rapides</h4>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                                        <a href="#" class="btn-action btn-primary" onclick="document.querySelector('.tab-button[data-tab=\'users-tab\']').click()">
                                            <i class="fas fa-user-plus"></i> Ajouter un utilisateur
                                        </a>
                                        <a href="#" class="btn-action btn-primary" onclick="document.querySelector('.tab-button[data-tab=\'formations-tab\']').click()">
                                            <i class="fas fa-graduation-cap"></i> Gérer les formations
                                        </a>
                                        <a href="#" class="btn-action btn-primary" onclick="document.querySelector('.tab-button[data-tab=\'planning-tab\']').click()">
                                            <i class="fas fa-calendar-plus"></i> Planifier une session
                                        </a>
                                        <a href="#" class="btn-action btn-primary" onclick="document.querySelector('.tab-button[data-tab=\'contacts-tab\']').click()">
                                            <i class="fas fa-envelope"></i> Voir les demandes de contact
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Users -->
                <div id="users-tab" class="tab-content">
                    <div class="filter-bar">
                        <form class="filter-form" method="GET" action="">
                            <input type="hidden" name="tab" value="users">
                            <div class="filter-group">
                                <label for="user_role">Rôle:</label>
                                <select id="user_role" name="user_role">
                                    <option value="">Tous</option>
                                    <option value="admin" <?php echo $user_filter === 'admin' ? 'selected' : ''; ?>>Administrateurs</option>
                                    <option value="secretaire" <?php echo $user_filter === 'secretaire' ? 'selected' : ''; ?>>Secrétaires</option>
                                    <option value="formateur" <?php echo $user_filter === 'formateur' ? 'selected' : ''; ?>>Formateurs</option>
                                    <option value="stagiaire" <?php echo $user_filter === 'stagiaire' ? 'selected' : ''; ?>>Stagiaires</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="user_search">Recherche:</label>
                                <input type="text" id="user_search" name="user_search" value="<?php echo htmlspecialchars($user_search); ?>" placeholder="Nom, email...">
                            </div>
                            
                            <button type="submit" class="btn-action btn-primary">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                        </form>
                    </div>
                    
                    <div class="action-buttons" style="margin-bottom: 1rem;">
                        <button class="btn-action btn-success" onclick="openUserModal()">
                            <i class="fas fa-plus"></i> Ajouter un utilisateur
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Rôle</th>
                                    <th>Statut</th>
                                    <th>Dernière connexion</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <?php 
                                                    switch($user['role']) {
                                                        case 'admin': echo 'Administrateur'; break;
                                                        case 'secretaire': echo 'Secrétaire'; break;
                                                        case 'formateur': echo 'Formateur'; break;
                                                        case 'stagiaire': echo 'Stagiaire'; break;
                                                        default: echo ucfirst($user['role']);
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="status-badge" style="background-color: <?php echo $user['is_active'] ? '#d1fae5' : '#fee2e2'; ?>; color: <?php echo $user['is_active'] ? '#065f46' : '#991b1b'; ?>">
                                                    <?php echo $user['is_active'] ? 'Actif' : 'Inactif'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais'; ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn-action btn-primary" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['fullname']); ?>', '<?php echo addslashes($user['email']); ?>', '<?php echo $user['role']; ?>', <?php echo $user['is_active']; ?>)">
                                                        <i class="fas fa-edit"></i> Modifier
                                                    </button>
                                                    <button class="btn-action btn-danger" onclick="confirmDeleteUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['fullname']); ?>', '<?php echo $user['role']; ?>')">
                                                        <i class="fas fa-trash"></i> Supprimer
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center;">Aucun utilisateur trouvé</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Tab Formations -->
                <div id="formations-tab" class="tab-content">
                    <div class="action-buttons" style="margin-bottom: 1rem;">
                        <button class="btn-action btn-success" onclick="openFormationModal()">
                            <i class="fas fa-plus"></i> Ajouter une formation
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Durée (mois)</th>
                                    <th>Prix</th>
                                    <th>Niveau requis</th>
                                    <th>Sessions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($formations)): ?>
                                    <?php foreach ($formations as $formation): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($formation['nom_formation']); ?></td>
                                            <td><?php echo $formation['duree_formation']; ?></td>
                                            <td><?php echo number_format($formation['prix'], 2, ',', ' '); ?> €</td>
                                            <td><?php echo htmlspecialchars($formation['niveau_requis'] ?: 'Non spécifié'); ?></td>
                                            <td>
                                                <?php 
                                                    $formation_sessions = array_filter($sessions ?? [], function($s) use ($formation) {
                                                        return $s['nom_formation'] === $formation['nom_formation'];
                                                    });
                                                    echo count($formation_sessions);
                                                ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn-action btn-primary" onclick="openFormationModal(<?php echo $formation['id_formation']; ?>, '<?php echo addslashes($formation['nom_formation']); ?>', '<?php echo addslashes($formation['description']); ?>', <?php echo $formation['duree_formation']; ?>, <?php echo $formation['prix']; ?>, '<?php echo addslashes($formation['niveau_requis']); ?>')">
                                                        <i class="fas fa-edit"></i> Modifier
                                                    </button>
                                                    <button class="btn-action btn-danger" onclick="confirmDeleteFormation(<?php echo $formation['id_formation']; ?>, '<?php echo addslashes($formation['nom_formation']); ?>')">
                                                        <i class="fas fa-trash"></i> Supprimer
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center;">Aucune formation trouvée</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Tab Planning -->
                <div id="planning-tab" class="tab-content">
                    <div class="action-buttons" style="margin-bottom: 1rem;">
                        <button class="btn-action btn-success" onclick="openSessionModal()">
                            <i class="fas fa-plus"></i> Ajouter une session
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Formation</th>
                                    <th>Dates</th>
                                    <th>Horaires</th>
                                    <th>Inscrits</th>
                                    <th>Formateur</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($sessions)): ?>
                                    <?php foreach ($sessions as $session): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($session['nom_formation']); ?></td>
                                            <td>
                                                <?php 
                                                    echo date('d/m/Y', strtotime($session['date_debut'])) . ' au ' . 
                                                         date('d/m/Y', strtotime($session['date_fin']));
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($session['horaire']); ?></td>
                                            <td><?php echo $session['nombre_inscrits']; ?> / <?php echo $session['places_disponibles']; ?></td>
                                            <td>
                                                <?php 
                                                    echo !empty($session['formateur_nom']) ? 
                                                        htmlspecialchars($session['formateur_nom']) : 
                                                        '<span style="color:#e11d48;">Non affecté</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn-action btn-primary" onclick="openSessionModal(<?php echo $session['id_session_formation']; ?>)">
                                                        <i class="fas fa-edit"></i> Modifier
                                                    </button>
                                                    <button class="btn-action btn-danger" onclick="confirmDeleteSession(<?php echo $session['id_session_formation']; ?>, '<?php echo addslashes($session['nom_formation']); ?>')">
                                                        <i class="fas fa-trash"></i> Supprimer
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center;">Aucune session trouvée</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Tab Contacts -->
                <div id="contacts-tab" class="tab-content">
                    <div class="filter-bar">
                        <form class="filter-form" method="GET" action="">
                            <input type="hidden" name="tab" value="contacts">
                            <div class="filter-group">
                                <label for="status">Statut:</label>
                                <select id="status" name="status">
                                    <option value="">Tous</option>
                                    <option value="non_traite" <?php echo $status_filter === 'non_traite' ? 'selected' : ''; ?>>Non traité</option>
                                    <option value="en_cours" <?php echo $status_filter === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                                    <option value="traite" <?php echo $status_filter === 'traite' ? 'selected' : ''; ?>>Traité</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="search">Recherche:</label>
                                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nom, email, sujet...">
                            </div>
                            
                            <button type="submit" class="btn-action btn-primary">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                        </form>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Sujet</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($contacts)): ?>
                                    <?php foreach ($contacts as $contact): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($contact['nom']); ?></td>
                                            <td><?php echo htmlspecialchars($contact['email']); ?></td>
                                            <td><?php echo htmlspecialchars($contact['sujet']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($contact['date_contact'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $contact['statut']; ?>">
                                                    <?php 
                                                        switch($contact['statut']) {
                                                            case 'non_traite': echo 'Non traité'; break;
                                                            case 'en_cours': echo 'En cours'; break;
                                                            case 'traite': echo 'Traité'; break;
                                                        }
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn-action btn-primary" onclick="openContactModal(<?php echo $contact['id_contact']; ?>)">
                                                        <i class="fas fa-eye"></i> Voir
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center;">Aucun contact trouvé</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination des contacts -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination" style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1rem;">
                            <?php if ($page > 1): ?>
                                <a href="?tab=contacts&page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" class="btn-action btn-primary">
                                    <i class="fas fa-chevron-left"></i> Précédent
                                </a>
                            <?php endif; ?>
                            
                            <span style="padding: 0.5rem 1rem; border: 1px solid #ddd; border-radius: 0.25rem;">
                                Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                            </span>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?tab=contacts&page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" class="btn-action btn-primary">
                                    Suivant <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Modal Utilisateur -->
        <div id="userModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('userModal')">&times;</span>
                <h2 id="userModalTitle">Ajouter un utilisateur</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="user_id" id="user_id" value="0">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="fullname">Nom complet*</label>
                            <input type="text" id="fullname" name="fullname" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email*</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="role">Rôle*</label>
                            <select id="role" name="role" class="form-control" required onchange="toggleSpecialiteField()">
                                <option value="admin">Administrateur</option>
                                <option value="secretaire">Secrétaire</option>
                                <option value="formateur">Formateur</option>
                                <option value="stagiaire">Stagiaire</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Mot de passe <span id="password-hint">(obligatoire pour un nouvel utilisateur)</span></label>
                            <input type="password" id="password" name="password" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group" id="specialite-container" style="display: none;">
                        <label for="specialite">Spécialité du formateur*</label>
                        <input type="text" id="specialite" name="specialite" class="form-control" placeholder="Ex: Développement web, Bureautique, Cybersécurité...">
                    </div>
                    
                    <div class="form-group">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" id="is_active" name="is_active" checked>
                            Compte actif
                        </label>
                    </div>
                    
                    <div class="form-group" style="text-align: right;">
                        <button type="submit" name="save_user" class="btn-action btn-primary">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal Formation -->
        <div id="formationModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('formationModal')">&times;</span>
                <h2 id="formationModalTitle">Ajouter une formation</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="formation_id" id="formation_id" value="0">
                    
                    <div class="form-group">
                        <label for="nom_formation">Nom de la formation*</label>
                        <input type="text" id="nom_formation" name="nom_formation" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="duree_formation">Durée (mois)*</label>
                            <input type="number" id="duree_formation" name="duree_formation" class="form-control" min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="prix">Prix (€)*</label>
                            <input type="text" id="prix" name="prix" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="niveau_requis">Niveau requis</label>
                        <input type="text" id="niveau_requis" name="niveau_requis" class="form-control">
                    </div>
                    
                    <div class="form-group" style="text-align: right;">
                        <button type="submit" name="save_formation" class="btn-action btn-primary">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal Session -->
        <div id="sessionModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('sessionModal')">&times;</span>
                <h2 id="sessionModalTitle">Ajouter une session</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="session_id" id="session_id" value="0">
                    
                    <div class="form-group">
                        <label for="formation_id">Formation*</label>
                        <select id="formation_id" name="formation_id" class="form-control" required>
                            <option value="">Sélectionnez une formation</option>
                            <?php foreach ($formations as $formation): ?>
                                <option value="<?php echo $formation['id_formation']; ?>">
                                    <?php echo htmlspecialchars($formation['nom_formation']); ?> (<?php echo $formation['duree_formation']; ?> mois)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="date_debut">Date de début*</label>
                            <input type="date" id="date_debut" name="date_debut" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="horaire">Horaires*</label>
                            <select id="horaire" name="horaire" class="form-control" required>
                                <option value="9h-12h">Matin (9h-12h)</option>
                                <option value="14h-17h">Après-midi (14h-17h)</option>
                                <option value="9h-17h">Journée complète (9h-17h)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="formateur_id">Formateur</label>
                            <select id="formateur_id" name="formateur_id" class="form-control">
                                <option value="">Aucun formateur assigné</option>
                                <?php foreach ($formateurs as $formateur): ?>
                                    <option value="<?php echo $formateur['id_formateur']; ?>">
                                        <?php echo htmlspecialchars($formateur['fullname']); ?> (<?php echo htmlspecialchars($formateur['specialite']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="places_disponibles">Places disponibles*</label>
                            <input type="number" id="places_disponibles" name="places_disponibles" class="form-control" min="1" value="20" required>
                        </div>
                    </div>
                    
                    <div class="form-group" style="text-align: right;">
                        <button type="submit" name="save_session" class="btn-action btn-primary">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal Contact -->
        <div id="contactModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('contactModal')">&times;</span>
                <h2>Détails de la demande de contact</h2>
                
                <div id="contact-details-container">
                    <!-- Le contenu sera chargé dynamiquement par AJAX -->
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p>Chargement des détails...</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Formulaires de suppression -->
        <form id="deleteUserForm" method="POST" action="" style="display: none;">
            <input type="hidden" name="user_id" id="delete_user_id">
            <input type="hidden" name="user_role" id="delete_user_role">
            <input type="hidden" name="delete_user" value="1">
        </form>
        
        <form id="deleteFormationForm" method="POST" action="" style="display: none;">
            <input type="hidden" name="formation_id" id="delete_formation_id">
            <input type="hidden" name="delete_formation" value="1">
        </form>
        
        <form id="deleteSessionForm" method="POST" action="" style="display: none;">
            <input type="hidden" name="session_id" id="delete_session_id">
            <input type="hidden" name="delete_session" value="1">
        </form>
    </main>

    <footer>
        <div class="footer-content">
            <p>&copy; 2025 MFC Admin - Tous droits réservés</p>
        </div>
    </footer>

    <script>
        // Gestion des onglets
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            
            // Au chargement, vérifier si un onglet est spécifié dans l'URL
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            
            if (tabParam) {
                showTab(tabParam + '-tab');
            }
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    showTab(tabId);
                });
            });
        });
        
        function showTab(tabId) {
            // Désactiver tous les onglets
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Activer l'onglet sélectionné
            document.getElementById(tabId).classList.add('active');
            document.querySelector(`.tab-button[data-tab="${tabId}"]`).classList.add('active');
            
            // Mettre à jour l'URL
            const baseTabName = tabId.replace('-tab', '');
            updateQueryParam('tab', baseTabName);
        }
        
        function updateQueryParam(key, value) {
            const url = new URL(window.location.href);
            url.searchParams.set(key, value);
            window.history.pushState({}, '', url);
        }
        
        // Gestion des modales
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        };
        
        // Modale utilisateur
        function openUserModal(userId = 0, fullname = '', email = '', role = 'stagiaire', isActive = true, specialite = '') {
            document.getElementById('userModalTitle').textContent = userId > 0 ? 'Modifier l\'utilisateur' : 'Ajouter un utilisateur';
            document.getElementById('user_id').value = userId;
            document.getElementById('fullname').value = fullname;
            document.getElementById('email').value = email;
            document.getElementById('role').value = role;
            document.getElementById('is_active').checked = isActive;
            document.getElementById('password').value = '';
            
            if (specialite) {
                document.getElementById('specialite').value = specialite;
            } else {
                document.getElementById('specialite').value = '';
            }
            
            // Afficher/cacher le champ spécialité en fonction du rôle
            toggleSpecialiteField();
            
            // Indication pour le mot de passe
            if (userId > 0) {
                document.getElementById('password-hint').textContent = '(laissez vide pour conserver l\'actuel)';
                document.getElementById('password').required = false;
            } else {
                document.getElementById('password-hint').textContent = '(obligatoire pour un nouvel utilisateur)';
                document.getElementById('password').required = true;
            }
            
            openModal('userModal');
        }
        
        // Afficher/cacher le champ spécialité en fonction du rôle
        function toggleSpecialiteField() {
            const roleSelect = document.getElementById('role');
            const specialiteContainer = document.getElementById('specialite-container');
            
            if (roleSelect.value === 'formateur') {
                specialiteContainer.style.display = 'block';
                document.getElementById('specialite').required = true;
            } else {
                specialiteContainer.style.display = 'none';
                document.getElementById('specialite').required = false;
            }
        }
        
        // Modale formation
        function openFormationModal(formationId = 0, nom = '', description = '', duree = 3, prix = 0, niveau = '') {
            document.getElementById('formationModalTitle').textContent = formationId > 0 ? 'Modifier la formation' : 'Ajouter une formation';
            document.getElementById('formation_id').value = formationId;
            document.getElementById('nom_formation').value = nom;
            document.getElementById('description').value = description;
            document.getElementById('duree_formation').value = duree;
            document.getElementById('prix').value = prix;
            document.getElementById('niveau_requis').value = niveau;
            
            openModal('formationModal');
        }
        
        // Modale session
        function openSessionModal(sessionId = 0) {
            document.getElementById('sessionModalTitle').textContent = sessionId > 0 ? 'Modifier la session' : 'Ajouter une session';
            document.getElementById('session_id').value = sessionId;
            
            if (sessionId > 0) {
                // Récupérer les détails de la session via AJAX
                fetch(`get-session-details.php?id=${sessionId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            alert(data.error);
                            return;
                        }
                        
                        const session = data.session;
                        document.getElementById('formation_id').value = session.id_formation;
                        document.getElementById('date_debut').value = session.date_debut;
                        document.getElementById('horaire').value = session.horaire;
                        document.getElementById('formateur_id').value = session.id_formateur || '';
                        document.getElementById('places_disponibles').value = session.places_disponibles;
                    })
                    .catch(error => {
                        alert('Erreur lors de la récupération des détails de la session: ' + error.message);
                    });
            } else {
                // Réinitialiser le formulaire
                document.getElementById('formation_id').value = '';
                document.getElementById('date_debut').value = '';
                document.getElementById('horaire').value = '9h-12h';
                document.getElementById('formateur_id').value = '';
                document.getElementById('places_disponibles').value = '20';
            }
            
            openModal('sessionModal');
        }
        
        // Modale contact
        function openContactModal(contactId) {
            openModal('contactModal');
            
            // Charger les détails du contact
            fetch(`get-contact-details.php?id=${contactId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('contact-details-container').innerHTML = `<div class="alert alert-error">${data.error}</div>`;
                        return;
                    }
                    
                    const contact = data.contact;
                    let statusLabel = '';
                    let statusClass = '';
                    
                    switch(contact.statut) {
                        case 'non_traite':
                            statusLabel = 'Non traité';
                            statusClass = 'status-non_traite';
                            break;
                        case 'en_cours':
                            statusLabel = 'En cours';
                            statusClass = 'status-en_cours';
                            break;
                        case 'traite':
                            statusLabel = 'Traité';
                            statusClass = 'status-traite';
                            break;
                    }
                    
                    // Générer le HTML pour les détails du contact
                    document.getElementById('contact-details-container').innerHTML = `
                        <div class="contact-details">
                            <div class="detail-group">
                                <div class="detail-label">Statut actuel:</div>
                                <span class="status-badge ${statusClass}">${statusLabel}</span>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">ID:</div>
                                <div>${contact.id_contact}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Nom:</div>
                                <div>${contact.nom}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Email:</div>
                                <div>${contact.email}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Sujet:</div>
                                <div>${contact.sujet}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Date:</div>
                                <div>${new Date(contact.date_contact).toLocaleString('fr-FR')}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Message:</div>
                                <div class="message-content">${contact.message}</div>
                            </div>
                        </div>
                        
                        <form class="update-form" method="POST" action="">
                            <input type="hidden" name="contact_id" value="${contact.id_contact}">
                            
                            <div class="form-group">
                                <label for="statut">Mettre à jour le statut:</label>
                                <select id="statut" name="statut" class="form-control" required>
                                    <option value="non_traite" ${contact.statut === 'non_traite' ? 'selected' : ''}>Non traité</option>
                                    <option value="en_cours" ${contact.statut === 'en_cours' ? 'selected' : ''}>En cours</option>
                                    <option value="traite" ${contact.statut === 'traite' ? 'selected' : ''}>Traité</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="notes">Notes administratives:</label>
                                <textarea id="notes" name="notes" class="form-control" rows="4">${contact.notes_admin || ''}</textarea>
                            </div>
                            
                            <div class="form-group" style="text-align: right;">
                                <button type="submit" name="update_status" class="btn-action btn-primary">
                                    <i class="fas fa-save"></i> Mettre à jour
                                </button>
                            </div>
                        </form>
                    `;
                })
                .catch(error => {
                    document.getElementById('contact-details-container').innerHTML = `<div class="alert alert-error">Erreur de chargement: ${error.message}</div>`;
                });
        }
        
        // Fonctions de confirmation de suppression avec vérification des dépendances
        function confirmDeleteUser(userId, fullname, role) {
            // Vérifier d'abord les dépendances
            fetch(`check-dependencies.php?type=user&id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.has_dependencies) {
                        // Afficher une modal avec les dépendances et les options
                        showDependenciesModal('user', userId, fullname, data.dependencies);
                    } else {
                        // Pas de dépendances, confirmer la suppression normale
                        if (confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur ${fullname} ?`)) {
                            document.getElementById('delete_user_id').value = userId;
                            document.getElementById('delete_user_role').value = role;
                            document.getElementById('deleteUserForm').submit();
                        }
                    }
                })
                .catch(error => {
                    console.error("Erreur lors de la vérification des dépendances:", error);
                    alert("Une erreur est survenue lors de la vérification des dépendances.");
                });
        }
        
        function confirmDeleteFormation(formationId, nom) {
            // Vérifier d'abord les dépendances
            fetch(`check-dependencies.php?type=formation&id=${formationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.has_dependencies) {
                        // Afficher une modal avec les dépendances et les options
                        showDependenciesModal('formation', formationId, nom, data.dependencies);
                    } else {
                        // Pas de dépendances, confirmer la suppression normale
                        if (confirm(`Êtes-vous sûr de vouloir supprimer la formation ${nom} ?`)) {
                            document.getElementById('delete_formation_id').value = formationId;
                            document.getElementById('deleteFormationForm').submit();
                        }
                    }
                })
                .catch(error => {
                    console.error("Erreur lors de la vérification des dépendances:", error);
                    alert("Une erreur est survenue lors de la vérification des dépendances.");
                });
        }
        
        function confirmDeleteSession(sessionId, formation) {
            // Vérifier d'abord les dépendances
            fetch(`check-dependencies.php?type=session&id=${sessionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.has_dependencies) {
                        // Afficher une modal avec les dépendances et les options
                        showDependenciesModal('session', sessionId, formation, data.dependencies);
                    } else {
                        // Pas de dépendances, confirmer la suppression normale
                        if (confirm(`Êtes-vous sûr de vouloir supprimer cette session de ${formation} ?`)) {
                            document.getElementById('delete_session_id').value = sessionId;
                            document.getElementById('deleteSessionForm').submit();
                        }
                    }
                })
                .catch(error => {
                    console.error("Erreur lors de la vérification des dépendances:", error);
                    alert("Une erreur est survenue lors de la vérification des dépendances.");
                });
        }
        
        // Fonction pour afficher la modal de gestion des dépendances
        function showDependenciesModal(entityType, entityId, entityName, dependencies) {
            // Créer dynamiquement le contenu de la modal en fonction des dépendances
            let modalContent = `
                <div class="modal-header">
                    <h2>Impossible de supprimer - Dépendances existantes</h2>
                    <span class="close" onclick="closeModal('dependenciesModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        L'élément <strong>${entityName}</strong> ne peut pas être supprimé car il est référencé dans d'autres tables.
                    </div>
                    <div class="dependencies-list">`;
            
            // Afficher les dépendances spécifiques selon le type d'entité
            if (entityType === 'user') {
                if (dependencies.sessions_formateur && dependencies.sessions_formateur.length > 0) {
                    modalContent += `
                        <h3>Sessions liées au formateur :</h3>
                        <ul>
                            ${dependencies.sessions_formateur.map(session => 
                                `<li>${session.nom_formation} (du ${new Date(session.date_debut).toLocaleDateString('fr-FR')} au ${new Date(session.date_fin).toLocaleDateString('fr-FR')})</li>`
                            ).join('')}
                        </ul>
                        <div class="form-group">
                            <label for="new_formateur">Réassigner à un autre formateur :</label>
                            <select id="new_formateur" class="form-control">
                                <option value="">Sélectionner un formateur</option>
                                <!-- Chargement dynamique des formateurs -->
                            </select>
                        </div>
                        <button class="btn-action btn-primary" onclick="reassignFormateur(${entityId}, 'sessions_formateur')">
                            <i class="fas fa-exchange-alt"></i> Réassigner les sessions
                        </button>`;
                    
                    // Charger la liste des formateurs disponibles
                    fetchFormateursList();
                }
                
                if (dependencies.inscriptions_stagiaire && dependencies.inscriptions_stagiaire.length > 0) {
                    modalContent += `
                        <h3>Inscriptions liées au stagiaire :</h3>
                        <ul>
                            ${dependencies.inscriptions_stagiaire.map(inscription => 
                                `<li>${inscription.nom_formation} (du ${new Date(inscription.date_debut).toLocaleDateString('fr-FR')} au ${new Date(inscription.date_fin).toLocaleDateString('fr-FR')})</li>`
                            ).join('')}
                        </ul>`;
                }
                
                // Proposer de désactiver l'utilisateur au lieu de le supprimer
                modalContent += `
                    <div class="form-group" style="margin-top: 1.5rem;">
                        <button class="btn-action btn-warning" onclick="deactivateUser(${entityId})">
                            <i class="fas fa-user-slash"></i> Désactiver l'utilisateur
                        </button>
                        <p class="form-hint">
                            <i class="fas fa-info-circle"></i> 
                            La désactivation garde l'utilisateur dans la base de données mais l'empêche de se connecter.
                        </p>
                    </div>`;
                
            } else if (entityType === 'formation') {
                if (dependencies.sessions && dependencies.sessions.length > 0) {
                    modalContent += `
                        <h3>Sessions utilisant cette formation :</h3>
                        <ul>
                            ${dependencies.sessions.map(session => 
                                `<li>Session du ${new Date(session.date_debut).toLocaleDateString('fr-FR')} au ${new Date(session.date_fin).toLocaleDateString('fr-FR')} (${session.nombre_inscrits} inscrits)</li>`
                            ).join('')}
                        </ul>
                        <p class="form-hint">
                            <i class="fas fa-info-circle"></i> 
                            Vous devez d'abord supprimer ou modifier ces sessions avant de pouvoir supprimer cette formation.
                        </p>`;
                }
                
                if (dependencies.modules && dependencies.modules.length > 0) {
                    modalContent += `
                        <h3>Modules de cette formation :</h3>
                        <ul>
                            ${dependencies.modules.map(module => 
                                `<li>${module.titre}</li>`
                            ).join('')}
                        </ul>
                        <p class="form-hint">
                            <i class="fas fa-info-circle"></i> 
                            Ces modules seront également supprimés.
                        </p>`;
                }
                
            } else if (entityType === 'session') {
                if (dependencies.inscriptions && dependencies.inscriptions.length > 0) {
                    modalContent += `
                        <h3>Inscriptions à cette session :</h3>
                        <ul>
                            ${dependencies.inscriptions.map(inscription => 
                                `<li>${inscription.prenom} ${inscription.nom} (${inscription.email}) - Statut: ${inscription.statut_paiement}</li>`
                            ).join('')}
                        </ul>
                        <div class="form-group">
                            <button class="btn-action btn-warning" onclick="cancelInscriptions(${entityId})">
                                <i class="fas fa-ban"></i> Annuler toutes les inscriptions
                            </button>
                            <p class="form-hint">
                                <i class="fas fa-info-circle"></i> 
                                Cette action marquera toutes les inscriptions comme "annulées".
                            </p>
                        </div>`;
                }
            }
            
            modalContent += `
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-action btn-primary" onclick="closeModal('dependenciesModal')">
                        <i class="fas fa-times"></i> Fermer
                    </button>
                </div>`;
            
            // Créer ou réutiliser la modal
            let dependenciesModal = document.getElementById('dependenciesModal');
            if (!dependenciesModal) {
                dependenciesModal = document.createElement('div');
                dependenciesModal.id = 'dependenciesModal';
                dependenciesModal.className = 'modal';
                document.body.appendChild(dependenciesModal);
            }
            
            // Définir le contenu et afficher
            const modalContentDiv = document.createElement('div');
            modalContentDiv.className = 'modal-content dependencies-modal-content';
            modalContentDiv.innerHTML = modalContent;
            
            dependenciesModal.innerHTML = '';
            dependenciesModal.appendChild(modalContentDiv);
            
            // Stocker l'ID et le type pour les actions ultérieures
            dependenciesModal.dataset.entityId = entityId;
            dependenciesModal.dataset.entityType = entityType;
            
            openModal('dependenciesModal');
        }
        
        // Fonction pour charger la liste des formateurs disponibles
        function fetchFormateursList() {
            fetch('get-formateurs-list.php')
                .then(response => response.json())
                .then(data => {
                    if (data.formateurs) {
                        const formateurSelect = document.getElementById('new_formateur');
                        data.formateurs.forEach(formateur => {
                            const option = document.createElement('option');
                            option.value = formateur.id_formateur;
                            option.textContent = `${formateur.fullname} (${formateur.specialite})`;
                            formateurSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error("Erreur lors du chargement des formateurs:", error);
                });
        }
        
        // Fonction pour réassigner un formateur
        function reassignFormateur(userId, dependencyType) {
            const newFormateurId = document.getElementById('new_formateur').value;
            if (!newFormateurId) {
                alert("Veuillez sélectionner un formateur.");
                return;
            }
            
            // Récupérer les IDs des sessions
            const modal = document.getElementById('dependenciesModal');
            const entityId = modal.dataset.entityId;
            
            // Vérifier à nouveau les dépendances pour obtenir les IDs des sessions
            fetch(`check-dependencies.php?type=user&id=${entityId}`)
                .then(response => response.json())
                .then(data => {
                    console.log("Dépendances reçues:", data); // Débogage
                    
                    if (data.dependencies && data.dependencies[dependencyType]) {
                        // Extraction correcte des IDs de session
                        let sessionIds = [];
                        data.dependencies[dependencyType].forEach(session => {
                            if (session.id_session_formation) {
                                sessionIds.push(session.id_session_formation);
                            }
                        });
                        
                        console.log("Sessions à réassigner:", sessionIds); // Débogage
                        
                        if (sessionIds.length === 0) {
                            alert("Aucune session à réassigner trouvée.");
                            return;
                        }
                        
                        // Envoyer la demande de réassignation
                        const requestData = {
                            action: 'reassign_formateur_sessions',
                            entity_type: 'user',
                            entity_id: entityId,
                            new_formateur_id: newFormateurId,
                            sessions: sessionIds
                        };
                        
                        console.log("Données envoyées:", requestData); // Débogage
                        
                        fetch('reassign-dependencies.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(requestData),
                        })
                        .then(response => {
                            console.log("Réponse reçue:", response); // Débogage
                            return response.text().then(text => {
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    console.error("Erreur de parsing JSON:", e);
                                    console.log("Texte de réponse:", text);
                                    throw new Error("Réponse invalide du serveur");
                                }
                            });
                        })
                        .then(result => {
                            console.log("Résultat:", result); // Débogage
                            if (result.success) {
                                alert(result.message);
                                closeModal('dependenciesModal');
                                // Recharger la page pour refléter les changements
                                window.location.reload();
                            } else {
                                alert(result.error || "Une erreur est survenue lors de la réassignation.");
                            }
                        })
                        .catch(error => {
                            console.error("Erreur lors de la réassignation:", error);
                            alert("Une erreur est survenue lors de la réassignation: " + error.message);
                        });
                    } else {
                        alert("Aucune session trouvée pour ce formateur.");
                        console.error("Structure de dépendance inattendue:", data);
                    }
                })
                .catch(error => {
                    console.error("Erreur lors de la vérification des dépendances:", error);
                    alert("Une erreur est survenue lors de la vérification des dépendances: " + error.message);
                });
        }
        
        // Fonction pour annuler toutes les inscriptions d'une session
        function cancelInscriptions(sessionId) {
            if (confirm("Êtes-vous sûr de vouloir annuler toutes les inscriptions à cette session ?")) {
                // Vérifier à nouveau les dépendances pour obtenir les IDs des inscriptions
                fetch(`check-dependencies.php?type=session&id=${sessionId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.dependencies.inscriptions) {
                            const inscriptionIds = data.dependencies.inscriptions.map(inscription => inscription.id_inscription);
                            
                            // Envoyer la demande d'annulation
                            fetch('reassign-dependencies.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    action: 'cancel_inscriptions',
                                    entity_type: 'session',
                                    entity_id: sessionId,
                                    inscriptions: inscriptionIds
                                }),
                            })
                            .then(response => response.json())
                            .then(result => {
                                if (result.success) {
                                    alert(result.message);
                                    closeModal('dependenciesModal');
                                    
                                    // Maintenant on peut soumettre le formulaire de suppression
                                    document.getElementById('delete_session_id').value = sessionId;
                                    document.getElementById('deleteSessionForm').submit();
                                } else {
                                    alert(result.error || "Une erreur est survenue lors de l'annulation des inscriptions.");
                                }
                            })
                            .catch(error => {
                                console.error("Erreur lors de l'annulation des inscriptions:", error);
                                alert("Une erreur est survenue lors de l'annulation des inscriptions.");
                            });
                        }
                    })
                    .catch(error => {
                        console.error("Erreur lors de la vérification des dépendances:", error);
                        alert("Une erreur est survenue lors de la vérification des dépendances.");
                    });
            }
        }
        
        // Fonction pour désactiver un utilisateur
        function deactivateUser(userId) {
            if (confirm("Êtes-vous sûr de vouloir désactiver cet utilisateur ? Il ne pourra plus se connecter à l'application.")) {
                // Envoyer la demande de désactivation
                fetch('reassign-dependencies.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'deactivate_user',
                        entity_type: 'user',
                        entity_id: userId
                    }),
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert(result.message);
                        closeModal('dependenciesModal');
                        // Recharger la page pour refléter les changements
                        window.location.reload();
                    } else {
                        alert(result.error || "Une erreur est survenue lors de la désactivation de l'utilisateur.");
                    }
                })
                .catch(error => {
                    console.error("Erreur lors de la désactivation de l'utilisateur:", error);
                    alert("Une erreur est survenue lors de la désactivation de l'utilisateur.");
                });
            }
        }

        // Modale contact
function openContactModal(contactId) {
    openModal('contactModal');
    
    // Charger les détails du contact
    fetch(`get-contact-details.php?id=${contactId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('contact-details-container').innerHTML = `<div class="alert alert-error">${data.error}</div>`;
                return;
            }
            
            const contact = data.contact;
            let statusLabel = '';
            let statusClass = '';
            
            switch(contact.statut) {
                case 'non_traite':
                    statusLabel = 'Non traité';
                    statusClass = 'alert-error';
                    break;
                case 'en_cours':
                    statusLabel = 'En cours';
                    statusClass = 'alert-warning';
                    break;
                case 'traite':
                    statusLabel = 'Traité';
                    statusClass = 'alert-success';
                    break;
            }
            
            // Générer le HTML pour les détails du contact
            document.getElementById('contact-details-container').innerHTML = `
                <div class="contact-details">
                    <div class="detail-group">
                        <div class="detail-label">Statut actuel:</div>
                        <span class="status-badge ${statusClass}">${statusLabel}</span>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Nom:</div>
                        <div>${contact.nom}</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Email:</div>
                        <div>${contact.email}</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Sujet:</div>
                        <div>${contact.sujet}</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Date:</div>
                        <div>${new Date(contact.date_contact).toLocaleString('fr-FR')}</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Message:</div>
                        <div style="background: #f5f5f5; padding: 1rem; border-radius: 0.5rem;">${contact.message}</div>
                    </div>
                </div>
                
                <form class="update-form" method="POST" action="">
                    <input type="hidden" name="contact_id" value="${contact.id_contact}">
                    
                    <div class="form-group">
                        <label for="statut">Mettre à jour le statut:</label>
                        <select id="statut" name="statut" class="form-control" required>
                            <option value="non_traite" ${contact.statut === 'non_traite' ? 'selected' : ''}>Non traité</option>
                            <option value="en_cours" ${contact.statut === 'en_cours' ? 'selected' : ''}>En cours</option>
                            <option value="traite" ${contact.statut === 'traite' ? 'selected' : ''}>Traité</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes administratives:</label>
                        <textarea id="notes" name="notes" class="form-control" rows="4">${contact.notes_admin || ''}</textarea>
                    </div>
                    
                    <div class="form-group" style="text-align: right;">
                        <button type="submit" name="update_status" class="btn-action btn-primary">
                            <i class="fas fa-save"></i> Mettre à jour
                        </button>
                    </div>
                </form>
            `;
        })
        .catch(error => {
            document.getElementById('contact-details-container').innerHTML = `<div class="alert alert-error">Erreur de chargement: ${error.message}</div>`;
        });
}
        
        // Fonction pour récupérer les détails du formateur avant d'ouvrir la modal
        function editUser(userId, fullname, email, role, isActive) {
            if (role === 'formateur') {
                // Récupérer la spécialité du formateur via AJAX
                fetch(`get-formateur-details.php?id_user=${userId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error(data.error);
                            // En cas d'erreur, on ouvre quand même le modal sans la spécialité
                            openUserModal(userId, fullname, email, role, isActive, '');
                        } else {
                            openUserModal(userId, fullname, email, role, isActive, data.specialite || '');
                        }
                    })
                    .catch(error => {
                        console.error("Erreur lors de la récupération des détails du formateur:", error);
                        openUserModal(userId, fullname, email, role, isActive, '');
                    });
            } else {
                // Pour les autres rôles, pas besoin de récupérer de détails supplémentaires
                openUserModal(userId, fullname, email, role, isActive);
            }
        }
        
        // Création d'un fichier pour récupérer les détails d'une session
        <?php
        // Création du fichier pour récupérer les détails d'un formateur
        if (!file_exists('get-formateur-details.php')) {
            $formateur_details_handler = '<?php
session_start();
require_once "config.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Non autorisé"]);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$id_user = isset($_GET["id_user"]) ? intval($_GET["id_user"]) : 0;

if ($id_user <= 0) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "ID utilisateur invalide"]);
    exit();
}

try {
    $query = "SELECT specialite FROM formateur WHERE id_user = :id_user";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id_user", $id_user);
    $stmt->execute();
    
    if ($formateur = $stmt->fetch(PDO::FETCH_ASSOC)) {
        header("Content-Type: application/json");
        echo json_encode(["specialite" => $formateur["specialite"]]);
    } else {
        header("Content-Type: application/json");
        echo json_encode(["error" => "Formateur non trouvé"]);
    }
} catch (PDOException $e) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Erreur de base de données: " . $e->getMessage()]);
}
?>';
            file_put_contents('get-formateur-details.php', $formateur_details_handler);
            echo "console.log('Fichier get-formateur-details.php créé avec succès.');";
        }
        
        // Création du fichier pour récupérer les détails d'une session
        if (!file_exists('get-session-details.php')) {
            $session_details_handler = '<?php
session_start();
require_once "config.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Non autorisé"]);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$session_id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

if ($session_id <= 0) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "ID de session invalide"]);
    exit();
}

try {
    $query = "SELECT * FROM session_formation WHERE id_session_formation = :session_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":session_id", $session_id);
    $stmt->execute();
    
    if ($session = $stmt->fetch(PDO::FETCH_ASSOC)) {
        header("Content-Type: application/json");
        echo json_encode(["session" => $session]);
    } else {
        header("Content-Type: application/json");
        echo json_encode(["error" => "Session non trouvée"]);
    }
} catch (PDOException $e) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Erreur de base de données: " . $e->getMessage()]);
}
?>';
            file_put_contents('get-session-details.php', $session_details_handler);
            echo "console.log('Fichier get-session-details.php créé avec succès.');";
        }
        ?>
    </script>
</body>
</html>