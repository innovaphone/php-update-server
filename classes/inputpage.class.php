<?php

/*
 * a class that implements a single input page that maintains its state within URL query args
 * (so that you can create a meaningful link to it)
 *
 */ 


require_once(__DIR__."/html.class.php");
require_once(__DIR__."/translator.class.php");
require_once(__DIR__."/core.php");

tl::register("inputpage", dirname(__FILE__) . "\\lang");

/**
 * class AsyncEvent describes an asynchronous event, used in the event array
 * of an genericInputPageField field 
 */
class AsyncEvent {
    const onAbort = "onabort", onBlur = "onblur", onChange = "onchange",
            onClick = "onclick", onDblClick = "ondblclick", onError = "onerror",
            onFocus = "onfocus", onKeyDown = "onkeydown", onKeyPress = "onkeypress",
            onKeyUp = "onkeyup", onLoad = "onload", onMouseDown = "onmousedown",
            onMouseUp = "onmouseup", onMouseMove = "onmousemove", onMouseOut = "onmouseout",
            onMouseOver = "onmouseover", onReset = "onreset", onSelect = "onselect",
            onSubmit = "onsubmit", onUnload = "onunload";
    
    /**
     *
     * @var string The event type (required)
     */
    var $type;
    /**
     *
     * @var string Own javascript, which will be used instead of a dynamically computed one (optional) 
     */
    var $javascript = "";
    
    /**
     *
     * @param string $type The event type (required)
     * @param string $javascript Own javascript, which will be used instead of a dynamically computed one (optional) 
     * @param string $action The action type (optional)
     * @param string $targetField The target field, which will receive the HTTP request answer (optional)
     * @param array $parameterFields An array of fields, which values will be used as parameters for the HTTP request (optional)
     * @param string $parameters HTTP GET parameters instead of $parameterFields (optional)
     * 
     */
    public function AsyncEvent($type, $javascript) {
        $this->type = $type;
        $this->javascript = $javascript;
    }
}

/**
 * generic input page item (any sort of field that can be put into the page)
 */
abstract class genericInputPageItem {
    /** 
     *
     * @var string name (used as name-tag in html)
     */
    var $name; 
    /**
     * @var string 
     */
    var $realName;
    /**
     * @var string id (used as id-tag in html)
     */
    var $id;
    /**
     * @var string item value (passed through with ?args)
     */
    var $value;
    /**
     * @var boolean flag true if it is formdata
     */
    var $formdata = false;
    /** 
     * @var boolean if sent with query args
     */
    var $sent = false;
    /**
     * @var string title (used as tool tip in html)
     */
    var $title;
    /**
     * hint to be used as title^
     * @var string 
     */
    var $hint = null;
    /**
     * @var array further attributes spread into tag
     */
    var $attributes = array();
    /**
     * @var array further attributes spread into the <td> elements for the field (see $attributes)
     */
    var $tdattributes = array();
    /**
     * @var array Array of class AsyncEvent 
     */
    var $events = array();
    /**
     * @var array Array of class AsyncEvent 
     */
    var $tdEvents = array();
    /**
     * @var boolean exclude from autorender?
     */
    var $autorender = true;
    /**
     * @var string section name the field belongs to (to enforce independant columns), implying a specific rendering style
     */
    var $style = "inline" ;
    /**
     * @var boolean flag true if this field is not shown on the screen
     */
    var $hidden = false;
    /**
     * @var boolean shall this field always be rendered on a new line?
     */
    var $onnewline = false;
    /**
     * @var boolean type  shall this field consume a full line?
     */
    var $fullline = false;
    /**
     * @var const  sortType, defines the sort type used by the default compare routine (if this field is a member of a list column)
     */
    const sortNumeric = 1, sortAlphanumeric = 2, sortAlphanumericCaseIndependent = 3, sortIgnore = 4;
    /**
     * @var int e.g. self::sortAlphanumericCaseIndependent
     */
    var $sortType = self::sortAlphanumericCaseIndependent; 
    /**
     * @var int  sortPrio (numeric) if this is used as a list column field, it defines the order/significance of field comparisons when sorting the rows (low is high!)
     */
    var $sortPrio = 0;
    /**
     * @var InputPage link to containing page
     */
    var $parentPage;
    /**
     * @var InputPageField link to parent field if this field is somehow nested into another field (such as in lists)
     */
    var $parentField;
    /**
     * @var InputPage link to basic InputPage object (this is usually what you are looking for)
     */
    var $rootPage; 
    /**
     * @var int line number (usually -1, setto index 0...n when field is rendered in a list field)
     */
    var $lineNumber = -1;
    /**
     *      list of field columns to be rendered with order of appearance.  order priority is from 1 to 4, column names are prompt, input, button, msg.
     *      columns mentioned are printed out by ascending order, except prio 0 is ignored (that is, not printed out).  This is to suppress the "filler" if desired.
     *      filler must be last column printed (if at all)
     * @var array
     */
    var $columnsToRender = null;
    /**
     * @var boolean type  flag to determine if field name ends with [], e.g. InputPageCheckboxField
     */
    var $isarray = false;
    
    static private $serial = 0;
    
    /**
     * @var InputPageAction optional inputpagefield for button
     */
    var $button = null;
    
    /**
     * @var stringlast error message from validator
     */
    var $msg = "";
    var $msgTagged = false;
    
    var $isAction = false;
    
    // constructor
    function genericInputPageItem($name, $value) {
        if ($name == "") {
            $name = "_unnamed_" . self::$serial++;
        }
        $this->name = $name;
        $this->value = $value;
        $this->parentField = null;
        $this->parentPage = null;
        $this->rootPage = null;
        $this->button = null;
    }
    
    // clear value
    function clear() {
        $this->value = null;
    }
    
    // adds a field description to the hint (retains previously set hints [likely by constructor])
    function setHint($hint) {
        if ($this->hint != "") $this->hint = $hint . " ... " . $this->hint; else $this->hint = $hint;
    }
    
    // get the hint to use as title upon rendering
    function getHint() {
        if ($this->hint === null) {
            return tl::tlx('Enter \'{$arg[1]}\' here.', $this->prompt);
        }
        return $this->hint;
    }
    
    // compare function, may be overridden when useful, must return (-1,0,1) if ($left is less, is equal, is higher)
    function compare($left, $right) {
        $ret = 0;
        // check if $left/$right is an array and get its value, e.g. if field is a dropdown box
        if(is_array($left)) {
            $leftVal = array_search(true, $left);
            if($leftVal !== false) {
                $left = $leftVal;
            }
        }
        if(is_array($right)) {
            $rightVal = array_search(true, $right);
            if($rightVal !== false) {
                $right = $rightVal;
            }
        }
        if ($this->sortType == self::sortAlphanumeric) {
            $ret = strcmp($left, $right);
        } elseif ($this->sortType == self::sortAlphanumericCaseIndependent) {
            $ret = strcasecmp($left, $right);
        } elseif ($this->sortType == self::sortNumeric) {
            $vleft = $left + 0;
            $vright = $right + 0;
            if ($vleft < $vright) $ret = -1;
            elseif ($vleft > $vright) $ret = 1;
            else $ret = 0;
        } else {
            $ret = 0;
        }
        return $ret;
    }
    
    // generates html code for a field that is expected to span $columns <td> columns
    // field is expected to print most important fields only if not enough columns available
    abstract function render($columns);
    
    // dump an item in viewable text form (e.g. to print into an email)
    abstract function dumptext();
    
    /**
     *     must validate field and return true if good.
     *     setting this to false will also set HTML5 "required" attribute.
     *     setting it to 0 instead will fail the field if empty, but will not set the attribute (so the browser will not check it, but the class).
     */
    var $emptyOk = false;
    abstract function valid();
    
    // function to be called when a field is in fact linked to a page.  This is after the constructor, but before any other stuff happens.
    // this is called by InputPage::addField and by default does nothing (pageref will be in ->rootPage member)
    function linkedToPage() { }
    
    // set a ja script init function
    function setJavaScriptInit($code) {
        if ($this->rootPage == null) die("setJavaScriptInit required to be linked to page");
        $this->rootPage->scriptcodes["JavaScriptInit_" . $this->name] = $code;
    }
}

abstract class sendableGenericInputPageItem extends genericInputPageItem  {
    // default value if not set
    var $default;
    
    // normally, input fields ar trimmed (that is, trailing white space removed). 
    var $cutTrailingWhiteSpace = true;
    
    function sendableGenericInputPageItem($name, $value) {
        $this->genericInputPageItem($name, $value);
        $this->formdata = true;
    }
    
    /**
     * clear any data that may be in POST/GET form data 
     */
    public function clearFormData() {
        InputPage::formClear($this->name);
    }
    
    // function that updates field value from form data passed (usually $_GET)
    public function updateFromForm() {
        // may be sufficiant for many input fields (or will be overridden)
        if (InputPage::formInput($this->name) !== null) {
            $this->value = InputPage::formInput($this->name);
            $this->sent = true;
        } else {
            $this->value = $this->default;
            $this->sent = false;
        }
    }
    
    public function computeFromForm() {
        // will be called after field value has been retrieved from form (get/post) data
        // may be overridden to convert e.g. a datestring into a php time
    }
    
    final function getFieldValueFromForm() {
        $this->updateFromForm();
        // if value came from form data, give derived class a chance to convert
        $raw = $this->value;
        if ($this->sent) $this->computeFromForm();
        InputPage::debug("sendableGenericInputPageItem::getFieldValueFromForm: retrieved (line={$this->lineNumber}, name={$this->name}, raw=" . print_r($raw, true) . ", value=" . print_r($this->value, true) . ")");
    }
    
    // function that creates URL args (for GET submission method) from field
    public function translateToForm() {
        // may be sufficiant for many input fields (or will be overridden)
        return ("{$this->name}=" . urlencode($this->value));
    }
    
    // function that creates URL args (for GET submission method) from field msg
    // may be sufficiant for many input fields (or will be overridden)
    public function translateMsgToForm() {
        if ($this->msgTagged) {
            return ("{$this->name}__msg=" . urlencode($this->msg));
        } else return "";
    }
    
    // function to tag a field msg so that it is inherited from/to GET args (usually used before a reload)
    public function tagMsg($msg) {
        $this->msgTagged = true;
        $this->msg = $msg;
    }
    
    // if the field has actions hidden within the field structure, those actions will not be called by the default engine
    // and it must be done within this member (which needs than to be overridden).  returns true if any action has been called.
    public function callActions(InputPage $page, $phase, $good) {
        return false;
    }
}

class InputPageHorizontalRule extends genericInputPageItem {
    
    // skip to next line, possibly with some decoration
    /**
     * @param string $decoration html-code to create the "rule" (usually left empty whch defaults to <hr>)
     */
    public function InputPageHorizontalRule($decoration = "<hr>") {
        $this->genericInputPageItem(null, $decoration);
        $this->onnewline = true;
        $this->fullline = true;
        $this->sortType = genericInputPageItem::sortIgnore;
    }
    
    // render the field in $columns columns
    public function render($columns) {
        if ($this->hidden || ($this->value == "")) return "";
        return "     <td colspan='$columns'>{$this->value}</td>"; 
    }
    
    public function dumptext() { return false; }
    
    // text areas are always valid
    function valid() {
        return true;
    }
}

class InputPageBlank extends genericInputPageItem {
    // a blank area in the form
    // used with class InputPage to skip a field, not passed between forms
    
    public function InputPageBlank() {
        $this->genericInputPageItem(null, "");
        $this->sortType = genericInputPageItem::sortIgnore;
    }
    
    
    // render the field in $columns columns
    public function render($columns) {
        if ($this->hidden) return "";
		return "     <td colspan='$columns'/>"; 
    }
    
    public function dumptext() { return false; }
    
    // text areas are always valid
    function valid() {
        return true;
    }
}

class InputPageMessage extends sendableGenericInputPageItem {
    // like InputPageText except that it has a msg member that can be passed between forms
    
    // hmtl tag
    var $type = "div";
    
    public function InputPageMessage($name, $value, $type = "div") {
        $this->sendableGenericInputPageItem($name, $value);
        $this->type = $type;
        $this->hint = "";
        $this->emptyOk = true;
    }
    
    // render the field in $columns columns
    public function render($columns) {
        if ($this->hidden) return "";
        $asyncTdEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->tdEvents) : "");
        $asyncEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->events) : "");
        $tdattribs = html::formatAttributes($this->tdattributes);
        $class = "";
        if(!isset($this->tdattributes["class"])) {
            $class = "class='hint'";
        }
        return "     <td colspan='$columns' $class $tdattribs title='" . html::hq($this->getHint()) . "' $asyncTdEvents>"
            . "<" . $this->type . " "
            . html::formatAttributes($this->attributes)
            . $asyncEvents
            . " name='" . $this->name . "'>"
            . $this->value 
            . "</" . $this->type . ">" 
            . "</td>"; 
    }
    
    public function dumptext() { return false; }
    
    // Messages are always valid
    function valid() {
        return true;
    }
}

class InputPageIFrame extends genericInputPageItem {
    // an iframe
    public function InputPageIFrame($name) {
        $this->genericInputPageItem($name, "");
        $this->hint = "";
        $this->sortType = genericInputPageItem::sortIgnore;
    }
    
    // render the field in $columns columns
    public function render($columns) {
        if ($this->hidden) return "";
        $tdattribs = html::formatAttributes($this->tdattributes);
        $asyncTdEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->tdEvents) : "");
        $asyncEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->events) : "");
        $class = "";
        if(!isset($this->tdattributes["class"])) {
            $class = "class='hint'";
        }
        return "     <td colspan='$columns' $class title='" . html::hq($this->getHint()) . "' {$tdattribs} $asyncTdEvents>"
            . "<iframe "
            . html::formatAttributes($this->attributes)
            . $asyncEvents
            . " name='" . $this->name . "'>"
            . tl::tlx('Your browser can\'t show embedded frames, but you can open the embedded site by following <a href=\'{$arg[1]}\' target=\'_blank\'>this link</a>', $this->attributes["src"])
            . "</iframe>" 
            . "</td>"; 
    }
    
    public function dumptext() { return false; }
    
    // text areas are always valid
    function valid() {
        return true;
    }
}

/**
 * a string field which expects an URL 
 */
class InputPageURLField extends InputPageStringField {
    public function __construct($name, $prompt = "", $default = "", InputPageAction $button = null) {
        parent::__construct($name, $prompt, $default, $button);
        $this->type = "url";
        $this->placeHolder = tl::tl("e.g.").' http://www.innovaphone.com';
    }
}

/**
 * a search field with a clear 'X' 
 */
class InputPageSearchField extends InputPageStringField {
    public function __construct($name, $prompt = "", $default = "", InputPageAction $button = null) {
        parent::__construct($name, $prompt, $default, $button);
        $this->type = "search";
        $this->placeHolder = tl::tl("Enter your search");
    }
}

class InputPageText extends genericInputPageItem {
    
    // hmtl tag
    var $type = "div";
    
    /**
     * a single text area in the form.
     * used with class InputPage, not passed between forms
     * @param string $name
     * @param string $value
     * @param string $type 
     */
    public function InputPageText($name, $value, $type = "div") {
        $this->genericInputPageItem($name, $value);
        $this->type = $type;
        $this->hint = "";
        $this->sortType = genericInputPageItem::sortIgnore;
    }
    
    // render the field in $columns columns
    public function render($columns) {
        if ($this->hidden) return "";
        $tdattribs = html::formatAttributes($this->tdattributes);
        $asyncTdEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->tdEvents) : "");
        $asyncEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->events) : "");
        $class = "";
        if(!isset($this->tdattributes["class"])) {
            $class = "class='hint'";
        }
        return "     <td colspan='$columns' $class title='" . html::hq($this->getHint()) . "' {$tdattribs} " . $asyncTdEvents . ">"
            . "<" . $this->type . " "
            . html::formatAttributes($this->attributes)
            . $asyncEvents
            . " name='" . $this->name . "'>"
            . $this->value 
            . "</" . $this->type . ">" 
            . "</td>"; 
    }
    
    public function dumptext() { return false; }
    
    // text areas are always valid
    function valid() {
        return true;
    }
}

class InputPageImageText extends genericInputPageItem {
    // a text and image area in the form
    // used with class InputPage, not passed between forms
    
    var $image = "";
    
    public function InputPageImageText($name, $value, $image = "") {
        $this->genericInputPageItem($name, $value);
        $this->image = $image;
		$this->position = "left";  // or "right"
		$this->altnativ = "";
		$this->hyperlink = "";
		$this->target = "_top";
        $this->hint = "";
        $this->sortType = genericInputPageItem::sortIgnore;
    }
    
