<?php
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
    // Récupérer l'ID du formateur
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
?>