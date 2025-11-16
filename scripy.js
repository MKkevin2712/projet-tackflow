// Fichier: script.js

// L'URL de base de l'API REST (doit correspondre à la constante API_BASE_URL en PHP)
const API_BASE_URL = 'http://localhost:8000/api'; 
const LOGIN_ENDPOINT = `${API_BASE_URL}/auth/login`;

document.getElementById('login-form').addEventListener('submit', handleLogin);

const messageElement = document.getElementById('message-status');

/**
 * Récupère la valeur d'une variable CSS.
 * @param {string} name - Ex: '--primary-color'.
 */
function getCssVariable(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

/**
 * Gère la soumission du formulaire de connexion et l'appel à l'API.
 */
async function handleLogin(event) {
    event.preventDefault(); 
    messageElement.textContent = 'Connexion en cours...';
    messageElement.style.color = '#333';

    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;

    try {
        const response = await fetch(LOGIN_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });

        const data = await response.json();

        if (response.ok) {
            // Connexion réussie (Statut 200 OK)
            messageElement.textContent = 'Connexion réussie ! Redirection...';
            messageElement.style.color = getCssVariable('--primary-color'); 
            
            // Stockage du jeton JWT et du rôle
            localStorage.setItem('taskflow_token', data.token);
            localStorage.setItem('taskflow_role', data.role);

            // window.location.href = 'dashboard.html'; 
            
        } else {
            // Connexion échouée (Statut 401 Unauthorized, etc.)
            messageElement.textContent = data.message || 'Identifiants invalides ou erreur API.';
            messageElement.style.color = getCssVariable('--error-color'); 
        }

    } catch (error) {
        console.error('Erreur réseau ou du serveur:', error);
        messageElement.textContent = 'Erreur: Impossible de joindre le serveur API.';
        messageElement.style.color = getCssVariable('--error-color');
    }
}