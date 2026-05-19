<?php
// On force explicitement un code d'erreur 500
http_response_code(500);
echo "Erreur critique de test pour le rollback";
exit();
