<?php 
// LICENSE: GNU LGPL, Version 3. https://www.gnu.org/licenses/lgpl-3.0.en.html

class template_engine {
    public $bindings = array();
    public $lang = array();
    private $locale;
    private $events = array(); 

    // SETTINGS
    private $cachePath = "templates/cache/";
    private $templatePath = "templates/";
    private $enable_cache = TRUE;
    private $hide_warnings = TRUE; 

    public function __construct ($locale) {
        $this->locale = $locale;
    }

    // Set a new variable.
    public function set ($key, $value) {
        $this->bindings[$key] = $value;
    }

    // Runs functions defined within events. 
    private function call_events ($key) {
        if (isset($this->events[$key])) {
            array_map(function ($event) { echo call_user_func($event);}, $this->events[$key]);
        }
    }

    // Set a new event and bind to listener.  
    public function set_event($key, $func) {
        if(empty($this->events[$key])) $this->events[$key] = array($func);
        else $this->events[$key][] = $func;
    }

    // Load a new language file for use
    public function load_lang($langFile) {
        require "Languages/".$this->locale."/$langFile.lang.php";
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
        return ob_get_clean();
    }

    // Converts local variables to reference $this->bindings correctly.
    private function format_expression($expr) {
        return preg_replace('/\$([A-Za-z0-9_]*)/', '$this->bindings["$1"]', $expr);
    }

    // Fetch bindings and lang strings safely (hides PHP warnings on unset template variables)
    private function get_var($key, $var) {
        if (isset($this->$var[$key])) {
            return $this->$var[$key];
        }
        return "";
    }

    // Compiles templates to vanilla PHP for fast performance.
    public function compile($template, $cache=true, $raw = false) {
        if (!$raw) {
            $contents = file_get_contents("templates/" . $template . ".html");
        } else {
            $contents = $template; // Pass contents of template rather than filename. 
        }

        //Replace [@else] first (avoids conflicts with standard variable syntax)
        $contents = str_replace("[@else]", '<?php else: ?>', $contents);
        $contents = str_replace("[/if]", '<?php endif; ?>', $contents);
        $contents = str_replace("[/endif]", '<?php endif; ?>', $contents); 

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
                // Replaced autoescaped variables
                $alias = $loop[2];
                $inner = preg_replace(
                    '/\[@'.$alias.':([a-zA-Z0-9_]+)\]/',
                    '<?php echo htmlspecialchars($'.$alias.'[\'$1\']); ?>',
                    $inner
                );
                // Replace non-escaped variables
                $inner = preg_replace(
                    '/\[\$'.$alias.':([a-zA-Z0-9_]+)\]/',
                    '<?php echo $'.$alias.'[\'$1\']; ?>',
                    $inner
                );
                return $inner;  // We're done with inner replacements. Return the result.
            },
            $contents
        );

        // Parse conditional expressions (if/elif/else)
        $contents = preg_replace_callback(
            '#\[@(if|elif|else if): *(.*?)]#',
            function ($expr) {
                $formatted = $this->format_expression($expr[2]); // Convert variable to bindings
                $syntax = array(
                    "if" => "if",
                    "elif" => "elseif",
                    "elseif" => "elseif",
                    "else if" => "elseif"
                );
                $ctrl = $syntax[$expr[1]];
                return "<?php $ctrl ($formatted): ?>";
            },
            $contents
        );

        // Listeners and template references/hooks [@template:val], [@template:@var], [@event:listener]
        $contents = preg_replace('/\[@template:([a-zA-Z0-9_]+)\]/', '<?php echo $this->render("$1", true); ?>', $contents);
        $contents = preg_replace('/\[@template:@([a-zA-Z0-9_]+)\]/', '<?php echo $this->render($this->bindings[\'$1\'], true); ?>', $contents);
        $contents = preg_replace('/\[@listen:([a-zA-Z0-9_]+)\]/', '<?php $this->call_events("$1"); ?>', $contents);

        // Compile template variables (language vars, standard vars, and array vars)
        $contents = preg_replace('/\[@lang:([a-zA-Z0-9_]+)\]/', '<?php echo $this->lang[\'$1\']; ?>', $contents);
        $contents = preg_replace("/\[@([a-zA-Z0-9_]+):([a-zA-Z0-9_]+)\]/", '<?php echo htmlspecialchars($this->bindings[\'$1\'][\'$2\']); ?>', $contents);
        $contents = preg_replace('/\[\$([a-zA-Z0-9_]+):([a-zA-Z0-9_]+)\]/', '<?php echo $this->bindings[\'$1\'][\'$2\']; ?>', $contents);
        $contents = preg_replace("/\[@([a-zA-Z0-9_]+)\]/", '<?php echo htmlspecialchars($this->bindings[\'$1\']); ?>', $contents);
        $contents = preg_replace('/\[\$([a-zA-Z0-9_]+)\]/', '<?php echo $this->bindings[\'$1\']; ?>', $contents);

        // Strip HTML comments and whitespace
        $contents = preg_replace("~[\r\n]+~","\r\n",trim($contents));
        $contents = preg_replace("/<!--(.|\s)*?-->/",'',$contents);

        // Strip warnings (if enabled)
        if ($this->hide_warnings) {
            $contents = preg_replace ('/\$this->bindings\[\'([A-Za-z0-9_]*)\'\]/', '$this->get_var(\'$1\', \'bindings\')', $contents);
            $contents = preg_replace ('/\$this->lang\[\'([A-Za-z0-9_]*)\'\]/', '$this->get_var(\'$1\', \'lang\')', $contents);
        }

        if ($cache) {
            if (!is_dir($this->cachePath)) {
                mkdir($this->cachePath, 0755, true); // Create cache directory (if missing)
            }
            if (!file_put_contents($this->cachePath . str_replace("/","_", "$template.php"), $contents)) {
                $this->enable_cache = false;  // Don't recursively re-attempt on write fail
            }
        }
        return $contents;
    }
    
    // Renders a template. Compiles if necessary.
    public function render($template, $silent = false, $extend = false, $block = "") {
        $cacheFile = $this->cachePath . str_replace("/","_", "$template.php");
        $tplFile = $this->templatePath . "$template.html";
        $cache_valid = file_exists($cacheFile) && (filemtime($cacheFile) >= filemtime($tplFile));
        ob_start();

        // Execute cache file (if exists and valid)
        if ($this->enable_cache) {
            if ($cache_valid) {
                require ($cacheFile);
            } else {
                // No valid cache file. Create, re-render, and return to outside caller.
                $this->compile($template);
                return $this->render($template, $silent);
            }
        }
        else {
            // Cache is disabled. Buffer from memory
            $buffer = $this->compile($template, false);
            eval("?>".$buffer."<?php");
        }

        $out = ob_get_clean();
        if (!$silent) {
            echo $out; 
        }
        return $out;
    }
}