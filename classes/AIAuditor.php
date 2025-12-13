<?php
require_once __DIR__ . '/../config/Database.php';

class AIAuditor {
    
    public static function analyze($demandeId, $userId, $dateDepense, $montantTotal) {
        $pdo = Database::getInstance()->getConnexion();
        $score = 100;
        $details = [];
        $status = "OK"; // OK, WARNING, DANGER

        // --- 1. VÉRIFICATION DE LA MISSION ---
        
        $sql = "SELECT m.titre 
                FROM missions m
                JOIN mission_participants mp ON m.id_mission = mp.id_mission
                WHERE mp.user_id = ? 
                AND ? BETWEEN m.date_debut AND m.date_fin";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $dateDepense]);
        $mission = $stmt->fetch();

        if ($mission) {
            $details[] = "<strong>COHÉRENCE MISSION :</strong> L'employé était bien prévu sur la mission '{$mission['titre']}'.";
        } else {
            $score -= 50;
            $status = "DANGER";
            $details[] = " <strong>ALERTE CRITIQUE :</strong> Aucune mission officielle trouvée pour cet employé à cette date.";
        }

        // --- 2. VÉRIFICATION DU MONTANT (Anti-Fraude basique) ---
        // Seuil d'alerte : 2000 DH par jour
        if ($montantTotal > 2000) {
            $score -= 20;
            $details[] = "🟠 <strong>MONTANT ÉLEVÉ :</strong> Le total dépasse le seuil de vigilance (2000 DH).";
        } else {
            $details[] = "✅ <strong>MONTANT :</strong> Dans la moyenne autorisée.";
        }

        // --- 3. VÉRIFICATION DES DÉLAIS ---
        // Si la demande est faite le week-end (Samedi/Dimanche)
        $jourSemaine = date('N', strtotime($dateDepense));
        if ($jourSemaine >= 6 && !$mission) {
            $score -= 10;
            $details[] = "⚠️ <strong>WEEK-END :</strong> Dépense effectuée un samedi/dimanche.";
        }

        return [
            'score' => $score,
            'details' => $details,
            'status' => $status
        ];
    }
}
?>