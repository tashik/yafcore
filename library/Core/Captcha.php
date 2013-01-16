<?php
class Core_Captcha
{
  private $id=false;

  public function GetString()
  {
    $id = $this->GetId();
    return "<img src=\"/captcha.php?id=$id\" onclick=\"this.src=\'/captcha.php?id=$id&t=\'+Math.random();\" />";
  }

  /**
   * @return Zend_Cache_Frontend_Class
   */
  protected static function _getCache() {
    if (!class_exists('Zend_Registry')) {
      return null;
    }
    if (isRegistered('shared_cache')) {
      return getRegistryItem('shared_cache');
    }
    if (isRegistered('cache')) {
      return getRegistryItem('cache');
    }
    return null;
  }

  public function ValidateCaptcha($captcha_id, $captcha_value)
  {
    $code = trim($_SESSION['captcha']['code']);
    //logVar($code, 'code');
    $captcha_value = trim($captcha_value);
    if ( false!==$code && ''!==$code )
    {
      if ( !strcasecmp($code, $captcha_value) )
      {
        //$this->delete('id='.$captcha_id); // Инвалидируем капчу
        unset($_SESSION['captcha']);
        /* @var $cache Zend_Cache_Frontend_Class */
        $cache = self::_getCache();
        if ($cache) {
          $cache_id = "captcha_{$code}";
          $cache->remove($cache_id);
        }
        return true;
      }
      //$this->code=false;
      //$this->Create(); // Юзер капчу не угадал, делаем новую
    }
    return false; // Запрошенной капчи и вовсе нет
  }

  /**
   * @deprecated
   */
  public function GetId()
  {
    if (false===$this->id)
    {
      $this->id=rand();
    }
    return $this->id;
  }

  static private function CreateCodeString()
  {
    $confirm_chars = array('A', 'B', 'C', 'D', 'E', 'H', 'I', 'K', 'M', 'N', 'P', 'Q', 'R', 'S', 'U', 'V', 'W', 'X', 'Y', 'Z', '2', '3', '4', '5', '6', '7', '8', '9');
    list($usec, $sec) = explode(' ', microtime());
    mt_srand($sec * $usec);
    shuffle($confirm_chars);
    $code=implode('', array_slice($confirm_chars, 0, 6));
    return $code;
  }

  public function Create()
  {
    $time = time();
    if (isset($_SESSION['captcha'])
        && isset($_SESSION['captcha']['code'])
        && isset($_SESSION['captcha']['time'])
        && $_SESSION['captcha']['time'] + 60 > $time
       )
    {
      return true;
    }
    $code = self::CreateCodeString();
    $_SESSION['captcha'] = array(
      'code' => $code,
      'time' => $time,
    );
    return true;
  }

  static protected function _displayEmptyImage() {
    $empty_img = 'iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAIAAAD/gAIDAAAAAXNSR0IArs4c6QAAAJ9JREFUeNrt0DEBAAAIAyC1f+dZwc8HItBJiptRIEuWLFmyZCmQJUuWLFmyFMiSJUuWLFkKZMmSJUuWLAWyZMmSJUuWAlmyZMmSJUuBLFmyZMmSpUCWLFmyZMlSIEuWLFmyZCmQJUuWLFmyFMiSJUuWLFkKZMmSJUuWLAWyZMmSJUuWAlmyZMmSJUuBLFmyZMmSpUCWLFmyZMlSIEvWtwXP8QPFWbLJBwAAAABJRU5ErkJggg==';
    header("Content-Type: image/png");
    echo base64_decode($empty_img);
    return 'image/png';
  }

  static public function Display()
  {
    Core_Debug::getGenerateTime('generateCaptcha start');
    if (!isset($_SESSION['captcha']) || !isset($_SESSION['captcha']['code'])) {
      return self::_displayEmptyImage();
    }
    $code = $_SESSION['captcha']['code'];

    $cache = self::_getCache();
    if ($cache) {
      $cache_id = "captcha_{$code}";
      if ( ($img=$cache->load($cache_id)) ) {
        header("Content-Type: {$img['type']}");
        echo base64_decode($img['data']);
        return $img['type'];
      }
    }
    $code=strtolower($code);
    $width = 130;
    $height = 70;
    $renderer = getConfigValue('general->captcha->renderer', 3);
    $config = getConfigValue('general->captcha->options', array());
    ob_end_clean();
    ob_start();
    $type = call_user_func("Core_Captcha::Renderer{$renderer}", $code, $width, $height, $config);
    $data = ob_get_contents();
    ob_end_clean();
    if ($type) {
      header("Content-Type: $type");
      echo $data;
      if ($data && $cache) {
        $data = array(
          'type' => $type,
          'data' => base64_encode($data),
        );
        $cache->save($data, $cache_id, array(), 10);
      }
      return $type;
    } else {
      return self::_displayEmptyImage();
    }
  }

