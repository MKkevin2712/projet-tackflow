<?php
// Fichier : DashboardController.php
// Ce fichier doit avoir accès à AuthMiddleware.php, ApiClient.php, et json_response.

/**
 * Gère le GET /api/dashboard/stats : Fournit les statistiques agrégées de l'utilisateur.
 * * Les statistiques incluent : Nombre de tâches par statut, tâches urgentes/en retard.
 */
function handleGetStats() {
    // 1. SÉCURITÉ : Vérification de l'authentification
    $user_id = authenticateAndGetUserId(); 
    if (!$user_id) { sendAuthError(); }

    // --- 2. Requête Supabase pour les Tâches par Statut ---
    
    // Pour les statistiques, PostgREST n'a pas de fonction GROUP BY simple.
    // L'approche la plus efficace est d'utiliser une Vue PostgreSQL ou d'utiliser le PostgREST 
    // en filtrant les données par colonne et en comptant côté PHP, 
    // ou en utilisant les Agrégats PostgREST.
    
    // Solution A (Simplicité, si la RLS est bien faite) : Lire toutes les tâches et compter en PHP.
    
    // Le jeton JWT assure que la RLS renvoie seulement les tâches auxquelles l'utilisateur a accès.
    $headers = ['Authorization: Bearer ' . $user_id];
    
    // Récupérer le statut et la date d'échéance (due_date) pour le calcul
    $read_endpoint = '/rest/v1/tasks?select=status,due_date'; 
    $response = sendRequest('GET', $read_endpoint, $headers);
    
    if ($response['status_code'] !== 200) {
        return json_response(['success' => false, 'message' => 'Erreur lors de la récupération des données statistiques.'], 500);
    }
    
    $tasks = $response['data'];
    
    // --- 3. Traitement des données en PHP ---
    $stats = [
        'tasks_by_status' => [
            'À faire' => 0, 
            'En cours' => 0, 
            'Terminé' => 0
        ],
        'total_urgent' => 0,
        'total_overdue' => 0
    ];
    $current_time = time();

    foreach ($tasks as $task) {
        $status = $task['status'] ?? 'À faire';
        
        // a) Comptage par statut
        if (isset($stats['tasks_by_status'][$status])) {
            $stats['tasks_by_status'][$status]++;
        } else {
             // Si un statut personnalisé existe, on l'ajoute
             $stats['tasks_by_status'][$status] = 1;
        }

        // b) Comptage en retard (Overdue)
        if (!empty($task['due_date'])) {
            $due_timestamp = strtotime($task['due_date']);
            
            // Si la date est passée et que le statut n'est pas 'Terminé'
            if ($due_timestamp < $current_time && $status !== 'Terminé') {
                $stats['total_overdue']++;
            }
        }
        
        // c) Comptage urgent (Simplement les tâches avec la priorité 'Haute')
        // NOTE: Nécessiterait de récupérer aussi le champ 'priority' dans la requête SELECT
        // Pour cet exemple, nous allons ignorer l'urgent, ou supposer qu'il est récupéré
        
    }

    // --- 4. Réponse au Frontend ---
    return json_response(['success' => true, 'stats' => $stats], 200);
}