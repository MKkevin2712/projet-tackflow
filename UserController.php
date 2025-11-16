<?php
// Fichier : UserController.php

// Note: Les fonctions utilitaires (sendRequest, json_response, sendAuthError) sont supposées accessibles.

/**
 * Gère le POST /auth/register : Enregistre un nouvel utilisateur via Supabase Auth.
 */
function handleRegister($data) {
    if (!isset($data['email'], $data['password'])) {
        return json_response(['success' => false, 'message' => 'Email et mot de passe requis.'], 400);
    }
    
    [cite_start]// Appel à Supabase Auth /signup (Module Authentification) [cite: 36]
    $endpoint = '/auth/v1/signup';
    $payload = ['email' => $data['email'], 'password' => $data['password']];

    // sendRequest utilise SUPABASE_ANON_KEY par défaut
    $response = sendRequest('POST', $endpoint, [], $payload); 

    if ($response['status_code'] === 201) {
        // Le profil et le rôle 'user' sont gérés par un trigger côté DB après l'insertion.
        return json_response(['success' => true, 'message' => 'Inscription réussie.'], 201);
    } 
    
    // Gestion des erreurs Supabase
    $error_message = $response['data']['msg'] ?? 'Email déjà utilisé ou erreur inconnue.';
    return json_response(['success' => false, 'message' => $error_message], 400);
}


/**
 * Gère le POST /auth/login : Connecte l'utilisateur et récupère le jeton/rôle.
 */
function handleLogin($data) {
    if (!isset($data['email'], $data['password'])) {
        return json_response(['success' => false, 'message' => 'Email ou mot de passe manquant.'], 400);
    }

    // 1. Appel à Supabase /token (sign-in)
    $endpoint = '/auth/v1/token?grant_type=password';
    $payload = ['email' => $data['email'], 'password' => $data['password']];
    
    $response = sendRequest('POST', $endpoint, [], $payload);
    
    if ($response['status_code'] !== 200) {
        return json_response(['success' => false, 'message' => 'Identifiants invalides.'], 401);
    }

    $jwt_token = $response['data']['access_token'];
    $user_id = $response['data']['user']['id']; 

    // 2. Récupération du Rôle (Lecture de la table 'profiles')
    // Utilise le JWT pour la RLS
    $endpoint_role = '/rest/v1/profiles?id=eq.' . $user_id . '&select=role';
    $headers_role = ['Authorization: Bearer ' . $jwt_token];

    $role_response = sendRequest('GET', $endpoint_role, $headers_role);
    
    $role = 'user'; // Valeur par défaut
    if ($role_response['status_code'] === 200 && !empty($role_response['data'][0])) {
        $role = $role_response['data'][0]['role']; [cite_start]// Récupère le rôle pour la gestion des permissions [cite: 39]
    }

    // 3. Réponse finale au Frontend
    return json_response([
        'success' => true, 
        'token' => $jwt_token, 
        'role' => $role
    ], 200);
}

/**
 * Gère le POST /auth/logout : Déconnecte l'utilisateur.
 */
function handleLogout() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    // Si le token n'est pas passé, on simule quand même une déconnexion réussie pour le Front-end
    if (empty($authHeader)) {
        return json_response(['success' => true, 'message' => 'Déconnexion effectuée.'], 200);
    }

    [cite_start]// Appel à Supabase /logout (Module Authentification) [cite: 38]
    $endpoint = '/auth/v1/logout';
    
    $response = sendRequest('POST', $endpoint, ['Authorization: ' . $authHeader]); 

    // Supabase retourne 204 No Content pour une déconnexion réussie.
    if ($response['status_code'] === 204) {
        return json_response(['success' => true, 'message' => 'Déconnexion effectuée.'], 200);
    }
    
    return json_response(['success' => false, 'message' => 'Échec de la déconnexion.'], 400);
}