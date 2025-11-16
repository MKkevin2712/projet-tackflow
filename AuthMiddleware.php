<?php
// Fichier : AuthMiddleware.php
require_once 'config.php'; // Pour les clés de service

// Fonction utilitaire pour envoyer une erreur JSON
function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *'); 
    echo json_encode($data);
    exit;
}

/**
 * Récupère l'ID utilisateur (UUID) à partir du jeton JWT.
 */
function authenticateAndGetUserId() {
    // ... (Logique de récupération et de décodage du JWT) ...
    // ... (Vérification de l'expiration) ...
    // Retourne $user_id (UUID) ou null
    
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return null;
    }

    $jwt = $matches[1];
    $payload_b64 = explode('.', $jwt)[1] ?? null;

    if (!$payload_b64) { return null; }

    $payload_json = base64_decode(strtr($payload_b64, '-_', '+/')); 
    $payload = json_decode($payload_json, true);
    
    if (empty($payload) || (isset($payload['exp']) && $payload['exp'] < time())) {
        return null; 
    }
    
    return $payload['sub'] ?? null; // Supabase utilise 'sub'
}

function sendAuthError() {
    json_response(['success' => false, 'message' => 'Accès refusé. Jeton invalide ou manquant.'], 401);
}

// Fonction pour le Module 5 : Administration
function getRoleByUserId($user_id) {
    // ... (Logique d'appel à Supabase pour lire le rôle en utilisant SUPABASE_SERVICE_KEY) ...
    // Retourne 'admin' ou 'user'
    
    // Simplifié : on devrait utiliser sendRequest ici, en utilisant SUPABASE_SERVICE_KEY
    // Pour l'exemple, supposons une lecture directe de la DB pour le rôle
    $role = 'user'; // Placeholder
    // return $role; 
    
    // Dans la pratique, on devrait le lire du token si l'on a pu l'y mettre lors du login.
    return $role; // À remplacer par la lecture sécurisée
}

function requireAdminPermission() {
    $user_id = authenticateAndGetUserId();
    if (!$user_id) { sendAuthError(); }

    $role = getRoleByUserId($user_id);

    if ($role !== 'admin') {
        json_response(['success' => false, 'message' => 'Accès refusé. Autorisation administrateur requise.'], 403);
    }
    
    return $user_id; 
}