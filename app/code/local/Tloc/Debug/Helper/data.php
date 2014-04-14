<?php
class Tloc_Debug_Helper_Data extends Mage_Core_Helper_Abstract{
   function _findMethod ($classOrObject, $method, $return=false) {
		$return = $return ? $return : new ArrayObject;
        $r = new ReflectionClass($classOrObject);

        $return[ $r->getName() ] = array(
        	'file' 		=> str_replace(Mage::getBaseDir('base'), '', $r->getFileName()),
        	'hasMethod'	=> false,	// This might get overwritten below
        	'line'		=> false,
        	'hasMagic'	=> $r->hasMethod('__call'),
        );

        $tokens = token_get_all(file_get_contents($r->getFilename()));

        $is_function_context = false;
        foreach($tokens as $token) {
            if(!is_array($token)){
            	continue;
            }          

            $token['name'] = token_name($token[0]);

            if($token['name'] == 'T_WHITESPACE'){
            	continue;
            }

            if($token['name'] == 'T_FUNCTION') { 
                $is_function_context = true;
                continue;
            }

            if($is_function_context) {
                if($token[1] == $method) {
                    $return[ $r->getName() ]['hasMethod'] = true;
                    $return[ $r->getName() ]['line'] = $token[2];
                }

                $is_function_context = false;
                continue;
            }
        }

        $parent = $r->getParentClass();

        if($parent) {
            self::_findMethod($parent->getName(), $method, $return);
        }

        return $return;
	}

	function findMethod($classOrObject, $method) {
		echo '<pre>';
		echo "<strong>Looking for class method `{$method}`</strong>\n\n";

		if (!$classOrObject) {
			echo 'Provided object is empty.</pre>';
			return;
		}

		$result = self::_findMethod($classOrObject, $method, false);

		$found = false;

		if (count($result)) {
			$i=1;
			foreach ($result as $class_name => $details) {
				if ($details['hasMethod']) {
					$found = true;
					echo '['.$i.'] <strong>'.$class_name.'</strong>&nbsp;&nbsp;<small>('.$details['file'].')</small>'."\n";
					echo "\t-&gt;{$method} on line {$details['line']}\n";
				} else {
					echo '['.$i.'] '.$class_name.'&nbsp;&nbsp;<small>('.$details['file'].')</small>'."\n";
				}

				$i++;
			}
		}

		if (!$found) {
			echo "\nThis method is probably <a href=\"http://www.php.net/manual/en/language.oop5.magic.php\">magic</a>.";
		}

		echo '</pre>'."\n";
	}

	function blockBacktrace ($block) {
		echo '<pre>';

		if (!is_subclass_of($block, 'Mage_Core_Block_Template')) {
			echo 'The provided object is not a Block.';
		} else {
			$name = $block->getNameInLayout();
			if (strpos($name, 'ANONYMOUS_') !== false) {
				echo "[0] The current block is dynamically generated at run time.\n";
			} else {
				echo "[0] The current block is `".$name."`\n";
			}

			echo "[0] and uses the class ". get_class($block) ."\n\n";

			$bts = debug_backtrace();

			for ($i=0, $x=1; $i<count($bts); $i++) {
				$bt = $bts[$i];

				$findFunctions = array('getChildHtml', 'getChildChildHtml', 'getItemHtml', 'getBlockHtml');

				if (in_array($bt['function'], $findFunctions)) {
					$prefix = "[$x] ";

					echo $prefix.'Included in ';
					echo str_replace(Mage::getBaseDir('base'), '', $bt['file']);
					echo ' on line '. $bt['line'] ."\n";

					echo $prefix.'By the parent block `'. $bt['object']->getNameInLayout() ."`\n";

					echo $prefix."By calling ". get_class($bt['object']) ."{$bt['type']}{$bt['function']}()\n\n";

					$x++;
				}
				
			}
		}

		echo '</pre>';
	}
}