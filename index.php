<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MFC - Maison de la Formation Continue: Centre de formation professionnelle proposant des formations en informatique, bureautique et cybersécurité">
    <title>MFC - Maison de la Formation Continue | Formation Professionnelle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
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
        <!-- Section Hero -->
        <section class="hero-section" id="accueil">
            <div class="hero-content fade-in">
                <h1>Formez-vous aux métiers de demain</h1>
                <p>Plus de 20 ans d'expertise en formation professionnelle</p>
                <div class="hero-buttons">
                    <a href="formation.php" class="btn-primary">Découvrir nos formations</a>
                </div>
            </div>
            <div class="hero-stats">
                <div class="stat-item fade-in delay-1">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span class="stat-number">30</span>
                    <span class="stat-text">Salles de formation</span>
                </div>
                <div class="stat-item fade-in delay-2">
                    <i class="fas fa-building"></i>
                    <span class="stat-number">5</span>
                    <span class="stat-text">Centres en France</span>
                </div>
                <div class="stat-item fade-in delay-3">
                    <i class="fas fa-users"></i>
                    <span class="stat-number">1000+</span>
                    <span class="stat-text">Stagiaires</span>
                </div>
            </div>
        </section>

        <!-- Section Interview -->
        <section id="interview" class="interview-section">
            <h2>Interview du Directeur Général – Aimé EFCE</h2>
            <article class="interview">
                <div class="interview-item">
                    <h3>Patrick Poivre d’Ervor - Présentez-nous la MFC</h3>
                    <p>Aimé EFCE - Dans un espace de formation réservé aux adultes, la Maison de la Formation Continue s'est dotée d'une organisation permettant d'optimiser les temps de formation. Notre culture pédagogique repose sur la capitalisation des savoirs fondamentaux et des savoir-faire que nous développons en permanence.</p>
                </div>
                <div class="interview-item">
                    <h3>PPE - Comment s’est construite cette expertise ?</h3>
                    <p>AE - Cette expertise s’est bâtie grâce aux retours d’expérience de nos consultants experts et pédagogues, ainsi qu'à notre cellule de veille et de recherche sur les meilleures pratiques d’apprentissage. Nous avons appelé cette méthode <em>La voie de la connaissance</em>.</p>
                </div>
                <div class="interview-item">
                    <h3>PPE – Pouvez-vous nous en dire plus ?</h3>
                    <p>AE - <em>La voie de la connaissance</em> incarne notre culture pédagogique et symbolise notre expertise. Elle s’enrichit aujourd’hui des nouvelles technologies pour répondre aux enjeux du 21e siècle.</p>
                </div>
                <div class="interview-item">
                    <h3>PPE - Comment s’organise une formation pour le stagiaire ?</h3>
                    <p>AE - Trois semaines avant sa formation, le stagiaire reçoit un dossier complet d’informations pratiques (programme, plan d’accès, moyens de transport, coordonnées de réservation hôtelière, etc.).</p>
                </div>
                <div class="interview-item">
                    <h3>PPE - Et pendant la formation ?</h3>
                    <p>AE - Des hôtesses accueillent et guident le stagiaire dès son arrivée, en lui fournissant tous les renseignements nécessaires au bon déroulement de sa formation.</p>
                </div>
                <div class="interview-item">
                    <h3>PPE - Et à la fin de la formation ?</h3>
                    <p>AE - Chaque formation fait l’objet d’un questionnaire d’évaluation exploité dans les 7 jours. Un résultat inférieur à 2 (sur 3) déclenche une action corrective et, si besoin, une proposition de compensation.</p>
                </div>
                <div class="interview-item">
                    <h3>PPE - Quels sont les horaires d’une formation ?</h3>
                    <p>AE - Nos formations débutent à 9h et se terminent entre 17h et 18h. Un petit déjeuner d’accueil est proposé dès 8h pour garantir un minimum de 7 heures de formation intensive par jour.</p>
                </div>
                <!-- Bouton pour lire l'interview complète (optionnel) -->
                <div class="interview-readmore">
                    <a href="interview.php" class="btn-secondary">Lire l'interview complète</a>
                </div>
            </article>
        </section>

        <!-- Section Expertise -->
        <section class="expertise-section">
            <h2>Notre expertise à votre service</h2>
            <div class="expertise-grid">
                <div class="expertise-card">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>Formation sur mesure</h3>
                    <p>Des parcours adaptés à vos besoins avec une pédagogie innovante et personnalisée.</p>
                </div>
                <div class="expertise-card">
                    <i class="fas fa-user-tie"></i>
                    <h3>Experts formateurs</h3>
                    <p>Des professionnels reconnus qui partagent leur expertise et leur expérience terrain.</p>
                </div>
                <div class="expertise-card">
                    <i class="fas fa-laptop"></i>
                    <h3>Environnement optimal</h3>
                    <p>Des équipements modernes et un cadre d'apprentissage confortable et stimulant.</p>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="footer-content">
            <p>&copy; 2024 MFC - Tous droits réservés</p>
        </div>
        <div class="footer-social">
            <div class="social-links">
                <a href="#" class="social-link" title="Facebook">
                    <i class="fab fa-facebook"></i>
                </a>
                <a href="#" class="social-link" title="Twitter">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="#" class="social-link" title="LinkedIn">
                    <i class="fab fa-linkedin"></i>
                </a>
                <a href="#" class="social-link" title="Instagram">
                    <i class="fab fa-instagram"></i>
                </a>
            </div>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animation des éléments avec fade-in
        setTimeout(() => {
            document.querySelectorAll('.fade-in').forEach(element => {
                element.classList.add('active');
            });
        }, 100);

        // Animation des chiffres
        const statNumbers = document.querySelectorAll('.stat-number');
        statNumbers.forEach(stat => {
            const finalValue = parseInt(stat.textContent);
            let currentValue = 0;
            const duration = 2000; // 2 secondes
            const step = finalValue / (duration / 16); // 60 FPS

            function updateValue() {
                if (currentValue < finalValue) {
                    currentValue = Math.min(currentValue + step, finalValue);
                    stat.textContent = Math.round(currentValue);
                    requestAnimationFrame(updateValue);
                } else {
                    stat.textContent = finalValue + (stat.textContent.includes('+') ? '+' : '');
                }
            }

            // Démarrer l'animation quand l'élément devient visible
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        updateValue();
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            observer.observe(stat);
        });
    });
    </script>
</body>
</html>
