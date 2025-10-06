<?php

/**
 * Библиотека giggsey/libphonenumber-for-php
 * composer require giggsey/libphonenumber-for-php
 */
namespace Drupal\func_helper\Phone;

use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;

class PhoneNormalizer {

  protected PhoneNumberUtil $phoneUtil;
  protected string $defaultRegion;

  public function __construct(string $default_region = 'UA') {
    $this->phoneUtil = PhoneNumberUtil::getInstance();
    $this->defaultRegion = $default_region;
  }

  /**
   * Нормализует номер в формат E.164 или возвращает NULL, если невалиден.
   *
   * @param string $raw
   *   Введённая строка номера.
   * @param string|null $region
   *   Двухбуквенный код страны по умолчанию (ISO 3166), например 'UA' или 'RU'.
   *
   * @return string|null
   *   Нормализованный номер в E.164 (+380...) или NULL.
   */
  public function normalize(string $raw, ?string $region = NULL): ?string {
    $region = $region ?: $this->defaultRegion;
    try {
      $proto = $this->phoneUtil->parse($raw, $region);
      if (! $this->phoneUtil->isValidNumber($proto)) {
        return NULL;
      }
      return $this->phoneUtil->format($proto, PhoneNumberFormat::E164);
    }
    catch (NumberParseException $e) {
      return NULL;
    }
  }

}
