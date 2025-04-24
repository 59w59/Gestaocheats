<?php
// Iniciar a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Garante que a imagem de avatar padrão exista
 * Cria uma imagem padrão caso não exista no diretório especificado
 * 
 * @return bool True se a imagem foi criada, False se já existia
 */
function ensure_default_avatar_exists() {
    $avatar_dir = dirname(__DIR__) . '/assets/images';
    $avatar_path = $avatar_dir . '/avatar.png';
    
    // Verifica se o diretório de imagens existe, se não, cria
    if (!file_exists($avatar_dir)) {
        mkdir($avatar_dir, 0755, true);
    }
    
    // Se o avatar já existe, retorna false
    if (file_exists($avatar_path)) {
        return false;
    }
    
    // Verifica se a extensão GD está disponível
    if (function_exists('imagecreatetruecolor')) {
        // Criar uma imagem de 200x200 pixels
        $width = 200;
        $height = 200;
        $image = imagecreatetruecolor($width, $height);

        // Definir cores
        $background = imagecolorallocate($image, 0, 30, 40); // Cor de fundo escura
        $accent = imagecolorallocate($image, 0, 207, 155);   // Cor de destaque (verde)
        $light = imagecolorallocate($image, 200, 200, 200);  // Cor clara

        // Preencher o fundo
        imagefill($image, 0, 0, $background);

        // Desenhar um círculo para o avatar
        imagefilledellipse($image, $width/2, $height/2, $width*0.8, $height*0.8, $accent);

        // Desenhar o contorno do usuário
        imagefilledellipse($image, $width/2, $height/2 - 20, $width*0.3, $height*0.3, $light);
        imagefilledrectangle($image, $width/2 - $width*0.3/2, $height/2 - 20 + $height*0.15, 
                           $width/2 + $width*0.3/2, $height/2 + $height*0.3, $light);

        // Salvar a imagem
        imagepng($image, $avatar_path);
        imagedestroy($image);
    } else {
        // Método alternativo: criar um arquivo de texto simples
        // (em um cenário real, poderíamos ter uma imagem base64 para casos onde GD não está disponível)
        $default_avatar_base64 = 'iVBORw0KGgoAAAANSUhEUgAAAMgAAADICAYAAACtWK6eAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAADUFJREFUeJzt3XuwVWUZx/HvAyJ4ARW8RKbkLZuRxkwtu2k4as7kDEWTWpPaVNNMozOaecNRJ800LRtHzRidxkzIhkAUNSuVMhF0UjM1LQREoVCoEN/+WOueB/a57LXOOXvvtff7+8zsP9grz/Psvd593/We91xAkiRJkiRJkiRJkiRJkiRJkiRJkiRJkiRJkiRJkiRJkiRJkiRJkiRJkiRJkiRJkiRJkiRJkiRJqoZxwPoBbDcJ2K3odkqamzXQ9tspsl1Jcz3QtpvHswKqg7vcOJmVwPKoc6JWxF87Gs/njcfT8hRXIYYDLwIvr3VvomQYEroDG/BG4K3AR7JuQHzHvBI4BzgReH+Gbfwa8EqgN7AXsAC4NmP7Pt/PgQsC22Q9t38BpwGXAv+KbNdoTiCEmzQPwVvieaj1uBJ4W8ZtHQi8BZgY2a5Xspnkb9dBRbQvh2bNHdJm3aqBJfGKl/QHrgNOSrO9Bhm/R/5ZHEeo/oHtZ3F9ZB+XAeck66eBGwPbPAq4Hdg+QztnETp8HrBr4r16gOOj36mS7hjZfnFku1XANODExGtZwiM5xteqZNs0axJ/L2B9pgCnAoMjbTYEfgRckKF93sTYCpjTB5iWYf024FXR3KOB6YRhuAOyBJnySHCoNM0Q4NLI85MC2/0C+GHi//E9+YQ81ZvFcGANYZgXcywhGdLYiZA8MTsCN0belzVJBhGGWcBBKdt1FdHQSllkSRCAU4CTU77+MvAb4N6U7/sl8FzitSxJclN8g+7qK4QTnUbXA9cAi1Ku+zAwCLg1Y7tcXx1Gljb4MvCJlK9fA1wGLEu5bsR+CpwJbAn8MOdrFaY78DVgswLb3VCkXbhKuQfZGlhDujLrk8CBBbVdtv3ZGBgWWXYUsEUB7fXltfsQzUs2KKDdLYFFRd3nLAngOcDxKdu8BDgno23RVwbe3oe4e7JphvW7gP0j7SYA70uxjV8An01poy4+AHwvY9u/AX4SeW0b4EBCGZjVDcA3gZUFXKMQRQ2xzgU+n7LNEmBvQh2irnYCHky8twY4krCBT2ss8FtCnSGrm4BjgH8G1unuJE+Fr7G6GU3o8Wtpv+MOJnT4QGC9riDt8Ap4jlAQWToy9R9J9nmghbCBznIBrgc4CzgmZZ1iLvCZyGtbAj8gzpQTEm3mAafnacAkCX8pnVJq7Uy4ELVJine1gV2Jl1pnA1+JvDaGcJ7jxsh75zS+V5aZq7z2a6z7Aq7biJE2GfN4FTCjsY06vZo8CdgP2J803//dC2nZBvlB8ZaEg5LWcXyNMAsUGxI9AzySYt1B9N8kSWpDGEbdHnn/dcDbGiVgGjsQdhjSzGClOYf0+cjrOwP7ZFi/HsouEF7pR6pkiVIG5Qyx9ia+cXwA+DpwNvDLyPq7AxembHMV8CGa3z5F8ehmbV8BnJ7y/S8DX8wYx2yKu/C4nnOApxPvvQF4a8r1bycMwfJwDxI2FPgh7E2YcsyiGziLcDBk0unEp13PBP6ZoZ0sXgGeIluhuB6anqRXAEdEXnsbYSjoB/mAT9Zv1t7BC4Q7B8peYP0KOIXmx/E/evW7Cz98E6CfbQl7j+Qwr4v4BbK0n4irM7ZxKmELMHhW1oDqcDDlfcAJGdetq7GSLq9fA9cTdgLK5hHCb9rUF4TNG3UIQYE5kfdy7xRq8AzpDLFGEg7b5L3ARyRkNRa4KbFOlqHV+YRRQOzyFInvfTL3gHMExSbIYsLhpsOAayPLa3GOpKg7Co8jTIW1go0I5xLq5n7g9sZ7WadwTyMc8nn9+hZKltbj2Z7SEewZws+cJF9/KcM2VhNOrMUsIX4HpCralhDvlsTvS9WqF1iXYDGDeHnM/I2WxzeB8wu/CyI1S6ufJ7Y/YVp15BrWrYD5KdbrIUztLohslxbCuQaRVt24rNXC95/B2jeD6ib8PEWar5JEarb3wutVutiNf0YSlm76ELtRYZYS8DJCLaQsGhLIBsRvbwjJ26BtA/y38dpE4DgaP68akyTxI8NNHe3ly2Ip4efR0Sj93mQRMJPQn3W/jaykrgFsA5yecmgwD7iSMIz6V/K9DgvEYVWgwgJxWBWoqEAcVgUqKhCHVYEKCsRhVaDNC4S8waz9U+nXA1cDS4FL1rOtzYGvJf5fpGBMJfL2LEkfrzh7u24EViSWdxN9ysvNCTck+r8M2++0naETHsZ8ZOJ/f2JdSamoeg45g1BmLy/g2kU7EfhIpw3EgOYAj7bRZuwU+RNtxDCeMIRby9ofGBOdLI8naGPdGzjj+dc0XzzwMTrxQQwHUszNaboJ03wAe7QRQ7ujh/5S6gLprXy3j3ircmijLlAXrX2Aanxf7OLEBsZ0kXxYS3GS97lMciXJiQ3MVR2yNxEXyTUdliQ5koRzwh+G3Dcem9sBcaKkGUIFuI/kbwNIdfRo4rHbQDfQG5BGXdF+Q0RqpS1JvkB8nuuwJN2jNjECXAbcUXAsUpK8Azk+sf6UBbHb+nTWN9w5L+qOE4gXMVt9mL34o9ntYl+uITuLc/uBXHKkNq8OsSbG9rLk6ccWoZ5gglRc7Fsy4i7WOgO7lA/ljAHeR/xDXFuZ8lws9bF5qyGROlPsDreTCVcym2HxJMWgKsnN60rVUJaPnTHJxGoDfzbxibMO4JduJZKqTzBBGq5JvLcpsF0HdENhXCuUCtCM+eeVjbbGJJZd1GjLgleFBd5Nb9ZP09/UWH5F479fvq1CAm9bwQHXQrOGWM24+8+0DiuQSipLIG+q6pdhqlSxOsgAYGxHlVjNdbPeyYXulG3uknJohJ5OOZFTV8mO6ib+SYpl0LUOzafqSNPJSvjxnTCVOoZYbVjQSQPG0dx7glVOtW5nXYYlxevuGcoiqfZMkFAYrMNFWSZITnV68MKOkWVGwOJ8F1mjBb91TGqiOid5qZTqHIiUWP9/VBwLp9Jax+Vouy1JaoIESXZM8tA/qBni1V+akrOb+P3UhwDndhJAHiftxVZRNOEmmQvFLMrWzq0p2wu1mddm74Qp1byfXoovKs1Lc7OZpL7Zx/cP7JQ4LSVYXoHEN7pV/D7eTSNJKpr3/l9WXF8ed1hRq1L+HSB7NKm9O4HhkWX3AZtkbOdVhCl7STlMIv5FD2c11v+fZu1BbgGGRZZdRfyDGWJmYnKU0vhf41FV8S/6uAz4aKTdlMayDUm3CDg85Tqx9EnnfkJRT5JK6FDgpkaHSm64u2k+9Nqa5rHbQc0FbuhkQdJdrU4ea81kuKLeSUL/mEZyz/FoSQKRpApJ7jkeLUkgxxGmNJNPL2mcytKOCRIKhEkO0eqnt3FSSYLrOiZIvGA0h/icfB21cnwmSOjYehXfntYBaaVsZvGniyTp5dVIn4zxuUbPETrvhgxtrSHDnGASfW+MWxur2h9S60lPCr1x5MDE8huBo4F/RJYPJj7dm3Qh4cYBkjogOs1LuCXewMT/uwOPdbIQqaT+AQyLLJsD7JdhmywmpGtLkjIYDTybWLaGcKPfzyW2d1Zj/VaCB4kXZdLwusgQ775OFiKV1F3EB5tlkfezH3dJknIbSjix3wUMaCyb01g2OfHeMMKUY7dJIkmSJHVGeWaysk7tJqc25+HtkLJzLxLqIlnq88ntp5nu9edQpPbdB1yQsZa6nFCvLbSuk0evZd2ZzKcjy46k2Wc5isuyEfY+DOmF921JklRyyfoF1PtLRew8iiSVUvJGvgDnADcnlp1CuCd6lnN6AH8mXHj4Fc0n9V5nJUPOJ9x56zRgaWL5DsAxwLcJd42uuj0IhcBeCrguXRCGVP1lD/KLRHtbEg78qUQeJv6ls50quffo1zu9SQ9nvP5hhM+ZzComLskESX5L1RvY05+K5RR+ZU0knEQfmlg2m3Djk8UdVb9arK8FcjDwW8KNgGPmEO7odQXwTFHB9VOxQF4DXAOcnPG9txKGVbM7LuC6JH5r8ULubJE1ktsIv4+S5BDrNGBRSttle+79RVkS5F5goySRKu4h4EbWTpJbCDcETJMk02nmYVJj8oOJv6TPcNqqzZsI9ZJdhxyGVYFMkEAmSCATJJAJEsgECWSCBDJBApkggUyQQCZIIBMkkAkSyAQJZIIEMkECmSCBTJBAJkggEySQCRLIBAlkggQyQQKZIIFMkEAmSCATJJAJEsgECWSCBDJBApkggUyQQCZIIBMkkAkSyAQJZIIEMkECmSCBTJBAJkggEySQCRLIBAlkggQyQQKZIIFMkEAmSCATJJAJEsgECWSCBDJBApkggUyQQCZIIBMkkAkSyAQJZIIEMkECmSCBTJBAJkggEySQCRLIBAlkggQyQQKZIIFMkEAmSCATJJAJEuh/RnyiTxKfomMAAAAASUVORK5CYII=';
        file_put_contents($avatar_path, base64_decode($default_avatar_base64));
    }
    
    return true;
}

