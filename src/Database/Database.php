<?php

/**
 * Database.php
 *
 *
 * @category   PHP Database
 * @author     Rubén Pérez López
 * @date       10/03/2019
 * @copyright  2018 Rubén Pérez López
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    5.0 03/08/2021
 * @link       www.rubenperezlopez.com
 */

namespace Almacil;

class Database
{
	private $dbDIR;
	private $dbEXT;

	public function __construct($dbDIR = __DIR__ . '/../data', $dbEXT = 'json') {
		$this -> dbDIR = implode('/', explode('//', $dbDIR . '/'));
		$this -> dbEXT = $dbEXT;

    if (!file_exists($dbDIR)) {
      mkdir($dbDIR);
    }
	}

	public function count($coleccion, $filter) {
		$fileData = $this->getFile($this -> dbDIR . $coleccion . '.' . $this -> dbEXT);

		if (isset($filter)) {
			$response = array();
			for ($i = 0, $len = count($fileData); $i < $len; $i++) {
				$item = $fileData[$i];
				$return = true;
				if (isset($filter)) {
					if (gettype($filter) == 'string') {
						$return = $item->_id == $filter ? true : false;
					} else {
						$return = $filter($item);
					}
				}
				if ($return) {
					array_push($response, $item);
				}
			}
			return count($response);
		} else {
			return count($fileData);
		}
	}

