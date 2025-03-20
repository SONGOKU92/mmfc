<?php

class Database {
    private $host = "localhost";
    private $db_name = "mfc_db";
    private $username = "root";
    private $password = "";
    private $conn = null;

    public function getConnection() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            echo "Erreur de connexion : " . $e->getMessage();
        }
        return $this->conn;
    }
}

// Classe pour gérer les utilisateurs
class User {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getUserFormations($user_id) {
        try {
            $query = "SELECT 
                f.id_formation,
                f.nom_formation,
                f.duree_formation,
                s.date_debut,
                s.date_fin,
                s.horaire,
                st.id_stagiaire,
                CASE 
                    WHEN CURRENT_DATE > s.date_fin THEN 100
                    WHEN CURRENT_DATE < s.date_debut THEN 0
                    ELSE
                        ROUND(
                            (DATEDIFF(CURRENT_DATE, s.date_debut) * 100.0) / 
                            NULLIF(DATEDIFF(s.date_fin, s.date_debut), 0)
                        )
                END as progression
            FROM users u
            JOIN stagiaire st ON st.email = u.email
            JOIN fiche_inscription fi ON fi.id_stagiaire = st.id_stagiaire
            JOIN session_formation s ON s.id_session_formation = fi.id_session
            JOIN formation f ON f.id_formation = s.id_formation
            WHERE u.id = :user_id
            ORDER BY s.date_debut DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();
            
            $formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Si nous avons la nouvelle table de progression, mettons à jour la progression réelle
            if ($this->tableExists('progression_stagiaire') && $this->tableExists('module') && $this->tableExists('chapitre')) {
                foreach ($formations as &$formation) {
                    // Récupérer la progression réelle à partir de la vue ou calculer
                    try {
                        if ($this->viewExists('v_progression_formation')) {
                            // Utiliser la vue si elle existe
                            $progressQuery = "SELECT pourcentage_progression 
                                             FROM v_progression_formation 
                                             WHERE id_stagiaire = :id_stagiaire 
                                               AND id_formation = :id_formation";
                            $progressStmt = $this->conn->prepare($progressQuery);
                            $progressStmt->bindParam(":id_stagiaire", $formation['id_stagiaire']);
                            $progressStmt->bindParam(":id_formation", $formation['id_formation']);
                            $progressStmt->execute();
                            
                            if ($progressRow = $progressStmt->fetch(PDO::FETCH_ASSOC)) {
                                $formation['progression'] = $progressRow['pourcentage_progression'] ?? $formation['progression'];
                            }
                        } else {
                            // Calculer manuellement si la vue n'existe pas
                            $progressQuery = "SELECT 
                                COUNT(DISTINCT ch.id_chapitre) AS total_chapitres,
                                COUNT(DISTINCT CASE WHEN ps.statut = 'termine' THEN ps.id_chapitre END) AS chapitres_termines
                            FROM module m
                            JOIN chapitre ch ON m.id_module = ch.id_module
                            LEFT JOIN progression_stagiaire ps ON ch.id_chapitre = ps.id_chapitre AND ps.id_stagiaire = :id_stagiaire
                            WHERE m.id_formation = :id_formation";
                            
                            $progressStmt = $this->conn->prepare($progressQuery);
                            $progressStmt->bindParam(":id_stagiaire", $formation['id_stagiaire']);
                            $progressStmt->bindParam(":id_formation", $formation['id_formation']);
                            $progressStmt->execute();
                            
                            if ($progressRow = $progressStmt->fetch(PDO::FETCH_ASSOC)) {
                                $totalChapitres = $progressRow['total_chapitres'];
                                $chapitresTermines = $progressRow['chapitres_termines'];
                                
                                if ($totalChapitres > 0) {
                                    $formation['progression'] = round(($chapitresTermines / $totalChapitres) * 100);
                                }
                            }
                        }
                        
                        // Pour les formations en cours (qui devraient avoir au moins 1% de progression)
                        // Si la progression est calculée à 0 mais que la date de début est passée, forcer à 1%
                        if ($formation['progression'] == 0 && strtotime($formation['date_debut']) <= time()) {
                            $formation['progression'] = 1;
                        }
                        
                    } catch (PDOException $e) {
                        // En cas d'erreur, conserver la progression basée sur les dates
                        error_log("Erreur lors du calcul de la progression: " . $e->getMessage());
                    }
                }
            }
            
            return $formations;
        } catch(PDOException $e) {
            error_log("Erreur lors de la récupération des formations: " . $e->getMessage());
            return [];
        }
    }

