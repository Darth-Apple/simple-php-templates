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

    public function __construct ($locale="") {
        $this->locale = $locale;
    }

    // Set a new variable.
    public function set ($key, $value) {
        $this->bindings[$key] = $value;
    }

    // Set a new event and bind to listener.
    public function set_event($key, $func, $args=null) {

        // Format arguments (if present) for event
        $func = (!is_array($args) && ($args !== null)) ? array($func,array($args)) : $func;
        $func = (is_array($args)) ? array($func,$args) : $func;

        if(empty($this->events[$key])) $this->events[$key] = array($func);  // New listener
        else $this->events[$key][] = $func;  // Append to listener
    }

    // Execute/call events/listeners
    private function call_events ($key) {
        if (isset($this->events[$key])) {
            array_map(function ($event) { echo (is_array($event)) ? call_user_func_array($event[0],$event[1]) : call_user_func($event);}, $this->events[$key]);
        }
    }

    // Load a new language file for use
    public function load_lang($langFile) {
        require "languages/".$this->locale."/$langFile.lang.php";
        $this->lang = $this->lang + $l;
    }

    // Renders template and RETURNS contents.
    public function parse($templateName) {
        return $this->render($templateName, true);
    }

    // Parses template contents directly and returns result. Does not cache.
    public function parse_raw($tpl_contents) {
        $buffer = $this->compile($tpl_contents,0,1);
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
        $extend = false;
        if (!$raw) {
            $contents = file_get_contents($this->templatePath . $template . ".html");
        } else {
            $contents = $template; // Pass contents of template rather than filename.
        }

        // Strip HTML comments and whitespace
        $contents = preg_replace("~[\r\n]+~","\r\n",trim($contents));
        $contents = preg_replace("/<!--(.|\s)*?-->/",'',$contents);
        $pattern = '/\[@extend:([a-zA-Z0-9_]+)\]/';

        if (preg_match($pattern, $contents, $matches)) {
            $extend = $matches[1];
            $contents = preg_replace($pattern, '', $contents);
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
        // Process template blocks (yields)
        $contents = preg_replace('/\[@yield:([a-zA-Z0-9_]+)\]/', '<?php echo $this->render("$tpl_child",1,"$1"); ?>', $contents);
        if ($extend) {
            $contents = preg_replace_callback(
                '#\[@block: *(.*?)]((?:[^[]|\[(?!/?@block:(.*?))|(?R))+)\[/block]#',
                function ($block) {
                    $name = htmlspecialchars($block[1]);
                    $inner = $block[2];
                    $htm = '<?php endif; ?>'."\n".'<?php if(isset($tpl_block) && $tpl_block == "'.$name.'"): ?>';
                    $htm .= "\n".$inner."\n".'<?php endif; ?>'."\n";
                    $htm .= '<?php if (isset($tpl_block) && $tpl_block == "main"): ?>'."\n";
                    return $htm;
                },
                $contents
            );
        }
        // Unpack non-extended blocks for standard render.
        else {
            $contents = preg_replace('#\[@block: *(.*?)]((?:[^[]|\[(?!/?@block:(.*?))|(?R))+)\[/block]#', "\n$2\n", $contents);
        }

        // Listeners and template references/hooks [@template:val], [@template:@var], [@event:listener]
        $contents = preg_replace('/\[@template:([a-zA-Z0-9_]+)\]/', '<?php echo $this->render("$1", true); ?>', $contents);
        $contents = preg_replace('/\[@template:@([a-zA-Z0-9_]+)\]/', '<?php echo $this->render(preg_replace("/[^a-zA-Z0-9]/","",$this->bindings[\'$1\']),true); ?>', $contents);
        $contents = preg_replace('/\[@listen:([a-zA-Z0-9_]+)\]/', '<?php $this->call_events("$1"); ?>', $contents);

        // Compile template variables (language vars, standard vars, and array vars)
        $contents = preg_replace('/\[@lang:([a-zA-Z0-9_]+)\]/', '<?php echo $this->lang[\'$1\']; ?>', $contents);
        $contents = preg_replace("/\[@([a-zA-Z0-9_]+):([a-zA-Z0-9_]+)\]/", '<?php echo htmlspecialchars($this->bindings[\'$1\'][\'$2\']); ?>', $contents);
        $contents = preg_replace('/\[\$([a-zA-Z0-9_]+):([a-zA-Z0-9_]+)\]/', '<?php echo $this->bindings[\'$1\'][\'$2\']; ?>', $contents);
        $contents = preg_replace("/\[@([a-zA-Z0-9_]+)\]/", '<?php echo htmlspecialchars($this->bindings[\'$1\']); ?>', $contents);
        $contents = preg_replace('/\[\$([a-zA-Z0-9_]+)\]/', '<?php echo $this->bindings[\'$1\']; ?>', $contents);

        // Strip warnings (if enabled)
        if ($this->hide_warnings) {
            $contents = preg_replace ('/\$this->bindings\[\'([A-Za-z0-9_]*)\'\]/', '$this->get_var(\'$1\',\'bindings\')', $contents);
            $contents = preg_replace ('/\$this->lang\[\'([A-Za-z0-9_]*)\'\]/', '$this->get_var(\'$1\',\'lang\')', $contents);
        }

        // Add opening/closing __main__ block tags for child templates
        if ($extend) {
            $open = '<?php if(!isset($tpl_block)) {echo $this->render("'.$extend.'",1,"main","'.$template.'");} ?>';
            $contents = $open."\n".'<?php if(isset($tpl_block) && $tpl_block == "main"): ?>'."\n".$contents."\n".'<?php endif; ?>';
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
    public function render($template, $silent = false, $tpl_block = null, $tpl_child = null) {
        $tplFile = $this->templatePath . "$template.html";
        if (!file_exists($tplFile)) {
            return;
        }
        $cacheFile = $this->cachePath . str_replace("/","_", "$template.php");
        $cache_valid = file_exists($cacheFile) && (filemtime($cacheFile) >= filemtime($tplFile));
        ob_start();

        // Execute cache file (if exists and valid)
        if ($this->enable_cache) {
            if ($cache_valid) {
                require ($cacheFile);
            } else {
                // No valid cache file. Create, re-render, and return to outside caller.
                $this->compile($template);
                return $this->render($template, $silent, $tpl_block, $tpl_child);
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

