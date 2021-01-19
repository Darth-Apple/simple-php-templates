<?php 

// LICENSE: LGNU GPL, Version 3. https://www.gnu.org/licenses/lgpl-3.0.en.html

class template_engine {
    public $bindings = array(); 
    public $lang = array();
    public $references = array(); // used for loops. 
    private $locale; 

    private $cachePath = "templates/cache/";
    private $templatePath = "templates/";
    private $enable_cache = TRUE; 

    public function __construct ($locale) {
        $this->locale = $locale;
    } 

    // Set a new variable. 
    public function set ($key, $value) {
        if ($key != null) { 
            $this->bindings[$key] = $value;  
        } 
    }
    
    // Merge existing language bindings with newly loaded language file. 
    public function load_lang($langFile) {
        require "languages/".$this->locale."/".$langFile.".lang.php";
        $this->lang = $this->lang + $l;
    }

    // Renders template and RETURNS contents.
    public function parse($templateName) {
        return $this->render($templateName, true); 
    }

    // Parses template contents directly and returns result. Does not cache.  
    public function parse_raw($tpl_contents) {
        $buffer = $this->compile($tpl_contents, false, true); 
        ob_start();
        eval("?>".$buffer."<?php"); // Use EVAL to execute file's contents, since no local cache file is available.  
        return ob_get_clean(); 
    }

    // Parses template and loads into new variable by template name. Useful for headers and footers. 
    // Deprecated syntax. Use [@template:something] instead
    public function load($templateName) {
        $this->set($templateName, $this->parse($templateName)); 
    }

    // Used for formatting IF/ELSE blocks. 
    protected function format_expression($expr) {
        return preg_replace('/\$([A-Za-z0-9_]*)/', '$this->bindings["$1"]', $expr);
    }

    protected function throw_warning($warning) {
        echo "<strong>Template Warning: </strong> $warning <br />"; 
    }

    // Compiles templates to vanilla PHP for fast performance. 
    public function compile($template, $cache=true, $raw = false) {
        
        if (!$raw) {
            $contents = file_get_contents("Styles/default/Templates/" . $template . ".html");
        } else {
            $contents = $template; // Raw templates pass the data directly 
        }

        //Replace [@else] first (avoid conflicts with standard variable syntax)
        $contents = str_replace("[@else]", '<?php else: ?>', $contents);
        $contents = str_replace("[/if]", '<?php endif; ?>', $contents);

        // Template inheritance/backreference tags([@template:header]) or ([@template:@content])
        $contents = preg_replace('/\[@template:([a-zA-Z0-9_]+)\]/', '<?php echo $this->render("$1", true); ?>', $contents);
        $contents = preg_replace('/\[@template:@([a-zA-Z0-9_]+)\]/', '<?php echo $this->render($this->bindings[\'$1\'], true); ?>', $contents);

        // Replace standard template and language variables. 
        $contents = preg_replace("/\[@([a-zA-Z0-9_]+)\]/", '<?php echo htmlspecialchars($this->bindings[\'$1\']); ?>', $contents); 
        $contents = preg_replace('/\[\$([a-zA-Z0-9_]+)\]/', '<?php echo $this->bindings[\'$1\']; ?>', $contents); 
        $contents = preg_replace('/\[@lang:([a-zA-Z0-9_]+)\]/', '<?php echo $this->lang[\'$1\']; ?>', $contents);
        
        // Parsing loops - Callback version
        $contents = preg_replace_callback(
            "#\[@loop: *(.*?) as (.*?)]((?:[^[]|\[(?!/?@loop:(.*?))|(?R))+)\[/loop]#", 
            function ($loop) {
                // First, we actually enclose with opening and closing tags. 
                $inner = preg_replace(
                    "#\[@loop: *(.*?) as (.*?)]((?:[^[]|\[(?!/?@loop:(.*?))|(?R))+)\[/loop]#", 
                    '<?php foreach(\$this->bindings[\'$1\'] as \$$2): ?> $3 <?php endforeach; ?>', 
                    $loop[0]
                );

                $alias = $loop[2]; 
                // Replaced autoescaped variables 
                $inner = preg_replace(
                    '/\[@'.$alias.':([a-zA-Z0-9_]+)\]/',
                    '<?php echo htmlspecialchars($'.$alias.'[\'$1\']); ?>', 
                    $inner
                );
                // Non-escaped variables
                $inner = preg_replace(
                    '/\[\$'.$alias.':([a-zA-Z0-9_]+)\]/',
                    '<?php echo $'.$alias.'[\'$1\']; ?>', 
                    $inner
                );
                return $inner;  // We're done with inner replacements. Return the result. 
            }, 
            $contents
        );

        // Conditional expressions. 
        $contents = preg_replace_callback(
            '#\[@(if|elif|else if): *(.*?)]#', 
            function ($expr) {

                // We must format the variable to use the proper template-engine references. 
                $formatted = $this->format_expression($expr[2]);

                // Convert our template engine's syntax to regular PHP syntax. 
                if ($expr[1] == "elif" || $expr[1] == "else if") {
                    $ctrl = "elseif";
                } else if ($expr[1] == "if") {
                    $ctrl = "if"; 
                } else {
                    $this->throw_warning("Encountered invalid control for if/elif/else block: " . $expr[1]);
                    return "";
                }
                $expr = "<?php " . $ctrl . " ($formatted): ?>"; // . $expr[3] . $end;
                return $expr; 
            }, 
            $contents
        );

        // Save or return results. 
        if ($cache == true) {
            file_put_contents($this->cachePath . $template . ".php", $contents); 
        } else {
            return $contents; 
        }
    }

    // Renders an already compiled template, or compiles if necessary. 
    public function render ($template, $return = false) {

        $cacheFile = $this->cachePath . $template . ".php";
        $tplFile = $this->templatePath . $template . ".html";

        if ($this->enable_cache) {
            // Check for valid cache file. 
            if (file_exists($this->cachePath . $template . ".php")) {

                if (filemtime($cacheFile) < filemtime($tplFile)) {
                    $this->compile($template);
                }
                // Check if we should return (rather than printing to browser)
                if ($return == true) {
                    ob_start(); 
                    require($this->cachePath . $template . ".php");
                    return ob_get_clean(); 
                } 
                // Go ahead and echo contents. 
                else {
                    require($cacheFile);
                }
            } 
            // No valid cache file found. Go ahead and compile.  
            else {
                $this->compile($template);
                $this->render($template);
            }
        }
        // If the cache isn't enabled, we must buffer the compilation and execute by alternative means. 
        else {
            $buffer = $this->compile($template, false);
            eval("?>".$buffer."<?php"); // We must render using EVAL, since no cache file exists. 
        }
    }
}