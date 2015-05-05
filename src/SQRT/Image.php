<?php

namespace SQRT;

use SQRT\Image\Exception as Ex;

class Image
{
  protected $width;
  protected $height;
  protected $image;
  protected $type;

  protected $modificators;

  const RESIZE       = 1;
  const CROP         = 2;
  const WATERMARK    = 3;
  const CROP_RESIZED = 4;

  const QUALITY_JPG = 75;
  const QUALITY_PNG = 3;

  /** $image - файл изображения или GD ресурс*/
  function __construct($image = null)
  {
    if ($image) {
      $this->load($image);
    }
  }

  /** Пропорциональное изменение размера - добавляется в цепочку модификаторов */
  public function resize($width, $height)
  {
    $this->modificators[] = array(
      'method' => static::RESIZE,
      'width'  => $width,
      'height' => $height
    );

    return $this;
  }

  /**
   * Обрезка изображения - добавляется в цепочку модификаторов.
   * Можно чередовать с RESIZE и WATERMARK
   * $width - высота
   * $height - ширина
   * $x - отступ по горизонтали: left|right|center|±int - в пикселях|"int%" - в процентах
   * $y - отступ по вертикали: top|bottom|center|±int - в пикселях|"int%" - в процентах
   */
  public function crop($width, $height, $x = null, $y = null)
  {
    $this->modificators[] = array(
      'method' => static::CROP,
      'width'  => $width,
      'height' => $height,
      'x'      => $x,
      'y'      => $y
    );

    return $this;
  }

  /**
   * Обрезка изображения с предварительным сжатием по максимальной стороне - добавляется в цепочку модификаторов.
   * $width - высота
   * $height - ширина
   * $x - отступ по горизонтали: left|right|center|±int - в пикселях|"int%" - в процентах
   * $y - отступ по вертикали: top|bottom|center|±int - в пикселях|"int%" - в процентах
   */
  public function cropResized($width, $height, $x = null, $y = null)
  {
    $this->modificators[] = array(
      'method' => static::CROP_RESIZED,
      'width'  => $width,
      'height' => $height,
      'x'      => $x,
      'y'      => $y
    );

    return $this;
  }

  /**
   * Водяной знак - добавляется в цепочку модификаторов.
   * Можно чередовать с RESIZE и CROP
   * $file - путь к изображению
   * $x - отступ по горизонтали: left|right|center|±int - в пикселях|"int%" - в процентах
   * $y - отступ по вертикали: top|bottom|center|±int - в пикселях|"int%" - в процентах
   */
  public function watermark($file, $x = null, $y = null)
  {
    $this->modificators[] = array(
      'method' => static::WATERMARK,
      'file'   => $file,
      'x'      => $x,
      'y'      => $y
    );

    return $this;
  }

  /**
   * Сохранить в файл
   * $quality - качество изображения, для JPG: от 0 до 100, для PNG: от 9 до 0
   */
  public function save($file, $quality = null)
  {
    $this->apply();

    return $this->makeImage($file, $quality);
  }

  /**
   * Вывести изображение в STDOUT
   * $quality - качество изображения, для JPG: от 0 до 100, для PNG: от 9 до 0
   */
  public function output($quality = null)
  {
    $this->apply();

    return $this->makeImage(null, $quality);
  }

  /** Загрузка изображения из файла или GD-ресурса */
  public function load($image)
  {
    $this->reset();

    $this->image = $this->makeGD($image);

    $this->updateDimensions();
  }

  /** Получить GD-ресурс */
  public function getImage()
  {
    return $this->image;
  }

  /** Ширина изображения */
  public function getWidth()
  {
    return $this->width;
  }

  /** Высота изображения */
  public function getHeight()
  {
    return $this->height;
  }

  /** Тип загруженного изображения */
  public function getType()
  {
    return $this->type;
  }

