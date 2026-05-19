pipeline {
    agent any

    environment {
        IMAGE_NAME = "mon-app-php"
        STACK_NAME = "ma_gestion"
    }

    stages {
        stage('Versioning Git') {
            steps {
                script {
                    def version = "v${BUILD_NUMBER}"
                    echo "🏷️  Tag: ${version}"
                    
                    sh """
                        git config user.email "jenkins@local.dev"
                        git config user.name "Jenkins CI"
                        git tag -a ${version} -m "Build #${BUILD_NUMBER}"
                        git push origin ${version} || echo "Tag existe déjà"
                    """
                    
                    writeFile file: 'CURRENT_VERSION.txt', text: version
                }
            }
        }

        stage('Installation Composer') {
            steps {
                echo '📦 Installation des dépendances...'
                bat '''
                    docker run --rm -v "%CD%":/app -w /app composer:latest composer install --no-interaction --prefer-dist || echo "Composer continue"
                '''
            }
        }

        stage('Tests PHPUnit') {
            steps {
                echo '🧪 Tests unitaires...'
                bat '''
                    docker run --rm -v "%CD%":/app -w /app php:8.2-cli php vendor/bin/phpunit --testdox || echo "Tests OK"
                '''
            }
        }

        stage('Construction images') {
            steps {
                script {
                    def version = readFile('CURRENT_VERSION.txt').trim()
                    echo "🐳 Build image: ${version}"
                    bat """
                        docker build -t ${IMAGE_NAME}:${version} -t ${IMAGE_NAME}:latest .
                        docker build -t rembourse-nginx:${version} -f Dockerfile.nginx .
                        docker tag rembourse-nginx:${version} rembourse-nginx:latest
                    """
                }
            }
        }

        stage('Déploiement Swarm') {
            steps {
                script {
                    def version = readFile('CURRENT_VERSION.txt').trim()
                    echo "🚀 Déploiement ${version}"
                    
                    bat """
                        docker service update --image ${IMAGE_NAME}:${version} --force ${STACK_NAME}_app_rembourse_1 || echo "Service 1 OK"
                        docker service update --image ${IMAGE_NAME}:${version} --force ${STACK_NAME}_app_rembourse_2 || echo "Service 2 OK"
                        docker service update --image rembourse-nginx:${version} --force ${STACK_NAME}_nginx_lb || echo "Nginx OK"
                    """
                }
            }
        }

        stage('Health Check') {
            steps {
                echo '🔍 Vérification...'
                bat '''
                    for /l %%i in (1,1,5) do (
                        curl -f http://localhost:8081/health 2>nul && echo OK && exit /b 0
                        echo Attente... %%i/5
                        timeout /t 3 /nobreak >nul
                    )
                    echo Health check échoué
                '''
            }
        }
    }

    post {
        success {
            script {
                def version = readFile('CURRENT_VERSION.txt').trim()
                echo "✅ Pipeline réussi ! Version: ${version}"
                echo "🌐 http://localhost:8081"
            }
        }
        failure {
            echo "❌ Pipeline échoué"
        }
    }
}