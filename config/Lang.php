<?php

class Lang {
    private static $currentLang = 'fr';
    private static $translations = [];
    private static $initialized = false;

    /**
     * Initialise le système de langue
     */
    public static function init($lang = null) {
        if (self::$initialized) {
            return;
        }

        // Démarrer la session si elle n'est pas déjà démarrée
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Déterminer la langue à utiliser
        if ($lang === null) {
            // Vérifier le paramètre GET
            if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
                $lang = $_GET['lang'];
                $_SESSION['lang'] = $lang;
            }
            // Vérifier la session
            elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], ['fr', 'en'])) {
                $lang = $_SESSION['lang'];
            }
            // Vérifier le cookie
            elseif (isset($_COOKIE['app_lang']) && in_array($_COOKIE['app_lang'], ['fr', 'en'])) {
                $lang = $_COOKIE['app_lang'];
                $_SESSION['lang'] = $lang;
            }
            // Vérifier l'en-tête Accept-Language
            else {
                $lang = self::detectLanguage();
            }
        }

        self::$currentLang = $lang;
        self::loadTranslations($lang);
        self::$initialized = true;
    }

    /**
     * Détecte la langue depuis les en-têtes HTTP
     */
    private static function detectLanguage() {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($langs as $lang) {
                $lang = strtolower(substr(trim($lang), 0, 2));
                if (in_array($lang, ['fr', 'en'])) {
                    return $lang;
                }
            }
        }
        return 'fr'; // Par défaut
    }

    /**
     * Charge les traductions depuis le fichier JSON
     */
    private static function loadTranslations($lang) {
        $file = __DIR__ . '/../lang/' . $lang . '.json';
        if (file_exists($file)) {
            $content = file_get_contents($file);
            self::$translations = json_decode($content, true) ?: [];
        } else {
            self::$translations = [];
        }
    }

    /**
     * Retourne la traduction d'une clé
     */
    public static function get($key, $default = null) {
        if (!self::$initialized) {
            self::init();
        }

        $keys = explode('.', $key);
        $value = self::$translations;

        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default !== null ? $default : $key;
            }
        }

        return $value;
    }

    /**
     * Retourne la langue actuelle
     */
    public static function current() {
        if (!self::$initialized) {
            self::init();
        }
        return self::$currentLang;
    }

    /**
     * Change la langue
     */
    public static function set($lang) {
        if (in_array($lang, ['fr', 'en'])) {
            // Démarrer la session si elle n'est pas déjà démarrée
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            self::$currentLang = $lang;
            self::loadTranslations($lang);
            $_SESSION['lang'] = $lang;
            setcookie('app_lang', $lang, time() + (365 * 24 * 60 * 60), '/');
        }
    }

    /**
     * Retourne toutes les traductions pour JavaScript
     */
    public static function getJSTranslations() {
        if (!self::$initialized) {
            self::init();
        }
        return json_encode(self::$translations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

