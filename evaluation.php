<?php
class Evaluation {
    private $conn;
    private $table_name = "evaluation";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($id_inscription, $note_generale, $note_contenu, $note_formateur, $commentaire) {
        try {
            // Vérifier si une évaluation existe déjà
            if($this->evaluationExists($id_inscription)) {
                return ['error' => 'Une évaluation existe déjà pour cette inscription'];
            }

            $query = "INSERT INTO " . $this->table_name . "
                    (id_inscription, note_generale, note_contenu, note_formateur, commentaire)
                    VALUES (:id_inscription, :note_generale, :note_contenu, :note_formateur, :commentaire)";

            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(":id_inscription", $id_inscription);
            $stmt->bindParam(":note_generale", $note_generale);
            $stmt->bindParam(":note_contenu", $note_contenu);
            $stmt->bindParam(":note_formateur", $note_formateur);
            $stmt->bindParam(":commentaire", $commentaire);

            if($stmt->execute()) {
                return ['success' => true, 'message' => 'Évaluation créée avec succès'];
            }
            return ['error' => 'Erreur lors de la création de l\'évaluation'];
        } catch(PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getEvaluationsByFormation($id_formation) {
        try {
            $query = "SELECT 
                        e.*,
                        s.nom as stagiaire_nom,
                        s.prenom as stagiaire_prenom,
                        f.nom_formation
                     FROM " . $this->table_name . " e
                     JOIN fiche_inscription fi ON e.id_inscription = fi.id_inscription
                     JOIN stagiaire s ON fi.id_stagiaire = s.id_stagiaire
                     JOIN session_formation sf ON fi.id_session = sf.id_session_formation
                     JOIN formation f ON sf.id_formation = f.id_formation
                     WHERE f.id_formation = :id_formation";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id_formation", $id_formation);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getEvaluationsBySession($id_session) {
        try {
            $query = "SELECT 
                        e.*,
                        s.nom as stagiaire_nom,
                        s.prenom as stagiaire_prenom
                     FROM " . $this->table_name . " e
                     JOIN fiche_inscription fi ON e.id_inscription = fi.id_inscription
                     JOIN stagiaire s ON fi.id_stagiaire = s.id_stagiaire
                     WHERE fi.id_session = :id_session";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id_session", $id_session);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function evaluationExists($id_inscription) {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " 
                 WHERE id_inscription = :id_inscription";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_inscription", $id_inscription);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }
}