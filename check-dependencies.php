<?php
session_start();
require_once "config.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Non autorisé"]);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Type d'entité et ID
$entity_type = isset($_GET["type"]) ? $_GET["type"] : '';
$entity_id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

if (empty($entity_type) || $entity_id <= 0) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Paramètres invalides"]);
    exit();
}

try {
    $dependencies = [];
    
    // Vérifier les dépendances selon le type d'entité
    switch ($entity_type) {
        case 'user':
            // Vérifier les sessions liées au formateur
            $query_formateur = "SELECT sf.id_session_formation, f.nom_formation, sf.date_debut, sf.date_fin 
                              FROM session_formation sf
                              JOIN formation f ON sf.id_formation = f.id_formation
                              JOIN formateur fr ON sf.id_formateur = fr.id_formateur
                              WHERE fr.id_user = :id_user";
            $stmt_formateur = $db->prepare($query_formateur);
            $stmt_formateur->bindParam(":id_user", $entity_id);
            $stmt_formateur->execute();
            
            if ($stmt_formateur->rowCount() > 0) {
                $formateur_sessions = $stmt_formateur->fetchAll(PDO::FETCH_ASSOC);
                $dependencies['sessions_formateur'] = $formateur_sessions;
                
                // Log pour débogage
                error_log("Sessions de formateur trouvées: " . json_encode($formateur_sessions));
            } else {
                // Vérifier pourquoi aucune session n'est trouvée
                $check_formateur = "SELECT id_formateur FROM formateur WHERE id_user = :id_user";
                $check_stmt = $db->prepare($check_formateur);
                $check_stmt->bindParam(":id_user", $entity_id);
                $check_stmt->execute();
                $formateur_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($formateur_data) {
                    $formateur_id = $formateur_data['id_formateur'];
                    
                    // Vérifier les sessions avec ce formateur
                    $session_check = "SELECT id_session_formation FROM session_formation WHERE id_formateur = :id_formateur";
                    $session_stmt = $db->prepare($session_check);
                    $session_stmt->bindParam(":id_formateur", $formateur_id);
                    $session_stmt->execute();
                    
                    if ($session_stmt->rowCount() > 0) {
                        $sessions = $session_stmt->fetchAll(PDO::FETCH_ASSOC);
                        error_log("Sessions avec ID formateur $formateur_id: " . json_encode($sessions));
                        
                        // Récupérer les sessions complètes (requête corrigée)
                        $full_sessions_query = "SELECT sf.id_session_formation, f.nom_formation, sf.date_debut, sf.date_fin 
                                             FROM session_formation sf
                                             JOIN formation f ON sf.id_formation = f.id_formation
                                             WHERE sf.id_formateur = :id_formateur";
                        $full_sessions_stmt = $db->prepare($full_sessions_query);
                        $full_sessions_stmt->bindParam(":id_formateur", $formateur_id);
                        $full_sessions_stmt->execute();
                        
                        $formateur_sessions = $full_sessions_stmt->fetchAll(PDO::FETCH_ASSOC);
                        $dependencies['sessions_formateur'] = $formateur_sessions;
                        error_log("Sessions récupérées avec requête corrigée: " . json_encode($formateur_sessions));
                    } else {
                        error_log("Aucune session trouvée pour le formateur ID $formateur_id");
                    }
                } else {
                    error_log("Aucun formateur trouvé pour l'utilisateur ID $entity_id");
                }
            }
            
            // Vérifier les inscriptions liées au stagiaire
            $query_stagiaire = "SELECT fi.id_inscription, f.nom_formation, sf.date_debut, sf.date_fin 
                              FROM fiche_inscription fi
                              JOIN stagiaire s ON fi.id_stagiaire = s.id_stagiaire
                              JOIN session_formation sf ON fi.id_session = sf.id_session_formation
                              JOIN formation f ON sf.id_formation = f.id_formation
                              WHERE s.id_user = :id_user";
            $stmt_stagiaire = $db->prepare($query_stagiaire);
            $stmt_stagiaire->bindParam(":id_user", $entity_id);
            $stmt_stagiaire->execute();
            
            if ($stmt_stagiaire->rowCount() > 0) {
                $stagiaire_inscriptions = $stmt_stagiaire->fetchAll(PDO::FETCH_ASSOC);
                $dependencies['inscriptions_stagiaire'] = $stagiaire_inscriptions;
            }
            
            // Récupérer le rôle de l'utilisateur pour le message d'aide
            $query_role = "SELECT role FROM users WHERE id = :id_user";
            $stmt_role = $db->prepare($query_role);
            $stmt_role->bindParam(":id_user", $entity_id);
            $stmt_role->execute();
            $user_role = $stmt_role->fetch(PDO::FETCH_ASSOC)['role'] ?? '';
            $dependencies['role'] = $user_role;
            break;
            
        case 'formation':
            // Vérifier les sessions utilisant cette formation
            $query_sessions = "SELECT sf.id_session_formation, sf.date_debut, sf.date_fin, sf.horaire,
                              (SELECT COUNT(*) FROM fiche_inscription fi WHERE fi.id_session = sf.id_session_formation) as nombre_inscrits
                              FROM session_formation sf
                              WHERE sf.id_formation = :id_formation";
            $stmt_sessions = $db->prepare($query_sessions);
            $stmt_sessions->bindParam(":id_formation", $entity_id);
            $stmt_sessions->execute();
            
            if ($stmt_sessions->rowCount() > 0) {
                $formation_sessions = $stmt_sessions->fetchAll(PDO::FETCH_ASSOC);
                $dependencies['sessions'] = $formation_sessions;
            }
            
            // Vérifier les modules de cette formation
            $query_modules = "SELECT id_module, titre FROM module WHERE id_formation = :id_formation";
            $stmt_modules = $db->prepare($query_modules);
            $stmt_modules->bindParam(":id_formation", $entity_id);
            $stmt_modules->execute();
            
            if ($stmt_modules->rowCount() > 0) {
                $formation_modules = $stmt_modules->fetchAll(PDO::FETCH_ASSOC);
                $dependencies['modules'] = $formation_modules;
            }
            break;
            
        case 'session':
            // Vérifier les inscriptions liées à cette session
            $query_inscriptions = "SELECT fi.id_inscription, s.nom, s.prenom, s.email, fi.date_inscription, fi.statut_paiement
                                 FROM fiche_inscription fi 
                                 JOIN stagiaire s ON fi.id_stagiaire = s.id_stagiaire
                                 WHERE fi.id_session = :id_session";
            $stmt_inscriptions = $db->prepare($query_inscriptions);
            $stmt_inscriptions->bindParam(":id_session", $entity_id);
            $stmt_inscriptions->execute();
            
            if ($stmt_inscriptions->rowCount() > 0) {
                $session_inscriptions = $stmt_inscriptions->fetchAll(PDO::FETCH_ASSOC);
                $dependencies['inscriptions'] = $session_inscriptions;
            }
            break;
            
        default:
            header("Content-Type: application/json");
            echo json_encode(["error" => "Type d'entité non pris en charge"]);
            exit();
    }
    
    // Renvoyer les dépendances trouvées
    header("Content-Type: application/json");
    echo json_encode([
        "has_dependencies" => !empty($dependencies),
        "dependencies" => $dependencies
    ]);
    
} catch (PDOException $e) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Erreur de base de données: " . $e->getMessage()]);
}
?>