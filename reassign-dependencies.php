<?php
session_start();
require_once "config.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Non autorisé"]);
    exit();
}

// Vérification si c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Méthode non autorisée"]);
    exit();
}

// Récupération des données POST
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Données invalides"]);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    $action = $data['action'] ?? '';
    $entity_type = $data['entity_type'] ?? '';
    $entity_id = intval($data['entity_id'] ?? 0);
    
    if (empty($action) || empty($entity_type) || $entity_id <= 0) {
        header("Content-Type: application/json");
        echo json_encode(["error" => "Paramètres manquants"]);
        exit();
    }
    
    switch ($action) {
        case 'reassign_formateur_sessions':
            $new_formateur_id = intval($data['new_formateur_id'] ?? 0);
            $sessions = $data['sessions'] ?? [];
            
            if ($new_formateur_id <= 0 || empty($sessions)) {
                header("Content-Type: application/json");
                echo json_encode(["error" => "Paramètres invalides pour la réaffectation"]);
                exit();
            }
            
            // Mise à jour des sessions avec le nouveau formateur
            $success = true;
            $db->beginTransaction();
            
            // Log des données reçues pour débogage
            error_log("Sessions à réassigner: " . json_encode($sessions));
            error_log("Nouveau formateur ID: " . $new_formateur_id);
            
            try {
                foreach ($sessions as $session_id) {
                    // Vérification que la session existe
                    $check_query = "SELECT id_session_formation FROM session_formation WHERE id_session_formation = :session_id";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(":session_id", $session_id, PDO::PARAM_INT);
                    $check_stmt->execute();
                    
                    if ($check_stmt->rowCount() === 0) {
                        error_log("Session non trouvée: " . $session_id);
                        continue; // Passer à la session suivante
                    }
                    
                    $query = "UPDATE session_formation SET id_formateur = :new_formateur_id WHERE id_session_formation = :session_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":new_formateur_id", $new_formateur_id, PDO::PARAM_INT);
                    $stmt->bindParam(":session_id", $session_id, PDO::PARAM_INT);
                    
                    $result = $stmt->execute();
                    error_log("Mise à jour de la session $session_id: " . ($result ? "réussie" : "échouée"));
                    
                    if (!$result) {
                        $success = false;
                        error_log("Erreur SQL: " . print_r($stmt->errorInfo(), true));
                        break;
                    }
                }
            } catch (Exception $e) {
                error_log("Exception lors de la réassignation: " . $e->getMessage());
                $success = false;
            }
            
            if ($success) {
                $db->commit();
                header("Content-Type: application/json");
                echo json_encode(["success" => true, "message" => "Les sessions ont été réaffectées avec succès"]);
            } else {
                $db->rollBack();
                header("Content-Type: application/json");
                echo json_encode(["error" => "Erreur lors de la réaffectation des sessions"]);
            }
            break;
            
        case 'cancel_inscriptions':
            $inscriptions = $data['inscriptions'] ?? [];
            
            if (empty($inscriptions)) {
                header("Content-Type: application/json");
                echo json_encode(["error" => "Aucune inscription spécifiée"]);
                exit();
            }
            
            // Annuler les inscriptions spécifiées
            $success = true;
            $db->beginTransaction();
            
            foreach ($inscriptions as $inscription_id) {
                $query = "UPDATE fiche_inscription SET statut_paiement = 'annulé' WHERE id_inscription = :inscription_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":inscription_id", $inscription_id);
                
                if (!$stmt->execute()) {
                    $success = false;
                    break;
                }
            }
            
            if ($success) {
                $db->commit();
                header("Content-Type: application/json");
                echo json_encode(["success" => true, "message" => "Les inscriptions ont été annulées avec succès"]);
            } else {
                $db->rollBack();
                header("Content-Type: application/json");
                echo json_encode(["error" => "Erreur lors de l'annulation des inscriptions"]);
            }
            break;
            
        case 'deactivate_user':
            // Désactiver l'utilisateur au lieu de le supprimer
            $query = "UPDATE users SET is_active = 0 WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":user_id", $entity_id);
            
            if ($stmt->execute()) {
                header("Content-Type: application/json");
                echo json_encode(["success" => true, "message" => "L'utilisateur a été désactivé avec succès"]);
            } else {
                header("Content-Type: application/json");
                echo json_encode(["error" => "Erreur lors de la désactivation de l'utilisateur"]);
            }
            break;
            
        default:
            header("Content-Type: application/json");
            echo json_encode(["error" => "Action non prise en charge"]);
            exit();
    }
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    header("Content-Type: application/json");
    echo json_encode(["error" => "Erreur de base de données: " . $e->getMessage()]);
}
?>