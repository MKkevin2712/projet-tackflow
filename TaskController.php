<?php
// Fichier : TaskController.php


// ***************************************************************
// MODULE 2 : CRUD DE BASE
// ***************************************************************

// READ (GET /api/tasks) - Inclut le filtrage et le tri
function handleReadTasks() {
    $owner_id = authenticateAndGetUserId(); 
    if (!$owner_id) { sendAuthError(); } 

    // Jointure pour inclure les collaborateurs et application des filtres/tris
    $endpoint = '/rest/v1/tasks?select=*,shared_tasks(shared_with_id,permission_level)'; 
    
    if (isset($_GET['status'])) { $endpoint .= '&status=eq.' . urlencode($_GET['status']); }
    if (isset($_GET['priority'])) { $endpoint .= '&priority=eq.' . urlencode($_GET['priority']); }
    if (isset($_GET['sort'])) { $endpoint .= '&order=' . urlencode($_GET['sort']); }

    // La RLS sur la table 'tasks' est censée gérer la vue des tâches partagées.
    $headers = ['Authorization: Bearer ' . $owner_id];
    $response = sendRequest('GET', $endpoint, $headers);
    
    if ($response['status_code'] === 200) {
        return json_response(['success' => true, 'tasks' => $response['data']], 200);
    }
    
    return json_response(['success' => false, 'message' => 'Erreur de récupération des tâches.'], 500);
}

// CREATE (POST /api/tasks)
function handleCreateTask($data) {
    $owner_id = authenticateAndGetUserId(); 
    if (!$owner_id) { sendAuthError(); }
    
    if (empty($data['title'])) { return json_response(['success' => false, 'message' => 'Le titre est requis.'], 400); }
    
    $payload = [
        'title' => $data['title'],
        'description' => $data['description'] ?? null,
        'due_date' => $data['due_date'] ?? null,
        'priority' => $data['priority'] ?? 'Moyenne',
        'owner_id' => $owner_id
    ];

    $headers = ['Authorization: Bearer ' . $owner_id, 'Prefer: return=representation'];
    $response = sendRequest('POST', '/rest/v1/tasks', $headers, $payload);
    
    if ($response['status_code'] === 201) {
        // NOTE: L'insertion dans 'shared_tasks' (pour le créateur) doit idéalement être gérée par un Trigger PostgreSQL dans Supabase après l'INSERT dans 'tasks'.
        return json_response(['success' => true, 'message' => 'Tâche créée.', 'task' => $response['data'][0]], 201);
    }
    return json_response(['success' => false, 'message' => 'Erreur lors de la création.'], 500);
}

// UPDATE (PUT /api/tasks/{id})
function handleUpdateTask($task_id, $data) {
    $owner_id = authenticateAndGetUserId(); 
    if (!$owner_id) { sendAuthError(); }

    // Filtrer uniquement les champs autorisés à la modification
    $update_fields = array_intersect_key($data, array_flip(['title', 'description', 'due_date', 'status', 'priority']));
    if (empty($update_fields)) { return json_response(['success' => false, 'message' => 'Aucune donnée valide fournie.'], 400); }
    
    $endpoint = '/rest/v1/tasks?id=eq.' . urlencode($task_id);
    
    $headers = ['Authorization: Bearer ' . $owner_id, 'Prefer: return=representation'];
    $response = sendRequest('PUT', $endpoint, $headers, $update_fields);
    
    if ($response['status_code'] === 200) {
        return json_response(['success' => true, 'message' => 'Tâche mise à jour.', 'task' => $response['data'][0]], 200);
    }
    // Si RLS échoue (accès non autorisé), Supabase renvoie souvent 403/404/406 ou un autre code d'échec
    return json_response(['success' => false, 'message' => 'Échec de la mise à jour ou accès non autorisé.'], 403);
}

// DELETE (DELETE /api/tasks/{id})
function handleDeleteTask($task_id) {
    $owner_id = authenticateAndGetUserId(); 
    if (!$owner_id) { sendAuthError(); }
    
    $endpoint = '/rest/v1/tasks?id=eq.' . urlencode($task_id);
    
    $headers = ['Authorization: Bearer ' . $owner_id];
    $response = sendRequest('DELETE', $endpoint, $headers);
    
    if ($response['status_code'] === 204) { // 204 No Content est la réponse standard pour un DELETE réussi
        return json_response(['success' => true, 'message' => 'Tâche supprimée.'], 204);
    }
    return json_response(['success' => false, 'message' => 'Échec de la suppression ou accès non autorisé.'], 403);
}


// ***************************************************************
// MODULE 3 : PARTAGE (Collaboration)
// ***************************************************************

// POST /api/tasks/{id}/share
function handleShareTask($task_id, $data) {
    $owner_id = authenticateAndGetUserId(); 
    if (!$owner_id) { sendAuthError(); }

    if (empty($data['shared_with_email'])) { return json_response(['success' => false, 'message' => 'L\'email est requis.'], 400); }

    $collaborator_email = $data['shared_with_email'];
    $permission = $data['permission_level'] ?? 'contributeur'; // Utiliser 'contributeur' comme dans le schéma

    // 1. Trouver l'ID de l'utilisateur cible par son Email (dans la table 'profiles')
    $email_search_endpoint = '/rest/v1/profiles?name=eq.' . urlencode($collaborator_email) . '&select=id';
    $headers_search = ['Authorization: Bearer ' . $owner_id]; 
    $search_response = sendRequest('GET', $email_search_endpoint, $headers_search);
    
    if ($search_response['status_code'] !== 200 || empty($search_response['data'][0])) {
        return json_response(['success' => false, 'message' => 'Utilisateur collaborateur non trouvé.'], 404);
    }

    $collaborator_id = $search_response['data'][0]['id'];
    if ($collaborator_id === $owner_id) { return json_response(['success' => false, 'message' => 'Vous êtes déjà le propriétaire.'], 400); }
    
    // 2. Insertion du partage dans la table 'shared_tasks'
    $endpoint_share = '/rest/v1/shared_tasks';
    $share_payload = [
        'task_id' => $task_id,
        'shared_with_id' => $collaborator_id,
        'permission_level' => $permission
    ];
    
    $response = sendRequest('POST', $endpoint_share, $headers_search, $share_payload);
    
    if ($response['status_code'] === 201) {
        return json_response([
            'success' => true, 
            'message' => 'Tâche partagée avec succès.',
            'collaborator_id' => $collaborator_id
        ], 201);
    }
    
    // 409 Conflict (si la clé primaire composite task_id, shared_with_id est violée)
    return json_response(['success' => false, 'message' => 'Échec du partage (cet utilisateur est peut-être déjà collaborateur).'], 409);
}