  static public function Renderer1($code, $width, $height, $config=array())
  {
    $defaults = array('fluctuation_amplitude'=>6,
                      'wave_distort' => false,
                      'foregarbage' => false,
                     );
    $config = array_merge($defaults, $config);

    $alphabet = "0123456789abcdefghijklmnopqrstuvwxyz"; # do not change without changing font files!
    // symbols used to draw CAPTCHA
    //$allowed_symbols = "0123456789"; #digits
    //$allowed_symbols = "23456789abcdeghkmnpqsuvxyz"; #alphabet without similar symbols (o=0, 1=l, i=j, t=f)
    // folder with fonts
    $fontsdir = 'fonts';
    // CAPTCHA string length
    //$length = mt_rand(5,6); # random 5 or 6
    //$length = 6;
    // CAPTCHA image size (you do not need to change it, whis parameters is optimal)
    // symbol's vertical fluctuation amplitude divided by 2
    $fluctuation_amplitude = $config['fluctuation_amplitude'];
    // increase safety by prevention of spaces between symbols
    $no_spaces = false;
    // show credits
    $show_credits = false; # set to false to remove credits line. Credits adds 12 pixels to image height
    $credits = 'www.captcha.ru'; # if empty, HTTP_HOST will be shown
    $wave_distort = $config['wave_distort'];
    $foregarbage = $config['foregarbage'];
    // CAPTCHA image colors (RGB, 0-255)
    //$foreground_color = array(0, 0, 0);
    //$background_color = array(220, 230, 255);
    $ctype=mt_rand(1,2);
    if ( 1==$ctype )
    {
      $foreground_color = array(mt_rand(0,100), mt_rand(0,100), mt_rand(0,100));
      $background_color = array(mt_rand(200,255), mt_rand(200,255), mt_rand(200,255));
    }
    else if ( 2==$ctype )
    {
      $background_color = array(mt_rand(0,100), mt_rand(0,100), mt_rand(0,100));
      $foreground_color = array(mt_rand(200,255), mt_rand(200,255), mt_rand(200,255));
    }

    // JPEG quality of CAPTCHA image (bigger is better quality, but larger file size)
    $jpeg_quality = 90;

    $fonts=array();
    $fontsdir_absolute=dirname(__FILE__).'/'.$fontsdir;
    if ($handle = opendir($fontsdir_absolute)) {
      while (false !== ($file = readdir($handle))) {
        if (preg_match('/\.png$/i', $file)) {
          $fonts[]=$fontsdir_absolute.'/'.$file;
        }
      }
        closedir($handle);
    }

    $alphabet_length=strlen($alphabet);

    Core_Debug::getGenerateTime('generateCaptcha starting generation');

    while(true){
      // generating random keystring
      $keystring=$code;
      $font_file=$fonts[mt_rand(0, count($fonts)-1)];
      $font=imagecreatefrompng($font_file);
      imagealphablending($font, true);
      $fontfile_width=imagesx($font);
      $fontfile_height=imagesy($font)-1;
      $font_metrics=array();
      $symbol=0;
      $reading_symbol=false;

      // loading font
      for($i=0;$i<$fontfile_width && $symbol<$alphabet_length;$i++){
        $transparent = (imagecolorat($font, $i, 0) >> 24) == 127;

        if(!$reading_symbol && !$transparent){
          $font_metrics[$alphabet{$symbol}]=array('start'=>$i);
          $reading_symbol=true;
          continue;
        }

        if($reading_symbol && $transparent){
          $font_metrics[$alphabet{$symbol}]['end']=$i;
          $reading_symbol=false;
          $symbol++;
          continue;
        }
      }

      $img=imagecreatetruecolor($width, $height);
      imagealphablending($img, true);
      $white=imagecolorallocate($img, 255, 255, 255);
      $black=imagecolorallocate($img, 0, 0, 0);

      imagefilledrectangle($img, 0, 0, $width-1, $height-1, $white);

      // draw text
      $x=1;
      for($i=0;$i<strlen($keystring);$i++){
        if (!isset($font_metrics[$keystring{$i}]))
        {
          break;
        }
        $m=$font_metrics[$keystring{$i}];

        $y=mt_rand(-$fluctuation_amplitude, $fluctuation_amplitude)+($height-$fontfile_height)/2+2;

        if($no_spaces){
          $shift=0;
          if($i>0){
            $shift=1000;
            for($sy=7;$sy<$fontfile_height-20;$sy+=1){
              //for($sx=$m['start']-1;$sx<$m['end'];$sx+=1){
              for($sx=$m['start']-1;$sx<$m['end'];$sx+=1){
                    $rgb=imagecolorat($font, $sx, $sy);
                    $opacity=$rgb>>24;
                if($opacity<127){
                  $left=$sx-$m['start']+$x;
                  $py=$sy+$y;
                  if($py>$height) break;
                  for($px=min($left,$width-1);$px>$left-12 && $px>=0;$px-=1){
                        $color=imagecolorat($img, $px, $py) & 0xff;
                    if($color+$opacity<190){
                      if($shift>$left-$px){
                        $shift=$left-$px;
                      }
                      break;
                    }
                  }
                  break;
                }
              }
            }
            if($shift==1000){
              $shift=mt_rand(4,6);
            }

          }
        }else{
          $shift=1;
        }
        imagecopy($img,$font,$x-$shift,$y,$m['start'],1,$m['end']-$m['start'],$fontfile_height);
        $x+=$m['end']-$m['start']-$shift;
      }
      if($x<$width-10) break; // fit in canvas

    }
    $center=$x/2;

    Core_Debug::getGenerateTime('generateCaptcha starting distortion');

    // credits. To remove, see configuration file
    $img2=imagecreatetruecolor($width, $height+($show_credits?12:0));
    $foreground=imagecolorallocate($img2, $foreground_color[0], $foreground_color[1], $foreground_color[2]);
    $background=imagecolorallocate($img2, $background_color[0], $background_color[1], $background_color[2]);
    imagefilledrectangle($img2, 0, $height, $width-1, $height+12, $foreground);

    if ($show_credits) {
      $credits=empty($credits)?$_SERVER['HTTP_HOST']:$credits;
      imagestring($img2, 2, $width/2-ImageFontWidth(2)*strlen($credits)/2, $height-2, $credits, $background);
    }

    // periods
    $rand1=mt_rand(750000,1200000)/10000000;
    $rand2=mt_rand(750000,1200000)/10000000;
    $rand3=mt_rand(750000,1200000)/10000000;
    $rand4=mt_rand(750000,1200000)/10000000;
    // phases
    $rand5=mt_rand(0,3141592)/500000;
    $rand6=mt_rand(0,3141592)/500000;
    $rand7=mt_rand(0,3141592)/500000;
    $rand8=mt_rand(0,3141592)/500000;
    // amplitudes
    $rand9=mt_rand(330,420)/110;
    $rand10=mt_rand(330,450)/110;

    //wave distortion
    for($x=0;$x<$width;$x++){
      for($y=0;$y<$height;$y++){
        if ($wave_distort) {
          $sx=$x+(sin($x*$rand1+$rand5)+sin($y*$rand3+$rand6))*$rand9-$width/2+$center+1;
          $sy=$y+(sin($x*$rand2+$rand7)+sin($y*$rand4+$rand8))*$rand10;
        } else {
          $sx = $x - $width/2 + $center + 1;
          $sy = $y;
        }

        if($sx<0 || $sy<0 || $sx>=$width-1 || $sy>=$height-1){
          $color=255;
          $color_x=255;
          $color_y=255;
          $color_xy=255;
        }else{
          $color=imagecolorat($img, $sx, $sy) & 0xFF;
          $color_x=imagecolorat($img, $sx+1, $sy) & 0xFF;
          $color_y=imagecolorat($img, $sx, $sy+1) & 0xFF;
          $color_xy=imagecolorat($img, $sx+1, $sy+1) & 0xFF;
        }

        if($color==0 && $color_x==0 && $color_y==0 && $color_xy==0){
          $newred=$foreground_color[0];
          $newgreen=$foreground_color[1];
          $newblue=$foreground_color[2];
        }else if($color==255 && $color_x==255 && $color_y==255 && $color_xy==255){
          $newred=$background_color[0];
          $newgreen=$background_color[1];
          $newblue=$background_color[2];
        }else{
          $frsx=$sx-floor($sx);
          $frsy=$sy-floor($sy);
          $frsx1=1-$frsx;
          $frsy1=1-$frsy;

          $newcolor=(
            $color*$frsx1*$frsy1+
            $color_x*$frsx*$frsy1+
            $color_y*$frsx1*$frsy+
            $color_xy*$frsx*$frsy);

          if($newcolor>255) $newcolor=255;
          $newcolor=$newcolor/255;
          $newcolor0=1-$newcolor;

          $newred=$newcolor0*$foreground_color[0]+$newcolor*$background_color[0];
          $newgreen=$newcolor0*$foreground_color[1]+$newcolor*$background_color[1];
          $newblue=$newcolor0*$foreground_color[2]+$newcolor*$background_color[2];
        }

        imagesetpixel($img2, $x, $y, imagecolorallocate($img2, $newred, $newgreen, $newblue));
      }
    }

    Core_Debug::getGenerateTime('generateCaptcha starting output');

    if ( true===$foregarbage )
    {
/*        for ($i=0; $i<100; $i++)
        {
          $x=mt_rand(0, $width);
          $y=mt_rand(0, $height);
          $dx=5*mt_rand(0,2)-5;
          $dy=5*mt_rand(0,2)-5;
          imageline($img2, $x, $y, $x+$dx, $y+$dy, imagecolorallocate($img2, $foreground_color[0],
                                                                             $foreground_color[1],
                       $foreground_color[2]));
        }*/
/*        for ($i=0; $i<4; $i++)
        {
          $y=$height*($i+1)/4+mt_rand(0,20)-10;
          $dx=mt_rand(5,10);
          $max_x=$width-mt_rand(0,20);
          for ($x=mt_rand(0,10); $x<$max_x; )
          {
            $new_y=$y+5*mt_rand(0,2)-5;
            $new_x=$x+mt_rand(5,10);
            if ($new_y<0)
            {
              $new_y=0;
            }
            if ($new_y>$height-1)
            {
              $new_y=$height-1;
            }
            imageline($img2, $x, $y, $new_x, $new_y, imagecolorallocate($img2, $foreground_color[0],
                                                                        $foreground_color[1], $foreground_color[2]));
            imageline($img2, $x, $y+1, $new_x, $new_y+1, imagecolorallocate($img2, $foreground_color[0],
                                                                        $foreground_color[1], $foreground_color[2]));
            $y=$new_y;
            $x=$new_x;
          }
        }*/
        $newred=($foreground_color[0]+$background_color[0])/2;
        $newgreen=($foreground_color[1]+$background_color[1])/2;
        $newblue=($foreground_color[2]+$background_color[2])/2;
        for($x=0;$x<$width;$x++){
          for($y=0;$y<$height;$y++){
              $rndmax=10;
              if ( $x%20<5 || $y%20<5 )
              {
                $rndmax=2;
              }
              if ( 0==mt_rand(0,$rndmax) )
              {
                imagesetpixel($img2, $x, $y, imagecolorallocate($img2, $newred+mt_rand(0,100)-50,
                                                                      $newgreen+mt_rand(0,100)-50,
                            $newblue+mt_rand(0,100)-50));
              }
          }
        }
    }

    $type = null;
    if(function_exists("imagejpeg")){
      //header("Content-Type: image/jpeg");
      imagejpeg($img2, null, $jpeg_quality);
      $type = 'image/jpeg';
    }else if(function_exists("imagegif")){
      //header("Content-Type: image/gif");
      imagegif($img2);
      $type = 'image/gif';
    }else if(function_exists("imagepng")){
      //header("Content-Type: image/x-png");
      imagepng($img2);
      $type = 'image/png';
    }
    Core_Debug::getGenerateTime('generateCaptcha end');
    return $type;
  }

