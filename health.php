<?php
// On provoque une erreur de syntaxe volontaire (pas de point-virgule, texte n'importe où)
Ceci est une erreur fatale qui va casser le moteur PHP
echo $variable_qui_n_existe_pas -> methode_inexistante();