<?php

require_once __DIR__ . '/../init.php';

use SQRT\Image;

class ImageTest extends PHPUnit_Framework_TestCase
{
  protected $image;
  protected $bike;
  protected $vertical;
  protected $watermark;
  protected $path_to_save;

  /** @dataProvider dataLoad */
  function testLoad($image, $width, $height, $type = null)
  {
    $img = new Image();
    $img->load($image);

    $this->assertEquals($width, $img->getWidth(), 'Ширина');
    $this->assertEquals($height, $img->getHeight(), 'Высота');
    $this->assertTrue(is_resource($img->getImage()), 'Ресурс GD');
    $this->assertEquals($type, $img->getType(), 'Тип изображения');
  }

  function dataLoad()
  {
    $this->setUp();

    return array(
      array($this->bike, 640, 480, IMG_JPG),
      array($this->image, 1000, 500, IMG_GIF),
      array($this->watermark, 256, 256, IMG_PNG),
      array(imagecreatefromjpeg($this->bike), 640, 480)
    );
  }

  function testFileNotExists()
  {
    try {
      new Image('bad_file.jpg');

      $this->fail('Ожидаемое исключение');
    } catch (Image\Exception $e) {
      $this->assertEquals(Image\Exception::FILE_NOT_EXIST, $e->getCode(), 'Код исключения');
    }
  }

  /**
   * @dataProvider dataCalculateDimensions
   */
  function testCalculateDimensions($exp, $w, $h, $o_w, $o_h)
  {
    list ($x, $y) = Image::CalculateResize($w, $h, $o_w, $o_h);
    $this->assertEquals($exp, $x . 'x' . $y, 'Resize ' . $o_w . 'x' . $o_h . ' to ' . $w . 'x' . $h);
  }

  function dataCalculateDimensions()
  {
    return array(
      array('400x300', 400, 300, 1200, 900),
      array('266x200', 400, 200, 1200, 900),
      array('400x300', 400, 500, 1200, 900),
      array('600x450', 1600, 450, 1200, 900),
      array('400x300', 400, 400, 1200, 900),
      array('399x265', 400, 300, 800, 531),
      array('451x300', null, 300, 800, 531),
      array('299x199', 300, null, 800, 531),
    );
  }

  /**
   * @dataProvider dataCalculateOffset
   */
  function testCalculateOffset($exp, $x, $y)
  {
    $img_w  = 1000;
    $img_h  = 500;
    $item_w = 100;
    $item_h = 30;
    list ($new_x, $new_y) = Image::CalculateOffset($x, $y, $img_w, $img_h, $item_w, $item_h);
    $this->assertEquals($exp, $new_x . 'x' . $new_y, 'Offset ' . $x . ' ' . $y);
  }

  function dataCalculateOffset()
  {
    return array(
      array('450x235', 'center', 'center'),
      array('900x470', 'right', 'bottom'),
      array('0x0', 'left', 'top'),
      array('250x135', '30%', '30%'),
      array('0x0', '5%', '2%'),
      array('900x470', '95%', '98%'),
      array('900x470', '100%', '100%'),
      array('0x0', '0%', '0%'),
      array('300x150', 300, 150),
      array('890x460', -10, -10)
    );
  }

  /**
   * @dataProvider dataResize
   */
  function testResize(Image $img, $file, $type)
  {
    $path = $this->path_to_save . $file;
    $img->save($path);

    $new = new Image($path);
    $this->assertEquals(266, $new->getWidth(), 'Ширина');
    $this->assertEquals(200, $new->getHeight(), 'Высота');
    $this->assertEquals($type, $new->getType(), 'Тип');
  }

  function dataResize()
  {
    $this->setUp();

    $img = new Image($this->bike);
    $img->resize(300, 200);

    return array(
      array($img, '/test_resize.jpg', IMG_JPG),
      array($img, '/test_resize.png', IMG_PNG),
      array($img, '/test_resize.gif', IMG_GIF),
    );
  }

  /**
   * @dataProvider dataOffset
   */
  function testCrop($x, $y)
  {
    $path = $this->path_to_save . '/crop_' . $x . '_' . $y . '.jpg';

    $img = new Image($this->bike);
    $img
      ->resize(300, 200)
      ->crop(100, 100, $x, $y)
      ->save($path);

    $new = new Image($path);
    $this->assertEquals(100, $new->getWidth(), 'Ширина');
    $this->assertEquals(100, $new->getHeight(), 'Высота');
  }

  /**
   * @dataProvider dataOffset
   */
  function testCropResized($x, $y)
  {
    $width  = 150;
    $height = 100;

    $path = $this->path_to_save . '/crop_resized_'.$x.'_'.$y.'_vertical.jpg';
    $img = new Image($this->vertical);
    $img->cropResized($width, $height, $x, $y);
    $img->save($path);

    $new = new Image($path);
    $this->assertEquals($width, $new->getWidth(), 'Ширина вертикальной');
    $this->assertEquals($height, $new->getHeight(), 'Высота вертикальной');

    $path = $this->path_to_save . '/crop_resized_'.$x.'_'.$y.'_horizontal.jpg';
    $img = new Image($this->bike);
    $img->cropResized($width, $height, $x, $y);
    $img->save($path);

    $new = new Image($path);
    $this->assertEquals(149, $new->getWidth(), 'Ширина горизонтальной');
    $this->assertEquals($height, $new->getHeight(), 'Высота горизонтальной');

    $path = $this->path_to_save . '/crop_resized_'.$x.'_'.$y.'_square.jpg';
    $img = new Image($this->watermark);
    $img->cropResized($width, $height, $x, $y);
    $img->save($path);

    $new = new Image($path);
    $this->assertEquals($width, $new->getWidth(), 'Ширина квадратной');
    $this->assertEquals($height, $new->getHeight(), 'Высота квадратной');

    $this->markTestSkipped('Нужна визуальная проверка правильности обрезки');
  }

  function dataOffset()
  {
    return array(
      array('left', 'top'),
      array('right', 'bottom'),
      array('center', 'center'),
    );
  }

  /**
   * @dataProvider dataOffset
   */
  function testWatermark($x, $y)
  {
    $img = new Image($this->bike);
    $img
      ->resize(400, 300)
      ->watermark($this->watermark, $x, $y)
      ->save($this->path_to_save . '/watermark_' . $x . '_' . $y . '.jpg');

    $this->markTestSkipped('Нужна визуальная проверка положения водяных знаков');
  }

  function setUp()
  {
    $this->image        = realpath(__DIR__ . '/../resourse/1000x500.gif');
    $this->bike         = realpath(__DIR__ . '/../resourse/bike.jpg');
    $this->vertical     = realpath(__DIR__ . '/../resourse/vertical.jpg');
    $this->watermark    = realpath(__DIR__ . '/../resourse/magic.png');
    $this->path_to_save = realpath(__DIR__ . '/../tmp');
  }
}