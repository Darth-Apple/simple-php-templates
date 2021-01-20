<?php 
// LICENSE: GNU LGPL, Version 3. https://www.gnu.org/licenses/lgpl-3.0.en.html

class template_engine {
    public $bindings = array();
    public $lang = array();
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

    // Load a new language file for use
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
        eval("?>".$buffer."<?php");
        // Flush and return output buffer
        return ob_get_clean(); 
    }

    //  Load entire template into [@templatename] variable (Deprecated - use [@template:tpl_name] instead)
    public function load($templateName) {
        $this->set($templateName, $this->parse($templateName));
    }

    // Converts PHP variables to reference $this->bindings
    protected function format_expression($expr) {
        return preg_replace('/\$([A-Za-z0-9_]*)/', '$this->bindings["$1"]', $expr);
    }

    // Compiles templates to vanilla PHP for fast performance.
    public function compile($template, $cache=true, $raw = false) {

        // Load the template file if necessary ($raw templates pass contents directly instead)
        if (!$raw) {
            $contents = file_get_contents("Styles/default/Templates/" . $template . ".html");
        } else {
            $contents = $template;
        }

        //Replace [@else] first (avoids conflicts with standard variable syntax)
        $contents = str_replace("[@else]", '<?php else: ?>', $contents);
        $contents = str_replace("[/if]", '<?php endif; ?>', $contents);

        // Template inheritance/backreference tags([@template:header]) or ([@template:@content])
        $contents = preg_replace('/\[@template:([a-zA-Z0-9_]+)\]/', '<?php echo $this->render("$1", true); ?>', $contents);
        $contents = preg_replace('/\[@template:@([a-zA-Z0-9_]+)\]/', '<?php echo $this->render($this->bindings[\'$1\'], true); ?>', $contents);

        // Replace standard template and language variables.
        $contents = preg_replace("/\[@([a-zA-Z0-9_]+)\]/", '<?php echo htmlspecialchars($this->bindings[\'$1\']); ?>', $contents);
        $contents = preg_replace('/\[\$([a-zA-Z0-9_]+)\]/', '<?php echo $this->bindings[\'$1\']; ?>', $contents);
        $contents = preg_replace('/\[@lang:([a-zA-Z0-9_]+)\]/', '<?php echo $this->lang[\'$1\']; ?>', $contents);
        
        // Convert [@loop] tags
        $contents = preg_replace_callback(
            "#\[@loop: *(.*?) as (.*?)]((?:[^[]|\[(?!/?@loop:(.*?))|(?R))+)\[/loop]#",
            function ($loop) {
                // Enclose loop with proper PHP foreach tags
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
                // Convert to PHP syntax, use $this->bindings for variables
                $formatted = $this->format_expression($expr[2]);
                $ctrl = "if";

                if ($expr[1] == "elif" || $expr[1] == "else if") {
                    $ctrl = "elseif";
                }
                return "<?php $ctrl ($formatted): ?>";
            },
            $contents
        );

        // Strip newlines and comments
        $contents = preg_replace('~[\r\n]+~',"\r\n",trim($contents));
        $contents = preg_replace('/<!--(.|\s)*?-->/','',$contents);

        if ($cache) {
            // Create cache directory if it doesn't exist
            if (!is_dir($this->cachePath)) {
                mkdir($this->cachePath, 0755, true);
            }
            // Disable cache if write fails.
            if (!file_put_contents($this->cachePath . str_replace("/","_", "$template.php"), $contents)) {
                $this->enable_cache = false; 
            }
        } else {
            return $contents; 
        }
    }

    // Renders an already compiled template, or compiles if necessary.
    public function render ($template, $return = false) {
        $cacheFile = $this->cachePath . str_replace("/","_", "$template.php");
        $tplFile = $this->templatePath . "$template.html";

        if ($this->enable_cache) {
            // Check for valid cache file.
            if (file_exists($cacheFile)) {

                if (filemtime($cacheFile) < filemtime($tplFile)) {
                    $this->compile($template);
                }

                // Return template contents? 
                if ($return) {
                    ob_start(); 
                    require($cacheFile);
                    return ob_get_clean(); 
                } 
                else {
                    // Render directly to browser
                    require($cacheFile); 
                }
            }
            // No valid cache file found. Must compile template.
            else {
                $this->compile($template); 
                $this->render($template);
            }
        }
        // Cache is disabled. Compile to buffer, execute from memory. 
        else {
            $buffer = $this->compile($template, false);
            if ($return) { 
                ob_start();
                eval("?>".$buffer."<?php");
                return ob_get_clean();
            } else {
                eval("?>".$buffer."<?php");
            }
        }
    }
}