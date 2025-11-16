<?php
// Fichier : CommentController.php
// Ce fichier doit avoir accès à AuthMiddleware.php (pour l'authentification) 
// et à ApiClient.php (pour sendRequest et json_response).

/**
 * Gère le POST /api/tasks/{id}/comments : Ajoute un commentaire à une tâche.
 *
 * La RLS sur la table 'comments' doit garantir que l'utilisateur a le droit de commenter cette tâche.
 *
 * @param string $task_id L'ID de la tâche commentée.
 * @param array $data Les données du commentaire (content).
 */
function handleCreateComment($task_id, $data) {
    // 1. SÉCURITÉ : Vérification de l'authentification
    $user_id = authenticateAndGetUserId(); 
    if (!$user_id) { sendAuthError(); }

    if (empty($data['content'])) {
        return json_response(['success' => false, 'message' => 'Le contenu du commentaire est requis.'], 400);
    }
    
    // 2. Préparation du payload
    $payload = [
        'task_id' => $task_id,
        'user_id' => $user_id, // L'ID de l'auteur est l'utilisateur authentifié
        'content' => $data['content']
    ];

    // 3. Appel à PostgREST pour l'insertion
    $headers = ['Authorization: Bearer ' . $user_id, 'Prefer: return=representation'];
    $response = sendRequest('POST', '/rest/v1/comments', $headers, $payload);
    
    if ($response['status_code'] === 201) {
        return json_response(['success' => true, 'message' => 'Commentaire ajouté.', 'comment' => $response['data'][0]], 201);
    }
    
    // Si la RLS refuse (utilisateur non propriétaire/collaborateur)
    return json_response(['success' => false, 'message' => 'Échec de l\'ajout du commentaire. Accès à la tâche non autorisé.'], 403);
}


/**
 * Gère le GET /api/tasks/{id}/comments : Lit les commentaires d'une tâche.
 *
 * @param string $task_id L'ID de la tâche.
 */
function handleReadComments($task_id) {
    // 1. SÉCURITÉ : Vérification de l'authentification
    $user_id = authenticateAndGetUserId(); 
    if (!$user_id) { sendAuthError(); }
    
    // 2. Construction de l'endpoint PostgREST
    // Filtre par task_id et jointures pour récupérer le nom de l'auteur
    $endpoint = '/rest/v1/comments?task_id=eq.' . urlencode($task_id) . '&select=id,task_id,content,created_at,user_id,profiles(name)';
    
    // Trie par date pour afficher les plus anciens en haut (asc)
    $endpoint .= '&order=created_at.asc';

    // 3. Appel à PostgREST
    // La RLS sur la table 'comments' garantit que l'utilisateur a le droit de lire.
    $headers = ['Authorization: Bearer ' . $user_id];
    $response = sendRequest('GET', $endpoint, $headers);
    
    if ($response['status_code'] === 200) {
        return json_response(['success' => true, 'comments' => $response['data']], 200);
    }
    
    // Si la RLS refuse
    return json_response(['success' => false, 'message' => 'Échec de la récupération des commentaires ou tâche inaccessible.'], 403);
}

// NOTE: Pour un CRUD complet, on ajouterait ici handleUpdateComment et handleDeleteComment
// La logique de ces deux fonctions serait similaire, avec une vérification additionnelle
// que l'utilisateur est bien le 'user_id' du commentaire ET a accès à la tâche.