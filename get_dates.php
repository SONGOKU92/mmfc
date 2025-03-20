<?php
// get_dates.php
header('Content-Type: application/json');

try {
    $conn = new PDO("mysql:host=localhost;dbname=mfc_db", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $formation_id = $_GET['formation_id'] ?? null;
    
    if($formation_id) {
        $stmt = $conn->prepare("SELECT date_debut, date_fin 
                               FROM session_formation 
                               WHERE id_formation = ? 
                               AND date_debut >= CURRENT_DATE 
                               ORDER BY date_debut");
        $stmt->execute([$formation_id]);
        $dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($dates);
    } else {
        echo json_encode([]);
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}