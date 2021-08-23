<?php

/**
 * to provide your own runtime generated snippets, rename this file to 'scripting.class.php' and 
 * implement the member functions
 * $property will have one member called 
 */

class CustomUpdateSnippet extends UpdateSnippet {

    /**
     * snippet to deliver after standard text snippets
     * @return array of string
     */
    public function getPostSnippet() {
        return parent::getPostSnippet();
    }

    /**
     * snippet to deliver before standard text snippets
     * @return array of strings
     */
    public function getPreSnippet() {
        return parent::getPreSnippet();
    }

    /** 
     * do we want to suppress the standard text snippets?
     * @return boolean
     */
    public function sendStandardSnippets() {
        return parent::sendStandardSnippets();
    }


}
?>
