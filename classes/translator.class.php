<?php

// a translator class
//
// usage:
// in your code, whereever a translatable string is used, call tl("original string")
//
// a translator class
class translator {

    private $tls = array(); // associative array for current language
    var $msg = null;  // error message if instantiaion failed

    // internal helper function

    static function makefn($tag, $lang, $path) {
        if ($tag != "")
            $tag .= "-";
        $fn = "lang-$tag$lang.inc";
        return $path . (($path != "") ? "/" : "") . $fn;
    }

    static function makefunction($tag, $lang) {
        if ($tag != "")
            $tag .= "_";
        // fix to PHP syntax
        return preg_replace('/[^_a-zA-Z0-9]/', "_", "_translator_$tag$lang");
    }

    // instantiate providing the current language and the place language files can be found
    function __construct(array $translators, $lang, $deflang) {
        // translators is an array of tag names and pathes to find translation files and functions
        // read language file, must export the function called "translator_$lang"
        $this->msg = null;
        foreach ($translators as $translator) {
            $tag = $translator[0];
            $path = $translator[1];
            $fn = self::makefn($tag, $lang, $path);
            @include_once($fn);
            // load language table
            $la = self::makefunction($tag, $lang);

            if (function_exists($la)) {
                $tmp = array();
                $la($tmp);
                $this->tls = array_merge($this->tls, $tmp);
            } else if ($lang != $deflang) {
                $this->msg = "translator: no '$lang' translation available " . ($tag == "" ? "" : "for $tag ") . "(file '$fn' or 'function $la' missing)";
                // exceptions do not work here for some reason :-(
                // throw new Exception($this->msg);
                // print "<pre><strong>{$this->msg}</strong></pre>";
                // $this->tls = array(); - do not clear all translations due to missing module
            }
        }
    }

    public function tl($txt) {
        $ret = isset($this->tls[$txt]) ? $this->tls[$txt] : $txt;
        if ($ret != "" && ($ret[0] == "@") && (preg_match('%^@[a-zA-Z0-9_ :./]*@(.*)$%', $ret, $match) >= 1)) {
            return $match[1];
        }
        return $ret;
    }

}

class tl {

    const encUtf8 = "UTF-8";
    const encIso88591 = "ISO-8859-1";

    // this is just a static class to hold the current language

    /* private */ static $cl = null;   // this will hold the "current" language
    /* private */
    static $curPath = "";
    /* private */
    static $curLang = "de";
    /* private */
    static $defLang = "de";
    /* private */
    static $tls = array();
    /* private */
    static $outputEncoding = "ISO-8859-1";
    /* private */
    static $inputEncoding = "UTF-8";    // how language files are encoded
    static public $iso639codes;

    public static function currentLanguage() {
        return self::$curLang;
    }

    public function __construct($deflang = "de", $defpath = "", $outputEncoding = self::encIso88591, $inputEncoding = self::encUtf8) {
        // sets the default language and the file location
        self::$curPath = $defpath;
        self::$curLang = $deflang;
        self::$defLang = $deflang;
        self::$outputEncoding = $outputEncoding;
        self::$inputEncoding = $inputEncoding;
        self::register("", $defpath);
    }

    // register a place where translations can be found
    // if you register("me"), then it is expected that you have language files called "lang-me-<lang>.inc" in the current directory
    public static function register($tag, $path = "") {
        $path = str_replace("\\", "/", $path);  // replace \ for unix systems
        self::$tls[] = array($tag, $path);
    }

