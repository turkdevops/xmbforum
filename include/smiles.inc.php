<?php
/**
 * SMILES image manager based on openmolecules.org name2structure 
 * service.
 *
 **/

if (!defined('IN_CODE')) {
  header('HTTP/1.0 403 Forbidden');
  exit("Not allowed to run this file directly.");
}

define("IMAGE_DIR", "/var/www/images/smiles/");
define("SMILES_IMG_LOC", "www.site.tld/images/smiles/");
define("OM_PREFIX", "http://n2s.openmolecules.org/?name=");
define("FAILURE_IMAGE", "smilesfailure.png");

class SmilesCode {
  
  // convert SMILES to img representing corresponding structure
  public function render($smiles) {
    $smiles = trim($smiles);
    $key = md5($smiles);
    $image_name = $key . '.png';
    $file_name = IMAGE_DIR . $image_name;

    // image not stored locally -- try to generate image file
    if (!file_exists($file_name)) {
      $file_name = $this->generate_file($smiles, $image_name);
    }

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
      $protocol = 'https://';
    }
    else {
      $protocol = 'http://';
    }

    $img_url = $protocol . SMILES_IMG_LOC . $image_name;
    $img = "<img src=\"$img_url\"></img>";
    return $img;
  }

  // generate an image file using the openmolecules.net server
  public function generate_file($smiles, $image_name) {
    $remote_url = OM_PREFIX . $smiles;
    $http = curl_init($remote_url);
    curl_setopt($http, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($http);
    $status = curl_getinfo($http, CURLINFO_HTTP_CODE);
    curl_close($http);

    if ($status != 200) {
      $image_name = FAILURE_IMAGE;
    }

    else {
      $out_name = IMAGE_DIR . $image_name;
      file_put_contents($out_name, $result);
      // crop image
      $im = new Imagick($out_name);
      //$im->minifyImage();
      $im->trimImage(0.0);      
      $im->writeImage();
      $cmd = "convert -resize 60% $out_name /tmp/scale.png; cp /tmp/scale.png $out_name";
      $result = exec($cmd);
    }

    return $image_name;
  }

  // replace all bbcode SMILES with molecular images
  public function bbcode_replace($message) {
    $codes = $this->extract_smiles($message);

    foreach ($codes as $code) {
      $bare_smiles = str_replace(array('[smiles]', '[/smiles]'),
				 array(), $code);
      $rendered = $this->render($bare_smiles);
      $message = str_replace($code, $rendered, $message);
    }
    return $message;
  }

  /* Get all bbcode SMILES markup */
  public function extract_smiles($message) {
    $codes = array();
    $offset = 0;
    $begin_tag = '[smiles]';
    $end_tag = '[/smiles]';
    $current_tag = $begin_tag;

    while ($offset < strlen($message)) {

      $old_pos = $offset;
      $pos = strpos($message, $current_tag, $offset);

      if ($pos === false) {
	break;
      }

      else {
	$offset = $pos;
      }

      // found begin -- switch to end search
      if ($current_tag == $begin_tag) {
	$current_tag = $end_tag;
      }
      
      // found end -- capture contents and switch back to begin search
      else {
	$smile = substr($message, $old_pos, 
			$pos - $old_pos + strlen($end_tag));
	array_push($codes, $smile);
	$current_tag = $begin_tag;
      }
    }

    return $codes;
  }
} 
?>