    // render the field in $columns columns
    public function render($columns) {
        if ($this->hidden) return "";
        $tdattribs = html::formatAttributes($this->tdattributes);
        $asyncTdEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->tdEvents) : "");
        $asyncEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->events) : "");
        $class = "";
        if(!isset($this->tdattributes["class"])) {
            $class = "class='hint'";
        }
        $return  = "     <td colspan='$columns' $class title='" . html::hq($this->getHint()) . "' {$tdattribs} $asyncTdEvents>";
        $return .= "<div " . html::formatAttributes($this->attributes) . " name='" . $this->name . "' $asyncEvents>";
		$return .= "<table width='100%'><tr valign='top'>";
		if ($this->hyperlink)
			$field1  = "<td><a href='" . $this->hyperlink . "' target='" . $this->target . "'><img src='" . $this->image . "' alt='" . $this->altnativ . "' border='0'></a></td>";
		else
			$field1  = "<td><img src='" . $this->image . "' alt='" . $this->altnativ . "'></td>";
        $field2  = "<td>" . $this->value . "</td>";
		if ($this->position == "right") $return .= $field2 . $field1; else $return .= $field1 . $field2;
		$return .= "</tr></table>";

        $return .= "</div>" . "</td>";

        return $return;
    }
    
    public function dumptext() { return false; }
    
    // text areas are always valid
    function valid() {
        return true;
    }
}

abstract class InputPageField extends sendableGenericInputPageItem {
    // superclass for a single input field in the form, this is an html <input> plus various decoration (such as extra button, msg, prompt, etc)
    
    // hmtl form type
    var $type = "text";
    // display prompt
    var $prompt;
    // further attributes spread into <input> tag
    var $attributes = array("maxlength" => "60", "size" => "30");
    // attributes spread into $msg field
    var $msgAttributes = array("style" => "color: red");
    // html placeholder
    var $placeHolder = "";
    // html pattern
    var $pattern = "";
    /**
     * @var string the name of the ok button which should be clicked when pressing 'ENTER'
     */
    var $buttonOkName = "";
    /**
     * @var string the name of the cancel button which should be clicked when pressing 'ESCAPE'
     */
    var $buttonCancelName = "";
    
    public function InputPageField($name, $type, $prompt = "", $default = "", InputPageAction $button = null, $placeholder = "") {
        $this->sendableGenericInputPageItem($name, $default);
        $this->type = $type;
        $this->default = $default;
        $this->prompt = ($prompt === "") ? $name : $prompt;
        if ($this->prompt !== null) 
            $this->prompt = strtoupper(substr($this->prompt, 0, 1)) . substr($this->prompt, 1);
        $this->button = $button;
        $this->placeHolder = $placeholder;
    }
    
    public function render_value() {
        // renders just the value, no decoration, no input field stuff
        // may be sufficiant for many input fields (or will be overridden)
        return html::hq($this->value);
    }
    
    /**
     * Sets names of ok and cancel buttons, which shall be clicked an ENTER/ESCAPE
     * 
     * @param string $buttonOk The name of the OK button
     * @param string $buttonCancel The name of the Cancel button (optional)
     */
    public function setButtons($buttonOkName, $buttonCancelName = "") {
        $this->buttonOkName = $buttonOkName;
        $this->buttonCancelName = $buttonCancelName;
    }
    
    private function setButtonJava() {
        if(!isset($this->attributes["onKeyDown"]) && !isset($this->attributes["onkeydown"])) {
            $buttonOkName = "";
            $buttonCancelName = "";
            if($this->buttonOkName != "" || $this->buttonCancelName != "") {
                $buttonOkName = $this->buttonOkName;
                $buttonCancelName = $this->buttonCancelName;
            }
            if($this->button != null) {
                $buttonOkName = $this->button->name;
            }
            if($buttonOkName != "" || $buttonCancelName != "") {
                $this->attributes["onKeyDown"] = "javascript:return inputPageSubmitButton(event, '$buttonOkName', '$buttonCancelName');";
            }
        }
    }
    
    public function render_input() {
        // render field as a form input field
        // may be sufficiant for many input fields (or will be overridden)
        
        // get common td field attribs
        $tdattribs = html::formatAttributes($this->tdattributes);
        $asyncTdEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->tdEvents) : "");
        $asyncEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->events) : "");
        return "<td class='input' title='" . html::hq($this->getHint()) . "' {$tdattribs} $asyncTdEvents>"
            . "<input value='" . $this->render_value() . "' "
            . html::formatAttributes($this->attributes)
            . $asyncEvents
            . "name='{$this->name}' "
            . "id='{$this->name}' "
            . "type='{$this->type}' "
            . ($this->placeHolder != "" ? "placeholder=\"".html::hq($this->placeHolder)."\" " : "")
            . ($this->pattern != "" ? "pattern=\"".html::hq($this->pattern)."\" " : "")
            . ($this->emptyOk === false ? "required=\"required\" " : "")
            . "/>" .
            "</td>"; 
    }
    
    public function render_hidden() {
        // render field as a hidden, but sent form input field
        // may be sufficiant for many input fields (or will be overridden)
        unset($this->attributes["type"]);
        unset($this->attributes["maxlength"]);
        return "<input value='" . $this->render_value() . "' name='{$this->name}' type='hidden' ". html::formatAttributes($this->attributes)."/>"; 
    }
    
    final public function render($columns) {
        $ret = "";
        if ($this->hidden) {
            // if the field is hidden, just pass the fields value as a hidden type input, else render all the decoration (prompt, msg, etc.)
            $ret .= $this->render_hidden();
        } else {
            // render a field in html in $columns columns
            // see what can be rendered in which sequence
            if (!is_array($this->columnsToRender)) {
                /* if ($this->button !== null) */ $fields = array("input" => 2, "button" => 3, "msg" => 4, "prompt" => 1);
                /* else                        $fields = array("input" => 2,                "msg" => 4, "prompt" => 1); */
            } else {
                $fields = $this->columnsToRender;
            }
            // add filler if not yet there
            $fields += array("filler" => 5);
            $used = 0;	// #of columns used
            $do = array();
            foreach ($fields as $type => $prio) if (($prio > 0) && ($used++ < $columns)) $do[$prio] = $type;
            if($fields["filler"] > 0) {
                $used--; // don't count filler as used
            }
            ksort($do);
            
            // get common td field attribs
            $tdattribs = html::formatAttributes($this->tdattributes);
            $asyncTdEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->tdEvents) : "");
            // render
			foreach ($do as $type) {
                // $ret .= "<!-- $type -->";
                switch ($type) {
                    case "prompt" :
						$ret .= "     <td class='prompt' {$tdattribs} $asyncTdEvents>" . html::hq($this->prompt) . "</td>"; 
						break;
                    case "input" :
                        if (!$this->formdata) {
                            $ret .= "<td class='readonly' {$tdattribs} $asyncTdEvents>" . $this->render_value() . "</td>";
                        } else {
                            $this->setButtonJava();
                            $ret .= $this->render_input();
                        }
                        break;
                    case "button" :
                        if ($this->button !== null) $ret .= $this->button->render_input();
                        else $ret .= "<td {$tdattribs} $asyncTdEvents></td>";
                        break;
                    case "msg" :
                        $ret .= "     <td class='error' {$tdattribs} $asyncTdEvents>" . html::hq($this->msg) . "</td>";
                        break;
                    case "filler" : 
                        if ($used < $columns) $ret .= "     <td colspan='" . ( $columns - $used) . "' " . /* $tdattribs . */ " $asyncTdEvents/>";
                        break;
                }
            } 
        }
        return $ret;
    }
    
    public function dumptext() { 
        return $this->value . "";
    }
}

// a string field with password stars no further validation except it must not be empty and minimum length of 6
class InputPagePasswordField extends InputPageField {
    var $minlength = 6;
    public function __construct($name, $prompt = "", $default = "", InputPageAction $button = null) {
        parent::__construct($name, "password", $prompt, $default, $button);
        $this->sortType = genericInputPageItem::sortIgnore;
        $this->placeHolder = tl::tl("Enter your password");
    }
    public function valid() {
        if ($this->value == "" || strlen($this->value) < $this->minlength) {
            $this->msg = tl::tlx('Please enter a non-empty string here, minimum {$arg[1]} chars.', $this->minlength);
            return false;
        }
        return true;
    }
}

// a string field with password stars no further validation except it must not be empty and minimum length of 6
class InputPageRetypePasswordField extends InputPageField {
    public $firstpassword = null;
    
    public function __construct(&$firstpw, $name, $prompt = "", $default = "", InputPageAction $button = null) {
        $this->firstpassword = $firstpw;
        parent::__construct($name, "password", $prompt, $default, $button);
        $this->sortType = genericInputPageItem::sortIgnore;
    }
    public function valid() {
        if ($this->value != $this->firstpassword->value) {
            $this->msg = tl::tl("Retyped password does not match your first password.");
            return false;
        }
        return true;
    }
}

class InputPageStringField extends InputPageField {
    /**
     * a string field with no further validation except it must not be empty
     * @param string $name
     * @param string $prompt
     * @param string $default
     * @param InputPageAction $button
     * @param string $placeholder 
     */
    
    public function __construct($name, $prompt = "", $default = "", InputPageAction $button = null, $placeholder = "") {
        parent::__construct($name, "text", $prompt, $default, $button, $placeholder);
    }
        
    public function valid() {
        if ($this->value == "") {
            $this->msg = tl::tl("Please enter a non-empty string here.");
            return false;
        }
        return true;
    }
}

/**
 *  InputPageAutoCompleteStringField provides an input string field which suggests available options
 *  on writing the string by using the AutoComplete function of jquery ui.
 * 
 *  The available options can be provided inside the HTML or by an URL. The URL will be determined in 
 *  the class itself and the method getOptions will be called to retrieve needed options
 */
class InputPageAutoCompleteStringField extends InputPageStringField {
    
   /**
    *
    * @var array If options are given, the field will get these options. Leave null to use the async getOptions method
    */
    var $options = null;
    /**
     *
     * @var boolean If autoFocus is true, the first option is automatically focused  
     */
    var $autoFocus = false;
    /**
     *
     * @var int the delay to wait after a keystroke to perform the request 
     */
    var $delay = 300;
    /**
     *
     * @var int the min length to perform a request 
     */
    var $minLength = 1;
    
    /**
     *
     * @var boolean if true, the option array is used as value/label pair
     */
    var $useOptionIndexes = false;
    
    /**
     *
     * @var array of url parameters (name/value pairs), which will be added to the async called url
     */
    var $urlParameters = array();
    
    /**
     *
     * @param string $name
     * @param string $prompt
     * @param string $default
     * @param mixed $options if options is a string, the options are retrieved by the URL, the string specifies, if options is an array, the options are inside the HTML script
     * @param InputPageAction $button
     * @param type $placeholder 
     */
    public function __construct($name, $prompt = "", $default = "", $options = null, InputPageAction $button = null, $placeholder = "") {
        parent::__construct($name, $prompt, $default, $button, $placeholder);
        $this->options = $options;
        $this->attributes["id"] = $name;
    }
    
    public function render_input() {
        $ret = parent::render_input();
        $ret .= "<script>
	$(function() {";
        $source = "";
        if(is_array($this->options)) {
            $ret .= "var availableTags = ";
            $ret .= $this->jencode($this->options);
            $ret .= ";";
            $source = "availableTags";
        }
        else {
            $url = $this->getUrl();
            $source = "\"$url\"";
        }
        $autoFocus = ($this->autoFocus ? "true" : "false");
        $id = str_replace("@", "\\\\@", $this->name);
        $ret .= "$( \"#$id\" ).autocomplete({
			source: $source, autoFocus: {$autoFocus}, delay: {$this->delay}, minLength: {$this->minLength}
		});
	});
	</script>";
        return $ret;
    }
    
    public function getUrl() {
        $url = $_SERVER["SCRIPT_NAME"]."?_method=getOptionsAsync&_methodfield=".urlencode($this->name);
        foreach($this->urlParameters as $param => $value) {
            $url .= "&$param=".urlencode($value);
        }
        return $url;
    }
    
    private function jencode(&$options) {
        $ret = "[";
        foreach($options as $index => $option) {
            if($this->useOptionIndexes) {
                $ret .= "{\"label\":\"".str_replace("\"", "\\\"", $option)."\",\"value\":\"".str_replace("\"", "\\\"", $index)."\"},";
            }
            else {
                $ret .= "\"".str_replace("\"", "\\\"", $option)."\",";
            }
        }
        $ret = rtrim($ret, ",");
        $ret .= "]";
        return $ret;
    }
    
    /**
     * method is called by jquery ui on changing the input field
     */
    final function getOptionsAsync() {
        $term = InputPage::formInput("term");
        $options = $this->getOptions($term);
        $ret = $this->jencode($options);
        echo $ret;
    }
    
    /**
     *
     * override this method to get the the options array for the string field
     * @param string $term contains the currently entered string
     * @return array 
     */
    public function getOptions($term) {
        return array();
    }
}

class InputPageListStringField extends InputPageStringField {
    public function __construct($name, $prompt = "", $default = "", InputPageAction $button = null) {
        parent::__construct($name, $prompt, $default, $button);
        $this->columnsToRender = array("input" => 1, "msg" => 2);
    }
}

// a hidden string field that has no validation requirements at all
// usually used to silently put a "row-id" field into a list (InputPageListField)

class InputPageHiddenIdField extends InputPageField {
    public function __construct($name, $default = "") {
        parent::__construct($name, "text", "", $default, null);
        $this->hidden = true;
        $this->sortType = InputPageListField::sortIgnore;
    }
    public function valid() {
        return true;
    }
}

/*

 */ 

class InputPageTextField extends InputPageField {
    /**
     * a field, which shows a text with a hidden input field
     * usally used to send the text in a list (InputPageListField)
     * @param string $name
     * @param string $prompt
     * @param string $default 
     */
    public function __construct($name, $prompt = "", $default = "") {
        parent::__construct($name, "text", $prompt, $default, null);
        $this->sortType = InputPageListField::sortAlphanumericCaseIndependent;
        $this->hint = "";
        $this->columnsToRender = array("input" => 1);
    }
    public function valid() {
        return true;
    }
    
    public function render_input() {
        $asyncTdEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->tdEvents) : "");
        $asyncEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->events) : "");
        $tdattributes = $this->tdattributes; 
        if (!isset($tdattributes['class'])) $tdattributes['class'] = ''; 
        $tdattributes['class'] .= " readOnlyInput";
        return "<td title='" . html::hq($this->getHint()) . "' "
            . html::formatAttributes($tdattributes). " $asyncTdEvents>" . html::hq($this->value)
            . "<input value='" . html::hq($this->value) . "' " . html::formatAttributes($this->attributes) . " "
            . "name='{$this->name}' "
            . $asyncEvents
            . "type='hidden' "
            . "/>"
            . "</td>";
    }
}

// a calendar date field
class InputPageCalendarDateField extends InputPageField {
    var $javaOnChange = ""; // you can define a further method called with onChange on the month dropdown like: checkDate(\'teststring\', true);
    var $javaOnEnter = "";  // define a further method called onKeyDown, e.g. to submit a certain button if key enter is pressed in year field
    var $additionalRender = "";
    
    public function __construct($name, $prompt = "", $default = 0, $button = null) {
        parent::__construct($name, "text", $prompt, $default, $button);
        $this->sortType = InputPageListField::sortNone;
        $this->hint = "";
        $this->value = 0;
    }
    
    public function valid() {
        return true;
    }
    
    private function getChar($day) {
        return substr($day, 0, 1);
    }
    
