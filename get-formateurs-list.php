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
    // Récupérer tous les formateurs disponibles
    $query = "SELECT f.id_formateur, u.fullname, f.specialite 
             FROM formateur f
             JOIN users u ON f.id_user = u.id
             WHERE f.disponible = 1 AND u.is_active = 1
             ORDER BY u.fullname";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $formateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header("Content-Type: application/json");
    echo json_encode(["formateurs" => $formateurs]);
    
} catch (PDOException $e) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Erreur de base de données: " . $e->getMessage()]);
}
?>