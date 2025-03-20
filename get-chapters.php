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
$module_id = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;
$stagiaire_id = isset($_GET['stagiaire_id']) ? intval($_GET['stagiaire_id']) : 0;

if (!$module_id || !$stagiaire_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres manquants']);
    exit();
}

// Initialisation de la base de données
$database = new Database();
$db = $database->getConnection();
$user = new User($db);

// Récupération des chapitres
try {
    $query = "SELECT m.id_formation FROM module m WHERE m.id_module = :module_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':module_id', $module_id);
    $stmt->execute();
    $formation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$formation) {
        throw new Exception('Module non trouvé');
    }
    
    $formation_name = '';
    $query = "SELECT nom_formation FROM formation WHERE id_formation = :formation_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':formation_id', $formation['id_formation']);
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $formation_name = $row['nom_formation'];
    }
    
    $chapters = $user->getCourseChapters($module_id, $stagiaire_id);
    
    // Retour des données au format JSON
    header('Content-Type: application/json');
    echo json_encode(['chapters' => $chapters]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>