    public function render_input() {
        $days = "'".$this->getChar(tl::tl("Sunday")).
                ",".$this->getChar(tl::tl("Monday")).
                ",".$this->getChar(tl::tl("Tuesday")).
                ",".$this->getChar(tl::tl("Wednesday")).
                ",".$this->getChar(tl::tl("Thursday")).
                ",".$this->getChar(tl::tl("Friday")).
                ",".$this->getChar(tl::tl("Saturday"))."'";
        $months = "'".tl::tl("January").",".
                tl::tl("February").",".
                tl::tl("March").",".
                tl::tl("April").",".
                tl::tl("May").",".
                tl::tl("June").",".
                tl::tl("July").",".
                tl::tl("August").",".
                tl::tl("September").",".
                tl::tl("October").",".
                tl::tl("November").",".
                tl::tl("December")."'";
        $noDateSelect = tl::tl("None");
        $phpYear = tl::tl("Year");
        $phpCurrentMonth = tl::tl("Show current month");
        $phpCalendar = tl::tl("Calendar");
        $required = $this->emptyOk ? "false" : "true";
        $defaultVal = "";
        if($this->value != 0) {
            $defaultVal = ", '".date("Y-m-d", $this->value)."'";
        }
        $asyncTdEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->tdEvents) : "");
        $asyncEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->events) : "");
        $ret = "<td title='" . html::hq($this->getHint()) . "' "
            . $asyncTdEvents
            . html::formatAttributes($this->tdattributes). ">"
            .       "<script>DateInput('{$this->name}', $months, $days, '$phpYear', '$phpCalendar', '$phpCurrentMonth', '$noDateSelect', '{$this->javaOnChange}', '{$this->javaOnEnter}', '{$this->additionalRender}', $required, 'YYYY-MM-DD' $defaultVal)</script>"
            .  "</td>";
        return $ret;
    }
    
    public function computeFromForm() {
        if(strtotime($this->value) !== false) {
            $this->value = strtotime($this->value);
        }
        elseif(intval($this->value) == 0) {
            $this->value = 0;
        }
    }
    
    public static function staticTranslateToForm($val) {
        return date("Y-m-d", $val);
    }
    
    public function translateToForm() {
        return $this->name."=".self::staticTranslateToForm($this->value);
    }
}

// a date field (not yet fully implemented)
class InputPageDateField extends InputPageStringField {
    var $showtime;  // bool if time should be also displayed
    
    // holds a unix times stamp as value
    function __construct($name, $prompt = "", $default = 0, InputPageAction $button = null, $showtime = false) {
        parent::__construct($name, $prompt, $default, $button);
        $this->sortType = genericInputPageItem::sortNumeric;
        $this->hint = tl::tl("Enter a date like month/day/year.");
        $this->showtime = $showtime;
        $this->placeHolder = tl::tl("e.g.")." ".self::translateDate(time());
        if($showtime) {
            $this->placeHolder .= " 11:20:34";
        }
    }
    
    function valid() {
        if(!is_int($this->value)) {
            $this->msg = $this->hint;
            return false;
        }
        else {
            return true;
        }
    }
    
    public function makeDisplayString($tick = null) {
        if ($tick === null) $d = $this->value;
        else $d = $tick;
        if ($d === 0) {
            // $dd = tl::tlx( /* date format, args are 1: year, 2: month, 3: day */ '{$arg[2]}/{$arg[3]}/{$arg[1]}', "****", "**", "**");
            $dd = "";
        } elseif (is_int($d)) {
			$dd = self::translateDate($d);
            if($this->showtime) {
                $dd = $dd.date(" H:i:s", $d);
            }
        } else {
            $dd = $d;
        }
        
        return $dd;
    }
	
    static function translateDate($d) {
            return tl::tlx( /* date format, args are 1: year, 2: month, 3: day */ '{$arg[2]}/{$arg[3]}/{$arg[1]}', date('Y', $d), date('m', $d), date('d', $d));
    }
    
    public function render_value() {
        return $this->makeDisplayString();
    }
    
    public function computeFromForm() {
        // need to parse date string (language specific!!!) 
        // first tokenize into digit strings (we do not care for the delimiters actually
        $count = preg_match_all  ( '/[0-9]+/', $this->value, $matches); 
        $spec = tl::tl(/* date format: year month day, specify order from 1 to 3, eg. 19/01/1961 => 3 2 1, 2002/31/12 => 1 3 2 */ "3 1 2" );
        preg_match('/([1-3]) ([1-3]) ([1-3])/', $spec, $specmatch);
        
        // int mktime  ([ int $Stunde  [, int $Minute  [, int $Sekunde  [, int $Monat  [, int $Tag  [, int $Jahr  [, int $is_dst  ]]]]]]] )
        $converted = 0;
        switch ($count) {
            case 3 : // day, month, year given
                $year = $matches[0][(array_search("1", $specmatch))-1];
                if ($year < 100) $year += 2000;
                $month = $matches[0][(array_search("2", $specmatch))-1];
                $day = $matches[0][(array_search("3", $specmatch))-1];
                $converted = mktime(0, 0, 0, $month, $day, $year);
                break;
            case 2 : // missing info
                break;
            case 0 : // not-set date
                $converted = 0;
        }
        
        // see if its good
        if (($converted !== 0) &&
                ((date("d", $converted) != $day) ||
                    (date("m", $converted) != $month) ||
                    (date("Y", $converted) != $year))) {
            // strange date, reject
            $this->msg = tl::tl("Please enter a valid date.");
        } else {
            $this->value = $converted;
        }
    } 
    
    public function translateToForm() {
        // use parent method 
        $saved = $this->value;
        $this->value = $this->makeDisplayString();
        $ret = parent::translateToForm();
        $this->value = $saved;
        return $ret;
    }
    
}

// a telephone field, allows digits and ()/-+
class InputPagePhoneField extends InputPageStringField {
    
    public function __construct($name, $prompt = "", $default = "", InputPageAction $button = null) {
        parent::__construct($name, $prompt, $default, $button);
        $this->setHint(tl::tl("A phone number starts with an optional '+' followed by digits with decoration such as '/()- '."));
        $this->placeHolder = tl::tl("e.g.")." +49 711 12345678";
        $this->pattern = '^\+? *?[-0-9 ]{1,3} *[-0-9/() ]+$';
    }
    
    public function valid() {
        if (!parent::valid()) return false;
        if (preg_match('%'.$this->pattern.'%', $this->value) < 1) {
            $this->msg = tl::tl("Please enter only telephone numbers here.");
            return false;
        }
        return true;
    }
}

// an IP field, allows 0.0.0.0 - 255.255.255.255 and optionally with port
class InputPageIPField extends InputPageStringField {
    var $withPort = false;
    
    public function __construct($name, $prompt = "", $default = "", InputPageAction $button = null, $withPort = false) {
        parent::__construct($name, $prompt, $default, $button);
        $this->withPort = $withPort;
        $hint = tl::tl("An IP address looks like 0.0.0.0 till 255.255.255.255");
        $placeHolder = tl::tl("e.g.")." 192.168.0.1";
        $pattern = "^(([01]?[0-9]{1,2}|2[0-4][0-9]|25[0-5])\.){3}([01]?[0-9]{1,2}|2[0-4][0-9]|25[0-5])";
        if($this->withPort) {
            $hint .= ", ".tl::tl("optional port possible, e.g. 122.122.122.122:22");
            $pattern .= "(:[0-9]{1,5}|)";
            $placeHolder .= ", 192.168.0.1:8080";
        }
        $pattern .= "$";
        $this->setHint($hint);
        $this->placeHolder = $placeHolder;
        $this->pattern = $pattern;
    }
    
    public function valid() {
        if (!parent::valid()) return false;
        if (preg_match('%'.$this->pattern.'%', $this->value) < 1) {
            $this->msg = tl::tl("Please enter only IP addresses here.");
            if($this->withPort) {
                $this->msg .= " ".tl::tl("Optional port allowed.");
            }
            return false;
        }
        return true;
    }
}

// a net mask, slight variation from IP address
class InputPageIPNetMask extends InputPageIPField {
    public function __construct($name, $prompt = "", $default = "", InputPageAction $button = null) {
        parent::__construct($name, $prompt, $default, $button);
        $this->hint = tl::tl("An IP net mask looks something like e.g. 255.255.0.0 (Class B) or  255.255.255.0 (Class C)");
        $this->placeHolder = tl::tl("e.g.")." 255.255.0.0";
    }
    public function valid() {
        if (!parent::valid()) {
            $this->msg = tl::tl("Please enter an IP net mask here.");
            return false;
        }
        return true;
    }
}

// digit string (no sign)
class InputPageDigitstringField extends InputPageStringField {
    public function __construct($name, $prompt = "", $default = "", InputPageAction $button = null) {
        parent::__construct($name, $prompt, $default, $button);
        $this->setHint(tl::tl("A digit string consists of one or more digits."));
        $this->sortType = genericInputPageItem::sortNumeric;
        $this->type = "number";
        $this->attributes["min"] = 0;
        $this->placeHolder = tl::tl("e.g.")." 12345";
        $this->pattern = "^[0-9]+$";
    }
    
    public function valid() {
        if (!parent::valid()) return false;
        if (preg_match('%'.$this->pattern.'%', $this->value) < 1) {
            $this->msg = tl::tl("Please enter a digit string here.");
            return false;
        }
        return true;
    }
}

// integer number (with optional sign)
class InputPageIntegerField extends InputPageStringField {
    public function __construct($name, $prompt = "", $default = "", InputPageAction $button = null) {
        parent::__construct($name, $prompt, $default, $button);
        $this->setHint(tl::tl("A natural number consists of an optional sign followed by one or more digits."));
        $this->sortType = genericInputPageItem::sortNumeric;
        $this->type = "number";
        $this->placeHolder = tl::tl("e.g.")." 12345";
        $this->pattern = "^[+-]{0,1}[0-9]+$";
    }
    
    public function valid() {
        if (!parent::valid()) return false;
        if (preg_match('%'.$this->pattern.'%', $this->value) < 1) {
            $this->msg = tl::tl("Please enter a natural number here.");
            return false;
        }
        return true;
    }
}


// integer number (with optional sign)
class InputPageCurrencyField extends InputPageStringField {
    var $currency = "&euro;";
    public function __construct($name, $currency = null, $prompt = "", $default = "", InputPageAction $button = null) {
        parent::__construct($name, $prompt, $default, $button);
        if ($currency !== null) $this->currency = $currency;
        $this->setHint(tl::tl("A currency value consists of an optional sign followed by one or more digits, a comma (',') and 2 digits."));
        $this->sortType = genericInputPageItem::sortNumeric;
        $this->placeHolder = tl::tl("e.g.")." 12345,67";
        $this->pattern = "^[-0-9+]*(,[0-9][0-9])?$";
    }
    
    public function valid() {
        $this->value = (string) $this->value;
        if (!parent::valid()) { return false; }
        if (preg_match('%'.$this->pattern.'%', $this->value) < 1) {
            $this->msg = tl::tl("Please enter a currency value here.");
            return false;
        }
        return true;
    }
    
    public function render_value() {
        // var_dump($this); die();
        return html::hq(sprintf("%.2f", $this->value)) . " {$this->currency}";
    }
    
}

// ZIP (germans read PLZ) field
class InputPageZIPField extends InputPageStringField {
    public function InputPageZIPField($name, $prompt = "", $default = "", InputPageAction $button = null) {
        $this->__construct($name, $prompt, $default, $button);
        $this->setHint(tl::tl("A ZIP code is an optional country selector (such as 'DE-') followed by a natural number of at least 4 digits."));
        $this->placeHolder = tl::tl("e.g.")." 71065";
        $this->pattern = "^([a-zA-Z]{2,5}-)?[0-9A-Z ]{4,99}$";
    }
    
    public function valid() {
        if (!parent::valid()) return false;
        // optional 2-5 digit/letters followed by a dash followed by at least 4 digit-letters
        if (preg_match('%'.$this->pattern.'%', $this->value) < 1) {
            $this->msg = tl::tl("Please enter a ZIP code her.");
            return false;
        }
        return true;
    }
}

class InputPageAsyncField extends genericInputPageItem {
    function InputPageAsyncField($name) {
        parent::genericInputPageItem($name, "");
    }
    
    function render($columns) {
        if ($this->hidden) return "";
        $ret = "";
        $ret .= "    <td colspan='$columns' ".html::formatAttributes($this->tdattributes)."><div ".html::formatAttributes($this->attributes)." id=\"{$this->name}\"/></td>"; 
        return $ret;
    }
    
    public function dumptext() {
        return false;
    }
    
    public function valid() {
        return true;
    }
}

abstract class InputPageAction extends InputPageField {
    
    // call even if form data is bad?
    public $anyway = false;
    
    // is action disabled for some reason?
    public $disabled = false;
    
    /**
     *
     * @var boolean causes the browser to skip the validation of "required" fields 
     */
    public $formNoValidate = false;
    
    /**
     * a submit field
     * @param string $name
     * @param string $value
     * @param string $prompt 
     */
    function __construct($name, $value, $prompt = null) {
        parent::__construct($name, "submit", $prompt, $value);
        $this->hint = "";
        $this->sortType = genericInputPageItem::sortIgnore;
        $this->isAction = true;
    }
    
    // submit buttons are always valid
    function valid() { 
        return true;
    }
    
    public function render_input() {
        if ($this->disabled) $this->attributes += array("disabled" => null);
        else unset($this->attributes["disabled"]);
        if($this->formNoValidate) $this->attributes += array("formnovalidate" => "formnovalidate");
        else unset($this->attributes["formnovalidate"]);
        return parent::render_input();
    }
    
    public function render_hidden() {
        // just dont render hidden buttons at all, as they will not be passed between pages
        return '';
    }
    
    public function translateToForm() {
        // do not pass to buttons to GET url
        return "";
    }
    
    public function dumptext() { return false; }
    
    // action to perform if clicked
    abstract function action(InputPage &$page, $phase); 
}

// a button that just shows no prompt
abstract class InputPageButtonOnlyAction extends InputPageAction {
    function __construct($name, $value, $prompt = null) {
        parent::__construct($name, $value, $prompt);
        $this->columnsToRender = array("input" => 1, "msg" => 2, "filler" => 0);
    }
}

// email field
class InputPageMailField extends InputPageField {
    
    public function __construct($name, $prompt, $default = "") {
        parent::__construct($name, "text", $prompt, $default);
        $this->setHint(tl::tl("An email is something like 'joe.user@name.tld', no extra names such as in 'Joe User <joe.user@name.tld>' please."));
        $this->type = "email";
        $this->placeHolder = tl::tl("e.g.").' joe.user@name.tld';
    }
    
    public function valid() {
        return self::checkMail($this->value, $this->msg);
    }
    
    public static function checkMail($value, &$msg) {
        $good = false;
        if ($value == "") {
            $msg = tl::tl("Please enter a valid email address here.");
        } else if (!valid_email($value)) {
            $msg = tl::tlx('\'{$arg[1]}\' doesn\'t look like a valid email address!', /* 1 */ $value);
        } else $good =  true;
        return $good;
    }
}

// email field with multiple comma separated mail addresses
class InputPageMultiMailField extends InputPageMailField {
    public function __construct($name, $prompt, $default = "") {
        parent::__construct($name, $prompt, $default);
        $this->attributes["multiple"] = "multiple";
        $this->placeHolder .= ",joe2.user@name.tld";
    }
    
    public function valid() {
        $value = $this->value;
        $mails = explode(",", $this->value);
        foreach($mails as $mail) {
            $this->value = $mail;
            if(!parent::valid()) {
                $this->value = $value;
                return false;
            }
        }
        $this->value = $value;
        return true;
    }
}

// a text area input field (long text)
class InputPageTextareaField extends InputPageField {
    
    // choices is either an array of choices names or null
    // a choice in turn is an indexed (by choice id) array of prompts (e.g. array('id' => 'bla'))
    public function __construct($name, $prompt) {
        parent::__construct($name, "textarea", $prompt);
        $this->attributes = array("cols" => "40", "rows" => "4");
        $this->sortType = genericInputPageItem::sortIgnore;
    }
    
    // TODO: render_inputs schauen, render_value einbauen wo nötig
    
    // render just the field, no decoration 
    public function render_input() {
        $ret = '';
        $asyncTdEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->tdEvents) : "");
        $asyncEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->events) : "");
        $ret .= "<td class='input' title='" . html::hq($this->getHint()) . "' $asyncTdEvents>";
        $ret .= 
            "<textarea name='{$this->name}' "
            . html::formatAttributes($this->attributes)
            . $asyncEvents
            . ($this->emptyOk === false ? "required=\"required\" " : "")
            . ($this->placeHolder != "" ? "placeholder=\"".html::hq($this->placeHolder)."\" " : "")
            . ">"
            . $this->value
            . "</textarea>";
        $ret .= "</td>"; 
        return $ret;
    }
    
    public function valid() {
        if ($this->value == "") {
            $this->msg = tl::tl("Enter your text here.");
            return false;
        }
        return true;
    }
}

// a checkbox with (possibly) multiple choices
class InputPageCheckboxField extends InputPageField {

    var $radioFirst = false; // should the radio button be printed before the prompt?
    var $choices;
    var $htmlFiller; // gives the chance to make a newline or some space between each choice

    /**
     * choices is either an array of choices names or null. 
     * a choice in turn is an indexed (by choice id) array of prompts (e.g. array('id' => 'bla'))
     * 
     * NB: checkbox fields cannot have a meaningfull default (that is, other than switched off).  This is because
     * html does not deliver un-ticked choices.  So there is no way to distinguish between an uninitialized tick and a tick that is off
     * @param string $name
     * @param string $prompt
     * @param array $choices
     * @param string $htmlFiller 
     */

