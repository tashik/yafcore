<?php

/**
 * Билдилка экста
 *
 * @author grundik
 */
class Core_ExtBuilder {
  protected static $_extPkg = null;

  protected static function getBuildScript() {
    if (!self::$_extPkg) {
      self::$_extPkg = json_decode(file_get_contents(APPLICATION_PATH.'/../public/extjs/sdk.jsb3'), true);
    }
    if (!self::$_extPkg) {
      throw new Exception('Отсутствует ExtJS!');
    }
    return self::$_extPkg;
  }

  protected static function _getPkgConfig($package) {
    $pkgs = self::getBuildScript();
    foreach ($pkgs['packages'] as $pkg) {
      if ($pkg['name']==$package || $pkg['target']==$package || $pkg['id']==$package) {
        return $pkg;
      }
    }
    //logVar("Package not found: $package");
    return null;
  }

  protected static function _getSourcesFor($package) {
    $config = self::_getPkgConfig($package);
    //logVar($config);
    $srcs = array();
    if ('manifest'==$config['id']) {
      return array('pkgs/manifest.js');
    }
    foreach ($config['files'] as $inc) {
      $path = preg_replace(
        array('@^\.\./platform/core@', '@^\.\./platform/src@'),
        array('src/core',              'src'),
        $inc['path']);
      $srcs[] = "{$path}{$inc['name']}";
    }
    //logVar($srcs, "Sources for $package");
    return $srcs;
  }

  public static function getSources($packages) {
    if (!is_array($packages)) {
      $packages = array($packages);
    }
    $srcs = array();
    foreach ($packages as $pkg) {
      $srcs = array_merge($srcs, self::_getSourcesFor($pkg));
    }
    return $srcs;
  }

  public static function getPackages($package) {
    $config = self::_getPkgConfig($package);
    $srcs = array();
    foreach ($config['pkgDeps'] as $dep) {
      $srcs[] = preg_replace('@^.*/([^/]+)\.js$@u', 'pkgs/$1.js', $dep);
    }
    return $srcs;
  }

  public static function build($target) {
    $cache = getCacheInstance(true);
    $key = "extjs_".str_replace(' ', '_', $target);
    $sources = $cache->load($key);
    if (false===$sources) {
      $config = self::getBuildScript();
      foreach ($config['builds'] as $build) {
        if ($build['name']!=$target && $build['target']!=$target) {
          continue;
        }
        $sources = self::getSources($build['packages']);
        $cache->save($sources, $key, array(), PERSISTENT_CACHE_TTL);
        break;
      }
    }
    return $sources;
  }
}
