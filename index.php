<?php
// Fichier : index.php - Le Routeur Principal de l'API TaskFlow

// -----------------------------------------------------------------
// 1. INCLUSIONS (CORRIGÉ : Utilisation de __DIR__ pour le chemin absolu)
// -----------------------------------------------------------------
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/AuthMiddleware.php';
require_once __DIR__ . '/UserController.php';
require_once __DIR__ . '/TaskController.php';
require_once __DIR__ . '/CommentController.php';
require_once __DIR__ . '/DashboardController.php';
require_once __DIR__ . '/AdminController.php';


// -----------------------------------------------------------------
// 2. GESTION CORS (Cross-Origin Resource Sharing)
// -----------------------------------------------------------------
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Réponse immédiate pour les requêtes OPTIONS (pré-vol CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
   exit;
}


// -----------------------------------------------------------------
// 3. PRÉPARATION DES DONNÉES
// -----------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
// Nettoyage : Retire '/api' du chemin (rendu possible par router.php)
$path = str_replace('/api', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$data = json_decode(file_get_contents('php://input'), true);


// -----------------------------------------------------------------
// 4. LOGIQUE DE ROUTAGE (Dispatch)
// -----------------------------------------------------------------

// MODULE 1 : AUTHENTIFICATION
if ($path === '/auth/register' && $method === 'POST') {
   handleRegister($data);
} elseif ($path === '/auth/login' && $method === 'POST') {
    handleLogin($data);
} elseif ($path === '/auth/logout' && $method === 'POST') {
    handleLogout();
}

// MODULE 4 : DASHBOARD
elseif ($path === '/dashboard/stats' && $method === 'GET') {
    handleGetStats();
} elseif ($path === '/dashboard/urgent' && $method === 'GET') {
    handleGetUrgentTasks();
}

// MODULE 5 : ADMINISTRATION
elseif ($path === '/admin/users' && $method === 'GET') {
    handleListAllUsers();
}
// Routes ciblées sur un utilisateur Admin
elseif (preg_match('/^\/admin\/users\/([a-f0-9-]+)$/i', $path, $matches)) {
    $user_id = $matches[1];
    if ($method === 'PUT') {
        handleUpdateUserRole($user_id, $data);
    } elseif ($method === 'DELETE') {
        handleDeleteUser($user_id);
    }
}

// MODULE 3 : PARTAGE ET COMMENTAIRES
// POST /tasks/{id}/share (Ajout collaborateur)
elseif (preg_match('/^\/tasks\/([a-f0-9-]+)\/share$/i', $path, $matches)) {
    $task_id = $matches[1];
   if ($method === 'POST') {
        handleShareTask($task_id, $data);
    }
}
// DELETE /tasks/{id}/share/{user_id} (Retrait collaborateur)
elseif (preg_match('/^\/tasks\/([a-f0-9-]+)\/share\/([a-f0-9-]+)$/i', $path, $matches)) {
    $task_id = $matches[1];
    $user_id_to_remove = $matches[2];
    if ($method === 'DELETE') {
       handleUnshareTask($task_id, $user_id_to_remove);
    }
}
// GET/POST /tasks/{id}/comments
elseif (preg_match('/^\/tasks\/([a-f0-9-]+)\/comments$/i', $path, $matches)) {
    $task_id = $matches[1];
    if ($method === 'POST') {
        handleCreateComment($task_id, $data);
    } elseif ($method === 'GET') {
        handleReadComments($task_id);
        }
}

// MODULE 2 : CRUD TÂCHES (GÉNÉRAL ET SPÉCIFIQUE)
// Routes ciblées sur une tâche spécifique
elseif (preg_match('/^\/tasks\/([a-f0-9-]+)$/i', $path, $matches)) {
    $task_id = $matches[1];
    if ($method === 'GET') {
        handleReadTaskDetail($task_id);
    } elseif ($method === 'PUT') {
      handleUpdateTask($task_id, $data);
   } elseif ($method === 'DELETE') {
     handleDeleteTask($task_id);
   }
}
// Routes générales des tâches
elseif ($path === '/tasks') {
    if ($method === 'GET') {
       handleReadTasks();
    } elseif ($method === 'POST') {
        handleCreateTask($data);
    }
}


// ROUTE NON TROUVÉE
else {
    json_response(['success' => false, 'message' => 'Endpoint non trouvé.'], 404);
}