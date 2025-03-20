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
?>