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
                    echo '--- APPEL DE L OUVRIER COMPOSER ---'
                    // LOGIQUE : 
                    // --volumes-from jenkins : On dit à Composer de copier les dossiers de Jenkins
                    // -w : On lui dit de travailler exactement là où Jenkins a posé le code
                    sh 'docker run --rm --volumes-from jenkins -w /var/jenkins_home/workspace/Pipeline-RembourseMaroc-Private composer install --no-interaction --prefer-dist'
                    
                    echo 'Succès : L ouvrier a fini. Les librairies sont dans /vendor.'
                }
            }
        }

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
        
        // PHASE 4 : Prochaine étape (on la laisse vide pour l'instant)
        stage('Packaging Docker') {
            steps {
                echo 'Etape suivante : Creation de l image de production...'
            }
        }
    }
}