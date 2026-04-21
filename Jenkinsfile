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
                // C est ici qu on connectera SonarQube plus tard
            }
        }
        stage('Packaging (Docker)') {
            steps {
                echo 'Création de l image Docker de RembourseMaroc...'
            }
        }
    }
}