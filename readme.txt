SIMPLE TEMPLATE ENGINE 
LICENSE: GNU LGPL, Version 3 (message if you need a different license)
VERSION: BETA (See GitHub Issues to track progress)


----------------------------------------------------------------------
ABOUT: 
----------------------------------------------------------------------

This is an extremely lightweight template engine that allows your application’s HTML to be fully decoupled from your PHP. This template engine runs at near-native speeds thanks to its built-in compiler, making it much faster than similar interpreted template engines. Almost no performance overhead is present on a properly cached setup. 

 - Template engine is under 7KB (uncompressed)
 - Can be installed with a single file
 - Supports native language variables. 
 - Standard variables are auto escaped for security, (can optionally be inserted raw)
 - If/elseif/else template conditionals are fully supported
 - built in foreach loops. 
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

   * For languages, see the $templates->lang_load() documentation. A sample language file is provided. 
   - you will need to explicitely call $templates->lang_load("filename") in order to load a new language file for use. 
   - This was done so that larger applications don't require every language variable to be loaded for every page. This can result in lower memory usage and faster load times. 

------------------------------------------------------
TEMPLATE SYNTAX: 
------------------------------------------------------

 - [@var] - autoescaped variable 
 - [$var] - unescaped variable. Use with care! 
 - [@lang:var] - Language string. 

 
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

 * Notice how the [/if] only appears at the end of the entire if/elseif/else block. 
 * This is a concession that was made as a result of PHP's internal template conditional syntax. 
 * Even though [@else] has the same syntax as a variable "else", the engine will interpret it as a conditional directive as shown above. 

TEMPLATES/INHERITANCE:

 This engine handles inheritance very differently than most template engines. Rather than defining blocks and sections, a simple tag is declared to load the contents of another template. 

EXAMPLE: 

[@template:header]
    My content
[@template:footer]

 * The above code will parse the header and footer templates in the locations listed above. 
 * The evaluation is all done at the final render. Any variables that are set up until the render will be accessible in both the footer and the header. 

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

$templates->load("my_template"); 
    - Deprecated. Use this to parse my_template.html and load it into [@my_template]
    - Although this works, it's recommended to simply declare [@template:my_template] instead. 
    - The newer syntax parses the template on-the-fly and requires less PHP syntax. 

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

 - Because templates are compiled to PHP, the engine can very quickly parse pages. However, the compilation step itself is computationally expensive. This only happens once, on the very first page generation. The cached file is then stored in templates/cache/my_template.php

 - The cache is auto-generated on an as-needed basis. Any updates to the host template.html file will trigger a re-cache internally. As a result, the cache will always be up to date. 

 - Because the cache prevents a costly recompilation on every page load, it is strongly recommended to leave the cache enabled. It can be disabled by setting the $enable_cache flag to "false" at the beginning of the template.php class file. 

 - parse_raw() never caches templates. Because of this, it is recommended to use parse() and render() when possible. 



----------------------------------------------------------
EXAMPLE - index.php
----------------------------------------------------------
<?php
$templates = new template_engine(“english”); 
$templates->load_lang(“core”); // Loads languages/english/core.lang.php

$people = array(
	array(“name” => “Steve”, “Age” => 23),
	array(“name” => “Emily”, “Age” => 21)
);

// Set some variables
$templates->set(“page_title”, “My Template Tester”);
$templates->set(“people”, $people);
$templates->set("true", true);
$templates->set("false", false);
$templates->set("html_var", "<b><u><i>FORMATTED TEXT</i></u></b>");

$templates->render(“my_template_file”);
?>

------------------------------------------------------------
EXAMPLE - templates/my_template_file.html: 
------------------------------------------------------------

<html>
<head>
<title> [@page_title] </title>
</head>

<body>

<strong>[@lang:title] - [@page_title] </strong><br />
A language variable: [@lang:my_language_string] <br />

<hr>
<strong>TESTING LOOPS</strong><br />
[@loop: people as person]
    Person's name is [@person:name], age is [@person:age]<br />
[/loop] <br />

<hr>
<strong> TESTING IF/ELSE statements </strong><br />
[@if: $true == true]
    In IF statement. Will display if true == true. 
[@elif: $true == false]
    In ELIF (else if) - won't display because true != false. 
[@else]
    In ELSE. Won't display unless one of the above conditions is modified. 
[/if] 
<br /><br />

<hr>
<strong>TESTING escaping</strong><br />
Escaped: [@html_var] <br />
Unescaped: [$html_var] <br />
<br />

</body>
</html>



----------------------------------------------------------
LICENSE: 
----------------------------------------------------------
Licensed under the GNU LGPL, Version 3. 
More information provided at https://www.gnu.org/licenses/lgpl-3.0.en.html
Message me if you require a separate license. 