    // Inscription d'un nouvel utilisateur
    public function register($fullname, $email, $password) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                    (fullname, email, password, role) 
                    VALUES (:fullname, :email, :password, 'stagiaire')";

            $stmt = $this->conn->prepare($query);

            // Hashage du mot de passe
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Liaison des paramètres
            $stmt->bindParam(":fullname", $fullname);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":password", $hashed_password);

            if($stmt->execute()) {
                return true;
            }
            return false;
        } catch(PDOException $e) {
            echo "Erreur d'inscription : " . $e->getMessage();
            return false;
        }
    }

    // Connexion d'un utilisateur
    public function login($email, $password) {
        try {
            // Modification pour ignorer la condition is_active qui peut causer des problèmes
            $query = "SELECT id, fullname, email, password, role 
                    FROM " . $this->table_name . " 
                    WHERE email = :email";
                    // Nous avons retiré "AND is_active = true"
    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
    
            if($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Vérification du mot de passe - vérifie si le mot de passe est haché ou non
                $password_matches = false;
                
                // Si le mot de passe commence par $2y$, c'est un hachage bcrypt (password_hash)
                if (strpos($row['password'], '$2y$') === 0) {
                    $password_matches = password_verify($password, $row['password']);
                } 
                // Sinon, comparaison directe (temporaire, à des fins de débogage uniquement)
                else {
                    $password_matches = ($password === $row['password']);
                }
                
                if ($password_matches) {
                    // Mise à jour de la dernière connexion
                    $this->updateLastLogin($row['id']);
                    
                    // Stockage des informations complètes dans la session
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['user_name'] = $row['fullname'];
                    $_SESSION['user_email'] = $row['email'];
                    $_SESSION['user_role'] = $row['role'];
                    
                    return $row;
                }
            }
            return false;
        } catch(PDOException $e) {
            echo "Erreur de connexion : " . $e->getMessage();
            return false;
        }
    }

    // Mise à jour de la dernière connexion
    private function updateLastLogin($user_id) {
        $query = "UPDATE " . $this->table_name . " 
                SET last_login = NOW() 
                WHERE id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
    }

    // Vérification si l'email existe déjà
    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table_name . " 
                WHERE email = :email";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
    
    // Vérification du mot de passe actuel
    public function verifyPassword($user_id, $password) {
        try {
            $query = "SELECT password FROM " . $this->table_name . " 
                    WHERE id = :user_id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();

            if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return password_verify($password, $row['password']);
            }
            return false;
        } catch(PDOException $e) {
            error_log("Erreur lors de la vérification du mot de passe: " . $e->getMessage());
            return false;
        }
    }

    // Mise à jour du profil utilisateur
    public function updateProfile($user_id, $fullname, $email, $new_password = null) {
        try {
            // Préparation de la requête de base
            $query = "UPDATE " . $this->table_name . " 
                    SET fullname = :fullname, email = :email";
            
            // Si un nouveau mot de passe est fourni, l'ajouter à la requête
            $params = [
                ":user_id" => $user_id,
                ":fullname" => $fullname,
                ":email" => $email
            ];
            
            if($new_password !== null) {
                $query .= ", password = :password";
                $params[":password"] = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            $query .= " WHERE id = :user_id";
            
            $stmt = $this->conn->prepare($query);
            
            // Exécuter la requête avec les paramètres
            if ($stmt->execute($params)) {
                // Si l'email a changé, mettre à jour l'email dans la table stagiaire si elle existe
                if ($email != $_SESSION['user_email'] && $this->tableExists('stagiaire')) {
                    try {
                        $queryStag = "UPDATE stagiaire 
                                    SET email = :new_email 
                                    WHERE email = :old_email";
                        $stmtStag = $this->conn->prepare($queryStag);
                        $stmtStag->bindParam(":new_email", $email);
                        $stmtStag->bindParam(":old_email", $_SESSION['user_email']);
                        $stmtStag->execute();
                    } catch(PDOException $e) {
                        error_log("Erreur lors de la mise à jour de l'email dans la table stagiaire: " . $e->getMessage());
                    }
                }
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Erreur lors de la mise à jour du profil: " . $e->getMessage());
            return false;
        }
    }
    
    // Récupération des modules d'une formation
    public function getCourseModules($formation_id) {
        try {
            if ($this->tableExists('module')) {
                // Si la table module existe, récupérer les vrais modules
                $query = "SELECT id_module as id, titre as title, description, icone as icon, ordre as order_num 
                        FROM module 
                        WHERE id_formation = :formation_id 
                        ORDER BY ordre";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":formation_id", $formation_id);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Sinon, retourner des données fictives en fonction du type de formation
                $modules = [];
                
                // Récupérer le nom de la formation
                $query = "SELECT nom_formation FROM formation WHERE id_formation = :formation_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":formation_id", $formation_id);
                $stmt->execute();
                $formation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$formation) {
                    return $modules;
                }
                
                // Structure des modules en fonction du type de formation
                switch($formation['nom_formation']) {
                    case 'Développement HTML/CSS':
                        $modules = [
                            ['id' => 1, 'title' => 'Les bases du HTML', 'icon' => 'fas fa-code'],
                            ['id' => 2, 'title' => 'CSS et mise en forme', 'icon' => 'fas fa-paint-brush'],
                            ['id' => 3, 'title' => 'Responsive Design', 'icon' => 'fas fa-mobile-alt'],
                            ['id' => 4, 'title' => 'Projet professionnel', 'icon' => 'fas fa-project-diagram']
                        ];
                        break;
                        
                    case 'Programmation Java':
                        $modules = [
                            ['id' => 1, 'title' => 'Fondamentaux Java', 'icon' => 'fab fa-java'],
                            ['id' => 2, 'title' => 'Java Avancé', 'icon' => 'fas fa-code-branch'],
                            ['id' => 3, 'title' => 'Frameworks & Outils', 'icon' => 'fas fa-tools'],
                            ['id' => 4, 'title' => 'Projet Professionnel', 'icon' => 'fas fa-laptop-code']
                        ];
                        break;
                        
                    case 'Bureautique':
                        $modules = [
                            ['id' => 1, 'title' => 'Microsoft Word', 'icon' => 'fas fa-file-word'],
                            ['id' => 2, 'title' => 'Microsoft Excel', 'icon' => 'fas fa-file-excel'],
                            ['id' => 3, 'title' => 'PowerPoint', 'icon' => 'fas fa-file-powerpoint'],
                            ['id' => 4, 'title' => 'Outlook', 'icon' => 'fas fa-envelope']
                        ];
                        break;
                        
                    case 'Cybersécurité':
                        $modules = [
                            ['id' => 1, 'title' => 'Fondamentaux de la Cybersécurité', 'icon' => 'fas fa-shield-alt'],
                            ['id' => 2, 'title' => 'Protection et Défense', 'icon' => 'fas fa-user-shield'],
                            ['id' => 3, 'title' => 'Tests d\'intrusion', 'icon' => 'fas fa-bug'],
                            ['id' => 4, 'title' => 'Gestion de la Sécurité', 'icon' => 'fas fa-tasks']
                        ];
                        break;
                }
                
                return $modules;
            }
        } catch(PDOException $e) {
            error_log("Erreur lors de la récupération des modules: " . $e->getMessage());
            return [];
        }
    }
    
    // Récupération des chapitres d'un module avec statut pour un stagiaire
    public function getCourseChapters($module_id, $stagiaire_id) {
        try {
            if ($this->tableExists('chapitre') && $this->tableExists('progression_stagiaire')) {
                // Récupérer les chapitres réels avec leur statut de progression
                $query = "SELECT 
                            ch.id_chapitre as id, 
                            ch.titre as title, 
                            ch.duree_estimee as duration,
                            ch.ordre as order_num,
                            COALESCE(ps.statut, 'non_commence') as status
                        FROM 
                            chapitre ch
                        LEFT JOIN 
                            progression_stagiaire ps ON ch.id_chapitre = ps.id_chapitre AND ps.id_stagiaire = :stagiaire_id
                        WHERE 
                            ch.id_module = :module_id
                        ORDER BY 
                            ch.ordre";
                            
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":module_id", $module_id);
                $stmt->bindParam(":stagiaire_id", $stagiaire_id);
                $stmt->execute();
                
                $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Convertir les statuts de la BD aux statuts utilisés dans l'interface
                foreach ($chapters as &$chapter) {
                    switch ($chapter['status']) {
                        case 'termine':
                            $chapter['status'] = 'completed';
                            break;
                        case 'en_cours':
                            $chapter['status'] = 'in-progress';
                            break;
                        default:
                            $chapter['status'] = 'not-started';
                    }
                }
                
                return $chapters;
            } else {
                // Données fictives si les tables n'existent pas
                return [
                    ['id' => 1, 'title' => 'Introduction', 'status' => 'completed'],
                    ['id' => 2, 'title' => 'Premier chapitre', 'status' => 'completed'],
                    ['id' => 3, 'title' => 'Deuxième chapitre', 'status' => 'in-progress'],
                    ['id' => 4, 'title' => 'Chapitre avancé', 'status' => 'not-started']
                ];
            }
        } catch(PDOException $e) {
            error_log("Erreur lors de la récupération des chapitres: " . $e->getMessage());
            return [];
        }
    }
    
    // Mise à jour du statut d'un chapitre pour un stagiaire
    public function updateChapterStatus($stagiaire_id, $chapitre_id, $statut) {
        try {
            if (!$this->tableExists('progression_stagiaire')) {
                return false;
            }
            
            // Vérifier si une entrée existe déjà
            $checkQuery = "SELECT id_progression FROM progression_stagiaire 
                          WHERE id_stagiaire = :stagiaire_id AND id_chapitre = :chapitre_id";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(":stagiaire_id", $stagiaire_id);
            $checkStmt->bindParam(":chapitre_id", $chapitre_id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                // Mettre à jour l'entrée existante
                $query = "UPDATE progression_stagiaire 
                          SET statut = :statut 
                          WHERE id_stagiaire = :stagiaire_id AND id_chapitre = :chapitre_id";
            } else {
                // Créer une nouvelle entrée
                $query = "INSERT INTO progression_stagiaire 
                          (id_stagiaire, id_chapitre, statut) 
                          VALUES (:stagiaire_id, :chapitre_id, :statut)";
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":stagiaire_id", $stagiaire_id);
            $stmt->bindParam(":chapitre_id", $chapitre_id);
            $stmt->bindParam(":statut", $statut);
            
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Erreur lors de la mise à jour du statut du chapitre: " . $e->getMessage());
            return false;
        }
    }
    
    // Vérifier si une table existe dans la base de données
    private function tableExists($table_name) {
        try {
            $query = "SHOW TABLES LIKE :table_name";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':table_name', $table_name);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            error_log("Erreur lors de la vérification de l'existence de la table: " . $e->getMessage());
            return false;
        }
    }
    
    // Vérifier si une vue existe dans la base de données
    private function viewExists($view_name) {
        try {
            $query = "SHOW TABLES LIKE :view_name";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':view_name', $view_name);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            error_log("Erreur lors de la vérification de l'existence de la vue: " . $e->getMessage());
            return false;
        }
    }
}
?>