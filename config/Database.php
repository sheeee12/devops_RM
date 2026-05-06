<?php

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        // --- CONFIGURATION DOCKER ---
        // Il lit les variables du docker-compose, sinon il prend des valeurs par défaut
        $host = "db_rembourse";
        $dbname = "rembourse_maroc";
        $user = "root";
        $pass = "root";
        try {
            $this->pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            // Petit conseil : on affiche l'erreur en détail pour le debug
            die("Erreur de connexion Docker-MySQL : " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnexion()
    {
        return $this->pdo;
    }
}
