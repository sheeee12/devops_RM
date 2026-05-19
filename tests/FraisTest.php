<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../classes/FraisHelper.php';

class FraisTest extends TestCase
{

    // TEST 1 : Vérifier la détection de montant abusif
    public function testDetectionMontantAbusif()
    {
        // Si je donne 3000, ça doit être VRAI (abusif)
        $this->assertTrue(FraisHelper::estMontantAbusif(3000));

        // Si je donne 500, ça doit être FAUX (pas abusif)
        $this->assertFalse(FraisHelper::estMontantAbusif(500));
    }

    // TEST 2 : Vérifier le calcul du remboursement
    public function testCalculRemboursementLuxe()
    {
        // Pour 1000 DH en catégorie Luxe, on attend 800 DH (80%)
        $resultat = FraisHelper::calculerRemboursement(1000, 'Luxe');
        $this->assertEquals(800, $resultat);
    }

    // TEST 3 : Vérifier la détection du week-end
    public function testDetectionWeekEnd()
    {
        // Le 2024-05-19 était un Dimanche (True)
        $this->assertTrue(FraisHelper::estWeekEnd('2024-05-19'));

        // Le 2024-05-20 était un Lundi (False)
        $this->assertFalse(FraisHelper::estWeekEnd('2024-05-20'));
    }
}
