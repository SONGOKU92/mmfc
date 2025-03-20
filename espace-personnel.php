<?php
session_start();
require_once 'config.php';

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialisation de la base de données
$database = new Database();
$db = $database->getConnection();
$user = new User($db);

// Si l'utilisateur est déjà connecté, rediriger vers le tableau de bord approprié
if(isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'stagiaire';
    
    switch($role) {
        case 'admin':
            header("Location: admin-contact.php");
            break;
        case 'secretaire':
            header("Location: secretaire-dashboard.php");
            break;
        case 'formateur':
            header("Location: formateur-dashboard.php");
            break;
        case 'stagiaire':
        default:
            header("Location: dashboard.php");
            break;
    }
    exit();
}

// Variables pour les messages d'erreur/succès
$loginError = '';
$registerError = '';
$successMessage = '';
$debugMessage = ''; // Message de débogage

// Traitement de la connexion
if(isset($_POST['login-submit'])) {
    $email = htmlspecialchars($_POST['login-email']);
    $password = $_POST['login-password'];
    
    // Vérifier si l'utilisateur existe dans la table users
    $verify_query = "SELECT COUNT(*) as count FROM users WHERE email = :email";
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->bindParam(':email', $email);
    $verify_stmt->execute();
    $user_exists = $verify_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    $debugMessage .= "Email saisi: $email<br>";
    $debugMessage .= "L'utilisateur existe dans la table users: " . ($user_exists ? 'Oui' : 'Non') . "<br>";
    
    // Si l'utilisateur n'existe pas dans users mais pourrait exister dans stagiaire
    if (!$user_exists) {
        $stagiaire_query = "SELECT COUNT(*) as count FROM stagiaire WHERE email = :email";
        $stagiaire_stmt = $db->prepare($stagiaire_query);
        $stagiaire_stmt->bindParam(':email', $email);
        $stagiaire_stmt->execute();
        $stagiaire_exists = $stagiaire_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        $debugMessage .= "L'utilisateur existe dans la table stagiaire: " . ($stagiaire_exists ? 'Oui' : 'Non') . "<br>";
        
        if ($stagiaire_exists) {
            $debugMessage .= "Problème détecté: L'utilisateur existe dans la table stagiaire mais pas dans users<br>";
        }
    }
    
    $result = $user->login($email, $password);
    if($result) {
        // La méthode login() stocke déjà les informations dans la session
        // Redirection basée sur le rôle
        switch($_SESSION['user_role']) {
            case 'admin':
                header("Location: admin-contact.php");
                break;
            case 'secretaire':
                header("Location: secretaire-dashboard.php");
                break;
            case 'formateur':
                header("Location: formateur-dashboard.php");
                break;
            case 'stagiaire':
            default:
                header("Location: dashboard.php");
                break;
        }
        exit();
    } else {
        $loginError = "Email ou mot de passe incorrect.";
        $debugMessage .= "La connexion a échoué.<br>";
        
        // Vérifier si l'email existe mais que le mot de passe est incorrect
        if ($user_exists) {
            $debugMessage .= "L'email existe, mais le mot de passe est probablement incorrect.<br>";
        } else {
            $debugMessage .= "L'email n'existe pas dans la table users.<br>";
        }
    }
}