  /** Применяем модификаторы */
  public function apply()
  {
    if (!empty($this->modificators)) {
      foreach ($this->modificators as $k => $mod) {
        switch ($mod['method']) {
          case static::RESIZE:
            $this->doResize($mod['width'], $mod['height']);
            break;
          case static::CROP:
            $this->doCrop($mod['width'], $mod['height'], $mod['x'], $mod['y']);
            break;
          case static::WATERMARK:
            $this->doWatermark($mod['file'], $mod['x'], $mod['y']);
            break;
          case static::CROP_RESIZED:
            $this->doCropResized($mod['width'], $mod['height'], $mod['x'], $mod['y']);
            break;
        }

        unset($this->modificators[$k]);
      }
    }

    return $this;
  }

  /** Формирование результирующего изображения */
  protected function makeImage($file = null, $quality = null)
  {
    $dir = dirname($file);
    if (!is_dir($dir)) {
      mkdir($dir, 0777, true);
    }

    switch ($this->getTypeByFile($file)) {
      case IMG_JPG:
        return imagejpeg($this->image, $file, $quality ? : static::QUALITY_JPG);
        break;
      case IMG_PNG:
        return imagepng($this->image, $file, $quality ? : static::QUALITY_PNG);
        break;
      case IMG_GIF:
        return imagegif($this->image, $file);
        break;
      default:
        Ex::ThrowError(Ex::BAD_TYPE);
    }
  }

  /** Выполнение изменения размера */
  protected function doResize($width, $heigth)
  {
    list ($x, $y) = static::CalculateResize($width, $heigth, $this->getWidth(), $this->getHeight());

    // Прозрачность
    $new_image = imagecreatetruecolor($x, $y);
    imagealphablending($new_image, true);
    imagefill($new_image, 0, 0, imagecolorallocatealpha($new_image, 0, 0, 0, 127));
    imagesavealpha($new_image, true);

    // Меняем размер
    if (!imagecopyresampled($new_image, $this->image, 0, 0, 0, 0, $x, $y, $this->getWidth(), $this->getHeight())) {
      Ex::ThrowError(Ex::RESIZE);
    }

    $this->image = $new_image;

    return $this->updateDimensions();
  }

  /** Выполнение обрезки изображения */
  protected function doCrop($width, $height, $x, $y)
  {
    if ($width > $this->getWidth()) {
      $width = $this->getWidth();
    }
    if ($height > $this->getHeight()) {
      $height = $this->getHeight();
    }

    list ($new_x, $new_y) = static::CalculateOffset($x, $y, $this->getWidth(), $this->getHeight(), $width, $height);

    // Прозрачность
    $new_image = imagecreatetruecolor($width, $height);
    imagealphablending($new_image, true);
    imagefill($new_image, 0, 0, imagecolorallocatealpha($new_image, 0, 0, 0, 127));
    imagesavealpha($new_image, true);

    if (!imagecopy($new_image, $this->image, 0, 0, $new_x, $new_y, $width, $height)) {
      Ex::ThrowError(Ex::CROP);
    }

    $this->image = $new_image;

    return $this->updateDimensions();
  }

  /** Выполнение ужатия и обрезки изображения */
  protected function doCropResized($width, $height, $x, $y)
  {
    $w = $this->getWidth();
    $h = $this->getHeight();

    if (($h / ($w / $width)) < $height) {
      $this->doResize(null, $height);
    } else {
      $this->doResize($width, null);
    }

    return $this->doCrop($width, $height, $x, $y);
  }

  /** Наложение водяного знака */
  protected function doWatermark($file, $x, $y)
  {
    if (is_null($x)) {
      $x = 'center';
    }

    if (is_null($y)) {
      $y = 'center';
    }

    try {
      $watermark = new Image($file);
    } catch (Ex $e) {
      throw new Ex($e->getMessage(), Ex::WATERMARK);
    }

    $width  = $watermark->getWidth();
    $height = $watermark->getHeight();

    list ($new_x, $new_y) = static::CalculateOffset($x, $y, $this->getWidth(), $this->getHeight(), $width, $height);

    if (!imagecopy($this->getImage(), $watermark->getImage(), $new_x, $new_y, 0, 0, $width, $height)) {
      Ex::ThrowError(Ex::WATERMARK);
    }

    return $this;
  }

