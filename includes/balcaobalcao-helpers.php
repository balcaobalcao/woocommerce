<?php

class Balcaobalcao_Helpers
{
  // retorna a dimensão em metros
  public function getSizeInMeters($unit, $dimension)
  {
    if (is_numeric($dimension)) {
      if ($unit == 'mm') { //Milímetro
        $dimension = $dimension / 1000;
      } else if ($unit == 'cm') { // Centímetro
        $dimension = $dimension / 100;
      } else if ($unit == 'in') { // Polegada
        $dimension = $dimension / 39.37;
      }
    }

    return (float) $dimension;
  }

  // retorna o peso em quilogramas
  public function getWeightInKg($unit, $weight)
  {
    if (is_numeric($weight)) {
      if ($unit == 'g') { //Gramas
        $weight = $weight / 1000;
      } else if ($unit == 'oz') { //Onça
        $weight = $weight / 35.274;
      } else if ($unit == 'lb') { //Líbra
        $weight = $weight / 2.205;
      }
    }
    return (float) $weight;
  }
}
