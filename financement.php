<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MFC - Découvrez nos options de financement pour votre formation professionnelle">
    <title>MFC - Solutions de Financement | Formation Professionnelle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style-complementaire.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <style>
        .financement-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .financement-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
            margin-top: 4rem;
        }

        .financement-card {
            background: var(--white);
            border-radius: 1rem;
            box-shadow: var(--shadow);
            padding: 2.5rem;
            text-align: center;
            transition: transform 0.3s;
        }

        .financement-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }

        .financement-icon {
            width: 80px;
            height: 80px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .financement-icon i {
            font-size: 2.5rem;
            color: var(--white);
        }

        .financement-card h3 {
            color: var(--secondary-color);
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .avantages-list {
            text-align: left;
            margin: 1.5rem 0;
        }

        .avantages-list li {
            margin-bottom: 0.75rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            color: var(--text-light);
        }

        .avantages-list i {
            color: var(--success);
            margin-top: 0.25rem;
        }

        .procedure-steps {
            background: var(--gray-50);
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin: 1.5rem 0;
            text-align: left;
        }

        .procedure-steps h4 {
            color: var(--secondary-color);
            margin-bottom: 1rem;
        }

        .procedure-steps ol {
            padding-left: 1.5rem;
            color: var(--text-light);
        }

        .procedure-steps li {
            margin-bottom: 0.5rem;
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
                <h1>Financement</h1>
                <p>Découvrez les options pour financer votre formation et concrétiser votre projet professionnel</p>
            </div>
        </section>

        <section class="financement-container">
            <div class="financement-grid">
                <article class="financement-card">
                    <div class="financement-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3>Financement Pôle Emploi</h3>
                    <p>Bénéficiez d'une prise en charge complète de votre formation par Pôle Emploi</p>
                    
                    <div class="avantages-list">
                        <ul>
                            <li><i class="fas fa-check"></i>Formation 100% financée pour les demandeurs d'emploi</li>
                            <li><i class="fas fa-check"></i>Maintien de vos allocations pendant la formation</li>
                            <li><i class="fas fa-check"></i>Accompagnement personnalisé</li>
                            <li><i class="fas fa-check"></i>Aide à la constitution du dossier</li>
                        </ul>
                    </div>

                    <div class="procedure-steps">
                        <h4>Procédure</h4>
                        <ol>
                            <li>Validation du projet avec votre conseiller</li>
                            <li>Constitution du dossier AIF</li>
                            <li>Étude de votre demande</li>
                            <li>Démarrage de la formation</li>
                        </ol>
                    </div>

                </article>

                <article class="financement-card">
                    <div class="financement-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3>Financement Personnel</h3>
                    <p>Investissez dans votre avenir avec nos solutions de paiement adaptées</p>
                    
                    <div class="avantages-list">
                        <ul>
                            <li><i class="fas fa-check"></i>Paiement en plusieurs fois sans frais</li>
                            <li><i class="fas fa-check"></i>Tarifs préférentiels</li>
                            <li><i class="fas fa-check"></i>Début de formation immédiat</li>
                            <li><i class="fas fa-check"></i>Flexibilité des modalités de paiement</li>
                        </ul>
                    </div>

                    <div class="procedure-steps">
                        <h4>Options de paiement</h4>
                        <ol>
                            <li>Paiement comptant (-10%)</li>
                            <li>Paiement en 3 fois</li>
                            <li>Paiement en 6 fois</li>
                            <li>Paiement en 10 fois</li>
                        </ol>
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