    public function __construct($name, $prompt, $choices = array("on" => ""), $htmlFiller = "") {
        parent::__construct($name, "checkbox", $prompt);
        $this->isarray = true;
        $this->type = "checkbox";
        foreach ($choices as $id => $prompt) {
            $this->value[$id] = false;
        }
        $this->default = $this->value;
        $this->choices = $choices;
		$this->htmlFiller = $htmlFiller;
        $this->hint = tl::tl("Check if you agree.");
        $this->sortType = genericInputPageItem::sortIgnore;
        $this->emptyOk = true;
    }
    
    // render just the field, no decoration 
    public function render_input(array $extraAttributes = array()) {
        $attrs = $this->attributes;
        // extra attrs will override those present (so we do not merge)
        foreach ($extraAttributes as $aname => $avalue) $attrs[$aname] = $avalue;
        $asyncTdEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->tdEvents) : "");
        $asyncEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->events) : "");
        $ret = "";
        $ret .= "<td class='input' title='" . html::hq($this->getHint()) . "' ".html::formatAttributes($this->tdattributes)." $asyncTdEvents>";
		$count = 0;
        foreach ($this->choices as $id => $prompt) {
            if(!$this->radioFirst) {
                $ret .= "$prompt";
            }
            $ret .= 
                "<input value='$id' "
                . ((isset($this->value[$id]) && $this->value[$id] === true) ? "checked='checked' " : "")
                . html::formatAttributes($attrs)
                . $asyncEvents
                . "name='{$this->name}[]' "
                . "type='{$this->type}' "
                . ($this->emptyOk === false ? "required=\"required\" " : "")
                . "/>";
            if($this->radioFirst) {
                $ret .= "$prompt";
            }
			if($this->htmlFiller != "" && ++$count < count($this->choices)) {
				$ret .= $this->htmlFiller;
			}
        }
        $ret .= "</td>"; 
        return $ret;
    }
    
    
    // render value but not as a sendable form item
    public function render_value() {
        return $this->render_input(array("readonly" => "readonly", "disabled" => "disabled"));
    }
    
    // render just the field to be passed between pages hidden
    public function render_hidden() {
        $ret = "";
        if (isset($this->value)) foreach ($this->value as $id => $value) {
            if ($value) {
                $ret .= "<input value='$id' name='{$this->name}[]' type='hidden'/>"; 
            }
        }
        return $ret;
    }
    
    public function valid() {
        // for pragmatic reasons, checkboxes are usually valid
        return true;
    }
    
    public function updateFromForm() {
        $input = InputPage::formInput($this->name);
        if (is_array($input)) {
            foreach ($this->choices as $id => $prompt) {
                $this->value[$id] = false;
            }
            foreach (InputPage::formInput($this->name) as $id) {
                $this->value[$id] = true;
            }
            $this->sent = true;
        } else {
            if (is_array($this->default)) {
                $this->value = $this->default;
            }
        }
    }
    
    public function translateToForm() {
        $args = "";
        if (isset($this->value)) foreach ($this->value as $id => $value) {
            if ($args !== "") $args .= "&";
            if ($value) $args .= "{$this->name}%5B%5D={$id}";
        }
        return $args;
    }
    
    // set checked status for specific id
    public function set($id, $value = true) {
        if (isset($this->value[$id])) $this->value[$id] = $value;
    }
    
    // get checked status for specific id
    public function get($id) {
        return ($this->value[$id] === true);
    }
    
    public function dumptext() { 
        // get (list of) selected value(s) or true/false for single-choice
        $ret = "";
        $i = 0;
        foreach ($this->value as $flag => $yes) {
            if ($i++ != 0) $ret .= ", ";
            if (count($this->value) != 1) $ret .= "$flag";
            else $ret .= ($yes ? "true" : "false");
        }
        return $ret;
    }
}

// a radio field, behaves exactly like a checkbox field (of course, it doesnt make sense to have only one choice)
class InputPageRadioField extends InputPageCheckboxField {
    public function __construct($name, $prompt, $choices, $htmlFiller = "") {
        parent::__construct($name, $prompt, $choices, $htmlFiller);
        $this->type = "radio";
        $this->default = array();
    }
    
    
    // at least one choice must be checked
    public function valid() {
        foreach ($this->value as $id => $value) {
            if ($value) return true;
        }
        $this->msg = tl::tl("Please select one of the choices!");
        return false;
    }
    
}

// a drop down list
class InputPageDropdownField extends InputPageField {
    
    var $options;
    var $multiple;
    var $size = 1;
    
    // an option is an indexed (by option id) array of prompts (e.g. array('id' => 'bla'))
    public function __construct($name, $prompt, $options, $multiple = false) {
        parent::__construct($name, "", $prompt);
        $this->value = array();
        foreach ($options as $id => $prompt) {
            $this->value[$id] = false;
        }
        $this->options = $options;
        $this->multiple = $multiple;
        $this->isarray = true;
        $this->hint = tl::tlx('Select \'{$arg[1]}\' here.', $this->prompt);
    }
    
    // render just the field, no decoration 
    public function render_input() {
        $ret = "";
        if(($this->multiple && $this->size > 4) || !$this->multiple) {
            $this->attributes["size"] = $this->size;
        }
        else {
            $this->attributes["size"] = 0;
        }
        $asyncTdEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->tdEvents) : "");
        $asyncEvents = (is_object($this->rootPage) ? $this->rootPage->formatAsyncEvents($this->events) : "");
        $ret .= "<td class='input' title='" . html::hq($this->getHint()) . "' $asyncTdEvents>";
        $ret .= "<select name='{$this->name}[]' " . html::formatAttributes($this->attributes)
            .  ($this->multiple ? " multiple='multiple' " : "") 
            . $asyncEvents
            . ">"; 
        foreach ($this->options as $id => $prompt) {
            $ret .= 
                "<option value='".html::hq($id)."' "
                . ((isset($this->value[$id]) && $this->value[$id]) ? "selected='selected'" : "")
                . ">" . html::hq($prompt) . "</option>";
        }
        $ret .= "</select>";
        $ret .= "</td>"; 
        return $ret;
    }
    
    // render just the field to be passed between pages hidden
    public function render_hidden() {
        $ret = "";
        foreach ($this->value as $id => $value) {
            if ($value) {
                $ret .= "<input value='".html::hq($id)."' name='{$this->name}[]' type='hidden'/>";
            }
        }
        return $ret;
    }
    
    public function render_value() {
        $all = array();
        foreach ($this->options as $id => $prompt) {
            if ($this->value[$id] === true) $all[] = $prompt;
        }
        return implode(", ", $all);
    }
    
    public function valid() {
        // for pragmatic reasons, drop downs are usually valid if at least one is selected
        foreach ($this->value as $id => $value) {
            if ($value) return true;
        }
        if ($this->multiple)  $this->msg = tl::tl("Please select at least one of the choices!");
        else $this->msg = tl::tl("Please select one of the choices!");
        return false;
    }
    
    public function updateFromForm() {
        $this->value = array();
        if (is_array(InputPage::formInput($this->name))) foreach (InputPage::formInput($this->name) as $id) {
                $this->value[$id] = true;
                $this->sent = true;
            } else {
            if (is_array($this->default)) $this->value = $this->default;
        }
    }
    
    public function translateToForm() {
        $args = "";
        foreach ($this->value as $id => $value) {
            if ($args !== "") $args .= "&";
            if ($value) $args .= "{$this->name}%5B%5D={$id}";
        }
        return $args;
    }
    
    // set checked status for specific id
    public function set($id, $value = true) {
        if (isset($this->value[$id])) $this->value[$id] = $value;
    }
    
    // get checked status for specific id
    public function get($id) {
        return ($this->value[$id] === true);
    }
    
    public function dumptext() {
        $sel = array(); 
        foreach ($this->value as $key => $flag) if ($flag) $sel[] = $key;
        return implode(", ", $sel);
    }
}

// an array with countries to select (no multiple selection)
class InputPageCountryDropdownField extends InputPageDropdownField {
    public function __construct($name, $prompt, $default = null) {
        $options = array("" => "",
                "Afghanistan" => tl::tl("Afghanistan"),
                "Aland Islands" => tl::tl("Aland Islands"),
                "Albania" => tl::tl("Albania"),
                "Algeria" => tl::tl("Algeria"),
                "American Samoa" => tl::tl("American Samoa"),
                "Andorra" => tl::tl("Andorra"),
                "Angola" => tl::tl("Angola"),
                "Anguilla" => tl::tl("Anguilla"),
                "Antigua and Barbuda" => tl::tl("Antigua and Barbuda"),
                "Argentina" => tl::tl("Argentina"),
                "Armenia" => tl::tl("Armenia"),
                "Aruba" => tl::tl("Aruba"),
                "Australia" => tl::tl("Australia"),
                "Austria" => tl::tl("Austria"),
                "Azerbaijan" => tl::tl("Azerbaijan"),
                "Azores" => tl::tl("Azores"),
                "Bahamas" => tl::tl("Bahamas"),
                "Bahrain" => tl::tl("Bahrain"),
                "Bali" => tl::tl("Bali"),
                "Bangladesh" => tl::tl("Bangladesh"),
                "Barbados" => tl::tl("Barbados"),
                "Belarus" => tl::tl("Belarus"),
                "Belgium" => tl::tl("Belgium"),
                "Belize" => tl::tl("Belize"),
                "Benin" => tl::tl("Benin"),
                "Bermuda" => tl::tl("Bermuda"),
                "Bhutan" => tl::tl("Bhutan"),
                "Bolivia" => tl::tl("Bolivia, Plurinational State of"),
                "Bosnia and Herzegovina" => tl::tl("Bosnia and Herzegovina"),
                "Botswana" => tl::tl("Botswana"),
                "Bouvet Island" => tl::tl("Bouvet Island"),
                "Brazil" => tl::tl("Brazil"),
                "British Indian Ocean Territory" => tl::tl("British Indian Ocean Territory"),
                "Brunei" => tl::tl("Brunei Darussalam"),
                "Bulgaria" => tl::tl("Bulgaria"),
                "Burkina Faso" => tl::tl("Burkina Faso"),
                "Burundi" => tl::tl("Burundi"),
                "Cambodia" => tl::tl("Cambodia"),
                "Cameroon" => tl::tl("Cameroon"),
                "Canada" => tl::tl("Canada"),
                "Canary Islands" => tl::tl("Canary Islands"),
                "Cape Verde" => tl::tl("Cape Verde"),
                "Cayman Islands" => tl::tl("Cayman Islands"),
                "Central African Republic" => tl::tl("Central African Republic"),
                "Chad" => tl::tl("Chad"),
                "Chile" => tl::tl("Chile"),
                "China" => tl::tl("China"),
                "Christmas Island" => tl::tl("Christmas Island"),
                "Christmas Island (Australia)" => tl::tl("Christmas Island (Australia)"),
                "Cocos (Keeling) Islands" => tl::tl("Cocos (Keeling) Islands"),
                "Cocos Island" => tl::tl("Cocos Island"),
                "Colombia" => tl::tl("Colombia"),
                "Comoros" => tl::tl("Comoros"),
                "Congo, Dem. Rep." => tl::tl("Congo, the Democratic Republic of the"),
                "Congo, Republic" => tl::tl("Congo, the Republic of"),
                "Cook Islands" => tl::tl("Cook Islands"),
                "Corsica" => tl::tl("Corsica"),
                "Costa Rica" => tl::tl("Costa Rica"),
                "Cote d'Ivoire" => tl::tl("Cote d'Ivoire"),
                "Croatia" => tl::tl("Croatia"),
                "Cuba" => tl::tl("Cuba"),
                "Cyprus" => tl::tl("Cyprus"),
                "Czechia" => tl::tl("Czech Republic"),
                "Denmark" => tl::tl("Denmark"),
                "Djibouti" => tl::tl("Djibouti"),
                "Dominica" => tl::tl("Dominica"),
                "Dominican Republic" => tl::tl("Dominican Republic"),
                "Ecuador" => tl::tl("Ecuador"),
                "Egypt" => tl::tl("Egypt"),
                "El Salvador" => tl::tl("El Salvador"),
                "Equatorial Guinea" => tl::tl("Equatorial Guinea"),
                "Eritrea" => tl::tl("Eritrea"),
                "Estonia" => tl::tl("Estonia"),
                "Ethiopia" => tl::tl("Ethiopia"),
                "Falkland Islands" => tl::tl("Falkland Islands"),
                "Falkland Islands (Malvinas)" => tl::tl("Falkland Islands (Malvinas)"),
                "Faroe Islands" => tl::tl("Faroe Islands"),
                "Fiji" => tl::tl("Fiji"),
                "Finland" => tl::tl("Finland"),
                "France" => tl::tl("France"),
                "French Guiana" => tl::tl("French Guiana"),
                "French Polynesia" => tl::tl("French Polynesia"),
                "French Southern Territories" => tl::tl("French Southern Territories"),
                "Gabon" => tl::tl("Gabon"),
                "Gambia" => tl::tl("Gambia"),
                "Georgia" => tl::tl("Georgia"),
                "Germany" => tl::tl("Germany"),
                "Ghana" => tl::tl("Ghana"),
                "Gibraltar" => tl::tl("Gibraltar"),
                "Greece" => tl::tl("Greece"),
                "Greenland" => tl::tl("Greenland"),
                "Grenada" => tl::tl("Grenada"),
                "Guadeloupe" => tl::tl("Guadeloupe"),
                "Guam" => tl::tl("Guam"),
                "Guatemala" => tl::tl("Guatemala"),
                "Guinea" => tl::tl("Guinea"),
                "Guinea-Bissau" => tl::tl("Guinea-Bissau"),
                "Guyana" => tl::tl("Guyana"),
                "Haiti" => tl::tl("Haiti"),
                "Heard Island and McDonald Islands" => tl::tl("Heard Island and McDonald Islands"),
                "Vatican City" => tl::tl("Holy See (Vatican City State)"),
                "Honduras" => tl::tl("Honduras"),
                "Hong Kong" => tl::tl("Hong Kong"),
                "Hungary" => tl::tl("Hungary"),
                "Iceland" => tl::tl("Iceland"),
                "India" => tl::tl("India"),
                "Indonesia" => tl::tl("Indonesia"),
                "Iran, Islamic Republic of" => tl::tl("Iran, Islamic Republic of"),
                "Iraq" => tl::tl("Iraq"),
                "Ireland" => tl::tl("Ireland"),
                "Israel" => tl::tl("Israel"),
                "Italy" => tl::tl("Italy"),
                "Jamaica" => tl::tl("Jamaica"),
                "Japan" => tl::tl("Japan"),
                "Jordan" => tl::tl("Jordan"),
                "Kazakhstan" => tl::tl("Kazakhstan"),
                "Kenya" => tl::tl("Kenya"),
                "Kiribati" => tl::tl("Kiribati"),
                "Korea, North" => tl::tl("Korea, Republic of"),
                "Kosovo" => tl::tl("Kosovo"),
                "Kuwait" => tl::tl("Kuwait"),
                "Kyrgyzstan" => tl::tl("Kyrgyzstan"),
                "Laos" => tl::tl("Lao People's Democratic Republic"),
                "Latvia" => tl::tl("Latvia"),
                "Lebanon" => tl::tl("Lebanon"),
                "Lesotho" => tl::tl("Lesotho"),
                "Liberia" => tl::tl("Liberia"),
                "Libya" => tl::tl("Libyan Arab Jamahiriya"),
                "Liechtenstein" => tl::tl("Liechtenstein"),
                "Lithuania" => tl::tl("Lithuania"),
                "Luxembourg" => tl::tl("Luxembourg"),
                "Macao" => tl::tl("Macao"),
                "Macedonia" => tl::tl("Macedonia, the former Yugoslav Republic of"),
                "Madagascar" => tl::tl("Madagascar"),
                "Madeira" => tl::tl("Madeira"),
                "Malawi" => tl::tl("Malawi"),
                "Malaysia" => tl::tl("Malaysia"),
                "Maldives" => tl::tl("Maldives"),
                "Mali" => tl::tl("Mali"),
                "Malta" => tl::tl("Malta"),
                "Marshall Islands" => tl::tl("Marshall Islands"),
                "Martinique" => tl::tl("Martinique"),
                "Mauritania" => tl::tl("Mauritania"),
                "Mauritius" => tl::tl("Mauritius"),
                "Mayotte" => tl::tl("Mayotte"),
                "Mexico" => tl::tl("Mexico"),
                "Micronesia" => tl::tl("Micronesia"),
                "Micronesia, Federated States of" => tl::tl("Micronesia, Federated States of"),
                "Moldova, Republic of" => tl::tl("Moldova, Republic of"),
                "Monaco" => tl::tl("Monaco"),
                "Mongolia" => tl::tl("Mongolia"),
                "Montenegro" => tl::tl("Montenegro"),
                "Montserrat" => tl::tl("Montserrat"),
                "Morocco" => tl::tl("Morocco"),
                "Mozambique" => tl::tl("Mozambique"),
                "Myanmar" => tl::tl("Myanmar"),
                "Namibia" => tl::tl("Namibia"),
                "Nauru" => tl::tl("Nauru"),
                "Nepal" => tl::tl("Nepal"),
                "Netherlands" => tl::tl("Netherlands"),
                "Netherlands Antilles" => tl::tl("Netherlands Antilles"),
                "New Caledonia" => tl::tl("New Caledonia"),
                "New Zealand" => tl::tl("New Zealand"),
                "Nicaragua" => tl::tl("Nicaragua"),
                "Niger" => tl::tl("Niger"),
                "Nigeria" => tl::tl("Nigeria"),
                "Niue" => tl::tl("Niue"),
                "Norfolk Island" => tl::tl("Norfolk Island"),
                "Northern Mariana Islands" => tl::tl("Northern Mariana Islands"),
                "Norway" => tl::tl("Norway"),
                "Oman" => tl::tl("Oman"),
                "Pakistan" => tl::tl("Pakistan"),
                "Palau" => tl::tl("Palau"),
                "Palestinian Territory, Occupied" => tl::tl("Palestinian Territory, Occupied"),
                "Panama" => tl::tl("Panama"),
                "Papua New Guinea" => tl::tl("Papua New Guinea"),
                "Paraguay" => tl::tl("Paraguay"),
                "Peru" => tl::tl("Peru"),
                "Philippines" => tl::tl("Philippines"),
                "Pitcairn" => tl::tl("Pitcairn"),
                "Poland" => tl::tl("Poland"),
                "Portugal" => tl::tl("Portugal"),
                "Puerto Rico" => tl::tl("Puerto Rico"),
                "Qatar" => tl::tl("Qatar"),
                "Reunion" => tl::tl("Reunion"),
                "Romania" => tl::tl("Romania"),
                "Russia" => tl::tl("Russian Federation"),
                "Rwanda" => tl::tl("Rwanda"),
                "Saint Barthélemy" => tl::tl("Saint Barthélemy"),
                "Saint Helena, Ascension and Tristan da Cunha" => tl::tl("Saint Helena, Ascension and Tristan da Cunha"),
                "Saint Kitts and Nevis" => tl::tl("Saint Kitts and Nevis"),
                "Saint Lucia" => tl::tl("Saint Lucia"),
                "Saint Martin (French part)" => tl::tl("Saint Martin (French part)"),
                "Saint Pierre and Miquelon" => tl::tl("Saint Pierre and Miquelon"),
                "Saint Vincent and the Grenadines" => tl::tl("Saint Vincent and the Grenadines"),
                "Samoa" => tl::tl("Samoa"),
                "San Marino" => tl::tl("San Marino"),
                "Sao Tome and Principe" => tl::tl("Sao Tome and Principe"),
                "Saudi Arabia" => tl::tl("Saudi Arabia"),
                "Senegal" => tl::tl("Senegal"),
                "Serbia" => tl::tl("Serbia"),
                "Seychelles" => tl::tl("Seychelles"),
                "Sierra Leone" => tl::tl("Sierra Leone"),
                "Singapore" => tl::tl("Singapore"),
                "Slovakia" => tl::tl("Slovakia"),
                "Slovenia" => tl::tl("Slovenia"),
                "Solomon Islands" => tl::tl("Solomon Islands"),
                "Somalia" => tl::tl("Somalia"),
                "South Africa" => tl::tl("South Africa"),
                "South Georgia and the South Sandwich Islands" => tl::tl("South Georgia and the South Sandwich Islands"),
                "Korea, South" => tl::tl("South Korea"),
                "Spain" => tl::tl("Spain"),
                "Sri Lanka" => tl::tl("Sri Lanka"),
                "Sudan" => tl::tl("Sudan"),
                "Suriname" => tl::tl("Suriname"),
                "Svalbard and Jan Mayen" => tl::tl("Svalbard and Jan Mayen"),
                "Swaziland" => tl::tl("Swaziland"),
                "Sweden" => tl::tl("Sweden"),
                "Switzerland" => tl::tl("Switzerland"),
                "Syrian Arab Republic" => tl::tl("Syrian Arab Republic"),
                "Taiwan" => tl::tl("Taiwan"),
                "Tajikistan" => tl::tl("Tajikistan"),
                "Tanzania, United Republic of" => tl::tl("Tanzania, United Republic of"),
                "Thailand" => tl::tl("Thailand"),
                "Timor-Leste" => tl::tl("Timor-Leste"),
                "Togo" => tl::tl("Togo"),
                "Tokelau" => tl::tl("Tokelau"),
                "Tonga" => tl::tl("Tonga"),
                "Trinidad and Tobago" => tl::tl("Trinidad and Tobago"),
                "Tunisia" => tl::tl("Tunisia"),
                "Turkey" => tl::tl("Turkey"),
                "Turkmenistan" => tl::tl("Turkmenistan"),
                "Turks and Caicos Islands" => tl::tl("Turks and Caicos Islands"),
                "Tuvalu" => tl::tl("Tuvalu"),
                "Uganda" => tl::tl("Uganda"),
                "UK" => tl::tl("UK"),
                "Ukraine" => tl::tl("Ukraine"),
                "United Arab Emirates" => tl::tl("United Arab Emirates"),
                "United States" => tl::tl("United States"),
                "United States Minor Outlying Islands" => tl::tl("United States Minor Outlying Islands"),
                "Uruguay" => tl::tl("Uruguay"),
                "Uzbekistan" => tl::tl("Uzbekistan"),
                "Vanuatu" => tl::tl("Vanuatu"),
                "Venezuela, Bolivarian Republic of" => tl::tl("Venezuela, Bolivarian Republic of"),
                "Vietnam" => tl::tl("Vietnam"),
                "Virgin Islands, British" => tl::tl("Virgin Islands, British"),
                "Virgin Islands, U.S." => tl::tl("Virgin Islands, U.S."),
                "Wallis and Futuna" => tl::tl("Wallis and Futuna"),
                "Western Sahara" => tl::tl("Western Sahara"),
                "Yemen" => tl::tl("Yemen"),
                "Zambia" => tl::tl("Zambia"),
                "Zimbabwe" => tl::tl("Zimbabwe")
            );
        asort($options);
        parent::__construct($name, $prompt, $options, false);
        if($default != null) {
            if(!isset($this->options[$default])) {
                $default = array_search($default, $this->options);
                if($default === false) $default = null;
            }
            if($default != null) {
                $this->default[$default] = true;
            }
        }
    }
    
