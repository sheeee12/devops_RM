#!/bin/bash
set -e   # Arrêter le script si une commande échoue

echo "🚀 DÉMARRAGE DE L'INFRASTRUCTURE DEVOPS..."

# 1. Construire les images AVANT de déployer (Swarm ne peut pas builder)
echo "🔨 Construction de l'image PHP..."
docker build -t mon-app-php:latest .

echo "🔨 Construction de l'image Nginx..."
docker build -t rembourse-nginx:latest -f Dockerfile.nginx .

# 2. Activer Docker Swarm si nécessaire
if [ "$(docker info --format '{{.Swarm.LocalNodeState}}')" != "active" ]; then
    echo "⚙️  Activation du mode Docker Swarm..."
    docker swarm init --advertise-addr 127.0.0.1
else
    echo "✅ Docker Swarm est déjà actif."
fi

# 3. Déployer la stack complète
echo "📦 Déploiement de la stack..."
docker stack deploy -c docker-compose.yml ma_gestion

echo "-------------------------------------------------------"
echo "🎉 INFRASTRUCTURE DÉPLOYÉE !"
echo ""
echo "  Jenkins     : http://localhost:8080"
echo "  Application : http://localhost:8081"
echo "  SonarQube   : http://localhost:9000"
echo "  Health check: http://localhost:8081/health"
echo ""
echo "⏳ Attendre 20-30 secondes que MySQL démarre..."
echo "-------------------------------------------------------"

echo "🛠️  Installation du client Docker dans Jenkins..."
# On attend que Jenkins soit allumé
sleep 10
# On installe le client Docker à l'intérieur du conteneur Jenkins de Swarm
JENKINS_ID=$(docker ps -q -f name=ma_gestion_jenkins)
docker exec -u root $JENKINS_ID apt-get update
docker exec -u root $JENKINS_ID apt-get install -y docker.io
echo "✅ Jenkins est maintenant capable de lancer des conteneurs !"