    // switch to a new language
    public static function change($to) {
        // load language
        $oldCurLang = self::$curLang;
        $tmp = new translator(self::$tls, $to, self::$defLang);
        if ($tmp->msg === null) {
            self::$cl = $tmp;
            self::$curLang = $to;
        }

        self::$iso639codes = array(
            // 'aa' => 'Afar',
            // 'ab' => 'Abkhazian',
            // 'af' => 'Afrikaans',
            // 'am' => 'Amharic',
            // 'ar' => 'Arabic',
            // 'as' => 'Assamese',
            // 'ay' => 'Aymara',
            // 'az' => 'Azerbaijani',
            // 'ba' => 'Bashkir',
            // 'be' => 'Byelorussian',
            // 'bg' => 'Bulgarian',
            // 'bh' => 'Bihari',
            // 'bi' => 'Bislama',
            // 'bn' => 'Bengali Bangla',
            // 'bo' => 'Tibetan',
            // 'br' => 'Breton',
            // 'ca' => 'Catalan',
            // 'co' => 'Corsican',
            'cs' => 'Czech',
            // 'cy' => 'Welsh',
            'da' => 'Danish',
            'de' => 'Deutsch',
            // 'dz' => 'Bhutani',
            'el' => 'Greek',
            'en' => 'English',
            // 'eo' => 'Esperanto',
            'es' => 'Spanish',
            'et' => 'Estonian',
            // 'eu' => 'Basque',
            // 'fa' => 'Persian',
            'fi' => 'Finnish',
            // 'fj' => 'Fiji',
            // 'fo' => 'Faeroese',
            'fr' => 'Français',
            // 'fy' => 'Frisian',
            // 'ga' => 'Irish',
            // 'gd' => 'Gaelic Scots Gaelic',
            // 'gl' => 'Galician',
            // 'gn' => 'Guarani',
            // 'gu' => 'Gujarati',
            // 'ha' => 'Hausa',
            // 'hi' => 'Hindi',
            'hr' => 'Croatian',
            'hu' => 'Hungarian',
            // 'hy' => 'Armenian',
            // 'ia' => 'Interlingua',
            // 'ie' => 'Interlingue',
            // 'ik' => 'Inupiak',
            // 'in' => 'Indonesian',
            // 'is' => 'Icelandic',
            'it' => 'Italiano',
            // 'iw' => 'Hebrew',
            // 'ja' => 'Japanese',
            // 'ji' => 'Yiddish',
            // 'jw' => 'Javanese',
            // 'ka' => 'Georgian',
            // 'kk' => 'Kazakh',
            // 'kl' => 'Greenlandic',
            // 'km' => 'Cambodian',
            // 'kn' => 'Kannada',
            // 'ko' => 'Korean',
            // 'ks' => 'Kashmiri',
            // 'ku' => 'Kurdish',
            // 'ky' => 'Kirghiz',
            // 'la' => 'Latin',
            // 'ln' => 'Lingala',
            // 'lo' => 'Laothian',
            'lt' => 'Lithuanian',
            'lv' => 'Latvian Lettish',
            // 'mg' => 'Malagasy',
            // 'mi' => 'Maori',
            // 'mk' => 'Macedonian',
            // 'ml' => 'Malayalam',
            // 'mn' => 'Mongolian',
            // 'mo' => 'Moldavian',
            // 'mr' => 'Marathi',
            // 'ms' => 'Malay',
            // 'mt' => 'Maltese',
            // 'my' => 'Burmese',
            // 'na' => 'Nauru',
            // 'ne' => 'Nepali',
            'nl' => 'Nederlands',
            'no' => 'Norwegian',
            // 'oc' => 'Occitan',
            // 'om' => 'Oromo Afan',
            // 'or' => 'Oriya',
            // 'pa' => 'Punjabi',
            'pl' => 'Polish',
            // 'ps' => 'Pashto Pushto',
            'pt' => 'Portuguese',
            // 'qu' => 'Quechua',
            // 'rm' => 'Rhaeto-Romance',
            // 'rn' => 'Kirundi',
            // 'ro' => 'Romanian',
            'ru' => 'Russian',
            // 'rw' => 'Kinyarwanda',
            // 'sa' => 'Sanskrit',
            // 'sd' => 'Sindhi',
            // 'sg' => 'Sangro',
            // 'sh' => 'Serbo-Croatian',
            // 'si' => 'Singhalese',
            // 'sk' => 'Slovak',
            'si' => 'Slovenian', // firmware/myPBX etc. use si instead of sl for slovenian!
            // 'sm' => 'Samoan',
            // 'sn' => 'Shona',
            // 'so' => 'Somali',
            // 'sq' => 'Albanian',
            // 'sr' => 'Serbian',
            // 'ss' => 'Siswati',
            // 'st' => 'Sesotho',
            // 'su' => 'Sudanese',
            'sv' => 'Swedish',
                // 'sw' => 'Swahili',
                // 'ta' => 'Tamil',
                // 'te' => 'Tegulu',
                // 'tg' => 'Tajik',
                // 'th' => 'Thai',
                // 'ti' => 'Tigrinya',
                // 'tk' => 'Turkmen',
                // 'tl' => 'Tagalog',
                // 'tn' => 'Setswana',
                // 'to' => 'Tonga',
                // 'tr' => 'Turkish',
                // 'ts' => 'Tsonga',
                // 'tt' => 'Tatar',
                // 'tw' => 'Twi',
                // 'uk' => 'Ukrainian',
                // 'ur' => 'Urdu',
                // 'uz' => 'Uzbek',
                // 'vi' => 'Vietnamese',
                // 'vo' => 'Volapuk',
                // 'wo' => 'Wolof',
                // 'xh' => 'Xhosa',
                // 'yo' => 'Yoruba',
                // 'zh' => 'Chinese',
                // 'zu' => 'Zulu',
        );
        if (self::$outputEncoding == self::encIso88591) {
            foreach (self::$iso639codes as &$val) {
                $val = iconv(self::encUtf8, self::encIso88591, $val);
            }
        }
        return $oldCurLang;
    }

