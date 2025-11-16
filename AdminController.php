<?php
// Fichier : AdminController.php

// Ce contrôleur gère les opérations du Module 5 : Administration (requiert le rôle 'admin').

/**
 * Gère le GET /api/admin/users : Liste tous les profils utilisateurs.
 */
function handleListAllUsers() {
    // 1. SÉCURITÉ : Vérifie si l'utilisateur est Admin
    requireAdminPermission(); 
    
    // 2. Requête Supabase : Utilisation de la clé de service (true) pour contourner la RLS.
    $endpoint = '/rest/v1/profiles?select=id,name,email,role,created_at';
    
    $response = sendRequest('GET', $endpoint, [], [], true); 

    if ($response['status_code'] === 200) {
        return json_response(['success' => true, 'users' => $response['data']], 200);
    }
    
    return json_response(['success' => false, 'message' => 'Échec de la récupération des utilisateurs.'], 500);
}


/**
 * Gère le PUT /api/admin/users/{id} : Met à jour le rôle d'un utilisateur cible.
 */
function handleUpdateUserRole($target_user_id, $data) {
    // 1. SÉCURITÉ : Vérifie si l'utilisateur est Admin
    requireAdminPermission();

    if (!isset($data['new_role']) || !in_array($data['new_role'], ['admin', 'user'])) {
        return json_response(['success' => false, 'message' => 'Rôle invalide.'], 400);
    }
    
    // 2. Mise à jour dans la table 'profiles' (avec clé de service)
    $endpoint = '/rest/v1/profiles?id=eq.' . urlencode($target_user_id);
    $payload = ['role' => $data['new_role']];

    $response = sendRequest('PUT', $endpoint, [], $payload, true); 

    if ($response['status_code'] === 204) {
        return json_response(['success' => true, 'message' => "Rôle de l'utilisateur $target_user_id mis à jour."], 200);
    }
    
    return json_response(['success' => false, 'message' => 'Échec de la mise à jour du rôle ou utilisateur non trouvé.'], 500);
}


/**
 * Gère le DELETE /api/admin/users/{id} : Supprime un utilisateur et ses données.
 */
function handleDeleteUser($target_user_id) {
    // 1. SÉCURITÉ : Vérifie si l'utilisateur est Admin
    requireAdminPermission();

    // 2. Suppression dans la table 'profiles'
    // La suppression en cascade (ON DELETE CASCADE) dans PostgreSQL gère la suppression des données liées.
    $endpoint = '/rest/v1/profiles?id=eq.' . urlencode($target_user_id);
    
    // Utilisation de la clé de service
    $response = sendRequest('DELETE', $endpoint, [], [], true); 

    if ($response['status_code'] === 204) {
        return json_response(['success' => true, 'message' => "Utilisateur $target_user_id supprimé."], 204);
    }
    
    return json_response(['success' => false, 'message' => 'Échec de la suppression du compte.'], 500);
}