  static public function Renderer2($code, $width, $height, $config = array()) {
    $defaults = array('font'=>'monofont.ttf',
                      'font_ratio' => (30+mt_rand(5, 20))/100,
                      'lines_ratio' => 1000,
                      'dots_ratio' => 150,
                     );
    $config = array_merge($defaults, $config);

    /* font size will be 75% of the image height */
    $font_size = $height * $config['font_ratio'];
    $image = imagecreate($width, $height) or die('Cannot initialize new GD image stream');
    /* set the colours */
    $background_color = imagecolorallocate($image, 0xDF, 0xE8, 0xF6);
    $text_color = imagecolorallocate($image, 20, 40, 100);
    $noise_color = $text_color;
    //$noise_color = imagecolorallocate($image, 100, 120, 180);

    /* generate random dots in background */
    $cnt = ($width*$height)/$config['dots_ratio'];
    for( $i=0; $i<$cnt; $i++ ) {
       imagefilledellipse($image, mt_rand(0,$width), mt_rand(0,$height), mt_rand(1, 3), mt_rand(1, 3), $noise_color);
    }

    /* generate random lines in background */
    $cnt = ($width*$height)/$config['lines_ratio'];
    for( $i=0; $i<$cnt; $i++ ) {
       imageline($image, mt_rand(0,$width), mt_rand(0,$height), mt_rand(0,$width), mt_rand(0,$height), $noise_color);
    }

    //$path = getcwd();
    //chdir(APPLICATION_PATH.'/../library/Core/fonts');
    /* create textbox and add text */
    $font = APPLICATION_PATH.'/../library/Core/fonts/'.$config['font'];
    $textbox = imagettfbbox($font_size, 0, $font, $code) or die("Error in imagettfbbox function");
    if ($textbox[4]<0) {
      $textbox[4]=$width-10;
    }
    if ($textbox[4]<0) {
      $textbox[4]=20;
    }
    $x = ($width - $textbox[4])/2;
    $y = ($height - $textbox[5])/2;
    imagettftext($image, $font_size, 0, $x, $y, $text_color, $font , $code) or die('Error in imagettftext function');
    /* output captcha image to browser */

    //header('Content-Type: image/jpeg');
    imagejpeg($image);
    imagedestroy($image);
    return 'image/jpeg';
    //chdir($path);
  }

