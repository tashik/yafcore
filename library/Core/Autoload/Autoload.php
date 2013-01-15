<?php

require_once "PHP_Autoload.php";
require_once "NameScheme.php";

class My_NameScheme_Autoload {
    private static $loadedFiles;
    private static $excludeRegexp;
    private static $disableCompilation = false;
    private static $_includeRegEx =  '/((?:require|include)_once\s*\(?[\'"]Zend\/(.*)[\'"]\)?\s*;)/smiU';
    //private static $_includeRegEx = '/((?:require|include)_once\s*\(?(?:([\'"]Zend\/(.*)[\'"])|(zz\$[a-zA-Z_]+))\)?\s*;)/smiU';

    static function classAutoloader($classname) {
        //error_log($classname);
        if (!preg_match('@^Zend(X)?_@', $classname)) {
          return include str_replace('_', '/', $classname) . '.php';
        }
        $fname = PEAR_NameScheme::name2path($classname);
        /*if (preg_match('@^[A-Za-z]+Controller$@', $classname)) {
          $fname = "controllers/$fname";
          echo "\n$fname\n";
        }*/
        $include_fname = false;
        $kill_fname = false;
        if ($f = @fopen($fname, "r", true)) {
            fclose($f);
            //if (0===strpos($classname, 'Zend_')) {
              $include_fname = $fname;
              $fname = APPLICATION_PATH.'/../cache/autoload_'.$classname.'.php';
              if (!file_exists($fname)) {
                $f = @fopen($include_fname, "r", true);
                $fstat = fstat($f);
                $file = fread($f, $fstat['size']);
                fclose($f);
                //$file = preg_replace('/^\s*((?:require|include)_once\s*)/smiU', '//*** $1', $file);
                $file = preg_replace(self::$_includeRegEx, '//*** $1', $file);
                $file = preg_replace('@(class_exists\(\$[a-zA-Z]+,) false@sm', '$1 true', $file);
                $real_path = self::realPath($include_fname);
                $file = str_replace('__FILE__', "'$real_path'", $file);
                file_put_contents($fname, $file);
                $kill_fname = $fname;
              //}
            }
            $result = include_once($fname);
            if ($include_fname) {
              self::$loadedFiles[] = $include_fname;
            }
            if ($kill_fname && self::$disableCompilation) {
              unlink($kill_fname);
            }
            return $result;
        }
        return false;
    }

    public static function addExcludeRegexp($regexp)
    {
        self::$excludeRegexp = $regexp;
    }

    public static function compileTo($outputFile)
    {
        if (self::$disableCompilation) {
            return;
        }
        if (!count(self::$loadedFiles)) {
            return;
        }
        $fp = fopen($outputFile, "a+");
        if (flock($fp, LOCK_EX)) {
            if ($filesize = filesize($outputFile)) {
                fseek($fp, 0);
                $currentFile = fread($fp, $filesize);
            } else {
                $currentFile = '';
            }

            if (!$currentFile) {
                $appendSource = "<?php\n";
                $existingClasses = array();
            } else {
                $appendSource = '';
                $existingClasses = self::getClassesFromSource($currentFile);
            }
            for ($i = 0; $i < count(self::$loadedFiles); $i++) {
                $filename = self::$loadedFiles[$i];
                if (self::$excludeRegexp && preg_match(self::$excludeRegexp, $filename)) {
                    continue;
                }
                $f = @fopen($filename, "r", true);
                $fstat = fstat($f);
                $file = fread($f, $fstat['size']);
                fclose($f);
                $file = preg_replace(self::$_includeRegEx, '//*** $1', $file);
                $classes = self::getClassesFromSource($file);

                if (!count(array_intersect($existingClasses, $classes))) {
                    if (strpos($file, '__FILE__') === false) {
                        //debug("Complile autoload $filename");
                        $endFile = substr($file, -2) == '?>' ? -2 : null;
                        $appendSource .= ($endFile === null ? substr($file, 5) : substr($file, 5, -2));
                    } else {
                        //Потенциально ненадежно, но работает
                        $filePath = self::realPath($filename);
                        if ($filePath) {
                            //warn("Complile autoload with __FILE__ constant $filename");
                            $file = str_replace('__FILE__', "'$filePath'", $file);
                            $endFile = substr($file, -2) == '?>' ? -2 : null;
                            $appendSource .= ($endFile === null ? substr($file, 5) : substr($file, 5, -2));
                        }
                    }
                } else {
                    //debug("Conflict detect on file $filename. Complile autoload terminated.");
                    $appendSource = '';
                    break;
                }
            }
            if ($appendSource) {
                fseek($fp, 0, SEEK_END);
                fwrite($fp, $appendSource);
            }
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    public static function getClassesFromSource($source)
    {
        /*
        //Данный метод требует относительно много памяти
        $classes = array();
        $tokens = token_get_all($source);
        foreach($tokens as $key => $token) {
            if (in_array($token[0], array(T_CLASS, T_INTERFACE))) {
                $classes[] = $tokens[$key+2][1];
            }
        }
        return $classes;
        */
        preg_match_all('{^\s*(class|interface)\s+(.*?)(\s|$)}im', $source, $matches, PREG_PATTERN_ORDER);
        return $matches[2];

    }

    public static function realPath($relativeFilename)
    {
        // Check for absolute path
        if (realpath($relativeFilename) == $relativeFilename) {
            return $relativeFilename;
        }

        // Otherwise, treat as relative path
        $paths = explode(PATH_SEPARATOR, get_include_path());
        foreach ($paths as $path) {
            $path = str_replace('\\', '/', $path);
            $path = rtrim($path, '/') . '/';
            $fullpath = realpath($path . $relativeFilename);
            if ($fullpath) {
                return $fullpath;
            }
        }

        return false;
    }

    public static function haltCompiler() {
      self::$disableCompilation = true;
    }
}

PHP_Autoload::register(array("My_NameScheme_Autoload", "classAutoloader"));
