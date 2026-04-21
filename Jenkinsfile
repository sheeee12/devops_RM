pipeline {
    agent any

    stages {
        stage('Construction (Build)') {
            steps {
                echo 'Vérification de l environnement PHP...'
                // Ici on dira plus tard à Jenkins d installer les bibliothèques
            }
        }
        stage('Audit de Sécurité (SonarQube)') {
            steps {
                echo 'Envoi du code à SonarQube pour analyse...'
// Cette étape utilise le plugin SonarQube pour scanner le code PHP
                script {
                    def scannerHome = tool 'SonarScanner' // On appelle l outil de scan
                    withSonarQubeEnv('MySonar') { // On se connecte au serveur configuré
                        sh "${scannerHome}/bin/sonar-scanner \
                        -Dsonar.projectKey=RembourseMaroc \
                        -Dsonar.sources=. \
                        -Dsonar.language=php"
                    }            }
        }
        stage('Packaging (Docker)') {
            steps {
                echo 'Création de l image Docker de RembourseMaroc...'
            }
        }
    }
}