    public function valid() {
        if (!parent::valid()) return false;
        if((isset($this->value[""]) && $this->value[""] === true)) {
            $this->msg = tl::tl("Select a valid country");
            return false;
        }
        return true;
    }
}

// a link field 
class InputPageLinkField extends InputPageText {
    var $url;
    
    // constructor
    public function __construct($name, $url, $text = null) {
        parent::__construct($name, "", "a");
        $this->setLink($url, $text);
    }
    
    // you should not modify $this->value, use setLink instead
    public function setLink($url, $text = null) {
        $this->url = $url;
        if (is_null($text)) {
            $tmp = parse_url($url);
            if (!isset($tmp["host"]) || $tmp["host"] == "") {
                // no host, probably relative path
                $text = $tmp["path"];
            } else {
                $text = $tmp["host"] . "/" . $tmp["path"];
            }
        }
        $this->value = $text;
        $this->attributes["href"] = $this->url;
    }
}


// an action class that is triggered with a link instead of a button
abstract class InputPageLinkAction extends InputPageLinkField {
    
    // this is an action
    var $default;  // this actually is a partially inherit from sendableGenericInputPageItem....
    
    // constructor
    public function __construct($name, $linktext) {
        $this->isAction = true;
        $this->formdata = true;
        $this->anyway = false;
        parent::__construct($name, "", $linktext);
    }
    
    function linkedToPage() { 
        $this->rootPage->scriptcodes["Code@InputPageLinkAction"] = "function InputPageLinkActionDo(form, name) { var f; f = document.forms[form];  f.action = '?' + name + '=do'; f.submit(); }";
    }
    
    // no multiple inheritance in php, so we must implement what we'd like to inherit from sendableGenericInputPageItem manually
    public function updateFromForm() {
        if (InputPage::formInput($this->name) !== null) {
            $this->sent = true;
        } else {
            $this->sent = false;
        }
    }
    
    public function setLink($url, $text = null) {
        parent::setLink($url, $text);
        $this->default = $this->value;
    }
    
    // the action itself
    abstract function action(InputPage &$page, $phase);
    
    public function render($columns) {
        $form = $this->rootPage->name;
        $myname = $this->name;
        $url = "javascript:InputPageLinkActionDo('$form', '$myname');";
        $this->setLink($url, $this->value);
        return parent::render($columns);
    }
}


// checkbox that must be checked, optionof $ifnot is shown if not
class InputPageMustTickCheckboxField extends InputPageCheckboxField {
    var $ifnot;
    
    public function __construct($name, $prompt, $ifnot = null) {
        if ($ifnot == null) $ifnot = tl::tl("You need to confirm this.");
        $this->ifnot = $ifnot;
        parent::__construct($name, $prompt);
        $this->emptyOk = false;
    }
    
    public function valid() {
        if (!$this->get("on")) {
            $this->tagMsg($this->ifnot);
            return false;
        }
        return true;
    }
}

// checkbox that must be checked if checkbox $ref is $condition (default true)
class InputPageMustTickIfCheckboxField extends InputPageMustTickCheckboxField {
    var $page;
    var $ifnot;
    var $ref;
    var $condition;
    public function __construct($name, $prompt, InputPageCheckboxField &$ref, $condition = true, $ifnot = null) {
        parent::__construct($name, $prompt, $ifnot);
        $this->ref = &$ref;
        $this->condition = $condition;
    }
    public function valid() {
        if ($this->ref->get("on") === $this->condition) return parent::valid();
        return true;
    }
}

class _fileUploadFieldData {
    // helper class to save file upload data
    var $fieldname;
    var $name;
    var $path;
    var $error;
    var $size;
    var $ok;
    public function reset() {
        $this->name = null; 
        $this->path = null;
        $this->error = 0;
        $this->size = -1;
        $this->ok = false;
    }        
    public function __construct(InputPageFileUploadField $field) {
        $this->fieldname = $field->name;
        $this->reset();
    }
    public function uploaded() {
        $this->reset();
        if (isset($_FILES[$this->fieldname]) &&
                ($_FILES[$this->fieldname]['error'] == 0)  &&
                (stat($_FILES[$this->fieldname]['tmp_name']) !== false)) {
            $this->path = $_FILES[$this->fieldname]['tmp_name'];
            $this->name = $_FILES[$this->fieldname]['name'];
            $this->type = $_FILES[$this->fieldname]['type'];
            $this->size = $_FILES[$this->fieldname]['size'];
            $this->error = $_FILES[$this->fieldname]['error'];
            $this->ok = true;
            return true;
        }
        return false;
    }
};

// file upload
class InputPageFileUploadField extends InputPageField {
    static $formInited = false;
    /**
     *
     * @var _fileUploadFieldData file upload status
     */
    var $status;
    function __construct($name, $prompt = "", InputPageAction $button = null) {
        
        parent::__construct($name, "file", $prompt, null, $button);
        if (InputPage::$method != "post") {
            die('You must set InputPage::$method to "post" for InputPageFileUploadField to work!');
        }
        $this->status = new _fileUploadFieldData($this);
    }
    
    function linkedToPage() { 
        // force proper encryption type to upload files
        $this->rootPage->attributes += array("enctype" => "multipart/form-data");
    }
    
    public function updateFromForm() {
        if ($this->status->uploaded()) {
            $this->sent = true;
            $this->value = $this->status->path;
        } else {
            $this->sent = false;
        }
    }

    
    function valid() {
        // this field is only valid if there is a file uploaded (as per $FILES)
        if ($this->status->uploaded()) {
            return true;
        } 
        $this->msg = tl::tl("Missing upload file");
        return false;
    }
}



// helper class for field section attributes
class _InputPageFieldSectionAttributes {
    var $columns;
    var $columnsPerField;
    var $fieldSet;
    var $legend;
    var $attributes = array();
    var $javaHide = false;
    var $divEnclosed = false;
    /**
     *
     * @var boolean if set, section will be rendered in default renderFieldSections
     */
    var $render = true;  
        
    public function __construct(array $attributes = array(), $columns = 1, $columnsPerField = 4, $fieldSet = true, $legend = "", $javaHide = false, $divEnclosed = false, $render = true) {
        $this->attributes = $attributes;
        $this->columns = $columns;
        $this->columnsPerField = $columnsPerField;
        $this->fieldSet = $fieldSet;
        $this->legend = $legend;
        $this->javaHide = $javaHide;
        $this->divEnclosed = $divEnclosed;
        $this->render = $render;
    }
};


// here we finally have the page rendering machine itself

abstract class InputPage extends HTMLDoc {
    
    // an input page
    /* 
    holds sub sections for parent sections (parent section is index of this array, which holds an array with sub section names)
    sub sections are normally only used for tab/edit sections whilst the tab/edit section is the parent section
    with sub sections, one is able to align field rows independent of each other in a tab/edit sections
    */
    var $subSections;
    
    // the name of it, used  as html form name too
    var $name = "unknown";
    
    // the heading
    var $title = "unknown title";
    var $formAction = null;
    
    // number of logical columns (fields per line)
    var $columns = 1;
    // number of real table columns <td/> per field
    // expected layout: <prompt><input><button><msg>
    // not used anymore, see _InputPageFieldSectionAttributes instead
    // var $columnsPerField = 4;
    
    /**
     * all the fields in the form
     * @var genericInputPageItem[]
     */
    var $fields = array();
    var $nfields = 0;
    var $magicFieldsDone = false;
    var $gotFields = false;
    
    // instance pseudo fields
    var $phase = null;
    var $page = null;
    
    // one time called pseudo vars (called by javascript)
    var $asyncMethod = null;        // method name, which is executed
    var $asyncMethodField = null;   // field object name, on which the asyncMethod will be called
    
    // form submit method
    static $method = "get";
    
    /**
     *
     * @var boolean if set, jquery and its plugins are added to the head section
     */
    var $useJquery = true;
    
    // static pseudo fields
    static function phase() {
        return InputPage::formInput('_phase');
    }
    static function page() {
        return InputPage::formInput('_page');
    }
    static function asyncMethod() {
        return InputPage::formInput('_method');
    }
    static function asyncMethodField() {
        return InputPage::formInput('_methodfield');
    }
    
    // HTML attributes used per row in form
    var $rowAttributes = array();
    
    // rendering attributes for the field sections 
    // this is an array of _InputPageFieldSectionAttributes indexed by section name (as per $field->style)
    var $fieldSectionAttributes = array();
    
    var $sectionLeftTdAttributes = array();
    var $sectionInlineTdAttributes = array();
    var $sectionRightTdAttributes = array();
	
    // access form input submitted
    public static function formInput($subscript = null) {
        if ((self::$method == "get") || (self::$method == "GET")) {
            $input = $_GET + $_POST;
        } else {
            $input = $_POST + $_GET;
        }
        if (is_null($subscript)) return $input;
        return isset($input[$subscript]) ? $input[$subscript] : null;
    }
    
    public static function formClear($subscript) {
        if (isset($_GET[$subscript])) unset($_GET[$subscript]);
        if (isset($_POST[$subscript])) unset($_POST[$subscript]);
    }
    
    public function addSubSection($subSection, $parentSection) {
        if(!isset($this->subSections[$parentSection][$parentSection])) {    // parent section is its own first sub section
            $this->subSections[$parentSection][$parentSection] = true;
        }
        $this->subSections[$parentSection][$subSection] = true;
    }
    
    // method checks wether a section is a sub section, the first sub section or the last sub section of a parent section
    public function isSubSection($section, &$parent, $first = false, $last = false) {
        foreach($this->subSections as $parent => $sections) {
            $count = 0;
            foreach($sections as $sec => $val) {
                $count++;
                if($sec == $section && ($first || (!$first && !$last) || ($last && $count == count($sections)))) {
                    return true;
                }
                if($first) {
                    return false;
                }
            }
        }
        return false;
    }
    
