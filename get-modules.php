<?php
session_start();
require_once 'config.php';

// Vérification si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Utilisateur non authentifié']);
    exit();
}

// Récupération des paramètres
$formation_id = isset($_GET['formation_id']) ? intval($_GET['formation_id']) : 0;
$stagiaire_id = isset($_GET['stagiaire_id']) ? intval($_GET['stagiaire_id']) : 0;

if (!$formation_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de formation manquant']);
    exit();
}

// Initialisation de la base de données
$database = new Database();
$db = $database->getConnection();
$user = new User($db);

// Récupération des modules
$modules = $user->getCourseModules($formation_id);

// Retour des données au format JSON
header('Content-Type: application/json');
echo json_encode(['modules' => $modules]);
?>