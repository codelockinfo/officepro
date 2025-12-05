<?php
/**
 * Login API Endpoint
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Validator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

$validator = new Validator();

// Validate inputs
$validator->required($email, 'Email');
$validator->email($email, 'Email');
$validator->required($password, 'Password');

if ($validator->hasErrors()) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()]);
    exit;
}

// Attempt login
$result = Auth::login($email, $password);

echo json_encode($result);


