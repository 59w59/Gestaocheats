<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

// Credenciais do administrador
$username = 'admin';
$password = 'admin123';
$role = 'super_admin'; // ou qualquer outro papel
$first_name = 'Administrador';
$email = 'admin@seudominio.com';

// Verificar se já existe um admin com este username
$stmt = $db->prepare("SELECT id FROM admins WHERE username = ?");
$stmt->execute([$username]);
if ($stmt->rowCount() > 0) {
    echo "Um administrador com este nome de usuário já existe.";
    exit;
}

// Criptografar a senha
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Inserir o administrador usando a coluna 'first_name' em vez de 'name'
$stmt = $db->prepare("INSERT INTO admins (username, password, role, first_name, email, is_active, created_at) 
                     VALUES (?, ?, ?, ?, ?, 1, NOW())");
$result = $stmt->execute([$username, $hashed_password, $role, $first_name, $email]);

if ($result) {
    echo "Administrador criado com sucesso!";
} else {
    echo "Erro ao criar administrador: " . implode(", ", $stmt->errorInfo());
}
?>