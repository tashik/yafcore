<?php

class Core_View_Helper_SetupResources extends Zend_View_Helper_Abstract
{
  public function SetupResources()
  {
    $view = $this->view;

    $css = array('/extjs/resources/css/ext-all.css', '/css/main.css', '/images/silk/icons/silk.css',
                 '/extjs/src/ux/css/CheckHeader.css');

    foreach ($css as $c) {
      $view->headLink()->appendStylesheet(linkToStatic($c));
    }
    //$view->headLink()->appendStylesheet('/css/print.css', 'print');

    $debug = isDebug();
    $ondemand = getConfigValue('interface->ondemand_js', false);
    if ( !$debug ) {
      //$view->headScript()->prependFile('/js/jquery/jquery-1.5.2.min.js');
      //$view->headScript()->appendFile('/js/ext/ext-jquery-adapter.js');
      //$view->headScript()->appendFile(linkToStatic('/js/ext/ext-base.js'));
      $view->headScript()->appendFile(linkToStatic($ondemand?'/extjs/ext.js':'/extjs/ext-all.js'));
    } else {
      //$view->headScript()->prependFile('/js/jquery/jquery-1.5.2.js');
      //$view->headScript()->appendFile('/js/ext/ext-jquery-adapter-debug.js');
      //$view->headScript()->appendFile(linkToStatic('/js/ext/ext-base-debug.js'));
      //$view->headScript()->appendFile(linkToStatic('/js/ext/ext-all-debug.js'));
      //$view->headScript()->appendFile(linkToStatic('/js/ext/pkgs/ext-core-debug.js'));
      //$view->headScript()->appendFile(linkToStatic('/extjs/ext-all-debug-w-comments.js'));
      $ext_src = Core_ExtBuilder::build($ondemand?'Bootstrap Dev':'All Dev');
      //array_unique($ext_src);
      foreach ($ext_src as $src) {
        $view->headScript()->appendFile(linkToStatic("/extjs/{$src}"));
      }
      /*$view->headScript()->appendFile(linkToStatic('/js/ext/debug.js'));
      $view->headLink()->appendStylesheet(linkToStatic('/css/ext/debug.css'));*/
    }

    $jsprefix = '/min/js';
    if ( $debug ) {
      $jsprefix = '';
    }
    //$view->headScript()->appendFile(linkToStatic('/extjs/locale/ext-lang-ru.js', $jsprefix));
    if ( !$debug && file_exists(APPLICATION_PATH.'/../public/applic.min.js')) {
      $view->headScript()->appendFile(linkToStatic('applic.min.js'));
    } else {
      $view->headScript()->appendFile(linkToStatic('/extjs/config.js'));
      $view->headScript()->appendFile(linkToStatic('/extjs/compat.js'));
      $view->headScript()->appendFile(linkToStatic('/extjs/ssw.js'));

      $this->appendScriptDir($view, 'util', '/^.+\.js$/', true, $jsprefix);
      $this->appendScriptDir($view, 'app/library', '/^.+\.js$/', true, $jsprefix);
      if (!$ondemand) {
        $this->appendScriptDir($view, 'app/model', '/^.+\.js$/', true, $jsprefix);
        $this->appendScriptDir($view, 'app/store', '/^.+\.js$/', true, $jsprefix);
        $this->appendScriptDir($view, 'app/view', '/^.+\.js$/', true, $jsprefix);
      }
      $this->appendScriptDir($view, 'app/controller', '/^.+\.js$/', true, $jsprefix);
      $app_js = array(
        'app.js',
      );
      foreach ($app_js as $js) {
        $view->headScript()->appendFile(linkToStatic('/'.$js, $jsprefix));
      }
    }
  }

  protected function appendScriptDir($view, $dir, $filter, $recursive = false, $jsprefix='') {
    $basepath = APPLICATION_PATH.'/../public/'.$dir;
    $d = @dir($basepath);
    $entries = array();
    if ($d) {
      while (false !== ($entry = $d->read())) {
        $entries[] = $entry;
      }
      $d->close();
      asort($entries);
      $dirs = array();
      $files = array();
      foreach($entries as $entry) {
        if ($recursive && '.'!=$entry[0] && is_dir($basepath.'/'.$entry)) {
          $dirs[] = $entry;
        } elseif (preg_match($filter, $entry)) {
          $files[] = $entry;
        }
      }
      foreach ($dirs as $entry) {
        $this->appendScriptDir($view, "$dir/$entry", $filter, $recursive, $jsprefix);
      }
      foreach ($files as $entry) {
        $view->headScript()->appendFile(linkToStatic("/$dir/$entry", $jsprefix));
      }
    }
  }
}
