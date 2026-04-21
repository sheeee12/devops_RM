pipeline {
    agent any

    stages {
        stage('Construction') {
            steps {
                echo 'Preparation du projet RembourseMaroc...'
            }
        }

        stage('Audit de Securite (SonarQube)') {
            steps {
                script {
                    // On définit l'outil de scan
                    def scannerHome = tool 'SonarScanner'
                    
                    // On se connecte à l'environnement SonarQube configuré dans Jenkins
                    withSonarQubeEnv('MySonar') {
                         sh """
                        ${scannerHome}/bin/sonar-scanner \
                        -Dsonar.projectKey=RembourseMaroc \
                        -Dsonar.sources=. \
                        -Dsonar.language=php \
                        -Dsonar.exclusions=jenkins_data/**,uploads/**,assets/**,lang/**,**/*.example
                        """
                    }
                }
            }
        }
        
        stage('Packaging Docker') {
            steps {
                echo 'Bientôt : Construction de l image Docker...'
            }
        }
    }
}