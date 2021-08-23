<?php

class UpdateSnippetDevice {
}

class UpdateSnippet {

    /**
     * all we know about the device
     * @var SimpleXMLElement
     */
    var $statexml;

    /**
     * dump $this->property->statexml
     * this includes a SIMPLEXMLElement with everything we know about the device
     */
    final function dump() {
        unset($this->statexml->msgs);
        $dom = new DOMDocument('1.0');
        $dom->preserveWhitespace = true;
        $dom->formatOutput = true;
        $dom->loadXML($this->statexml->asXML());
        print "<pre>" . htmlspecialchars($dom->saveXML()) . "</pre>";
    }

    function __construct() {
        $this->statexml = new SimpleXMLElement('<e/>');
    }

    /**
     * custom snippet to be sent before all standard snippets
     * array of strings (each element one line) 
     * @return array
     */
    function getPreSnippet() {
        return array();
    }

    /**
     * custom snippet to be sent after all standard snippets
     * array of strings (each element one line) 
     * @return array
     */
    function getPostSnippet() {
        return array();
    }

    /**
     * shall we send or suppress all standard snippets?
     * @return boolean
     */
    function sendStandardSnippets() {
        return true;
    }

}

?>