<?php
// Definir o caminho da imagem (corrigido para o caminho absoluto correto)
$avatar_path = dirname(__DIR__) . '/assets/images/avatar.png';

// Verificar se o diretório de imagens existe, se não, criá-lo
$image_dir = dirname($avatar_path);
if (!file_exists($image_dir)) {
    mkdir($image_dir, 0755, true);
}

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

echo "Avatar padrão criado em: " . $avatar_path;
?>