// Traitement de l'inscription
if(isset($_POST['register-submit'])) {
    $fullname = htmlspecialchars($_POST['register-name']);
    $email = htmlspecialchars($_POST['register-email']);
    $password = $_POST['register-password'];
    $confirm_password = $_POST['register-confirm'];
    
    $debugMessage .= "Tentative d'inscription pour: $email<br>";
    
    if($password !== $confirm_password) {
        $registerError = "Les mots de passe ne correspondent pas.";
    } elseif(strlen($password) < 8) {
        $registerError = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif($user->emailExists($email)) {
        $registerError = "Cet email est déjà utilisé.";
        $debugMessage .= "L'email existe déjà dans la table users.<br>";
    } else {
        // Inscription dans la table users
        if($user->register($fullname, $email, $password)) {
            $debugMessage .= "Inscription réussie dans la table users.<br>";
            
            // Créer un stagiaire dans la table stagiaire
            try {
                // Diviser le nom complet en nom et prénom
                $nameParts = explode(' ', $fullname, 2);
                $prenom = $nameParts[0];
                $nom = isset($nameParts[1]) ? $nameParts[1] : '';
                
                // Récupérer l'ID utilisateur
                $query_user = "SELECT id FROM users WHERE email = :email";
                $stmt_user = $db->prepare($query_user);
                $stmt_user->bindParam(':email', $email);
                $stmt_user->execute();
                $user_row = $stmt_user->fetch(PDO::FETCH_ASSOC);
                $user_id = $user_row['id'] ?? null;
                
                $debugMessage .= "ID utilisateur récupéré: " . ($user_id ? $user_id : 'Non trouvé') . "<br>";
                
                // Insérer dans la table stagiaire avec l'id_user si disponible
                $query = "INSERT INTO stagiaire (nom, prenom, email, tel, adresse, id_user) 
                          VALUES (:nom, :prenom, :email, '', '', :id_user)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':nom', $nom);
                $stmt->bindParam(':prenom', $prenom);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':id_user', $user_id);
                $stmt->execute();
                
                $id_stagiaire = $db->lastInsertId();
                $debugMessage .= "Création du stagiaire réussie avec ID: $id_stagiaire<br>";
                
                $successMessage = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
            } catch (PDOException $e) {
                error_log("Erreur lors de la création du stagiaire: " . $e->getMessage());
                $registerError = "Une erreur est survenue lors de l'inscription dans la table stagiaire.";
                $debugMessage .= "Erreur lors de la création du stagiaire: " . $e->getMessage() . "<br>";
            }
        } else {
            $registerError = "Une erreur est survenue lors de l'inscription.";
            $debugMessage .= "Erreur lors de l'inscription dans la table users.<br>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MFC - Espace personnel - Connexion et inscription">
    <title>MFC - Espace Personnel | Formation Professionnelle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style-complementaire.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <style>
        .auth-container {
            max-width: 500px;
            margin: 2rem auto;
            background: var(--white);
            border-radius: 1rem;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .auth-tabs {
            display: flex;
            border-bottom: 2px solid var(--gray-100);
        }

        .auth-tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            background: var(--gray-50);
            border: none;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-light);
            transition: all 0.3s;
        }

        .auth-tab.active {
            background: var(--white);
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            margin-bottom: -2px;
        }

        .auth-form {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
        }

        .password-field {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-light);
        }

        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .submit-btn:hover {
            background: var(--secondary-color);
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .alert-error {
            background: var(--error);
            color: var(--white);
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .alert-debug {
            background-color: #f4f4f5;
            color: #18181b;
            font-family: monospace;
            white-space: pre-wrap;
        }

        .auth-links {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
            color: var(--text-light);
        }

        .auth-links a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s;
        }

        .auth-links a:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }

        .separator {
            margin: 1.5rem 0;
            text-align: center;
            position: relative;
        }

        .separator::before,
        .separator::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 45%;
            height: 1px;
            background: var(--gray-200);
        }

        .separator::before { left: 0; }
        .separator::after { right: 0; }

        .social-login {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .social-btn {
            padding: 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--white);
            text-decoration: none;
            color: var(--text-color);
        }

        .social-btn:hover {
            background: var(--gray-50);
            border-color: var(--primary-color);
        }
        
        /* Custom checkbox style */
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .checkbox-container input[type="checkbox"] {
            width: auto;
            margin-right: 0.5rem;
        }
        
        .checkbox-container label {
            display: inline;
            margin-bottom: 0;
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
                <h1>Espace Personnel</h1>
                <p>Accédez à votre espace pour suivre vos formations et gérer votre compte</p>
            </div>
        </section>

        <?php if($successMessage): ?>
            <div class="alert alert-success" style="max-width: 500px; margin: 2rem auto;">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($debugMessage)): ?>
            <div class="alert alert-debug" style="max-width: 500px; margin: 2rem auto;">
                <strong>Informations de débogage:</strong><br>
                <?php echo $debugMessage; ?>
            </div>
        <?php endif; ?>

        <div class="auth-container">
            <div class="auth-tabs">
                <button class="auth-tab <?php echo !$registerError ? 'active' : ''; ?>" onclick="switchTab('login')">Connexion</button>
                <button class="auth-tab <?php echo $registerError ? 'active' : ''; ?>" onclick="switchTab('register')">Inscription</button>
            </div>

            <!-- Formulaire de Connexion -->
            <form id="loginForm" class="auth-form" method="POST" action="" style="display: <?php echo $registerError ? 'none' : 'block'; ?>;">
                <?php if($loginError): ?>
                    <div class="alert alert-error">
                        <?php echo $loginError; ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="login-email" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="login-password">Mot de passe</label>
                    <div class="password-field">
                        <input type="password" id="login-password" name="login-password" required autocomplete="off">
                        <i class="fas fa-eye password-toggle" onclick="togglePasswordVisibility('login-password', this)"></i>
                    </div>
                </div>

                <button type="submit" name="login-submit" class="submit-btn">Se connecter</button>

                <div class="auth-links">
                    <a href="reset-password.php">Mot de passe oublié ?</a>
                </div>

                <div class="separator">ou</div>

                <div class="social-login">
                    <a href="#" class="social-btn">
                        <i class="fab fa-google"></i>
                        Google
                    </a>
                    <a href="#" class="social-btn">
                        <i class="fab fa-facebook"></i>
                        Facebook
                    </a>
                </div>
            </form>

            <!-- Formulaire d'Inscription -->
            <form id="registerForm" class="auth-form" method="POST" action="" style="display: <?php echo $registerError ? 'block' : 'none'; ?>;">
                <?php if($registerError): ?>
                    <div class="alert alert-error">
                        <?php echo $registerError; ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="register-name">Nom complet</label>
                    <input type="text" id="register-name" name="register-name" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="register-email">Email</label>
                    <input type="email" id="register-email" name="register-email" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="register-password">Mot de passe</label>
                    <div class="password-field">
                        <input type="password" id="register-password" name="register-password" required minlength="8" autocomplete="new-password">
                        <i class="fas fa-eye password-toggle" onclick="togglePasswordVisibility('register-password', this)"></i>
                    </div>
                    <small style="color: var(--text-light);">Le mot de passe doit contenir au moins 8 caractères</small>
                </div>

                <div class="form-group">
                    <label for="register-confirm">Confirmer le mot de passe</label>
                    <div class="password-field">
                        <input type="password" id="register-confirm" name="register-confirm" required minlength="8" autocomplete="new-password">
                        <i class="fas fa-eye password-toggle" onclick="togglePasswordVisibility('register-confirm', this)"></i>
                    </div>
                </div>

                <button type="submit" name="register-submit" class="submit-btn">S'inscrire</button>
            </form>
        </div>
    </main>

    <footer>
        <div class="footer-content">
            <p>&copy; 2024 MFC - Tous droits réservés</p>
        </div>
    </footer>

    <script>
        function switchTab(tab) {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const tabs = document.querySelectorAll('.auth-tab');

            if (tab === 'login') {
                loginForm.style.display = 'block';
                registerForm.style.display = 'none';
                tabs[0].classList.add('active');
                tabs[1].classList.remove('active');
            } else {
                loginForm.style.display = 'none';
                registerForm.style.display = 'block';
                tabs[0].classList.remove('active');
                tabs[1].classList.add('active');
            }
        }

        // Toggle password visibility
        function togglePasswordVisibility(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Désactiver l'autocomplétion des formulaires
        document.addEventListener('DOMContentLoaded', function() {
            // Désactiver l'autocomplétion pour tous les formulaires
            document.querySelectorAll('form').forEach(form => {
                form.setAttribute('autocomplete', 'off');
            });
            
            // Désactiver l'autocomplétion pour tous les champs de formulaire
            document.querySelectorAll('input').forEach(input => {
                input.setAttribute('autocomplete', 'off');
            });
            
            // Pour les mots de passe, utiliser un attribut spécial pour empêcher le remplissage automatique
            document.querySelectorAll('input[type="password"]').forEach(input => {
                input.setAttribute('autocomplete', 'new-password');
            });
        });
    </script>
</body>
</html>