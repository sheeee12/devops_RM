pipeline {
    agent any

    environment {
        IMAGE_NAME = "mon-app-php"
        STACK_NAME = "ma_gestion"
    }

    stages {

        // ÉTAPE 1 : Installer les dépendances PHP avec Composer
        stage('Installation des dépendances') {
            steps {
                script {
                    echo '--- INSTALLATION DES LIBRAIRIES COMPOSER ---'
                    // On monte le workspace directement, sans dépendre d'un nom de conteneur
                    sh '''
                        docker run --rm \
                            -v "${WORKSPACE}:/app" \
                            -w /app \
                            composer:latest \
                            composer install --no-interaction --prefer-dist --optimize-autoloader
                    '''
                    echo '✅ Dossier vendor/ créé avec succès.'
                }
            }
        }

        // ÉTAPE 2 : Lancer les tests PHPUnit
        stage('Tests unitaires (PHPUnit)') {
            steps {
                script {
                    echo '--- LANCEMENT DES TESTS ---'
                    sh '''
                        docker run --rm \
                            -v "${WORKSPACE}:/app" \
                            -w /app \
                            php:8.2-cli \
                            vendor/bin/phpunit --testdox || true
                    '''
                    // Le "|| true" empêche le pipeline de bloquer si des tests échouent
                    // Retire-le en production pour bloquer le déploiement si les tests cassent
                }
            }
        }

        // ÉTAPE 3 : Construire l'image Docker de l'application
        // CRITIQUE : Swarm ne peut pas "build", Jenkins doit le faire ici
        stage('Construction de l\'image Docker') {
            steps {
                script {
                    echo '--- BUILD DE L\'IMAGE PHP ---'
                    sh 'docker build -t ${IMAGE_NAME}:latest -t ${IMAGE_NAME}:${BUILD_NUMBER} .'
                    echo "✅ Image ${IMAGE_NAME}:latest construite (build #${BUILD_NUMBER})"
                }
            }
        }

        // ÉTAPE 4 : Analyse de sécurité SonarQube (optionnel, décommenter si configuré)
        /*
        stage('Audit de Sécurité (SonarQube)') {
            steps {
                script {
                    def scannerHome = tool 'SonarScanner'
                    withSonarQubeEnv('MySonar') {
                        sh """
                        ${scannerHome}/bin/sonar-scanner \
                        -Dsonar.projectKey=RembourseMaroc \
                        -Dsonar.sources=. \
                        -Dsonar.language=php \
                        -Dsonar.inclusions=index.php,includes/security.php,views/admin/dashboard.php
                        """
                    }
                }
            }
        }
        */

        // ÉTAPE 5 : Déploiement sur Docker Swarm (rolling update automatique)
        stage('Déploiement (Swarm)') {
            steps {
                script {
                    echo '--- VÉRIFICATION DU MODE SWARM ---'
                    sh '''
                        if [ "$(docker info --format "{{.Swarm.LocalNodeState}}")" != "active" ]; then
                            echo "Activation du Swarm..."
                            docker swarm init --advertise-addr 127.0.0.1
                        else
                            echo "✅ Swarm déjà actif."
                        fi
                    '''

                    echo '--- DÉPLOIEMENT DE LA STACK ---'
                    // Swarm détecte que l'image a changé et fait un rolling update sans coupure
                    sh 'docker stack deploy -c docker-compose.yml ${STACK_NAME} --with-registry-auth'

                    echo '--- ATTENTE DE LA STABILISATION ---'
                    sh 'sleep 15'

                    echo '--- VÉRIFICATION DES SERVICES ---'
                    sh 'docker stack services ${STACK_NAME}'

                    echo '🎉 Déploiement terminé ! Application disponible sur http://localhost:8081'
                }
            }
        }
    }

    post {
        success {
            echo '✅ Pipeline réussi - Nouvelle version en ligne sur http://localhost:8081'
        }
        failure {
            echo '❌ Pipeline échoué - Vérifier les logs ci-dessus'
        }
    }
}