  /** Сброс настроек */
  protected function reset()
  {
    $this->width = $this->height = $this->type = null;
  }

  /** Обновить размеры текущего изображения */
  protected function updateDimensions()
  {
    $this->width  = $this->image ? imagesx($this->image) : null;
    $this->height = $this->image ? imagesy($this->image) : null;

    return $this;
  }

  /** Создание ресурса GD из файла */
  protected function makeGD($image)
  {
    if (is_resource($image)) {
      return $image;
    }

    if (!is_file($image)) {
      Ex::ThrowError(Ex::FILE_NOT_EXIST, $image);
    }

    $this->type = $this->getTypeByFile($image);

    switch ($this->type) {
      case IMG_JPEG:
        return imagecreatefromjpeg($image);
        break;
      case IMG_GIF:
        return imagecreatefromgif($image);
        break;
      case IMG_PNG:
        return imagecreatefrompng($image);
        break;
      default:
        $file = file_get_contents($image);

        return imagecreatefromstring($file);
    }
  }

  /** Получение типа файла IMG_* по расширению */
  protected function getTypeByFile($file)
  {
    switch (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
      case 'jpg':
      case 'jpeg':
        $type = IMG_JPG;
        break;
      case 'png':
        $type = IMG_PNG;
        break;
      case 'gif':
        $type = IMG_GIF;
        break;
      default:
        $type = false;
    }

    return $type;
  }

  /**
   * Расчет пропорционального изменения размеров изображения.
   * Возвращает массив [width, height]
   */
  public static function CalculateResize($width, $height, $orig_width, $orig_height)
  {
    $ratio = $orig_width / $orig_height;

    if (!$width) {
      $width = $orig_width;
    }

    if (!$height) {
      $height = $orig_height;
    }

    //Если изначальные размеры меньше необходимых - ничего не меняем
    if ($width >= $orig_width && $height >= $orig_height) {
      return array($orig_width, $orig_height);
    }

    $new_width  = $width;
    $new_height = $height;

    if ($width < $orig_width) {
      $new_width  = $width;
      $new_height = floor($width / $ratio);
    }

    if ($new_height > $height) {
      $new_width  = floor($new_width * ($new_height / $height));
      $new_height = $height;
    }

    if ($new_height < $orig_height) {
      $new_width = floor($new_height * $ratio);
    }

    return array($new_width, $new_height);
  }

  /**
   * Расчет заданного отступа по размерам изображения.
   * Возвращает массив [x, y]
   *
   * * $x - отступ по горизонтали: left|right|center|±int - в пикселях|"int%" - в процентах
   *
   * * $y - отступ по вертикали: top|bottom|center|±int - в пикселях|"int%" - в процентах
   */
  public static function CalculateOffset($x, $y, $image_width, $image_height, $item_width = null, $item_height = null)
  {
    //WIDTH
    if ($x == 'left') {
      $x = 0;
    } elseif ($x == 'center') {
      $x = floor($image_width / 2 - $item_width / 2);
    } elseif ($x == 'right') {
      $x = $image_width - $item_width;
    } elseif (strpos($x, '%')) {
      $x = floor((($image_width / 100) * $x) - ($item_width / 2));
      if ($x < 0) {
        $x = 0;
      }
      if (($x + $item_width) > $image_width) {
        $x = $image_width - $item_width;
      }
    } elseif ($x < 0) {
      $x = $image_width - $item_width + $x;
    }

    //HEIGHT
    if ($y == 'top') {
      $y = 0;
    } elseif ($y == 'center' || $y == 'middle') {
      $y = floor($image_height / 2 - $item_height / 2);
    } elseif ($y == 'bottom') {
      $y = $image_height - $item_height;
    } elseif (strpos($y, '%')) {
      $y = floor((($image_height / 100) * $y) - ($item_height / 2));
      if ($y < 0) {
        $y = 0;
      }
      if (($y + $item_height) > $image_height) {
        $y = $image_height - $item_height;
      }
    } elseif ($y < 0) {
      $y = $image_height - $item_height + $y;
    }

    return array((int)$x, (int)$y);
  }

}