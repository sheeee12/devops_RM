<?php
// classes/FraisHelper.php

class FraisHelper
{

    // Règle 1 : Vérifier si un montant dépasse le plafond (ex: 2000 DH)
    public static function estMontantAbusif($montant)
    {
        return $montant > 2000;
    }

    // Règle 2 : Calculer le montant du remboursement après une retenue imaginaire
    // (Exemple : si c'est une catégorie "Luxe", on ne rembourse que 80%)
    public static function calculerRemboursement($montant, $categorie)
    {
        if ($categorie == 'Luxe') {
            return $montant * 0.8;
        }
        return $montant;
    }

    // Règle 3 : Vérifier si une date tombe un week-end
    public static function estWeekEnd($date)
    {
        $jour = date('N', strtotime($date));
        return ($jour >= 6); // 6 = Samedi, 7 = Dimanche
    }
}
