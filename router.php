<?php
// Fichier: router.php

// Si la ressource demandée existe déjà sur le disque (comme un fichier .css ou .js),
// le serveur PHP le sert directement.
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js)$/', $_SERVER["REQUEST_URI"])) {
    return false;
} else {
    // Sinon, toutes les autres requêtes (y compris celles vers /api/...) sont 
    // redirigées vers notre routeur principal.
    require 'index.php';
}
?>