	public function find($coleccion, $filter) {
		$fileData = $this->getFile($this -> dbDIR . $coleccion . '.' . $this -> dbEXT);

		$response = array();
		for ($i = 0, $len = count($fileData); $i < $len; $i++) {
			$item = $fileData[$i];
			$return = true;
			if (isset($filter)) {
				if (gettype($filter) == 'string') {
					$return = $item->_id == $filter ? true : false;
				} else {
					$return = $filter($item);
				}
			}
			if ($return) {
				array_push($response, $item);
			}
		}

		return $response;
	}
	public function findOne($coleccion, $filter) {
		$response = $this->find($coleccion, $filter);
		return $response[0];
	}
	public function insert($coleccion, $documento, $overwrite = false) {

		$viene_con_id = false;
		if ($documento->_id != '') {
			$viene_con_id = true;
		} else {
			$documento->_id = $this->newid(64);
		}

		// Comprobar si ya hay un documento con ese _id
		$duplicateId = !!$this->findOne($coleccion, $documento->_id);
		if ($duplicateId) {
			$documento->_id = '';
			if ($viene_con_id) {
				return false;
			} else {
				return $this->insert($coleccion, $documento);
			}
		}

		// Campos de control
		$documento->_created_at = $documento->_created_at ?? $this->milliseconds();
		$documento->_updated_at = $documento->_updated_at ?? $this->milliseconds();
		$documento->_removed_at = 0;

		$fileData = $this->getFile($this -> dbDIR . $coleccion . '.' . $this -> dbEXT);
		array_push($fileData, $documento);

		$segmentos = $this->trimArray(explode('/', $this -> dbDIR . $coleccion . '.' . $this -> dbEXT));
		$ruta = '';
		for ($s = 0; $s < count($segmentos) - 1; $s++) {
			$ruta .= '/' . $segmentos[$s];
			if (!is_dir($ruta)) mkdir($ruta);
		}

		$handle = fopen($this -> dbDIR . $coleccion . '.' . $this -> dbEXT, "w");
		$result = fwrite($handle, json_encode($fileData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		if (!$result) return false;
		return $documento;

	}
	public function upsert($coleccion, $filter, $update) {

		$matchDoc = $this->findOne($coleccion, $filter);
        if (!isset($matchDoc)) {
            $documento = $this->insert($coleccion, $update);
        } else {
            $documento = $this->update($coleccion, $filter, $update);
        }
		return $documento;

	}
	public function update($coleccion, $filter, $update) {

		$fileData = $this->getFile($this -> dbDIR . $coleccion . '.' . $this -> dbEXT);

		$matchDocs = $this->find($coleccion, $filter);

		if (count($matchDocs) === 0) return 0;

		for ($m = 0; $m < count($matchDocs); $m++) {
			$matchDoc = $matchDocs[$m];
			foreach($update as $clave => $valor) {
				$matchDoc->{$clave} = $valor;
			}

			// Campos de control
			$matchDoc->_updated_at = $this->milliseconds(); //$matchDoc->_updated_at ?? $this->milliseconds();

			for ($i = 0; $i < count($fileData); $i++) {
				if ($fileData[$i]->_id == $matchDoc->_id) {
					$fileData[$i] = $matchDoc;
				}
			}
		}


		$handle = fopen($this -> dbDIR . $coleccion . '.' . $this -> dbEXT, "w");
		$result = fwrite($handle, json_encode($fileData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		if (!$result) return false;
		return count($matchDocs);
	}
	public function remove($coleccion, $filter, $permanent = false) {

		if (!$permanent) {

			$update = new \stdClass();
			$update -> _removed_at = $this->milliseconds();
			return $this->update($coleccion, $filter, $update);

		} else {

			$fileData = $this->getFile($this -> dbDIR . $coleccion . '.' . $this -> dbEXT);

			$matchDocs = $this->find($coleccion, $filter);

			if (count($matchDocs) === 0) {
				return 0;
			}

			$temp = array();
			for ($i = 0; $i < count($fileData); $i++) {
				$notMatch = true;
				for ($j = 0; $j < count($matchDocs); $j++) {
					if ($matchDocs[$j] -> _id == $fileData[$i] -> _id) {
						$notMatch = false;
					}
				}
				if ($notMatch) {
					array_push($temp, $fileData[$i]);
				}
			}
			$fileData = $temp;

			if (count($fileData) === 0) {
				@unlink($this -> dbDIR . $coleccion . '.' . $this -> dbEXT);
				return count($matchDocs);
			} else {
				$handle = fopen($this -> dbDIR . $coleccion . '.' . $this -> dbEXT, "w");
				$result = fwrite($handle, json_encode($fileData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
				if (!$result) {
					return false;
				} else {
					return count($matchDocs);
				}
			}

		}
	}
	public function drop($coleccion) {
		@unlink($this -> dbDIR . $coleccion . '.' . $this -> dbEXT);
	}
	public function newid($len = 64, $charSet = '') {
		if ($len == '') $len = 64;
		if ($charSet == '') $charSet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		$randomString = '';
		$max = strlen($charSet) - 1;
		for ($i = 0; $i < $len; $i++) {
			$randomString .= $charSet[mt_rand(0, $max)];
		}
		return $randomString;
	}

	// FUNCIONES
	protected function getFile($file) {
		if (!file_exists($file)) {
			$fileData = array();
		} else {
			$fileContent = file_get_contents($file);
			if ($fileContent == '' || !$fileContent || !isset($fileContent)) {
				$fileData = array();
			} else {
				$fileData = json_decode($fileContent);
			}

			if (!$fileData || !isset($fileContent)) {
				$fileData = array();
			}
		}
		return $fileData;
	}
	protected function trimArray($arr) {
		$temp = array();
		for ($i = 0; $i < count($arr); $i++) {
			if ($arr[$i] !== '') array_push($temp, $arr[$i]);
		}
		return $temp;
	}
	protected function milliseconds() {
	    $mt = explode(' ', microtime());
	    return ((int)$mt[1]) * 1000 + ((int)round($mt[0] * 1000));
	}
	protected function normalize( $str = '', $utf8 = true ) {
		$str = (string)$str;
	    if( is_null($utf8) ) {
	        if( !function_exists('mb_detect_encoding') ) {
	            $utf8 = (strtolower( mb_detect_encoding($str) )=='utf-8');
	        } else {
	            $length = strlen($str);
	            $utf8 = true;
	            for ($i=0; $i < $length; $i++) {
	                $c = ord($str[$i]);
	                if ($c < 0x80) $n = 0; # 0bbbbbbb
	                elseif (($c & 0xE0) == 0xC0) $n=1; # 110bbbbb
	                elseif (($c & 0xF0) == 0xE0) $n=2; # 1110bbbb
	                elseif (($c & 0xF8) == 0xF0) $n=3; # 11110bbb
	                elseif (($c & 0xFC) == 0xF8) $n=4; # 111110bb
	                elseif (($c & 0xFE) == 0xFC) $n=5; # 1111110b
	                else return false; # Does not match any model
	                for ($j=0; $j<$n; $j++) { # n bytes matching 10bbbbbb follow ?
	                    if ((++$i == $length)
	                        || ((ord($str[$i]) & 0xC0) != 0x80)) {
	                        $utf8 = false;
	                        break;
	                    }

	                }
	            }
	        }

	    }

	    if(!$utf8)
	        $str = utf8_encode($str);

	    $transliteration = array(
	    'Ĳ' => 'I', 'Ö' => 'O','Œ' => 'O','Ü' => 'U','ä' => 'a','æ' => 'a',
	    'ĳ' => 'i','ö' => 'o','œ' => 'o','ü' => 'u','ß' => 's','ſ' => 's',
	    'À' => 'A','Á' => 'A','Â' => 'A','Ã' => 'A','Ä' => 'A','Å' => 'A',
	    'Æ' => 'A','Ā' => 'A','Ą' => 'A','Ă' => 'A','Ç' => 'C','Ć' => 'C',
	    'Č' => 'C','Ĉ' => 'C','Ċ' => 'C','Ď' => 'D','Đ' => 'D','È' => 'E',
	    'É' => 'E','Ê' => 'E','Ë' => 'E','Ē' => 'E','Ę' => 'E','Ě' => 'E',
	    'Ĕ' => 'E','Ė' => 'E','Ĝ' => 'G','Ğ' => 'G','Ġ' => 'G','Ģ' => 'G',
	    'Ĥ' => 'H','Ħ' => 'H','Ì' => 'I','Í' => 'I','Î' => 'I','Ï' => 'I',
	    'Ī' => 'I','Ĩ' => 'I','Ĭ' => 'I','Į' => 'I','İ' => 'I','Ĵ' => 'J',
	    'Ķ' => 'K','Ľ' => 'K','Ĺ' => 'K','Ļ' => 'K','Ŀ' => 'K','Ł' => 'L',
	    'Ñ' => 'N','Ń' => 'N','Ň' => 'N','Ņ' => 'N','Ŋ' => 'N','Ò' => 'O',
	    'Ó' => 'O','Ô' => 'O','Õ' => 'O','Ø' => 'O','Ō' => 'O','Ő' => 'O',
	    'Ŏ' => 'O','Ŕ' => 'R','Ř' => 'R','Ŗ' => 'R','Ś' => 'S','Ş' => 'S',
	    'Ŝ' => 'S','Ș' => 'S','Š' => 'S','Ť' => 'T','Ţ' => 'T','Ŧ' => 'T',
	    'Ț' => 'T','Ù' => 'U','Ú' => 'U','Û' => 'U','Ū' => 'U','Ů' => 'U',
	    'Ű' => 'U','Ŭ' => 'U','Ũ' => 'U','Ų' => 'U','Ŵ' => 'W','Ŷ' => 'Y',
	    'Ÿ' => 'Y','Ý' => 'Y','Ź' => 'Z','Ż' => 'Z','Ž' => 'Z','à' => 'a',
	    'á' => 'a','â' => 'a','ã' => 'a','ā' => 'a','ą' => 'a','ă' => 'a',
	    'å' => 'a','ç' => 'c','ć' => 'c','č' => 'c','ĉ' => 'c','ċ' => 'c',
	    'ď' => 'd','đ' => 'd','è' => 'e','é' => 'e','ê' => 'e','ë' => 'e',
	    'ē' => 'e','ę' => 'e','ě' => 'e','ĕ' => 'e','ė' => 'e','ƒ' => 'f',
	    'ĝ' => 'g','ğ' => 'g','ġ' => 'g','ģ' => 'g','ĥ' => 'h','ħ' => 'h',
	    'ì' => 'i','í' => 'i','î' => 'i','ï' => 'i','ī' => 'i','ĩ' => 'i',
	    'ĭ' => 'i','į' => 'i','ı' => 'i','ĵ' => 'j','ķ' => 'k','ĸ' => 'k',
	    'ł' => 'l','ľ' => 'l','ĺ' => 'l','ļ' => 'l','ŀ' => 'l','ñ' => 'n',
	    'ń' => 'n','ň' => 'n','ņ' => 'n','ŉ' => 'n','ŋ' => 'n','ò' => 'o',
	    'ó' => 'o','ô' => 'o','õ' => 'o','ø' => 'o','ō' => 'o','ő' => 'o',
	    'ŏ' => 'o','ŕ' => 'r','ř' => 'r','ŗ' => 'r','ś' => 's','š' => 's',
	    'ť' => 't','ù' => 'u','ú' => 'u','û' => 'u','ū' => 'u','ů' => 'u',
	    'ű' => 'u','ŭ' => 'u','ũ' => 'u','ų' => 'u','ŵ' => 'w','ÿ' => 'y',
	    'ý' => 'y','ŷ' => 'y','ż' => 'z','ź' => 'z','ž' => 'z','Α' => 'A',
	    'Ά' => 'A','Ἀ' => 'A','Ἁ' => 'A','Ἂ' => 'A','Ἃ' => 'A','Ἄ' => 'A',
	    'Ἅ' => 'A','Ἆ' => 'A','Ἇ' => 'A','ᾈ' => 'A','ᾉ' => 'A','ᾊ' => 'A',
	    'ᾋ' => 'A','ᾌ' => 'A','ᾍ' => 'A','ᾎ' => 'A','ᾏ' => 'A','Ᾰ' => 'A',
	    'Ᾱ' => 'A','Ὰ' => 'A','ᾼ' => 'A','Β' => 'B','Γ' => 'G','Δ' => 'D',
	    'Ε' => 'E','Έ' => 'E','Ἐ' => 'E','Ἑ' => 'E','Ἒ' => 'E','Ἓ' => 'E',
	    'Ἔ' => 'E','Ἕ' => 'E','Ὲ' => 'E','Ζ' => 'Z','Η' => 'I','Ή' => 'I',
	    'Ἠ' => 'I','Ἡ' => 'I','Ἢ' => 'I','Ἣ' => 'I','Ἤ' => 'I','Ἥ' => 'I',
	    'Ἦ' => 'I','Ἧ' => 'I','ᾘ' => 'I','ᾙ' => 'I','ᾚ' => 'I','ᾛ' => 'I',
	    'ᾜ' => 'I','ᾝ' => 'I','ᾞ' => 'I','ᾟ' => 'I','Ὴ' => 'I','ῌ' => 'I',
	    'Θ' => 'T','Ι' => 'I','Ί' => 'I','Ϊ' => 'I','Ἰ' => 'I','Ἱ' => 'I',
	    'Ἲ' => 'I','Ἳ' => 'I','Ἴ' => 'I','Ἵ' => 'I','Ἶ' => 'I','Ἷ' => 'I',
	    'Ῐ' => 'I','Ῑ' => 'I','Ὶ' => 'I','Κ' => 'K','Λ' => 'L','Μ' => 'M',
	    'Ν' => 'N','Ξ' => 'K','Ο' => 'O','Ό' => 'O','Ὀ' => 'O','Ὁ' => 'O',
	    'Ὂ' => 'O','Ὃ' => 'O','Ὄ' => 'O','Ὅ' => 'O','Ὸ' => 'O','Π' => 'P',
	    'Ρ' => 'R','Ῥ' => 'R','Σ' => 'S','Τ' => 'T','Υ' => 'Y','Ύ' => 'Y',
	    'Ϋ' => 'Y','Ὑ' => 'Y','Ὓ' => 'Y','Ὕ' => 'Y','Ὗ' => 'Y','Ῠ' => 'Y',
	    'Ῡ' => 'Y','Ὺ' => 'Y','Φ' => 'F','Χ' => 'X','Ψ' => 'P','Ω' => 'O',
	    'Ώ' => 'O','Ὠ' => 'O','Ὡ' => 'O','Ὢ' => 'O','Ὣ' => 'O','Ὤ' => 'O',
	    'Ὥ' => 'O','Ὦ' => 'O','Ὧ' => 'O','ᾨ' => 'O','ᾩ' => 'O','ᾪ' => 'O',
	    'ᾫ' => 'O','ᾬ' => 'O','ᾭ' => 'O','ᾮ' => 'O','ᾯ' => 'O','Ὼ' => 'O',
	    'ῼ' => 'O','α' => 'a','ά' => 'a','ἀ' => 'a','ἁ' => 'a','ἂ' => 'a',
	    'ἃ' => 'a','ἄ' => 'a','ἅ' => 'a','ἆ' => 'a','ἇ' => 'a','ᾀ' => 'a',
	    'ᾁ' => 'a','ᾂ' => 'a','ᾃ' => 'a','ᾄ' => 'a','ᾅ' => 'a','ᾆ' => 'a',
	    'ᾇ' => 'a','ὰ' => 'a','ᾰ' => 'a','ᾱ' => 'a','ᾲ' => 'a','ᾳ' => 'a',
	    'ᾴ' => 'a','ᾶ' => 'a','ᾷ' => 'a','β' => 'b','γ' => 'g','δ' => 'd',
	    'ε' => 'e','έ' => 'e','ἐ' => 'e','ἑ' => 'e','ἒ' => 'e','ἓ' => 'e',
	    'ἔ' => 'e','ἕ' => 'e','ὲ' => 'e','ζ' => 'z','η' => 'i','ή' => 'i',
	    'ἠ' => 'i','ἡ' => 'i','ἢ' => 'i','ἣ' => 'i','ἤ' => 'i','ἥ' => 'i',
	    'ἦ' => 'i','ἧ' => 'i','ᾐ' => 'i','ᾑ' => 'i','ᾒ' => 'i','ᾓ' => 'i',
	    'ᾔ' => 'i','ᾕ' => 'i','ᾖ' => 'i','ᾗ' => 'i','ὴ' => 'i','ῂ' => 'i',
	    'ῃ' => 'i','ῄ' => 'i','ῆ' => 'i','ῇ' => 'i','θ' => 't','ι' => 'i',
	    'ί' => 'i','ϊ' => 'i','ΐ' => 'i','ἰ' => 'i','ἱ' => 'i','ἲ' => 'i',
	    'ἳ' => 'i','ἴ' => 'i','ἵ' => 'i','ἶ' => 'i','ἷ' => 'i','ὶ' => 'i',
	    'ῐ' => 'i','ῑ' => 'i','ῒ' => 'i','ῖ' => 'i','ῗ' => 'i','κ' => 'k',
	    'λ' => 'l','μ' => 'm','ν' => 'n','ξ' => 'k','ο' => 'o','ό' => 'o',
	    'ὀ' => 'o','ὁ' => 'o','ὂ' => 'o','ὃ' => 'o','ὄ' => 'o','ὅ' => 'o',
	    'ὸ' => 'o','π' => 'p','ρ' => 'r','ῤ' => 'r','ῥ' => 'r','σ' => 's',
	    'ς' => 's','τ' => 't','υ' => 'y','ύ' => 'y','ϋ' => 'y','ΰ' => 'y',
	    'ὐ' => 'y','ὑ' => 'y','ὒ' => 'y','ὓ' => 'y','ὔ' => 'y','ὕ' => 'y',
	    'ὖ' => 'y','ὗ' => 'y','ὺ' => 'y','ῠ' => 'y','ῡ' => 'y','ῢ' => 'y',
	    'ῦ' => 'y','ῧ' => 'y','φ' => 'f','χ' => 'x','ψ' => 'p','ω' => 'o',
	    'ώ' => 'o','ὠ' => 'o','ὡ' => 'o','ὢ' => 'o','ὣ' => 'o','ὤ' => 'o',
	    'ὥ' => 'o','ὦ' => 'o','ὧ' => 'o','ᾠ' => 'o','ᾡ' => 'o','ᾢ' => 'o',
	    'ᾣ' => 'o','ᾤ' => 'o','ᾥ' => 'o','ᾦ' => 'o','ᾧ' => 'o','ὼ' => 'o',
	    'ῲ' => 'o','ῳ' => 'o','ῴ' => 'o','ῶ' => 'o','ῷ' => 'o','А' => 'A',
	    'Б' => 'B','В' => 'V','Г' => 'G','Д' => 'D','Е' => 'E','Ё' => 'E',
	    'Ж' => 'Z','З' => 'Z','И' => 'I','Й' => 'I','К' => 'K','Л' => 'L',
	    'М' => 'M','Н' => 'N','О' => 'O','П' => 'P','Р' => 'R','С' => 'S',
	    'Т' => 'T','У' => 'U','Ф' => 'F','Х' => 'K','Ц' => 'T','Ч' => 'C',
	    'Ш' => 'S','Щ' => 'S','Ы' => 'Y','Э' => 'E','Ю' => 'Y','Я' => 'Y',
	    'а' => 'A','б' => 'B','в' => 'V','г' => 'G','д' => 'D','е' => 'E',
	    'ё' => 'E','ж' => 'Z','з' => 'Z','и' => 'I','й' => 'I','к' => 'K',
	    'л' => 'L','м' => 'M','н' => 'N','о' => 'O','п' => 'P','р' => 'R',
	    'с' => 'S','т' => 'T','у' => 'U','ф' => 'F','х' => 'K','ц' => 'T',
	    'ч' => 'C','ш' => 'S','щ' => 'S','ы' => 'Y','э' => 'E','ю' => 'Y',
	    'я' => 'Y','ð' => 'd','Ð' => 'D','þ' => 't','Þ' => 'T','ა' => 'a',
	    'ბ' => 'b','გ' => 'g','დ' => 'd','ე' => 'e','ვ' => 'v','ზ' => 'z',
	    'თ' => 't','ი' => 'i','კ' => 'k','ლ' => 'l','მ' => 'm','ნ' => 'n',
	    'ო' => 'o','პ' => 'p','ჟ' => 'z','რ' => 'r','ს' => 's','ტ' => 't',
	    'უ' => 'u','ფ' => 'p','ქ' => 'k','ღ' => 'g','ყ' => 'q','შ' => 's',
	    'ჩ' => 'c','ც' => 't','ძ' => 'd','წ' => 't','ჭ' => 'c','ხ' => 'k',
	    'ჯ' => 'j','ჰ' => 'h','ʼ' => "'",'ḩ' => 'h','ʼ' => "'",'‘' => "'",
	    '’' => "'",'ừ' => 'u','ế' => 'e','ả' => 'a','ị' => 'i','ậ' => 'a',
	    'ệ' => 'e','ỉ' => 'i','ộ' => 'o','ồ' => 'o','ề' => 'e','ơ' => 'o',
	    'ạ' => 'a','ẵ' => 'a','ư' => 'u','ắ' => 'a','ằ' => 'a','ầ' => 'a',
	    'ḑ' => 'd','Ḩ' => 'H','Ḑ' => 'D','ḑ' => 'd','ş' => 's','ā' => 'a',
	    'ţ' => 't',
	    );
	    $str = str_replace( array_keys( $transliteration ),
	                        array_values( $transliteration ),
	                        $str);
	    return strtolower($str);
	}
}
?>