  static public function Renderer3($code, $width, $height, $config = array()) {
    require_once APPLICATION_PATH.'/../library/Core/SimpleCaptcha.php';
    $defaults = array('width' => $width,
                      'height' => $height,
                      'resourcesPath' => APPLICATION_PATH.'/../library/Core',
                      'maxWordLength' => 6,
                      'sizeCorrection' => 4,
                      'Yamplitude' => 6,
                      'Xamplitude' => 3,
                      'maxRotation' => 8,
                      //'blur' => true,
                      //'lineWidth' => 0,
                      'backgroundColor' => array(0xDF, 0xE8, 0xF6),
                     );
    $config = array_merge($defaults, $config);
    $captcha = new SimpleCaptcha($config);
    return $captcha->CreateImage($code);
  }

  static public function Renderer4($code, $width, $height, $config = array()) {
    $defaults = array('font'=>'monofont.ttf',
                      'font_size' => 24,
                      'line_noise' => 5,
                      'dot_noise' => 100,
                      'start_image' => null,
                     );
    $config = array_merge($defaults, $config);
    if (!extension_loaded("gd")) {
      require_once 'Zend/Captcha/Exception.php';
      throw new Zend_Captcha_Exception("Image CAPTCHA requires GD extension");
    }

    if (!function_exists("imagepng")) {
      require_once 'Zend/Captcha/Exception.php';
      throw new Zend_Captcha_Exception("Image CAPTCHA requires PNG support");
    }

    if (!function_exists("imageftbbox")) {
      require_once 'Zend/Captcha/Exception.php';
      throw new Zend_Captcha_Exception("Image CAPTCHA requires FT fonts support");
    }

    $font = APPLICATION_PATH.'/../library/Core/fonts/'.$config['font'];

    if (empty($font)) {
      require_once 'Zend/Captcha/Exception.php';
      throw new Zend_Captcha_Exception("Image CAPTCHA requires font");
    }

    $w = $width;
    $h = $height;
    $fsize = $config['font_size'];

    if (empty($config['start_image'])) {
      $img = imagecreatetruecolor($w, $h);
    } else {
      $img = imagecreatefrompng(APPLICATION_PATH.'/../library/Core/fonts/'.$config['start_image']);
      if (!$img) {
        require_once 'Zend/Captcha/Exception.php';
        throw new Zend_Captcha_Exception("Can not load start image");
      }
      $w = imagesx($img);
      $h = imagesy($img);
    }
    $text_color = imagecolorallocate($img, 0, 0, 0);
    $bg_color = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, $bg_color);
    $textbox = imageftbbox($fsize, 0, $font, $word);
    $x = ($w - ($textbox[2] - $textbox[0])) / 2;
    $y = ($h - ($textbox[7] - $textbox[1])) / 2;
    imagefttext($img, $fsize, 0, $x, $y, $text_color, $font, $word);

