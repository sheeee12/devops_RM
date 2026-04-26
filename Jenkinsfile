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
   stage('Phase 4 : Packaging & CD (Deploy)') {
            steps {
                script {
                    echo '--- NETTOYAGE CIBLÉ (Laisse Jenkins et Sonar vivants) ---'
                    sh 'docker rm -f nginx_lb db_rembourse app_rembourse_1 app_rembourse_2 || true'
                    
                    // NOUVELLE LIGNE : On supprime le vieux volume pour forcer la réinjection SQL !
                    sh 'docker volume rm pipeline-remboursemaroc-private_db_data || true'
                    
                    echo '--- DÉPLOIEMENT AUTOMATISÉ (BUILD + UP) ---'
                    sh 'docker compose up -d --build --no-deps app_rembourse_1 app_rembourse_2 db_rembourse nginx_lb'
                    
                    echo '--- ATTENTE MYSQL ---'
                    sh 'sleep 20'
                    
                    echo 'Félicitations ! http://localhost:8081 est prêt !'
                }
            }
        }
    }
}