    public function formatAsyncEvents($events) {
        $ret = "";
        foreach($events as /* @var $event AsyncEvent */$event) {
            $ret .= "{$event->type}=\"";
            $ret .= $event->javascript;
            $ret .= "\" ";
        }
        return $ret;
    }
    
    public function getFieldValueArray($fields) {
        $valueArray = array();
        if(!count($fields)) return $valueArray;
        foreach($fields as $fieldName) {
            $field = $this->fields[$fieldName];
            if(!isset($field)) continue;
            if(isset($field->columns)) {    // $field is an InputPageListField
                foreach($field->value as $row => $values) {
                    foreach($values as $colName => $colValue) {
                        $valueArray["$colName@$row"] = $colValue;
                    }
                }
            }
            else {
                $valueArray[$field->name] = $field->value;
            }
        }
        return $valueArray;
    }
    
    public function addField(genericInputPageItem $field) {
        if (!is_null($field)) {
            // set the backlinks to parent field, parent page and root page
            $field->parentPage = $this;
            for ($walker = $field; isset($walker->parentField); $walker = $walker->parentField) { }
            $field->rootPage = $walker->parentPage;
            if ($field->rootPage !== null) $field->linkedToPage();
        }
        return $this->fields[$field->name] = $field;
    }
    
    /**
     * get a field from page by name
     * @param string$id
     * @return genericInputPageItem 
     */
    public function getField($id) {
        return isset($this->fields[$id]) ? $this->fields[$id] : null;
    }
    
    public function InputPage() {
        parent::__construct();
        $this->phase = $this->phase();
        $this->page = $this->page();
        $this->asyncMethod = $this->asyncMethod();
        $this->asyncMethodField = $this->asyncMethodField();
        $this->fieldSectionAttributes = array(
                "left" => new _InputPageFieldSectionAttributes(array(), 1, 4, false),
                "right" => new _InputPageFieldSectionAttributes(array(), 1, 4, false),
                "top" => new _InputPageFieldSectionAttributes(array(), 1, 4, false),
                "bottom" => new _InputPageFieldSectionAttributes(array(), 1, 4, false),
                "inline" => new _InputPageFieldSectionAttributes(array(), 1, 4, true)
                );
        $this->scriptfiles += array("java_calendar" => "/classes/calendarDateInput.js");
        if($this->useJquery) {
            $this->scriptfiles += array("jquery" => "/classes/jquery-1.7.2.min.js");
            $this->scriptfiles += array("jqueryui" => "/classes/jquery-ui-1.8.21.custom.min.js");
            $this->scriptfiles += array("list" => "/classes/list.js");
            $this->links[] = array("rel" => "stylesheet", "type" => "text/css", "href" => "/classes/jquery-ui-1.8.21.custom.css");
        }
        $hideSectionCode = "
            function hideSection(hideElement, id) {
                var e = document.getElementById(id); 
                if(e.style.visibility == 'collapse' || e.style.visibility == '') {
                    e.style.visibility = 'visible';
                    hideElement.innerHTML = '-';
                }
                else {
                    e.style.visibility = 'collapse';
                    hideElement.innerHTML = '+';
                }
            }";
        $this->scriptcodes += array("hide_section" => $hideSectionCode);
        $submitButtonCode = "function inputPageSubmitButton(event, okButtonName, cancelButtonName) {
            var e = event || window.event;
            var btnName = \"\";
            switch(e.keyCode) {
                case 13:    // enter
                    btnName = okButtonName;    
                    break;
                case 27:    // escape
                    btnName = cancelButtonName;
                    break;
            }
            if(typeof(btnName) != \"undefined\" && btnName != \"\") {
                var btn = document.getElementsByName(btnName);
                btn[0].click();
                return false;
            }
            return true;
        }";
        $this->scriptcodes += array("submit_button" => $submitButtonCode);
        $this->subSections = array();
        
        // strange php notice: you must set default timezone explicitly
        // so we get it, ignoring any warnings and then set it as defaulted (or perviously set explicitly)
        $tz = @date_default_timezone_get();
        date_default_timezone_set($tz);

    }
    
    public function setFieldSectionAttributes($name, _InputPageFieldSectionAttributes $settings) {
        $target = isset($this->fieldSectionAttributes[$name]) ? $this->fieldSectionAttributes[$name] : null;
        if (is_null($target)) {
            return $this->fieldSectionAttributes[$name] = $settings;
        } else {
            $target->columns = $settings->columns;
            $target->columnsPerField = $settings->columnsPerField;
            html::mergeAttributes($target->attributes, $settings->attributes);
        }
        return $target;
    }
    
    // the page is built from these building blocks
    // head
    // body
    //     prolog
    //     form
    //         prefix
    //         field-enclosure
    //             field-section
    //                 field
    //                 field....
    //             field-section
    //                 field
    //                 field ...
    //         suffix
    //     epilog
    //
    // by default, field-enclosure is a <form>, field-section is a <table> with 4 columns per field.  
    // prolog/epilog are outside the form, prefix/suffix are inside the form
    
    // implementation of the HTMLDoc abstract functions
    
    // we don't have no inline CSS styles
    public function style() { return ''; }
    
    // the HTML generator
    public function body() {
        $ret = "";
        $ret .= $this->renderProlog();
        $ret .= $this->renderFieldEnclosure();
        $ret .= $this->renderEpilog();
        return $ret;
    }
    
    // the fieldEnclosure (by default a form)
    public function renderFieldEnclosure() {
        // surrounding form
        $action = $_SERVER['PHP_SELF'];
        if($this->formAction != null) {
            $action = $this->formAction;
        }
        $ret = "";
        $ret .= "<form action='$action' method='" . self::$method . "' name='$this->name' id='$this->name' " . html::formatAttributes($this->attributes) . ">";
        $ret .= $this->renderPrefix(); 
        $ret .= $this->renderFields();
        $ret .= $this->renderSuffix();
        $ret .= "</form>";
        return $ret;
    }
    
    // these functions make up the beef and must be overridden
    abstract public function renderProlog();
    abstract public function renderEpilog();
    abstract public function renderPrefix();
    abstract public function renderSuffix();
    
    // render a field section
    // this is by default a table with 4 columns per field and $this->columns logical fields per line (this $this->columns * 4 html columns per line)
    // this cannot be changed easily, as field rendering algorithms assume that those 4 columns have to be rendered.  However, you can change this 
    // behaviour by setting the fields $columnsToRender member.
    // the field section names "top", "bottom", "left", "right" are handled specially
    //
    // $start is true if the section begin is to be rendered, the end otherwise
    // $seq is the sequential number of the section (starting with 1)
    // $name is the section name found in the field ($fld->style)
    // the function must return the html code for the section start/end and may change the number of logical fields per line in this section (thru $columns)
    
    public function isEmptySection($name) {
        foreach($this->fields as $field) {
            if($field->style == $name && !$field->hidden && $field->autorender) {
                /*if($name == "inline")
                    var_dump($field);*/
                return false;
            }
        }
        return true;
    }
    
    public function renderFieldSection(&$columns, $start, $seq, $name) {
        // care for list field rows, which must not create their own section start/end
        $ret = "";
        $parent = "";
        if ($name == "listFieldRow") return "";
        // an empty section has no fields or just hidden fields
        if($this->isEmptySection($name)) {
            $tag = $start ? "start" : "close";
            return "\n\n<!-- $tag empty/hidden section {$name} -->\n";
        }
        if($start) {
            // print first table stuff, if section is no sub section or section is the first sub section (parent section is always first sub section)
            $cellSp = "";
            $ret .= "\n\n<!-- start section {$name} -->\n";
            if($this->fieldSectionAttributes[$name]->fieldSet === true) {
                $fieldsetClass = "main";
                $legend = "";
                if($this->fieldSectionAttributes[$name]->legend != "") {
                    $legend = "<legend>{$this->fieldSectionAttributes[$name]->legend}</legend>";
                    $fieldsetClass = "main-edit";
                }
                $ret .= "<fieldset class=\"$fieldsetClass\">$legend";
            }
            elseif($this->fieldSectionAttributes[$name]->javaHide === true) {
                $ret .= "<div class=\"hide-section\" onClick=\"javascript:hideSection(this, '$name');\">+</div><b>{$this->fieldSectionAttributes[$name]->legend}</b><div class=\"hidden-section\" id=\"$name\">";
            }
            elseif($this->fieldSectionAttributes[$name]->divEnclosed) {
                $ret .= "<div id=\"$name\">";
            }
            $ret .= "\n\n<table " . html::formatAttributes($this->fieldSectionAttributes[$name]->attributes) . ">";
            /*if(isset($this->editProperties->sections[$name])) {
                // table content in an own row
                $ret .= "<tr>";
                $ret .= "  <td>";
                $ret .= "    <table>";
            }*/
            if($this->isSubSection($name, $parent, true)) {
                $ret .= "\n\n<!-- start with first sub section {$name} -->\n";
                $ret .= "      <tr>";
                $ret .= "        <td>";
                $ret .= "          <table>";
            }
            return $ret;
        }
        else {
            $orgName = $name;
            if($this->isSubSection($name, $parent, false, true)) {  // this is a last sub section
                $ret .= "          </table>";
                $ret .= "        </td>";
                $ret .= "      </tr>";
                $ret .= "\n<!-- closed last sub section {$name} -->\n";
                $name = $parent; 
            }
            /*if(isset($this->editProperties->sections[$name])) {
                $ret .= "    </table>";
                $ret .= "  </td>";
                $ret .= "</tr>";
            }*/
            $ret .= "</table>";
            if($this->fieldSectionAttributes[$name]->fieldSet === true && ($this->isSubSection($orgName, $parent, false, true) || !$this->isSubSection($name, $parent))) {
                $ret .= "</fieldset>";
            }
            else if($this->fieldSectionAttributes[$name]->javaHide === true) {
                $ret .= "</div>";
            }
            else if($this->fieldSectionAttributes[$name]->divEnclosed === true) {
                $ret .= "</div>";
            }
            $ret .= "\n<!-- closed section $name -->\n";
            return $ret;
        }
    }
    
    // render all field sections in the page 
    // this will create the appropriate number of field sections
    // fields will be rendered in a table as follows
    
    //    +-------------------------------------------------------------------+
    //    | +---------------------------------------------------------------+ |
    //    | |                           top                                 | |
    //    | |                                                               | |
    //    | +---------------------------------------------------------------+ |
    //    | +-------------+ +------------------------------+ +--------------+ |
    //    | |             | |         userdef 1            | |              | |
    //    | |             | |           ...                | |              | |
    //    | |    left     | +------------------------------+ |  right       | |
    //    | |             | +------------------------------+ |              | |
    //    | |             | |         userdef n            | |              | |
    //    | +-------------+ +------------------------------+ +--------------+ |
    //    | +---------------------------------------------------------------+ |
    //    | |                          bottom                               | |
    //    | |                                                               | |
    //    | +---------------------------------------------------------------+ |
    //    +-------------------------------------------------------------------+
    
    public function renderFieldSections(array $otherFieldsToRender, array $bottomFieldsToRender, array $topFieldsToRender, array $rightFieldsToRender, array $leftFieldsToRender) {
        // this might be overriden in (probably) rare cases
        $ret = "";
        $verticalFields = ((count($leftFieldsToRender) + count($rightFieldsToRender)) > 0);
        $outerTableColumns = $verticalFields ? 3 : 1;
        $tblattrs = $this->setFieldSectionAttributes("outerSpace", new _InputPageFieldSectionAttributes(array("align" => "center")));
        
        $ret .= "<table " . html::formatAttributes($tblattrs->attributes) . ">";
        if (count($topFieldsToRender) > 0) {
            $ret .= "<tr><td colspan='{$outerTableColumns}'>".$this->renderFieldRows($topFieldsToRender)."</td></tr>";
        }
        $ret .= "<tr>";
		if ($verticalFields) /* we need 3 columns, else we only need one */ $ret .= "<td ".html::formatAttributes($this->sectionLeftTdAttributes).">";
        if (count($leftFieldsToRender) > 0) $ret .= $this->renderFieldRows($leftFieldsToRender);
        if ($verticalFields) $ret .= "</td>";
        $ret .= "<td ".html::formatAttributes($this->sectionInlineTdAttributes).">";
        if (count($otherFieldsToRender) > 0) $ret .= $this->renderFieldRows($otherFieldsToRender, -1);
        $ret .= "</td>";
		if ($verticalFields) $ret .= "<td ".html::formatAttributes($this->sectionRightTdAttributes).">";
        if (count($rightFieldsToRender) > 0) $ret .= $this->renderFieldRows($rightFieldsToRender);
        if ($verticalFields) $ret .= "</td>";
        $ret .= "</tr>";
        if (count($bottomFieldsToRender) > 0) {
            $ret .= "<tr><td colspan='{$outerTableColumns}'>".$this->renderFieldRows($bottomFieldsToRender)."</td></tr>";
        }
        $ret .= "</table>";
        
        return $ret;
    }
    
    public final function renderFields() {
        // this shall not be overridden
        $ret = "";
        // determine fields to display
        $leftFieldsToRender = array();
        $rightFieldsToRender = array();
        $topFieldsToRender = array();
        $bottomFieldsToRender = array();
        $otherFieldsToRender = $bottomFieldsToRender = $topFieldsToRender = $rightFieldsToRender = $leftFieldsToRender = array();
        foreach ($this->fields as $fld) {
            // skip non-render fields TODO: do we need to render hidden non-autorender fields??
            if (!$fld->autorender) continue;
            // sort field into proper field section
            switch ($fld->style) {
                case "left" :   $leftFieldsToRender[] = $fld; break;
                case "right" :  $rightFieldsToRender[] = $fld; break;
                case "top" :    $topFieldsToRender[] = $fld; break;
                case "bottom" : $bottomFieldsToRender[] = $fld; break;
                default :       
                    if(!isset($this->fieldSectionAttributes[$fld->style]) || (isset($this->fieldSectionAttributes[$fld->style]) && $this->fieldSectionAttributes[$fld->style]->render)) {
                        $otherFieldsToRender[] = $fld; 
                    }
                    break;
            }
        }
        
        $ret .= $this->renderFieldSections($otherFieldsToRender, $bottomFieldsToRender, $topFieldsToRender, $rightFieldsToRender, $leftFieldsToRender);
        
        // add magic fields
        if ($this->phase != null) $ret .= "<input type='hidden' name='_phase' value='{$this->phase}'/>";
        if ($this->page != null) $ret .= "<input type='hidden' name='_page' value='{$this->page}'/>";
        return $ret;
    }
    
    // render fields in a section
    public function renderFieldRows(array &$fields, $lineNumber = -1) {
        $currentSection = "";
        $currentSectionNumber = 0; 
        
        $ret = "";
        $total = 0;
        $styleAttrs = null;
		$lastFieldRendered = false;
		$noRowClose = false;
        $spanfields = 1;
        // all fields in list (may be in different section names)
        foreach ($fields as &$field) {
            // do we need to open a new section?
            if ($currentSection != $field->style) {
                if ($currentSection != "") {
					if($lastFieldRendered) {
						$ret .= "</tr>";
					}
                    $ret .= $this->renderFieldSection($styleAttrs->columns, false, $currentSectionNumber, $currentSection);
                }
                // new section, retrieve appropriate rendering parameters
                $currentSection = $field->style;
                if (!isset($this->fieldSectionAttributes[$currentSection])) {
                    $this->setFieldSectionAttributes($currentSection, new _InputPageFieldSectionAttributes);
                }
                $styleAttrs = $this->fieldSectionAttributes[$currentSection];
                $ret .= $this->renderFieldSection($styleAttrs->columns, true, ++$currentSectionNumber, $currentSection);
				$noRowClose = true;
                $total = 0;
            }
            // do not account for hidden fields!
            if (!$field->hidden) {
                if ($field->onnewline) {
                    // field must be on new line, fill remaining columns with blanks
                    $b = new InputPageBlank();
                    while (($total % $styleAttrs->columns) != 0) {
                        $ret .= $b->render($styleAttrs->columnsPerField);
                        $total++;
                    }
                }
                // start new line?
                if (($total % $styleAttrs->columns) == 0) {
					if(!$noRowClose) {
						if ($total != 0) $ret .= "</tr>"; // end old line
					}
					else {
						$noRowClose = false;
					}
					$ret .= "<tr " . html::formatAttributes($this->rowAttributes) . ">";			// start new line
                }
                if ($field->fullline) {
                    $spanfields = $styleAttrs->columns - ($total % $styleAttrs->columns);
                } else {
                    $spanfields = 1;
                }
                $total += $spanfields;
				$lastFieldRendered = true;
            }
			else {
				$lastFieldRendered = false;
			}
            // show field
            {
                $realname = $field->name;
                $realid = null;
                if(isset($field->attributes["id"])) $realid = $field->attributes["id"];
                $field->realName = $realname;
                $reallinenumber = $field->lineNumber;
                if ($field->button != null) $realbuttonname = $field->button->name;
                // support for InputPageListFields
                if ($lineNumber > -1) {
                    $field->name .= "@$lineNumber";
                    if(isset($field->attributes["id"])) {
                        $field->attributes["id"] .= "@$lineNumber";
                    }
                    if ($field->button != null) $field->button->name .= "@$lineNumber";
                    // announce linenumber to field (just in case it is interested)
                    $field->lineNumber = $lineNumber;
                }
                $ret .= $field->render($spanfields * $styleAttrs->columnsPerField);
                $field->name = $realname;
                if ($field->button != null) $field->button->name = $realbuttonname;
                $field->lineNumber = $reallinenumber;
                if(isset($field->attributes["id"])) {
                    $field->attributes["id"] = $realid;
                }
            }
        }
		if ($currentSection != "") {
			if($lastFieldRendered) {
				$ret .= "</tr>";
			}
            $ret .= $this->renderFieldSection($styleAttrs->columns, false, $currentSectionNumber, $currentSection);
        }
        return $ret;
    }
    
    // update all fields from form data
    public function updateFields() {
        // scan $_GET for field values
        foreach ($this->fields as $key => &$field) {
            // update field content from GET args
            if (!$field->formdata) continue;
            $field->getFieldValueFromForm();
            if ($field->cutTrailingWhiteSpace) {
                // remove trailing white space if field type is string
                if (is_array($field->value)) {
                    foreach ($field->value as $index => &$value)
                        if (is_string($value)) $value = rtrim($value);
                } else if (is_string($field->value)) {
                        $field->value = rtrim($field->value);
                    }
            }
            $msgkey = $field->name . "__msg";
            if (array_key_exists($msgkey, InputPage::formInput())) {
                $field->msg = InputPage::formInput($msgkey);
                $field->msgTagged = true;
            } else {
                $field->msgTagged = false;
            }
        }
        $this->gotFields = true;
    }
    
    // default form validator
    private function valid() {
        return true;
    }
    
    function validate($lineNumber = -1, $validatehidden = false) {
        // validate form, first validate all fields, then validate complete form
        $good = true;
        foreach ($this->fields as $key => &$field) {
            // validate only shown fields, as hidden fields can't be fixed by definition!
            $reallinenumber = $field->lineNumber;
            $field->lineNumber = $lineNumber;
            if (($validatehidden || !$field->hidden) &&		// we must validate hidden fields or its not hidden
                    (!$field->emptyOk  || ($field->value != "")) && // empty values are not ok or it is not empty
                    (($field->msg != "") || !$field->valid()))	{		// validation failed
                InputPage::debug("InputPage::validate: valid(line=$lineNumber, name={$field->name}, value=" . print_r($field->value, true) . ") failed");
                $good = false;
            }
            $this->lineNumber = $reallinenumber;
        }
        // TODO form itself must be evaluated from button code itself? if (!$this->valid()) $good = false;
        InputPage::debug("InputPage::validate: form validation yields " . ($good ? "true" : "false"));
        return $good;
    }
    
    // this entry will render the page
    public function render() {
        //        function setJavaScriptInit($code) {
        //            if ($this->rootPage == null) die("setJavaScriptInit required to be linked to page");
        //            $this->rootPage->scriptcodes["JavaScriptInit_" . $this->name] = $code;
        //        }
        // see if we need to generate java script init code
        $initcode = "";
        foreach ($this->fields as $name => $field) {
            $fn = "JavaScriptInit_" . $field->name;
            $namesuffix = "";
            if($field->isarray) $namesuffix = "[]";
            if (isset($this->scriptcodes[$fn])) {
                $initcode .= "    $fn(document.getElementsByName('{$field->name}$namesuffix')[0]);\n";
            }
        }
        if ($initcode != "") {
            $fn = "JavaScriptInit_" . $this->name;
            if(!isset($this->attributes["onload"])) $this->attributes["onload"] = "";
            $initcode .= $this->attributes["onload"] . ";";
            $this->attributes["onload"] .= "; $fn(document.getElementsByName('{$this->name}')[0]);";
            $this->scriptcodes[$fn] = "function $fn(form) {\n$initcode\n}";
        }
        print $this->generateHTML();
    }
    
    public function reload($nextpage = null, $nextphase = null, array $moreargs = array()) {
        // reloads the page again, setting page to $nextpage and phase to $nextphase, preserving all form data
        if ($nextphase === null) $nextphase = $this->phase;
        if ($nextphase !== "") $moreargs += array("_phase" => $nextphase);
        if ($nextpage === null) $nextpage = $this->page;
        if ($nextpage !== "") $moreargs += array("_page" => $nextpage);
        $url = $_SERVER['PHP_SELF'] . "?junk=junk";
        foreach ($this->fields as $key => $field) {
            if ($field->formdata) {
                $tmp = $field->translateToForm();
                if ($tmp != "") $url .= "&" . $tmp;
                InputPage::debug("InputPage::reload: translateToForm(line={$field->lineNumber}, name={$field->name}, value=" . print_r($field->value, true) . ") yields '$tmp'");
                $tmp = $field->translateMsgToForm();
                if ($tmp != "") $url .= "&" . $tmp;
            }
        }
        foreach ($moreargs as $name => $value) {
            $url .= "&" . $name . "=" . html::hq($value);
        }
        // print "<pre> url: $url";
        $this->warp($url);
    }
    
    public function warp($url) {
        // loads a new page
        header("Location: $url");
    }
    
    public function work() {
        // do the work according to phase
        // first update field contents from form data (if not yet done)
        if (!$this->gotFields) $this->updateFields();
        
        // do what has to be done
        $show = true;
        
        if (($this->phase != "") &&
                ($this->phase != "show")) {
            // validate and do action if ok
            $good = $this->validate();
            // call action
            $called = false;
            foreach ($this->fields as $key => &$field) {
                if ($field->sent && ($field->isAction === true)) {
                    if ($good || $field->anyway) {
                        $field->action($this, $this->phase);
                        // form is not shown if action was called
                        // this is to allow actions to use header redirects
                        $show = false;
                        break;
                    }
                } else {
                    if ($field->formdata && $field->callActions($this, $this->phase, $good)) {
                        $show = false;
                        break;
                    }
                }
            }
        }
        if($this->asyncMethod != "" && $this->asyncMethodField != "") {
            $field = $this->fields[$this->asyncMethodField];
            if(is_object($field)) {
                $field->{$this->asyncMethod}();
                $show = false;
            }
        }
        
        // show page
        if ($show) {
            $this->phase = "validate";
            $this->render();
            $this->flushDebugBuffer();
        }
    }
    
    public function flushDebugBuffer() {
        self::debug(" -- fields --");
        foreach ($this->fields as $n => $v) {
            self::debug("  $n: " . print_r($v->value, true));
        }
        if (self::$debugbuf != null) {
            print "<p><div style='font-family: courier'>" . self::$debugbuf . "</div>";
        }
    }    
    
    static $debugbuf = null;
    static $debugswitch = false;
    static function debug($msg) {
        if (self::$debugswitch) self::$debugbuf .= "<br>" . htmlspecialchars($msg);
    }
}

