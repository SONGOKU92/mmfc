<?php
// Connexion à la base de données
$conn = new PDO("mysql:host=localhost;dbname=mfc_db", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Récupération des formations
$stmt = $conn->query("SELECT * FROM formation");
$formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn->beginTransaction();

        // Insertion du stagiaire
        $stmt = $conn->prepare("INSERT INTO stagiaire (nom, prenom, tel, adresse, email) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([
    $_POST['nom'],
    $_POST['prenom'], 
    $_POST['telephone'],
    $_POST['adresse'],
    $_POST['email']  // Ajoutez un champ email dans le formulaire HTML
]);
        
        $id_stagiaire = $conn->lastInsertId();

        // Calcul de la date de fin basée sur la durée de la formation
        $stmt = $conn->prepare("SELECT duree_formation FROM formation WHERE id_formation = ?");
        $stmt->execute([$_POST['formation']]);
        $formation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $date_debut = $_POST['date_debut'];
        $date_fin = date('Y-m-d', strtotime($date_debut . ' + ' . $formation['duree_formation'] . ' months'));

        // Insertion de la session de formation
        $stmt = $conn->prepare("INSERT INTO session_formation (date_debut, date_fin, id_formation, horaire) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $date_debut,
            $date_fin,
            $_POST['formation'],
            $_POST['horaire']
        ]);

        $id_session = $conn->lastInsertId();

        // Insertion de la fiche d'inscription
        $stmt = $conn->prepare("INSERT INTO fiche_inscription (date_inscription, id_stagiaire, id_session) VALUES (NOW(), ?, ?)");
        $stmt->execute([
            $id_stagiaire,
            $id_session
        ]);
            
        $conn->commit();
        $message = "Inscription réussie !";
        
    } catch(Exception $e) {
        $conn->rollBack();
        $error = "Une erreur est survenue : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MFC - Inscription aux formations</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style-complementaire.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .inscription-form {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <ul>
                <li class="nav-logo">MFC</li>
                <li><a href="index.php">Accueil</a></li>
                <li><a href="formation.php">Formations</a></li>
                <li><a href="financement.php">Financement</a></li>
                <li><a href="espace-personnel.php">Espace Personnel</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="inscription.php" class="btn-primary">Inscription</a></li>
            </ul>
        </nav>
    </header>

    <main>
    <section class="hero-section not-home-hero">
            <div class="hero-content">
                <h1>Inscription à une formation</h1>
                <p>Remplissez le formulaire ci-dessous pour vous inscrire à l'une de nos formations</p>
            </div>
        </section>

        <div class="inscription-form">
            <?php if(isset($message)): ?>
                <div class="alert alert-success">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" id="nom" name="nom" required>
                    </div>

                    <div class="form-group">
                        <label for="prenom">Prénom *</label>
                        <input type="text" id="prenom" name="prenom" required>
                    </div>

                    <div class="form-group">
                        <label for="telephone">Téléphone *</label>
                        <input type="tel" id="telephone" name="telephone" required>
                    </div>

                    <div class="form-group">
                        <label for="adresse">Adresse</label>
                        <input type="text" id="adresse" name="adresse">
                    </div>
                    <div class="form-group">
                   <label for="email">Email *</label>
                  <input type="email" id="email" name="email" required>
             </div>

                    <div class="form-group">
                        <label for="formation">Formation *</label>
                        <select name="formation" id="formation" required>
                            <option value="">Sélectionnez une formation</option>
                            <?php foreach($formations as $formation): ?>
                                <option value="<?php echo $formation['id_formation']; ?>" 
                                        data-duree="<?php echo $formation['duree_formation']; ?>">
                                    <?php echo htmlspecialchars($formation['nom_formation']); ?> - 
                                    <?php echo number_format($formation['prix'], 2, ',', ' '); ?> €
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date_debut">Date de début souhaitée *</label>
                        <input type="date" id="date_debut" name="date_debut" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="horaire">Horaires *</label>
                        <select name="horaire" id="horaire" required>
                            <option value="">Choisir un horaire</option>
                            <option value="9h-12h">Matin (9h-12h)</option>
                            <option value="14h-17h">Après-midi (14h-17h)</option>
                            <option value="9h-17h">Journée complète (9h-17h)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn-primary">Valider l'inscription</button>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <div class="footer-content">
            <p>&copy; 2024 MFC - Tous droits réservés</p>
        </div>
    </footer>

    <script>
    // Empêcher la sélection de dates passées
    const dateInput = document.getElementById('date_debut');
    dateInput.min = new Date().toISOString().split('T')[0];
    
    // Mettre à jour la date minimale si elle est dans le passé
    dateInput.addEventListener('click', function() {
        if (this.value < this.min) {
            this.value = this.min;
        }
    });
    </script>
</body>
</html>