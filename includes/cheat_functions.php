<?php
if (!function_exists('create_slug')) {
    function create_slug($string) {
        $string = strtolower($string);
        $string = preg_replace('/[^a-z0-9-]/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        return trim($string, '-');
    }
}

if (!function_exists('slug_exists')) {
    function slug_exists($db, $slug) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM cheats WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetchColumn() > 0;
    }
}

if (!function_exists('handle_cheat_upload')) {
    function handle_cheat_upload($file) {
        $upload_dir = '../../uploads/cheats/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $allowed_types = ['exe', 'dll', 'zip', 'rar'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowed_types)) {
            throw new Exception('Tipo de arquivo não permitido. Use apenas .exe, .dll, .zip ou .rar');
        }

        // Verify MIME type for zip files
        if ($extension === 'zip' && $file['type'] !== 'application/zip' && $file['type'] !== 'application/x-zip-compressed') {
            throw new Exception('Arquivo ZIP inválido');
        }

        // Verify MIME type for rar files
        if ($extension === 'rar' && $file['type'] !== 'application/x-rar-compressed' && $file['type'] !== 'application/vnd.rar') {
            throw new Exception('Arquivo RAR inválido');
        }

        // Updated to 1GB
        if ($file['size'] > 1024 * 1024 * 1024) {
            throw new Exception('Arquivo muito grande. Tamanho máximo: 1GB');
        }

        $filename = uniqid('cheat_') . '.' . $extension;
        $destination = $upload_dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Erro ao fazer upload do arquivo');
        }

        return $filename;
    }
}

if (!function_exists('handle_image_upload')) {
    function handle_image_upload($file) {
        $upload_dir = '../../assets/images/cheats/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Tipo de imagem não permitido. Use JPEG, PNG ou WEBP');
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            throw new Exception('Imagem muito grande. Tamanho máximo: 2MB');
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('cheat_img_') . '.' . $extension;
        $destination = $upload_dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Erro ao fazer upload da imagem');
        }

        return $filename;
    }
}
?>