<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

// Vérification si l'utilisateur est connecté et a le rôle d'administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Accès non autorisé']);
    exit();
}

// Vérification de l'ID du contact
$contact_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($contact_id <= 0) {
    echo json_encode(['error' => 'ID de contact invalide']);
    exit();
}

// Initialisation de la base de données
$database = new Database();
$db = $database->getConnection();

try {
    // Récupération des détails du contact
    $query = "SELECT * FROM prisedecontact WHERE id_contact = :id_contact";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_contact', $contact_id);
    $stmt->execute();
    
    if ($contact = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['contact' => $contact]);
    } else {
        echo json_encode(['error' => 'Contact non trouvé']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}