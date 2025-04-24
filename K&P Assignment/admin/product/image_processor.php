<?php
class ImageProcessor {
    private $image;
    private $type;
    
    public function __construct($imagePath) {
        $imageInfo = getimagesize($imagePath);
        $this->type = $imageInfo[2];
        
        switch ($this->type) {
            case IMAGETYPE_JPEG:
                $this->image = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $this->image = imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_GIF:
                $this->image = imagecreatefromgif($imagePath);
                break;
            default:
                throw new Exception('Unsupported image type');
        }
    }
    
    public function rotate($degrees) {
        $this->image = imagerotate($this->image, $degrees, 0);
        return $this;
    }
    
    public function flip($direction = 'horizontal') {
        $width = imagesx($this->image);
        $height = imagesy($this->image);
        
        $new = imagecreatetruecolor($width, $height);
        
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                if ($direction === 'horizontal') {
                    imagecopy($new, $this->image, $width - $x - 1, $y, $x, $y, 1, 1);
                } else {
                    imagecopy($new, $this->image, $x, $height - $y - 1, $x, $y, 1, 1);
                }
            }
        }
        
        $this->image = $new;
        return $this;
    }
    
    public function resize($newWidth, $newHeight) {
        $new = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($new, $this->image, 0, 0, 0, 0, 
                          $newWidth, $newHeight, imagesx($this->image), imagesy($this->image));
        $this->image = $new;
        return $this;
    }
    
    public function save($path, $quality = 90) {
        switch ($this->type) {
            case IMAGETYPE_JPEG:
                imagejpeg($this->image, $path, $quality);
                break;
            case IMAGETYPE_PNG:
                imagepng($this->image, $path, round($quality/10));
                break;
            case IMAGETYPE_GIF:
                imagegif($this->image, $path);
                break;
        }
    }
    
    public function __destruct() {
        if ($this->image) {
            imagedestroy($this->image);
        }
    }
}
?>