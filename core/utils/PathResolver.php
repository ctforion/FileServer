<?php

class PathResolver {
    private static $basePath;
    
    public static function setBasePath($path) {
        self::$basePath = rtrim($path, '/\\');
    }
    
    public static function resolve($path) {
        if (self::$basePath) {
            return self::$basePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
        }
        return $path;
    }
    
    public static function normalize($path) {
        // Replace backslashes with forward slashes
        $path = str_replace('\\', '/', $path);
        
        // Remove multiple slashes
        $path = preg_replace('/\/+/', '/', $path);
        
        // Remove trailing slash
        return rtrim($path, '/');
    }
    
    public static function isAbsolute($path) {
        return $path[0] === '/' || (strlen($path) > 1 && $path[1] === ':');
    }
    
    public static function makeRelative($path, $basePath) {
        $path = self::normalize($path);
        $basePath = self::normalize($basePath);
        
        if (strpos($path, $basePath) === 0) {
            return ltrim(substr($path, strlen($basePath)), '/');
        }
        
        return $path;
    }
    
    public static function join(...$paths) {
        $result = '';
        foreach ($paths as $path) {
            if ($path !== '') {
                if ($result === '') {
                    $result = $path;
                } else {
                    $result .= '/' . ltrim($path, '/');
                }
            }
        }
        return self::normalize($result);
    }
    
    public static function getExtension($path) {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }
    
    public static function getFilename($path) {
        return pathinfo($path, PATHINFO_FILENAME);
    }
    
    public static function getBasename($path) {
        return pathinfo($path, PATHINFO_BASENAME);
    }
    
    public static function getDirname($path) {
        return pathinfo($path, PATHINFO_DIRNAME);
    }
    
    public static function isSafe($path, $allowedBasePath) {
        $realPath = realpath($path);
        $realBasePath = realpath($allowedBasePath);
        
        if (!$realPath || !$realBasePath) {
            return false;
        }
        
        return strpos($realPath, $realBasePath) === 0;
    }
    
    public static function createDirectory($path, $permissions = 0755) {
        if (!is_dir($path)) {
            return mkdir($path, $permissions, true);
        }
        return true;
    }
}
