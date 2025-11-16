<?php
// Fichier : ApiClient.php
require_once 'config.php'; // Pour accéder aux constantes SUPABASE_URL, SUPABASE_ANON_KEY, etc.

/**
 * Envoie une requête HTTP à l'API de Supabase (Auth ou PostgREST) en utilisant cURL.
 *
 * @param string $method   Méthode HTTP (GET, POST, PUT, DELETE).
 * @param string $endpoint Chemin de l'endpoint (ex: /auth/v1/signup ou /rest/v1/tasks).
 * @param array $headers   En-têtes additionnels (ex: ['Authorization: Bearer JWT']).
 * @param array $data      Données à envoyer dans le corps de la requête (pour POST/PUT).
 * @return array           Tableau associatif contenant le statut et les données de la réponse.
 */
function sendRequest($method, $endpoint, $headers = [], $data = []) {
    // 1. Construction de l'URL complète
    $url = SUPABASE_URL . $endpoint; 

    // 2. Initialisation de cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Retourne la réponse

    // --- 3. Gestion des En-têtes ---
    $defaultHeaders = [
        // La clé Anon Key est toujours requise par Supabase, même si l'Authorization est présente
        'apikey: ' . SUPABASE_ANON_KEY, 
        'Content-Type: application/json',
    ];
    // Fusionner les en-têtes (permet de passer l'en-tête Authorization: Bearer JWT)
    $requestHeaders = array_merge($defaultHeaders, $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);


    // --- 4. Gestion des Méthodes et des Données ---
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'PUT' || $method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($method === 'PUT' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } 

    // --- 5. Exécution et Traitement de la Réponse ---
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        // Erreur de connexion/réseau
        return [
            'status_code' => 503,
            'data' => ['message' => 'Erreur de connexion à l\'API Supabase: ' . curl_error($ch)]
        ];
    }
    
    curl_close($ch);

    // Décodage de la réponse JSON
    $decodedData = json_decode($response, true);
    
    return [
        'status_code' => $statusCode,
        'data' => $decodedData
    ];
}

/**
 * Fonction utilitaire pour renvoyer une réponse JSON au Frontend.
 * Cette fonction est appelée par tous les contrôleurs et par AuthMiddleware.
 */
function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    // En-têtes CORS nécessaires pour communiquer entre localhost:X (Frontend) et localhost:Y (API)
    header('Access-Control-Allow-Origin: *'); 
    header('Access-Control-Allow-Headers: Content-Type, Authorization'); 
    echo json_encode($data);
    exit;
}