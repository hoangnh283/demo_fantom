<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInitff229c4f3d349829ea1ddf67bb1f0aa3
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        spl_autoload_register(array('ComposerAutoloaderInitff229c4f3d349829ea1ddf67bb1f0aa3', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInitff229c4f3d349829ea1ddf67bb1f0aa3', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInitff229c4f3d349829ea1ddf67bb1f0aa3::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}