/* 
 * InputPageListField implements lists as a field type for the inputpage class
 *
 * idea:
 * you would define an InputPageListField object which displays all the lines.  Each line consists of 
 * standard fields again (derived somehow from abstract class genericInputPageItem).
 * The InputPageListField field is not editable in fact, however, its columns may be.
 */

// this field type can be used as a list

// helper class, a row in the InputPageListField
class _InputPageListRow extends InputPage {
    var $lineNumber = -1; 
    function __construct() { 
        parent::__construct();
        $this->setFieldSectionAttributes("listFieldRow", new _InputPageFieldSectionAttributes(array(), 999)); 
    }
    function renderProlog() {}
    function renderEpilog() {}
    function renderPrefix() {}
    function renderSuffix() {}
    function generateHTML() { return $this->renderFieldRows($this->fields, $this->lineNumber); }
}

// helper class, a column header in the InputPageListField
class _InputPageListColumnHead extends InputPageText {
    var $colfield;
    var $listfield;
    function __construct($name, $value, &$colfield, &$listfield) {
        parent::__construct($name, $value);
        $this->colfield = &$colfield;
        $this->listfield = &$listfield;
        $this->attributes = array();
        $this->tdattributes = array("nowrap" => "nowrap");
    }
    
    public function render($columns) {
        $saved = $this->value;
        if (($this->listfield->sortDirection != InputPageListField::sortNone) &&
                ($this->colfield->sortType !== genericInputPageItem::sortIgnore)) {
			$this->attributes["class"] = $this->colfield->sortPrio == -1 ? $this->listfield->colSortHeadClass : $this->listfield->colHeadClass;     
            $this->attributes["title"] = html::hq($this->colfield->sortPrio == -1 ? tl::tl("Click column header to reverse sort order.") : tl::tl("Click column header to sort."));
            $this->value = 
                "<a " . 
                html::formatAttributes($this->attributes) .
                "href='javascript:document.{$this->listfield->parentPage->name}._{$this->listfield->name}_sorter.value=\"{$this->colfield->name}\"; document.{$this->listfield->parentPage->name}.submit()'>" .
                html::hq($saved) . 
                "</a>";
            
        }
        else {
            $this->attributes["class"] = $this->listfield->colNoLinkHeadClass;
        }
        $ret = InputPageText::render($columns);
        $this->value = $saved;
        return $ret;
    }
}

// helper class, a column header in the InputPageListField
class _InputPageListHeadNavigator extends InputPageText {
    var $colfield;
    var $listfield;
    // sequential index of this column head
    var $index;
    
    function __construct($name, &$listfield) {
        parent::__construct($name, "");
        $this->listfield = &$listfield;
        $this->attributes = array("class" => "listpagenavigator");
        $this->tdattributes = array("nowrap" => "nowrap");
        $this->index = 0;
    }
    
    function reset() {
        $this->index = 0;
    }
    
    public function render($columns) {
        $saved = $this->value;
        $npagesFraction = count($this->listfield->value) / $this->listfield->repeatHeader;
        $npages = intval($npagesFraction);
        if ($npages < $npagesFraction) $npages++;
        $pn = "_{$this->listfield->name}_page";
        $pntop = $pn . "0";
        $pnme = $pn . $this->index;
        $pnup = $pn . ($this->index - 1);
        $pndown = $pn . ($this->index + 1);
        $pnbottom = $pn . ($npages - 1);
        $v = "";
        /*html::debug(array("count" => count($this->listfield->value),
                    "repeat" => $this->listfield->repeatHeader,
                    "npagesFraction" => $npagesFraction,
                    "npages" => $npages));*/
        if ($npages < 2) return "";
        if ($this->index > 0) {
            $v .= "<a title='" .
                tl::tl("Go to top") . 
                "' href='#{$pntop}'><img border=0 src='/images/page-top.png'/></a>";
            $v .= "<a title='" .
                tl::tl("Page up") . 
                "' href='#{$pnup}'><img border=0 src='/images/page-up.png'/></a>";
        } else {
            $v .= "<a><img border=0 src='/images/page-null.png'/></a>";
            $v .= "<a><img border=0 src='/images/page-null.png'/></a>";
        }
        if ($this->index < ($npages - 1)) {
            $v .= "<a title='" .
                tl::tl("Page down") .
                "' href='#{$pndown}'><img border=0 src='/images/page-down.png'/></a>";
            $v .= "<a title='" . 
                tl::tl("Go to bottom") . 
                "' href='#{$pnbottom}'><img border=0 src='/images/page-bottom.png'/></a>";
        } else {
            $v .= "<a><img border=0 src='/images/page-null.png'/></a>";
            $v .= "<a><img border=0 src='/images/page-null.png'/></a>";
        }
        $v .= "<a name='{$pnme}'></a>";
        $this->value = $v;
        $ret = parent::render($columns);
        $this->value = $saved;
        $this->index++;
        return $ret;
    }
}

class InputPageScrollingListField extends InputPageListField {
    public function __construct($name, $rows = array()) {
        parent::__construct($name, $rows);
        $this->listclass = "scrollinglistfield";
        $this->repeatHeader = 999999;
    }
}

class InputPageAsyncListField extends InputPageListField {
    public function __construct($name, $rows = array(), InputPageAsyncField $detailsfield = null, $script = null) {
        parent::__construct($name, $rows);
        $this->listclass = "asynclistfield";
        $this->sortDirection = self::sortNone;              // no sorting with asynclist fields
        $this->useAsync = true;
        $this->asyncDetailsField = $detailsfield;

        $this->repeatHeader = 999999;
        $this->idfield = null;
        $this->trattributes['onclick'] = 'userActionList(event, this, selectItemList); return false;';
    }
}

class InputPageListField extends sendableGenericInputPageItem {
    // the list is sendable as we have the notion of a "current" item, which is sent
    // all entries must be in $value, which is in this case an array of arrays:
    //            array(
    //                /* row0 */ array("field1" => "value for field1 in row0", "field2" => "value for field2 in row0"),
    //                /* row1 */ array("field1" => "value for field1 in row1", "field2" => "value for field2 in row1"),
    //                );
    // columns is a (pseudo) page that holds the column fields
    /*private*/ var $columns = null;
    // column heads
    private $columnHeads = null;
    var $colNoLinkHeadClass = "";
	var $colHeadClass = "columnhead";
	var $colSortHeadClass = "sortcolumnhead";
    // attributes
    // odd/even to print odd/even lines in different style
    var $oddAttributes = array("class" => "oddlist listrow");   
    var $evenAttributes = array("class" => "evenlist listrow");
    // another style would be to set class to listfield-tr-separator, which makes a border at the bottom of each tr
    /*var $oddAttributes = array("class" => "listfield-tr-separator");   
    var $evenAttributes = array("class" => "listfield-tr-separator");*/
    var $headAttributes = array("class" => "headlist");
    var $activeAttributes = array("class" => "activelistitem");
    // further attributes spread into the <tr> elements for a table
    var $trattributes = array();
    var $divattributes = array(); /* can be used e.g. to set ID for individual scrolling status */
    // draw this line in another color if set, use activeAttributes class in css to define style
    var $activeLine = null;
	// #of lines after which to repeat the column header
    var $repeatHeader = 99999;
    // do we want a column header although there are no rows?
    var $hasColumnHeaderOnEmptyTable = true;
    // do we want to have a field that is shown instead of an empty table?
    var $fieldOnEmptyTable = null;
    // sort direction
    const sortAscending = 1, sortDescending = 2, sortNone = 0;
    // row field which identifies a row as a description, not a data, row, which is separatly css formated
    const descRow = "listfield_desc_row";
    var $sortDirection = self::sortNone;
    // internal column counter
    private $colno;
    // cumulated result from column validates
    private $good = true;
    // column fields sorted accordig their sort prio and user override
    var $sortedFields = array();
    // nsorting priority for next column
    private $nextSortPrio = 1;
    // do we have column heads?
    private $hasColumnHeader = false;
    // did we add the row navigator?
    private $hasRowNavigator = null;
    // listfield class name 
    protected $listclass = "listfield";
    // ignores the mandatory head row if row count is >= 10 and hasColumnHeads is false
    var $ignoreHead = false;
    // ignores the list bottom navigator link
    var $ignoreBottomNavigatorLink = false;
    
    /**
     *
     * @var string The class of the dummy row of an async list 
     */
    var $hiddenRowClass = "list-hidden-row";
    /**
     *  @var boolean if list is used asynchronously, the rows are filled by ajax, not by $value
     */
    var $useAsync = false;
    /**
     * @var InputPageHiddenIdField field that holds the "id" attribte
     */
    var $asyncIdField = null;
    /**
     * @var InputPageAsyncField
     */
    var $asyncDetailsField = null;
    /**
     * @var string name of script used by ajax to fill this field
     */
    var $asyncDetailsScript = null;
    
    /**
     * contains function arguments, which are used for the bindListEvents method in list.js
     *
     * @var array 
     *
     */    
    var $jsArguments = array();
    /**
     *  an identifier used in the value[X][] array for a row identifier 
     */
    const hiddenRowColumn = "listfield_hidden_row";
    const hiddenRowDetailsForm = "listfield_hidden_details";
    /**
     *
     * @var boolean if set, rows can be removed/added via javascript
     * a column to remove and a column to add is automatically added
     * and the whole row (independent of its columns) is added/removed
     */
    var $removableRows = false;
    
