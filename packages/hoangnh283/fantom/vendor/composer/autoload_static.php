<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitff229c4f3d349829ea1ddf67bb1f0aa3
{
    public static $prefixLengthsPsr4 = array (
        'H' => 
        array (
            'Hoangnh283\\Fantom\\' => 18,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Hoangnh283\\Fantom\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitff229c4f3d349829ea1ddf67bb1f0aa3::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitff229c4f3d349829ea1ddf67bb1f0aa3::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitff229c4f3d349829ea1ddf67bb1f0aa3::$classMap;

        }, null, ClassLoader::class);
    }
}
