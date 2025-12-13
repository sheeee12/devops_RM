<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Google\Authenticator\GoogleAuthenticator;

class User{
    public static function verifyLogin($email, $password){
        $pdo = Database::getInstance()->getConnexion();
        $stmt=$pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $user=$stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])){
            return $user;
        }
        return false;
    }

      // Gestion du Secret QR Code
    public static function get2FASecret($userId) {
        $pdo = Database::getInstance()->getConnexion();
        $stmt = $pdo->prepare("SELECT google_secret FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        $g = new GoogleAuthenticator();

        if ($user['google_secret']) {
            // Il a déjà configuré son compte
            return ['secret' => $user['google_secret'], 'is_new' => false];
        } else {
            // C'est nouveau : on génère un secret
            $secret = $g->generateSecret();
            // On le sauve en base
            $pdo->prepare("UPDATE users SET google_secret = ? WHERE user_id = ?")->execute([$secret, $userId]);
            return ['secret' => $secret, 'is_new' => true];
        }
    }

    // Générer l'URL du QR Code (pour l'afficher)
    public static function getQRUrl($email, $secret) {
        $g = new GoogleAuthenticator();
        // Le nom "ProjetFrais" apparaîtra dans l'appli Google Auth
        return $g->getUrl('ProjetFrais', $email, $secret);
    }
    
    public static function checkCode($secret, $code) {
        $g = new GoogleAuthenticator();
        return $g->checkCode($secret, $code);
    }


    public static function initiatePasswordReset($email) {
        $pdo = Database::getInstance()->getConnexion();

        
        $stmt = $pdo->prepare("SELECT user_id, nom FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(16)); 
            $tokenHash = hash("sha256", $token); 

            $sql = "UPDATE users SET reset_token_hash = ?, reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR)  WHERE user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tokenHash, $user['user_id']]);

            return ['token' => $token, 'nom' => $user['nom']];// On retourne le token BRUT el nom pour l'envoyer par mail 
        }

    return false; // Email introuvable
    }

    public static function resetPassword($tokenBrut, $newPassword) {
        $pdo = Database::getInstance()->getConnexion();

        $tokenHash = hash("sha256", $tokenBrut);

        //DEBUG : Afficher le hash cherché
        echo "Token reçu : $tokenBrut <br>";
        echo "Hash cherché : $tokenHash <br>";
        
        $sql = "SELECT user_id FROM users 
                WHERE reset_token_hash = ? 
                AND reset_expires_at > NOW()";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch();

        if (!$user) {
           
            die("ERREUR : Aucun utilisateur trouvé avec ce token. Soit le token est faux, soit il a expiré (Heure BDD vs Heure PHP).");
        }

        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $update = "UPDATE users 
                   SET password = ?, 
                       reset_token_hash = NULL, 
                       reset_expires_at = NULL 
                   WHERE user_id = ?";
        
        $stmtUpdate = $pdo->prepare($update);
        $stmtUpdate->execute([$newPasswordHash, $user['user_id']]);
        
        if ($stmtUpdate->rowCount() > 0) {
            return true; 
        } else {
            // Si le mot de passe est le même qu'avant, MySQL renvoie 0.
            // Mais ici avec password_hash, ça devrait toujours changer.
            // Si on est là, c'est un problème d'ID.
            die("ALERTE : La requête UPDATE a fonctionné mais 0 ligne modifiée. Vérifie l'ID : " . $user['user_id']);
        }
    }
    }