<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MFC - Découvrez nos formations en HTML, Java, bureautique et cybersécurité">
    <title>MFC - Nos Formations | Formation Professionnelle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style-complementaire.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <style>
        .formations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .formation-card {
            background: var(--white);
            border-radius: 1rem;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.3s;
        }

        .formation-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }

        .formation-image {
            height: 200px;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .formation-image i {
            font-size: 4rem;
            color: var(--white);
        }

        .formation-content {
            padding: 2rem;
        }

        .formation-content h3 {
            color: var(--secondary-color);
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .formation-content p {
            color: var(--text-light);
            margin-bottom: 1.5rem;
        }

        .formation-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        .formation-duration {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-light);
        }

        .btn-details {
            background: var(--primary-color);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .btn-details:hover {
            background: var(--secondary-color);
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
                <h1>Nos Formations</h1>
                <p>Découvrez nos parcours de formation adaptés à vos objectifs professionnels</p>
            </div>
        </section>

        <section>
            <div class="formations-grid">
                <article class="formation-card">
                    <div class="formation-image">
                        <i class="fas fa-code"></i>
                    </div>
                    <div class="formation-content">
                        <h3>Développement HTML/CSS</h3>
                        <p>Maîtrisez les fondamentaux du développement web et créez des sites internet modernes et responsifs. Formation idéale pour débuter dans le web.</p>
                        <div class="formation-details">
                            <div class="formation-duration">
                                <i class="far fa-clock"></i>
                                <span>3 mois</span>
                            </div>
                            <a href="html.html" class="btn-details">En savoir plus</a>
                        </div>
                    </div>
                </article>

                <article class="formation-card">
                    <div class="formation-image">
                        <i class="fab fa-java"></i>
                    </div>
                    <div class="formation-content">
                        <h3>Programmation Java</h3>
                        <p>Développez des applications robustes avec Java. Du langage aux frameworks professionnels, devenez un développeur Java confirmé.</p>
                        <div class="formation-details">
                            <div class="formation-duration">
                                <i class="far fa-clock"></i>
                                <span>6 mois</span>
                            </div>
                            <a href="java.html" class="btn-details">En savoir plus</a>
                        </div>
                    </div>
                </article>

                <article class="formation-card">
                    <div class="formation-image">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <div class="formation-content">
                        <h3>Bureautique</h3>
                        <p>Perfectionnez-vous sur les outils bureautiques essentiels. Word, Excel, PowerPoint : maîtrisez la suite Office pour plus d'efficacité.</p>
                        <div class="formation-details">
                            <div class="formation-duration">
                                <i class="far fa-clock"></i>
                                <span>2 mois</span>
                            </div>
                            <a href="bureautique.html" class="btn-details">En savoir plus</a>
                        </div>
                    </div>
                </article>

                <article class="formation-card">
                    <div class="formation-image">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="formation-content">
                        <h3>Cybersécurité</h3>
                        <p>Protégez les systèmes d'information et anticipez les menaces. Formez-vous aux dernières techniques de sécurité informatique.</p>
                        <div class="formation-details">
                            <div class="formation-duration">
                                <i class="far fa-clock"></i>
                                <span>4 mois</span>
                            </div>
                            <a href="cyber.html" class="btn-details">En savoir plus</a>
                        </div>
                    </div>
                </article>
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