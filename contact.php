<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MFC - Contactez-nous pour toute information">
    <title>MFC - Contact | Formation Professionnelle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style-complementaire.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <h1>Contactez-nous</h1>
                <p>Une question ? N'hésitez pas à nous contacter</p>
            </div>
        </section>

        <section class="contact-section">
            <div class="contact-form">
                <form action="process-contact.php" method="POST">
                    <div class="form-group">
                        <label for="nom">Nom*</label>
                        <input type="text" id="nom" name="nom" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email*</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="sujet">Sujet*</label>
                        <input type="text" id="sujet" name="sujet" required>
                    </div>

                    <div class="form-group">
                        <label for="message">Message*</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>

                    <button type="submit" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Envoyer
                    </button>
                </form>
            </div>

            <div class="contact-info" style="text-align: center; margin-top: 3rem;">
                <h2>Nos coordonnées</h2>
                <p><i class="fas fa-map-marker-alt"></i> 123 rue de la Formation, 75000 Paris</p>
                <p><i class="fas fa-phone"></i> 01 23 45 67 89</p>
                <p><i class="fas fa-envelope"></i> contact@mfc-formation.fr</p>
            </div>
        </section>
    </main>

    <footer>
        <div class="footer-content">
            <p>&copy; 2024 MFC - Tous droits réservés</p>
        </div>
    </footer>
</body>
</html>