#!/bin/bash

echo "🚀 DÉMARRAGE DE L'INFRASTRUCTURE DEVOPS..."

# 1. Vérifier si Swarm est déjà activé, sinon l'activer
if [ "$(docker info --format '{{.Swarm.LocalNodeState}}')" != "active" ]; then
    echo "⚙️ Activation du mode Orchestrateur (Docker Swarm)..."
    docker swarm init --advertise-addr 127.0.0.1
else
    echo "✅ Docker Swarm est déjà prêt."
fi

# 2. Lancer la Stack (C'est automatique, Swarm va lire le YAML)
echo "📦 Déploiement de la pile de services (Stack)..."
docker stack deploy -c docker-compose.yml ma_gestion

echo "-------------------------------------------------------"
echo "🎉 INFRASTRUCTURE DÉPLOYÉE !"
echo "Jenkins : http://localhost:8080"
echo "Application : http://localhost:8081"
echo "-------------------------------------------------------"
echo "Note : L'orchestrateur va mettre 10-20 secondes à tout stabiliser."