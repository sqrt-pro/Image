<?php

namespace SQRT\Image;

class Exception extends \SQRT\Exception
{
  const FILE_NOT_EXIST = 1;
  const BAD_TYPE       = 2;
  const RESIZE         = 10;
  const CROP           = 11;
  const WATERMARK      = 12;

  protected static $errors_arr = array(
    self::FILE_NOT_EXIST => 'Изображение "%s" не существует',
    self::BAD_TYPE       => 'Неверный тип изображения',
    self::RESIZE         => 'Ошибка при изменении размера изображения',
    self::CROP           => 'Ошибка при обрезке изображения',
    self::WATERMARK      => 'Ошибка при наложении водяного знака',
  );
}