    public static function tlx($txt) {
        $arg = func_get_args();
        $tra = str_replace('"', '\"', self::tl($txt));
        $result = @eval("return \"${tra}\";");
        if ($result === false) {
            $msg = "###tl::tlx: string format error in format '$txt' (translated to $tra))###";
            print "<pre><strong>$msg</strong></pre>";
            $result = "###err###";
        }
        return $result;
    }

    public static function encode_output($text) {
        switch (self::$outputEncoding) {
            case self::encUtf8:
                switch (self::$inputEncoding) {
                    case self::encUtf8: return $text;
                    case self::encIso88591: return iconv(self::encIso88591, self::encUtf8, $text);
                }
                break;
            case self::encIso88591:
                switch (self::$inputEncoding) {
                    case self::encUtf8: return iconv(self::encUtf8, self::encIso88591, $text);
                    case self::encIso88591: return $text;
                }
                break;
        }
    }

    // translate string
    public static function tl($txt) {
        if (self::$cl === null) {
            self::change(self::$curLang);
        }
        return self::encode_output(self::$cl->tl($txt));
    }

    // return a list of available languages (with existing translations) as array(lang => path)
    public static function languages($with_classes = false) {
        $languages = array();
        foreach (self::$tls as $pair) {
            $tag = $pair[0];
            $path = $pair[1];
            if (!$with_classes && ($tag != ""))
                continue; // ignore class translations
            foreach (glob(translator::makefn("", "*", $path)) as $lang) {
                if (preg_match('/.*-(.+).inc$/', $lang, $match) > 0)
                    $languages[$match[1]] = $path;
            }
        }
        // print "<pre>languages: " . print_r($languages);
        return $languages;
    }

    // return an English language name for a 
    public static function iso639($shorthand = null) {
        if ($shorthand == null)
            $shorthand = self::currentLanguage();
        $ret = empty(self::$iso639codes[$shorthand]) ? "" : self::$iso639codes[$shorthand];
        if ($ret == "")
            $ret = $shorthand . " (invalid code)";
        return $ret;
    }

    /**
     * convert a string in to a valid @...@ tag
     * @param string $tag
     * @return string
     */
    public static function makeSaveTag($tag) {
        // beware: the pattern MUST match the one used in translator::tl
        return preg_replace('%[^a-zA-Z0-9_ :./]+%', '_', $tag);
    }

}

?>