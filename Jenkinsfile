//pipline god deploy the bdd

pipeline {
    agent any

    stages {
        // PHASE 1 : On prépare le terrain
        stage('Construction') {
            steps {
                echo 'Initialisation du pipeline RembourseMaroc...'
            }
        }

        // PHASE 2 : On installe les librairies réelles
       stage('Installation des Librairies') {
            steps {
                script {
                    echo '--- MISE A JOUR DES LIBRAIRIES (MODE UPDATE) ---'
                    // On utilise "update" car on a modifié le composer.json manuellement
                    sh 'docker run --rm --volumes-from jenkins -w /var/jenkins_home/workspace/Pipeline-RembourseMaroc-Private composer update --no-interaction --prefer-dist'
                    
                    echo 'Succès : Le dossier vendor/ a été mis à jour avec PHPUnit.'
                }
            }
        }
        /* ON COMMENTE SONAR POUR GAGNER DU TEMPS PENDANT LES TESTS CD

        // PHASE 3 : Audit ciblé sur 5 fichiers uniquement
        stage('Audit de Securite (SonarQube)') {
            steps {
                script {
                    def scannerHome = tool 'SonarScanner'
                    withSonarQubeEnv('MySonar') {
                        // LOGIQUE WHITEBOX : 
                        // On utilise "sonar.inclusions" pour forcer le robot à ne lire 
                        // que ces 5 fichiers précis. Temps estimé : < 10 secondes.
                         sh """
                        ${scannerHome}/bin/sonar-scanner \
                        -Dsonar.projectKey=RembourseMaroc \
                        -Dsonar.sources=. \
                        -Dsonar.language=php \
                        -Dsonar.inclusions=index.php,includes/security.php,views/admin/dashboard.php,classes/Lang.php,actions/delete_notifications_employee.php
                        """
                    }
                }
            }
        }
         */
        // PHASE 4 : Prochaine étape (on la laisse vide pour l'instant)
        /*
   stage('Phase 4 : Packaging & CD (Deploy)') {
            steps {
                script {
                    echo '--- NETTOYAGE CIBLÉ ---'
                    sh 'docker rm -f nginx_lb db_rembourse app_rembourse_1 app_rembourse_2 || true'
                    // On ne s'embête plus à essayer de deviner le nom du volume pour le supprimer !
                    
                    echo '--- DÉPLOIEMENT AUTOMATISÉ (BUILD + UP) ---'
                    sh 'docker compose up -d --build --no-deps app_rembourse_1 app_rembourse_2 db_rembourse nginx_lb'
                    
                    echo '--- ATTENTE INTELLIGENTE DE MYSQL ---'
                    // Au lieu d'un sleep aveugle, on ping la base jusqu'à ce qu'elle réponde
                    sh '''
                    for i in {1..30}; do
                        if docker exec db_rembourse mysqladmin ping -u root -proot --silent; then
                            echo "✅ MySQL est réveillé et prêt !"
                            break
                        fi
                        echo "⏳ En attente du démarrage interne de MySQL..."
                        sleep 2
                    done
                    sleep 3
                    '''
                    
                    echo '--- INJECTION FORCÉE DE LA BASE DE DONNÉES ---'
                    // L'arme absolue : on écrase tout et on recrée les tables à partir du fichier !
                    sh 'docker exec -i db_rembourse mysql -u root -proot rembourse_maroc < config/sql/01_create_database_complete.sql'
                    
                    echo '🎉 Félicitations ! http://localhost:8081 est prêt et la base est remplie !'
                }
            }
        } */
        stage('Phase 4 : Packaging & CD (Deploy Swarm)') {
            steps {
                script {
                    echo '--- MISE A JOUR DE LA STACK SWARM ---'
                    // Dans Swarm, on ne fait pas "rm", on redéploie simplement. 
                    // Swarm voit le changement et met à jour les serveurs sans coupure.
                    sh 'docker stack deploy -c docker-compose.yml ma_gestion'
                    
                    echo '✅ La nouvelle version est en cours de déploiement sur le cluster !'
                }
            }
        }
    }
}