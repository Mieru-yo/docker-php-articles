<?php
// Configuration de la base lue depuis l'environnement (fournie par docker-compose.yml).
// Aucune valeur n'est codée en dur : sans ces variables, l'application refuse de démarrer.
function env_required(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException("Variable d'environnement manquante : $name");
    }
    return $value;
}

$dbHost = env_required('DB_HOST');
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = env_required('DB_NAME');

define('DB_DSN', "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4");
define('DB_USER', env_required('DB_USER'));
define('DB_PASS', env_required('DB_PASSWORD'));

$options = array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // gérer les erreurs en tant qu'exception
    PDO::ATTR_EMULATE_PREPARES => false // faire des vrais requêtes préparées et non une émulation
);