    // generate noise
    for ($i = 0; $i < $config['dot_noise']; $i++) {
      imagefilledellipse($img, mt_rand(0, $w), mt_rand(0, $h), 2, 2, $text_color);
    }
    for ($i = 0; $i < $config['line_noise']; $i++) {
      imageline($img, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), $text_color);
    }

    // transformed image
    $img2 = imagecreatetruecolor($w, $h);
    $bg_color = imagecolorallocate($img2, 255, 255, 255);
    imagefilledrectangle($img2, 0, 0, $w - 1, $h - 1, $bg_color);
    // apply wave transforms
    $freq1 = self::_randomFreq();
    $freq2 = self::_randomFreq();
    $freq3 = self::_randomFreq();
    $freq4 = self::_randomFreq();

    $ph1 = self::_randomPhase();
    $ph2 = self::_randomPhase();
    $ph3 = self::_randomPhase();
    $ph4 = self::_randomPhase();

    $szx = self::_randomSize();
    $szy = self::_randomSize();

    for ($x = 0; $x < $w; $x++) {
      for ($y = 0; $y < $h; $y++) {
        $sx = $x + (sin($x * $freq1 + $ph1) + sin($y * $freq3 + $ph3)) * $szx;
        $sy = $y + (sin($x * $freq2 + $ph2) + sin($y * $freq4 + $ph4)) * $szy;

        if ($sx < 0 || $sy < 0 || $sx >= $w - 1 || $sy >= $h - 1) {
          continue;
        } else {
          $color = (imagecolorat($img, $sx, $sy) >> 16) & 0xFF;
          $color_x = (imagecolorat($img, $sx + 1, $sy) >> 16) & 0xFF;
          $color_y = (imagecolorat($img, $sx, $sy + 1) >> 16) & 0xFF;
          $color_xy = (imagecolorat($img, $sx + 1, $sy + 1) >> 16) & 0xFF;
        }
        if ($color == 255 && $color_x == 255 && $color_y == 255 && $color_xy == 255) {
          // ignore background
          continue;
        } elseif ($color == 0 && $color_x == 0 && $color_y == 0 && $color_xy == 0) {
          // transfer inside of the image as-is
          $newcolor = 0;
        } else {
          // do antialiasing for border items
          $frac_x = $sx - floor($sx);
          $frac_y = $sy - floor($sy);
          $frac_x1 = 1 - $frac_x;
          $frac_y1 = 1 - $frac_y;

          $newcolor = $color * $frac_x1 * $frac_y1
                  + $color_x * $frac_x * $frac_y1
                  + $color_y * $frac_x1 * $frac_y
                  + $color_xy * $frac_x * $frac_y;
        }
        imagesetpixel($img2, $x, $y, imagecolorallocate($img2, $newcolor, $newcolor, $newcolor));
      }
    }

    // generate noise
    for ($i = 0; $i < $config['dot_noise']; $i++) {
      imagefilledellipse($img2, mt_rand(0, $w), mt_rand(0, $h), 2, 2, $text_color);
    }
    for ($i = 0; $i < $config['line_noise']; $i++) {
      imageline($img2, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), $text_color);
    }

    imagepng($img2);
    imagedestroy($img);
    imagedestroy($img2);
    return 'image/png';
  }

  /**
   * Generate random frequency
   *
   * @return float
   */
  protected static function _randomFreq()
  {
      return mt_rand(700000, 1000000) / 15000000;
  }

  /**
   * Generate random phase
   *
   * @return float
   */
  protected static function _randomPhase()
  {
      // random phase from 0 to pi
      return mt_rand(0, 3141592) / 1000000;
  }

  /**
   * Generate random character size
   *
   * @return int
   */
  protected static function _randomSize()
  {
      return mt_rand(300, 700) / 100;
  }
}
