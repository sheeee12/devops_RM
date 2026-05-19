<?php
// On simule une erreur 500 (Panne serveur)
http_response_code(500);
echo "CRITICAL ERROR";
exit();
