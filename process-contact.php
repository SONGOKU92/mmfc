<?php
// Inclusion de la configuration de la base de données
require_once 'config.php';

// Initialisation des variables pour les messages
$success = false;
$error = '';

// Vérification si le formulaire a été soumis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Récupération et nettoyage des données du formulaire
    $nom = isset($_POST['nom']) ? htmlspecialchars(trim($_POST['nom'])) : '';
    $email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
    $sujet = isset($_POST['sujet']) ? htmlspecialchars(trim($_POST['sujet'])) : '';
    $message = isset($_POST['message']) ? htmlspecialchars(trim($_POST['message'])) : '';
    
    // Validation des données
    if (empty($nom)) {
        $error = "Le nom est obligatoire.";
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Une adresse email valide est requise.";
    } elseif (empty($sujet)) {
        $error = "Le sujet est obligatoire.";
    } elseif (empty($message)) {
        $error = "Le message est obligatoire.";
    } else {
        // Si tout est valide, connexion à la base de données
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Préparation de la requête d'insertion
            $query = "INSERT INTO prisedecontact (nom, email, sujet, message) 
                      VALUES (:nom, :email, :sujet, :message)";
            
            $stmt = $db->prepare($query);
            
            // Bind des paramètres
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':sujet', $sujet);
            $stmt->bindParam(':message', $message);
            
            // Exécution de la requête
            if ($stmt->execute()) {
                $success = true;
                
                // Envoi d'un email de notification à l'administrateur (optionnel)
                $to = "contact@mfc-formation.fr"; // Remplacer par l'email de l'administrateur
                $subject = "Nouvelle demande de contact sur MFC";
                $message_email = "Nouvelle demande de contact de $nom ($email)\n\n";
                $message_email .= "Sujet: $sujet\n\n";
                $message_email .= "Message: $message\n\n";
                $message_email .= "Date: " . date("Y-m-d H:i:s") . "\n";
                
                $headers = "From: noreply@mfc-formation.fr\r\n";
                
                // Désactivé pour le moment pour éviter les problèmes lors des tests
                // mail($to, $subject, $message_email, $headers);
                
            } else {
                $error = "Une erreur est survenue lors de l'enregistrement de votre message.";
            }
            
        } catch (PDOException $e) {
            $error = "Erreur de connexion à la base de données: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MFC - Contactez-nous pour toute information">
    <title>MFC - Demande de contact | Formation Professionnelle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style-complementaire.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <style>
        .message-box {
            max-width: 800px;
            margin: 3rem auto;
            padding: 2rem;
            border-radius: 0.5rem;
            background-color: #fff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .success-message {
            color: #0f5132;
            background-color: #d1e7dd;
            border: 1px solid #badbcc;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .error-message {
            color: #842029;
            background-color: #f8d7da;
            border: 1px solid #f5c2c7;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: l.5rem;
        }
        
        .btn-back {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn-back:hover {
            background-color: var(--secondary-color);
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
                <h1>Demande de contact</h1>
                <p>Nous traitons votre demande</p>
            </div>
        </section>
        
        <div class="message-box">
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle fa-3x" style="margin-bottom: 1rem;"></i>
                    <h2>Message envoyé avec succès !</h2>
                    <p>Merci d'avoir pris contact avec nous. Nous avons bien reçu votre message et nous vous répondrons dans les plus brefs délais.</p>
                </div>
            <?php elseif (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle fa-3x" style="margin-bottom: 1rem;"></i>
                    <h2>Une erreur est survenue</h2>
                    <p><?php echo $error; ?></p>
                </div>
            <?php else: ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle fa-3x" style="margin-bottom: 1rem;"></i>
                    <h2>Accès invalide</h2>
                    <p>Veuillez remplir le formulaire de contact pour nous envoyer un message.</p>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 2rem;">
                <a href="contact.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour au formulaire de contact
                </a>
                <a href="index.php" class="btn-back" style="margin-left: 1rem;">
                    <i class="fas fa-home"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </main>

    <footer>
        <div class="footer-content">
            <p>&copy; 2024 MFC - Tous droits réservés</p>
        </div>
    </footer>
</body>
</html>