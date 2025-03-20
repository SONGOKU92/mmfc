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

try {
    $query = "SELECT 
             f.nom_formation,
             SUM(CASE WHEN ps.statut = 'non_commence' THEN 1 ELSE 0 END) as non_commence,
             SUM(CASE WHEN ps.statut = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
             SUM(CASE WHEN ps.statut = 'termine' THEN 1 ELSE 0 END) as termine
             FROM progression_stagiaire ps
             JOIN chapitre c ON ps.id_chapitre = c.id_chapitre
             JOIN module m ON c.id_module = m.id_module
             JOIN formation f ON m.id_formation = f.id_formation
             GROUP BY f.nom_formation";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $result = [
        "labels" => [],
        "notStarted" => [],
        "inProgress" => [],
        "completed" => []
    ];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result["labels"][] = $row["nom_formation"];
        $result["notStarted"][] = (int)$row["non_commence"];
        $result["inProgress"][] = (int)$row["en_cours"];
        $result["completed"][] = (int)$row["termine"];
    }
    
    header("Content-Type: application/json");
    echo json_encode($result);
} catch (PDOException $e) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Erreur de base de données: " . $e->getMessage()]);
}
?>