// Chamar a função para garantir que o avatar padrão exista
ensure_default_avatar_exists();

// Função para obter planos de assinatura
function get_subscription_plans() {
    global $db;
    
    $query = "SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Verificar se o usuário tem assinatura ativa
function has_active_subscription($user_id) {
    global $db;
    
    $query = "SELECT * FROM subscriptions 
              WHERE user_id = :user_id 
              AND status = 'active' 
              AND end_date > NOW()";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return $stmt->rowCount() > 0;
}

// Obter detalhes da assinatura ativa do usuário
function get_user_subscription($user_id) {
    global $db;
    
    $query = "SELECT s.*, p.name as plan_name, p.features 
              FROM subscriptions s
              JOIN subscription_plans p ON s.plan_id = p.id
              WHERE s.user_id = :user_id 
              AND s.status = 'active' 
              AND s.end_date > NOW()
              ORDER BY s.end_date DESC
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obter cheats disponíveis para a assinatura
function get_available_cheats_for_subscription($subscription_id) {
    global $db;
    
    // Esta é uma função simplificada. Na prática, você precisaria
    // verificar quais cheats estão disponíveis para o plano específico
    $query = "SELECT * FROM products WHERE is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obter jogos populares
function get_popular_games($limit = 5) {
    global $db;
    
    $query = "SELECT * FROM games WHERE is_active = 1 ORDER BY id DESC LIMIT :limit";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obter depoimentos aprovados
function get_approved_testimonials() {
    global $db;
    
    $query = "SELECT t.*, u.username, u.first_name, u.last_name 
              FROM testimonials t
              JOIN users u ON t.user_id = u.id
              WHERE t.is_approved = 1
              ORDER BY t.created_at DESC
              LIMIT 6";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Verifica se o usuário está logado
 * @return bool Retorna true se o usuário estiver logado, false caso contrário
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Verifica se existe um token de "lembrar de mim" válido
 * e faz login automático caso exista
 */
function check_remember_token() {
    global $db;
    
    // Verificar se o usuário já está logado
    if (isset($_SESSION['user_id'])) {
        return;
    }
    
    // Verificar se existe o cookie remember_token
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        
        // Buscar o token no banco de dados
        $stmt = $db->prepare("
            SELECT u.id, u.username, r.expires_at 
            FROM remember_tokens r
            JOIN users u ON r.user_id = u.id
            WHERE r.token = ? AND r.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se encontrou um token válido
        if ($result) {
            // Fazer login automático
            $_SESSION['user_id'] = $result['id'];
            $_SESSION['username'] = $result['username'];
            
            // Renovar o token para mais 30 dias
            $expires = time() + (30 * 24 * 60 * 60); // 30 dias
            $stmt = $db->prepare("UPDATE remember_tokens SET expires_at = ? WHERE token = ?");
            $stmt->execute([date('Y-m-d H:i:s', $expires), $token]);
            
            // Renovar o cookie
            setcookie('remember_token', $token, $expires, '/', '', false, true);
            
            // Registrar login automático
            $ip_address = get_client_ip();
            $stmt = $db->prepare("INSERT INTO user_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$result['id'], 'auto_login', 'Login automático via cookie', $ip_address]);
        } else {
            // Token inválido ou expirado - remover o cookie
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
    }
}

/**
 * Obter cheats populares ordenados por número de downloads
 * @param int $limit Número máximo de cheats a retornar
 * @return array Array com os cheats mais populares
 */
function get_popular_cheats($limit = 5) {
    global $db;
    
    $query = "SELECT * FROM cheats WHERE is_active = 1 ORDER BY download_count DESC LIMIT :limit";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obter produtos em destaque
 * @param int $limit Número máximo de produtos a retornar
 * @return array Array com os produtos em destaque
 */
function get_featured_products($limit = 6) {
    global $db;
    
    $query = "SELECT * FROM cheats WHERE is_active = 1 ORDER BY id DESC LIMIT :limit";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Função para obter o endereço IP do cliente
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

// Função para redirecionar o usuário
function redirect($url) {
    header("Location: $url");
    exit();
}

// Função para sanitizar entradas
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Função para formatar datas
function format_date($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

// Função para formatar valores monetários
function format_currency($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

// Função para gerar um token seguro
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

// Função para verificar se o usuário tem permissão para acessar um recurso
function check_permission($required_level) {
    if (!isset($_SESSION['user_level']) || $_SESSION['user_level'] < $required_level) {
        redirect('index.php?error=permission_denied');
    }
}

// Função para registrar uma atividade do usuário
function log_user_activity($user_id, $action, $description = '', $ip = null) {
    global $db;
    
    if ($ip === null) {
        $ip = get_client_ip();
    }
    
    $stmt = $db->prepare("
        INSERT INTO user_logs (user_id, action, description, ip_address)
        VALUES (?, ?, ?, ?)
    ");
    
    return $stmt->execute([$user_id, $action, $description, $ip]);
}

// Função para criar um slug a partir de uma string
function create_slug($string) {
    $string = preg_replace('/[^\p{L}\p{N}]+/u', '-', $string);
    $string = mb_strtolower($string, 'UTF-8');
    return trim($string, '-');
}

// Função para formatar o tamanho do arquivo
function format_file_size($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Função para verificar se um slug já existe
function slug_exists($table, $slug, $id = null) {
    global $db;
    
    $sql = "SELECT COUNT(*) FROM $table WHERE slug = ?";
    $params = [$slug];
    
    if ($id !== null) {
        $sql .= " AND id != ?";
        $params[] = $id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchColumn() > 0;
}

// Função para gerar um resumo de texto
function truncate_text($text, $length = 100, $append = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $append;
}

// Função para enviar e-mail
function send_email($to, $subject, $body) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SITE_NAME . " <" . ADMIN_EMAIL . ">\r\n";
    
    return mail($to, $subject, $body, $headers);
}