    // constructor
    public function InputPageListField($name, $rows = array(), $removableRows = false) {
        parent::__construct($name, null);
        $this->default = $rows;
        $this->columns = new _InputPageListRow();
        $this->columnHeads = new _InputPageListRow();
        $this->attributes = array("class" => $this->listclass);
        $this->fullline = true;
        $this->removableRows = $removableRows;
    }
    
    public function getListRemoveLink($row) {
        return "<a style=\"cursor:pointer\" class=\"add-line\" title=\"".tl::tl("Remove this line")."\" name=\"{$this->name}_removelink@$row\" onClick=\"javascript:listRemoveRow('{$this->name}', this.name)\">&#150;</a>";
    }
    
    public function getListAddLink() {
        $listFirstFieldName = "";
        $array = "";
        foreach($this->columns->fields as $field) {
            $listFirstFieldName = $field->name;
            if(is_array($field->value)) {
                $array = "[]";
            }
            break;
        }
        return "<a style=\"cursor:pointer\" class=\"add-line\" title=\"".tl::tl("Add new line")."\" onClick=\"javascript:listAddRow('$this->name', '$listFirstFieldName@0$array')\">+</a>";
    }
    
    // to add a field to the columns structure
    public function addColumn($field, $header = null) {
        if ($header !== null) {
            $headfield = $this->columnHeads->addField(new _InputPageListColumnHead("__colhead_{$this->name}_" . ++$this->colno, $header, $field, $this));
            $this->columnHeads->rowAttributes += $this->headAttributes;
            $this->hasColumnHeader = true;
        } else {
            $headfield = $this->columnHeads->addField(new InputPageBlank);
        }
        $headfield->style = $field->style = "listFieldRow";
        $headfield->hidden = $field->hidden;
        $field->sortPrio = $this->nextSortPrio++;
        $field->parentField = $this;
        return $this->columns->addField($field);
    }
    
    private function compareSortPrio($left, $right) {
        $l = $left->sortPrio + 0;
        $r = $right->sortPrio + 0;
        if ($l < $r) {
            return -1;
        }
        if ($l > $r) {
            return 1;
        }
        return 0;
    }
    
    private function compareRow($left, $right) {
        // left and right are rows
        $ret = 0;
        foreach ($this->sortedFields as $index => $column) {
            // $column is a field actually
            if ($column->sortType == genericInputPageItem::sortIgnore) continue;
            $cmp = $column->compare($left[$column->name], $right[$column->name]);
            if ($cmp < 0) { $ret = -1; break; }
            if ($cmp > 0) { $ret = 1; break; };
        }
        return $ret;
    }
    
    private function compareRowAndFlip($left, $right) {
        $ret = $this->compareRow($left, $right);
        if ($this->sortDirection == self::sortDescending) $ret *= -1;
        return $ret;
    }  
    
    protected function sortRows(&$sorter) {
        $rows = $this->value; // sort in a deep copy
        if ($this->sortDirection != self::sortNone) {
            // see if there is a user defined sort prio override
            $i = "_{$this->name}_sorter";
            $sorter = InputPage::formInput($i);
            $i = "_{$this->name}_currentsorter";
            $currentsorter = InputPage::formInput($i);
            $i = "_{$this->name}_currentdir";
            $currentdir = InputPage::formInput($i);
            if ($sorter != "") {
                $tosortby = $this->columns->getField($sorter);
                if (is_object($tosortby)) {
                    if ($sorter == $currentsorter) {
                        // reverse sorting direction
                        if ($currentdir == self::sortAscending) {
                            $this->sortDirection = self::sortDescending;
                        } else {
                            $this->sortDirection = self::sortAscending;
                        }
                    } 
                    // push other column on top
                    $tosortby->sortPrio = -1;
                }
            }
            // sort column fields according sort prio (in a deep copy)
            $this->sortedFields = $this->columns->fields;
            uasort($this->sortedFields, array($this, "compareSortPrio"));
            foreach ($this->sortedFields as $fname => $ffield) 
                if ($ffield->sortType != genericInputPageItem::sortIgnore) { 
                    $sorter = $fname; break; 
                }
            uasort($rows, array($this, "compareRowAndFlip"));
        }
        return $rows;
    }
    
    public function render($columns) {
        // override to render this field (which is actually a table)
        if ($this->hidden) return "";
        
        if($this->removableRows) {
            $remove = $this->addColumn(new InputPageText($this->name."_listremove", null), null /*$addLink*/);
            $remove->sortType = genericInputPageItem::sortIgnore;
            $add = $this->addColumn(new InputPageText($this->name."_listadd", null), null /*$addLink*/);
            $add->sortType = genericInputPageItem::sortIgnore;
        }
        
        // see if we need to add the row navigator
        if ($this->hasColumnHeader) {
            if (!is_object($this->hasRowNavigator)) {
                $this->hasRowNavigator = $this->columnHeads->addField(new _InputPageListHeadNavigator("_" . $this->name . "_cnav", $this));
                $this->hasRowNavigator->style = "listFieldRow";
            }
            $this->hasRowNavigator->reset();
        }
        
        
        $lino = 0;
        $ret = "";
        // this is the time to sort the rows for display
        // PHP does a deep copy, so $rows can be changed with no harm
        $rows = $this->sortRows($sorter);
        $visibleColumns = 0;
        foreach($this->columns->fields as $field) {
            if(!$field->hidden) {
                $visibleColumns++;
            }
        }
        if ($this->useAsync) {
            if ($this->asyncIdField) $this->divattributes["data-keyfield"] = $this->asyncIdField->name;
            if ($this->asyncDetailsField) $this->divattributes["data-detailsfield"] = $this->asyncDetailsField->name;
        }
        $visibleColumns *= $columns;
        $ret .= "<td colspan='$columns' " . html::formatAttributes($this->tdattributes + array("class" => $this->listclass)) . ">";
        $ret .= "<input type='hidden' value='' name='_{$this->name}_sorter'></input>";
		$ret .= "<input type='hidden' value='{$sorter}' name='_{$this->name}_currentsorter'></input>";
		$ret .= "<input type='hidden' value='{$this->sortDirection}' name='_{$this->name}_currentdir'></input>";
        $ret .= "<div " . html::formatAttributes($this->divattributes + array("class" => $this->listclass, "id" => $this->name)) . "><table " . html::formatAttributes($this->attributes) . ">";
        // always at least one column header
        if (!$this->hasColumnHeader && count($rows) >= 10 && !$this->ignoreHead) {        
            $ret .= "<tr><td class='listtopbottomnavigator' colspan='$visibleColumns'><a name='_{$this->name}_top' href='#_{$this->name}_bottom'>" . tl::tl("Go to bottom") . "</a></td></tr>";
        }
        if (!$this->ignoreHead && (($this->hasColumnHeader &&
                    $this->hasColumnHeaderOnEmptyTable && 
                    (count($rows) == 0)) ||
                (count($rows) > 0))) {
            $ret .= "<thead>" . $this->columnHeads->generateHTML() . "</thead>";
        }
        
        $ret .= "<tbody>";
        if (count($rows) == 0) {
            if (is_object($this->fieldOnEmptyTable)) {
                $ret .= "<tr>" . $this->fieldOnEmptyTable->render($visibleColumns) . "</tr>";
            }
        } else {
            foreach($rows as $index => $row) {
                if($this->removableRows) {
                    $row[$this->name."_listremove"] = $this->getListRemoveLink($lino);
                    $row[$this->name."_listadd"] = $this->getListAddLink();
                }
                // for each row in listfield values
                if ($this->hasColumnHeader && (($lino % $this->repeatHeader) == 0)) {
                    if ($lino > 0) {
                        $ret .= $this->columnHeads->generateHTML();
                    }
                }
                $lino++;
                if($this->useAsync) {
                    // just render dummy column if used async
                    $ret .= $this->renderDummyRow($lino - 1, $row[self::hiddenRowColumn], $this->hiddenRowClass);
                    //$ret .= "<tr id=\"".$this->name.($lino - 1)."\" class='list-hidden-row' name=\"{$row[self::hiddenRowColumn]}\"><td colspan='$visibleColumns'><div>&nbsp;</div></td></tr>";
                    continue;
                }
                
                if(isset($row[self::descRow])) {
                    $ret .= "<tr><td class='list-description-row' colspan='$visibleColumns'>".$row[self::descRow]."</td></tr>";
                    continue;
                }
                
                $ret .= $this->renderRow($row, $index, $lino);
            }
            if (!$this->ignoreBottomNavigatorLink && !$this->hasColumnHeader && (count($rows) >= 10) && !$this->useAsync) {
                $ret .= "<tr><td class='listtopbottomnavigator' colspan='$visibleColumns'><a href='#_{$this->name}_top' name='_{$this->name}_bottom'>" . tl::tl("Go to top") . "</a></td></tr>";
            }
        }
        $ret .= "</tbody></table></div><input value='" . count($rows) . "' name='_{$this->name}_count' type='hidden'/></td>";
        if($this->useAsync) {
            $argStr = "'".implode("','", $this->jsArguments)."'";
            $ret .= "
                <script type='text/javascript'>
                    bindListEvents('{$this->name}', $lino, '".$_SERVER["SCRIPT_NAME"]."', '{$this->hiddenRowClass}', $argStr);
                </script>";
        }
        return $ret;
    }
    
    function renderRow($row, $index, $lino) {
        $ret = "";
        $savedattrs = $this->columns->rowAttributes;
        // initialize field values with defaults
        foreach ($this->columns->fields as $fname => $field) {
            if ($field->formdata) $field->value = $field->default;
        }

        // set the field values, from listfield value
        foreach ($row as $name => $value) {
            $field = $this->columns->getField($name);
            if (is_object($field)) {
                $field->origvalue = $field->value;
                $field->value = $value;
                if(isset($row[$name."_options"]))  {
                    $field->options = $row[$name."_options"];
                }
                if(isset($row[$name."_hint"])) {
                    $field->hint = $row[$name."_hint"];
                }
                if(isset($row[$name."_readonly"])) {
                    $field->attributes["readonly"] = "readonly";
                }
                if(isset($row[$name."_max"])) {
                    $field->attributes["max"] = $row[$name."_max"];
                }
                $field->msg = "";
            }
        }
        // validate field values
        if ($this->sent && !$this->columns->validate($index)) $this->good = false;
        // valid() overrides may have changed values, so put back to $this->value
        foreach ($this->columns->fields as $fieldname => $field) {
            $this->value[$index][$field->name] = $field->value;
        }
        if($index === $this->activeLine) {
            $this->columns->rowAttributes += $this->activeAttributes;
        }
        else {
            $this->columns->rowAttributes += (($lino & 1) ? $this->oddAttributes : $this->evenAttributes);
        }
        $this->columns->rowAttributes += $this->trattributes;
        if($this->useAsync) {
            // legacy ...
            $this->columns->rowAttributes["id"] = $this->name.$lino;
            $this->columns->rowAttributes["name"] = isset($row[self::hiddenRowColumn]) ? $row[self::hiddenRowColumn] : "";    // name attribute of tr is the value of the hidden magic hiddenRowColumn
            // ... end legacy
            if (isset($row[self::hiddenRowColumn]))
                $this->columns->rowAttributes["data-key"] = $row[self::hiddenRowColumn];
            $this->columns->rowAttributes["data-linenumber"] = $lino;
            if (isset($row[self::hiddenRowDetailsForm]))
                $this->columns->rowAttributes["data-detailsform"] = $row[self::hiddenRowDetailsForm];
        }
        $this->columns->lineNumber = $index;
        $ret = $this->columns->generateHTML();
        $this->columns->rowAttributes = $savedattrs;
        return $ret;
    }
    
    function renderDummyRow($lino, $hiddenRowColumnVal, $class) {
        return "<tr id=\"".$this->name.$lino."\" class='$class' name=\"$hiddenRowColumnVal\" 'data-linenumber'='$lino'><td><div>&nbsp;</div></td></tr>";
    }
    
    /**
     * method is called by list.js on scrolling 
     */
    function getRowsAsync() {
        $ids = InputPage::formInput("ids");
        if(!is_array($ids) || count($ids) === 0) return;
        $firstRow = intval(InputPage::formInput("firstrow"));
        $rows = $this->getRows($ids);   // call overridden getRows method
        // remap returned rows to requested ids to hide not returned ids and to maintain the initial sort order
        foreach($ids as $index => $id) {
            foreach($rows as $row)  {
                if($row[self::hiddenRowColumn] == $id) {
                    $ids[$index] = $row;
                    break;
                }
            }
        }
        foreach($ids as $index => $row) {
            if(!is_array($row)) {
                echo $this->renderDummyRow($firstRow + $index, $row, "list-invisible-row");
            }
            else {
                echo $this->renderRow($row, $firstRow + $index, $firstRow + $index);
            }
        }
    }
    
    /**
     *
     * if list is used async, override this method to get the values for an array of ids
     * do not worry if a requested id does not exist anymore, the method getRowsAsync renders a dummy invisible row here!
     * @param array $ids
     * @return type 
     */
    function getRows($ids) {
        return array();
    }
    
    // dump an item in viewable text form (e.g. to print into an email)
    function dumptext() { return false; }
    
    // must validate column fields for all rows and return true if good
    function valid() { 
        if (!$this->sent) return true;
        $this->good = true;
        $line = -1;
        foreach($this->value as $index => $row) {
            $line++;
            foreach ($row as $name => $value) {
                $field = $this->columns->getField($name);
                if (is_object($field)) {
                    $field->value = $value;
                }
            }
            if (!$this->columns->validate($line)) $this->good = false;
        }
        return $this->good;
    }
    
    // this will return an indexed (by integer) array with all field values for a specific column in all rows
    private function getColumnValues($field, &$ret, $nlines) {
        $realname = $field->name;
        $realvalue = $field->value;
        $realsent = $field->sent;
        $prefix = $field->name . "@";
            
            // for each line, scan field settings
            for ($index = 0; $index < $nlines; $index++) {
                $field->name = $prefix . $index;
                if (!($field instanceof sendableGenericInputPageItem)) continue;
                $field->getFieldValueFromForm();
                InputPage::debug("InputPageListField::getColumnValues: retrieved (index=$index, line={$field->lineNumber}, name={$field->name}, value=" . print_r($field->value, true) . ")");
                if ($field->sent) {
                    $ret[$index][$realname] = $field->value;
                    $realsent = true;
                }
                $field->value = $realvalue;
            }
            $field->name = $realname;
            $field->sent = $realsent;
        }
        
        // get form data, collects columns array field values
        function updateFromForm() {
            $this->value = array();
            if(!$this->useAsync) {  // do not update an async list from form, as only X of all rows are submitted on submit and not all rows at once
                // we need to call each fields updateFromForm() member!
                $nlines = InputPage::formInput("_" . $this->name . "_count") + 0;
                foreach ($this->columns->fields as $name => $field) {
                    if ($field->formdata) $this->getColumnValues($field, $this->value, $nlines);
                }
            }
            if (!count($this->value) == 0) {
                ksort($this->value, SORT_NUMERIC);
                $this->sent = true;
            } else {
                $this->value = $this->default;
                $this->sent = false;
            }
        }
        
        function translateToForm() {
            // die("InputPageListField cannot be reloaded");
            // this fields value will not be inherited (as it would kill GET args)
        }
        
        public function callActions(InputPage $page, $phase, $good) {
            foreach ($this->columns->fields as $name => $field) {
                // html::debug(array("field $name " . time() => "sent {$field->sent} isAction {$field->isAction} good $good anyway {$field->anyway}"));
                if (($field->isAction === true) && $field->sent) {
                    if ($good || $field->anyway) {
                        $realnumber = $field->lineNumber;
                        foreach ($this->value as $line => $columns) {
                            if (isset($columns[$field->name])) {
                                $field->lineNumber = $line;
                            }
                        }
                        $field->action($page, $phase);
                        // html::debug(array("field $name action on line " => $field->lineNumber));
                        $field->lineNumber = $realnumber;
                        return true;
                    }
                }
            }
            return false;
        }
        
        public function getGood() {
            return $this->good;
        }
        
        function linkedToPage() { 
            // here we need to call the linkedToPage members of our columns fields (as only now they have a valid rootPage!)
            // this means that if an InputPageListField has its own linkedToPage member it MUST call the parents one from within
            foreach ($this->columns->fields as $field) {
                for ($walker = $field; isset($walker->parentField); $walker = $walker->parentField) { }
                $field->rootPage = $walker->parentPage;
                if ($field->rootPage !== null) $field->linkedToPage();
            }
        }
        
    }

?>
