<?php
function getPlaceholderImage($width, $height) {
    $image = imagecreatetruecolor($width, $height);
    $bg_color = imagecolorallocate($image, 240, 240, 240);
    $text_color = imagecolorallocate($image, 150, 150, 150);
    
    imagefill($image, 0, 0, $bg_color);
    imagestring($image, 5, $width/3, $height/2, 
                "{$width}x{$height}", $text_color);
    
    header('Content-Type: image/png');
    imagepng($image);
    imagedestroy($image);
}

if (isset($_GET['w']) && isset($_GET['h'])) {
    getPlaceholderImage($_GET['w'], $_GET['h']);
}
?> 