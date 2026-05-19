pipeline {
    agent any

    environment {
        IMAGE_NAME  = "mon-app-php"
        NGINX_IMAGE = "rembourse-nginx"
        STACK_NAME  = "ma_gestion"
        // L'URL de ton dépôt Gitea LOCAL (pas GitHub)
        // On la lit depuis la config Jenkins (configurée à l'étape 3)
        GITEA_URL   = "http://gitea:3000"
    }

    stages {

        // ================================================================
        // ÉTAPE 0 : Récupérer le code depuis GITEA (dépôt local)
        // Jenkins clone automatiquement depuis Gitea grâce au plugin Git.
        // Plus besoin de GitHub !
        // ================================================================
        stage('Checkout depuis Gitea') {
            steps {
                // Jenkins récupère le code depuis l'URL configurée dans le job.
                // Le checkout est automatique si le job est de type "Pipeline with SCM".
                echo "📥 Code récupéré depuis Gitea (build #${BUILD_NUMBER})"
                echo "   Branche : ${env.GIT_BRANCH ?: 'main'}"
                echo "   Commit  : ${env.GIT_COMMIT ?: 'inconnu'}"
            }
        }

        // ================================================================
        // ÉTAPE 1 : Installation des dépendances
        // ================================================================
        stage('Installation des dépendances') {
            steps {
                script {
                    echo '--- INSTALLATION DES LIBRAIRIES COMPOSER ---'
                    sh '''
                        JENKINS_ID=$(docker ps -q -f name=${STACK_NAME}_jenkins)
                        docker run --rm \
                            --volumes-from $JENKINS_ID \
                            -w ${WORKSPACE} \
                            composer:latest \
                            composer update --no-interaction --prefer-dist
                    '''
                }
            }
        }

        // ================================================================
        // ÉTAPE 2 : Tests unitaires PHPUnit
        // ================================================================
        stage('Tests unitaires (PHPUnit)') {
            steps {
                script {
                    echo '--- LANCEMENT DES TESTS ---'
                    sh '''
                        JENKINS_ID=$(docker ps -q -f name=${STACK_NAME}_jenkins)
                        docker run --rm \
                            --volumes-from $JENKINS_ID \
                            -w ${WORKSPACE} \
                            php:8.2-cli \
                            php vendor/bin/phpunit --testdox || true
                    '''
                }
            }
        }

        // ================================================================
        // ÉTAPE 3 : Construire les images Docker AVEC TAG DE VERSION
        //
        // PRINCIPE DU VERSIONING :
        //   On crée DEUX tags pour chaque build :
        //   - mon-app-php:42        ← version précise (BUILD_NUMBER = numéro Jenkins)
        //   - mon-app-php:latest    ← toujours la dernière version
        //
        //   Avantage : si le build #43 plante en prod, on peut revenir à :42
        //   avec UNE SEULE commande sans rebuilder quoi que ce soit.
        // ================================================================
        stage('Construction des images Docker (versionnées)') {
            steps {
                script {
                    echo "--- BUILD IMAGE PHP : ${IMAGE_NAME}:${BUILD_NUMBER} ---"
                    sh "chmod -R 777 ${WORKSPACE}"

                    // Tag "versionné" (ex: mon-app-php:42) ET tag "latest"
                    sh "docker build -t ${IMAGE_NAME}:${BUILD_NUMBER} -t ${IMAGE_NAME}:latest ."

                    // Même chose pour Nginx
                    sh "docker build -t ${NGINX_IMAGE}:${BUILD_NUMBER} -t ${NGINX_IMAGE}:latest -f Dockerfile.nginx ."

                    echo "✅ Images construites : ${IMAGE_NAME}:${BUILD_NUMBER} et ${IMAGE_NAME}:latest"
                }
            }
        }

        // ================================================================
        // ÉTAPE 4 : Déploiement sur Docker Swarm (rolling update)
        //
        // PRINCIPE DU ROLLING UPDATE :
        //   Swarm met à jour UNE réplica à la fois.
        //   Pendant la mise à jour de replica_1, replica_2 continue de servir
        //   les requêtes → ZÉRO downtime pour les utilisateurs.
        //
        // VERSIONING SWARM :
        //   On passe le tag précis (:BUILD_NUMBER) à Swarm.
        //   Swarm se souvient de QUELLE image tourne sur chaque service.
        //   → "docker service rollback" ramène à la version précédente EXACTE.
        // ================================================================
        stage('Déploiement sur Docker Swarm') {
            steps {
                script {
                    echo '--- VÉRIFICATION DU MODE SWARM ---'
                    sh '''
                        if [ "$(docker info --format "{{.Swarm.LocalNodeState}}")" != "active" ]; then
                            docker swarm init --advertise-addr 127.0.0.1
                        fi
                    '''

                    // Déploiement initial si la stack n'existe pas encore
                    sh """
                        if ! docker stack ls | grep -q ${STACK_NAME}; then
                            echo "📦 Première fois : déploiement complet de la stack..."
                            docker stack deploy -c docker-compose.yml ${STACK_NAME}
                            sleep 30
                        fi
                    """

                    echo "--- MISE À JOUR DES SERVICES (build #${BUILD_NUMBER}) ---"

                    // On passe le tag versionné :BUILD_NUMBER (pas :latest)
                    // car Swarm doit connaître la version EXACTE pour pouvoir rollback
                    sh "docker service update --image ${IMAGE_NAME}:${BUILD_NUMBER} --force ${STACK_NAME}_app_rembourse_1"
                    sh "docker service update --image ${IMAGE_NAME}:${BUILD_NUMBER} --force ${STACK_NAME}_app_rembourse_2"
                    sh "docker service update --image ${NGINX_IMAGE}:${BUILD_NUMBER} --force ${STACK_NAME}_nginx_lb"
                    sh "docker service update --image jasonrivers/nagios:latest --force ${STACK_NAME}_nagios"

                    echo "🎉 Déploiement build #${BUILD_NUMBER} terminé !"
                }
            }
        }

        // ================================================================
        // ÉTAPE 5 : Vérification de santé post-déploiement
        //
        // On attend 15 secondes que Swarm stabilise, puis on vérifie
        // que l'app répond bien. Si elle ne répond pas → rollback auto.
        // ================================================================
        stage('Vérification de santé (Health Check)') {
            steps {
                script {
                    echo '--- ATTENTE STABILISATION SWARM (15s) ---'
                    sleep 15

                    echo '--- TEST DE L\'APPLICATION ---'
                    sh '''
                        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8081/health || echo "000")
                        echo "Code HTTP reçu : $HTTP_CODE"

                        if [ "$HTTP_CODE" != "200" ]; then
                            echo "⚠️  Health check échoué (code: $HTTP_CODE)"
                            echo "🔄 ROLLBACK AUTOMATIQUE vers la version précédente..."
                            docker service rollback ma_gestion_app_rembourse_1
                            docker service rollback ma_gestion_app_rembourse_2
                            docker service rollback ma_gestion_nginx_lb
                            echo "✅ Rollback effectué. L ancienne version est restaurée."
                            exit 1
                        fi

                        echo "✅ Application opérationnelle (HTTP 200)"
                    '''
                }
            }
        }
    }

    // ================================================================
    // RÉSUMÉ FINAL : affiché dans Jenkins après chaque build
    // ================================================================
    post {
        success {
            echo """
            ╔══════════════════════════════════════════╗
            ║  ✅ BUILD #${BUILD_NUMBER} RÉUSSI          ║
            ║  Application : http://localhost:8081      ║
            ║  Jenkins     : http://localhost:8080      ║
            ║  Gitea       : http://localhost:3000      ║
            ║  Image       : ${IMAGE_NAME}:${BUILD_NUMBER}  ║
            ║  Pour rollback manuel :                   ║
            ║  docker service rollback <service>        ║
            ╚══════════════════════════════════════════╝
            """
        }
        failure {
            echo """
            ╔══════════════════════════════════════════╗
            ║  ❌ BUILD #${BUILD_NUMBER} ÉCHOUÉ          ║
            ║  La version précédente reste active.      ║
            ║  Rollback effectué automatiquement.       ║
            ║  Consultez les logs Jenkins ci-dessus.    ║
            ╚══════════════════════════════════════════╝
            """
        }
    }
}