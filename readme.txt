SIMPLE TEMPLATE ENGINE 
LICENSE: GNU LGPL, Version 3 (message if you need a different license)
VERSION: BETA (See GitHub Issues to track progress)


----------------------------------------------------------------------
ABOUT: 
----------------------------------------------------------------------

This is an extremely lightweight template engine that allows your application’s HTML to be fully decoupled from your PHP. This template engine runs at near-native speeds thanks to its built-in compiler, making it much faster than similar interpreted template engines. Almost no performance overhead is present on a properly cached setup. 

 - Single file template engine. Fits in under 8KB (uncompressed)
 - Flexible template variables 
 - Automatic auto-escaping (variables can also be inserted unescaped)
 - Rich if/elseif/else blocks
 - Event Listeners (specialized template hooks)
 - Template loops 
 - Load nested templates with [@template:mytemplate] tags. 
 - Clean, simple syntax. 

This engine is ideal for small, simple projects that need a capable, secure, and fast template engine. Installation is quick and easy, and the overhead is minimal.


----------------------------------------------------------------------
INSTALLATION: 
----------------------------------------------------------------------

- To install, simply include the "template_engine.php" file in your application.
- Documentation on how to set your engine up is provided below. 
- Edit configuration paths in template_engine.php as needed. (See below)

  - Default Template Directory: templates/[templatename].html
  - Default Language Directory: languages/[locale]/lang_file.lang.php
  - Default Cache directory: templates/cache/template.php (this is handled internally, simply create the directory and you're done!)

  - For languages, see the $templates->lang_load() documentation. A sample language file is provided. 

  - $hide_warnings can optionally be enabled to suppress PHP warnings that may occur from the use of unset template variables within templates. (Slight performance degradation may occur)

------------------------------------------------------
TEMPLATE SYNTAX: 
------------------------------------------------------

VARIABLES: 
 - [@var] - autoescaped variable 
 - [$var] - unescaped variable. Use with care!

 - [@array:key] - Access $array[$key] variable, escaped. 
 - [$array:key] - Access $array[$key] (unescaped). 

Currently, only one-dimensional dictionary-style arrays are supported. 
 

LANGUAGE VARIABLES: 
 - [@lang:var] - Language string. 


TEMPLATE EVENT LISTENERS (HOOKS): 
 - [@listen:listener_name]   - Listen for event name

  Event listeners are specialized hooks that allow dynamically-added functions to be executed at predefined locations in the template. Multiple events can be added for each listener. See corresponding documentation in the PHP Syntax section for more information. 


LOOPS: 
  - [@loop: array as item] Inner loop data [@item:key] some more data [/loop]


IF/ELSE IF/ELSE: 

[@if: $variable == something]
    IF block
 [@elif: $variable == something_else]
    Else If Block
 [@else]
    ELSE block 
[/if]

  * The [/if] only appears once, at the end of all if/elif/else blocks. 
  * Although [@else] has the same syntax as a variable "else", the engine will interpret it as a conditional directive as shown above. 
  * You may nest If/Elif/Else blocks indefinitely. 
  * Rich expressions may be used. Expressions are parsed directly to PHP, enabling full, advanced functionality. 


TEMPLATES/INHERITANCE:

 This engine handles inheritance very differently than most template engines. Rather than defining blocks and sections, a simple tag is declared to load the contents of another template. 

  example: 

  [@template:header]
     My content
  [@template:footer]

  * The above code will parse the header and footer templates in the locations listed above. 
  * The evaluation is all done at the final render. Any variables that are set up until the render will be accessible in both the footer and the header. 


TEMPLATE REFERENCES: 

 - [@template:@somevar] 

   Template references are similar to normal template substitution tags, except that a variable can contain the name of the template to be loaded. In this case, @somevar can be set like normal to contain ANY template name desired. The engine will substitute it as normal and parse the corresponding template as expected. 

 

------------------------------------------------------
PHP SYNTAX: 
------------------------------------------------------

$templates = new template_engine("english"); 
   - Declares a new template engine, and looks for language variables in languages/english

$templates->load_lang("core"); 
   - Loads languages/[english]/core.lang.php and all language strings carried within. 

$templates->set("key", "value"); 
    - Sets the [@key] variable with "value"
    - Also used for IF statements and loops. See examples above. 

$templates->render("my_page");
    - Loads templates/my_page.html and renders the template. 
    - This function sends the result to the browser. No need to echo result. 

$contents = $templates->parse("my_page"); 
    - Same as $templates->render, but returns the final page rather than sending to the browser.
    - Use this if you need to evaluate templates at intermediate steps (before final page generation)

$contents = $templates->parse_raw(file_get_contents("templates/my_page.html")); 
    - This function allows you to pass the template's data/contents rather than its filename. 
    - Useful if you're generating highly dynamic templates that aren't stored in files. 
    - Note that these aren't cached. It's recommended to use parse() or render() for most use cases. 
    - results ARE NOT echoed to browser. Capture results in return variable. 


LISTENERS: 

$templates->set_event("listener_name", "function_name");

(OR) - Listeners with arguments: 

$templates->set_event("listener_name", "function_name", $single_argument);
$templates->set_event("listener_name", "function_name", array($arg1, $arg2, ...)); 

    - Calls "function_name" when [@event:listener_name] is encountered in a template. 
    - You may add as many events to a listener as you would like. 
    - Events are executed in the order for which they are added. 
    - Your defined function should return (not echo) the output that should be injected into the template. 
    - Arguments may be passed if desired. You may pass none, or pass many (as needed)
    - If using multiple arguments, pass an array of arguments (as shown above)

----------------------------------------------------------
LANGUAGE STRINGS:  
----------------------------------------------------------
Language strings follow the [@lang:lang_string] convention. 

This template engine is packaged with a sample language file in languages/english/core.lang.php. To use this file, follow the convention below: 

$templates = new template_engine("english"); // Substitute for locale desired
$templates->lang_load("core"); // Loads the "core.lang.php" file for use. 

You may load as many language files as required. It's recommended to separate different pages/functions so that only the necessary language files are loaded on each page. This improves performance and decreases load times. 

----------------------------------------------------------
TEMPLATE CACHE: 
----------------------------------------------------------

 - This template engine compiles all templates to raw PHP, enabling near-native performance. This compilation step is only performed once (on the very first load of the template), and is stored in templates/cache/template_name.html. 

 - Template caches will always be up-to-date. The cache is auto-generated on an as-needed basis. Any updates to the host template.html file will trigger a re-cache internally. 

 - It is recommended to leave the cache enabled, as it saves the costly compilation step from needing to be performed on each template load. However, it can be disabled if needed via the $enable_cache variable in template_engine.php

 - parse_raw() never caches templates. Because of this, it is recommended to use parse() and render() when possible. 


------------------------------------------------------------
EXAMPLES: 
------------------------------------------------------------

Examples are provided with this template engine. To demonstrate, upload all files to your server, and run example_index.php. 

The template file associated with this example is found within templates/example.html


----------------------------------------------------------
LICENSE: 
----------------------------------------------------------
Licensed under the GNU LGPL, Version 3. 
More information provided at https://www.gnu.org/licenses/lgpl-3.0.en.html
Message me if you require a separate license. 