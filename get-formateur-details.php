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
?>