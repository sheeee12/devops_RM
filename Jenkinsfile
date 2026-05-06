pipeline {
    agent any

    environment {
        IMAGE_NAME = "mon-app-php"
        STACK_NAME = "ma_gestion"
    }

    stages {
// ÉTAPE 1 : Installation des dépendances
        stage('Installation des dépendances') {
            steps {
                script {
                    echo '--- INSTALLATION DES LIBRAIRIES COMPOSER ---'
                    sh '''
                        # On récupère l'ID dynamique du Jenkins actuel
                        JENKINS_ID=$(docker ps -q -f name=ma_gestion_jenkins)
                        # On lance Composer en partageant les disques de Jenkins
                        docker run --rm --volumes-from $JENKINS_ID -w ${WORKSPACE} composer:latest composer update --no-interaction --prefer-dist
                    '''
                }
            }
        }

        // ÉTAPE 2 : Lancer les tests PHPUnit
        stage('Tests unitaires (PHPUnit)') {
            steps {
                script {
                    echo '--- LANCEMENT DES TESTS ---'
                    sh '''
                        JENKINS_ID=$(docker ps -q -f name=ma_gestion_jenkins)
                        docker run --rm --volumes-from $JENKINS_ID -w ${WORKSPACE} php:8.2-cli php vendor/bin/phpunit --testdox || true
                    '''
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